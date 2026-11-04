"""
# ==============================================================================
# 🛡️ ESTRATEGIA DE VERIFICACIÓN PROXY: proxy_checker.py
# Verificación de servidores Proxy y navegación con caché anti-colisión y candado
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import time
from typing import Tuple
import urllib.parse

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig
from monitor.proxy_cache import (
    ProxyInflightGuard,
    get_cached_proxy_result,
    save_cached_proxy_result,
)


async def check_proxy(
    proxy_url: str,
    test_url: str = "https://core.telegram.org/bots",
    timeout: float = 4.5,
) -> Tuple[bool, str, str]:
    """Verifica navegación a través de un proxy corporativo (Squid/pfSense) usando curl con SECLEVEL=0 y caché anti-colisión."""
    if not proxy_url:
        return False, "0", "Proxy no configurado"

    target = test_url if test_url else "https://core.telegram.org/bots"

    # 1. Comprobar caché de corto plazo (180s) en memoria RAM compartida (/dev/shm)
    cached = get_cached_proxy_result(proxy_url, target, max_age_seconds=180.0)
    if cached:
        return cached["is_ok"], cached["code_str"], cached["detail"]

    # 2. Candado In-Flight: Si otro proceso ya está chequeando este proxy en este instante, esperar su resultado
    guard = ProxyInflightGuard(proxy_url, target)
    waited_cached = await guard.acquire_or_wait(timeout=timeout)
    if waited_cached:
        return waited_cached["is_ok"], waited_cached["code_str"], waited_cached["detail"]

    # 3. Realizar chequeo físico con curl
    t0 = time.perf_counter()
    try:
        proc = await asyncio.create_subprocess_exec(
            "curl",
            "-x", proxy_url,
            "-k",
            "--ciphers", "DEFAULT:@SECLEVEL=0",
            "-s",
            "-o", "/dev/null",
            "-w", "%{http_code}",
            "--connect-timeout", "3",
            "--max-time", str(int(timeout)),
            target,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout + 1.0)
        elapsed_ms = (time.perf_counter() - t0) * 1000.0
        code_str = stdout.decode("utf-8", errors="ignore").strip()
        is_ok = False
        detail = "Proxy no responde (Timeout / Caído)"
        if code_str.isdigit():
            code = int(code_str)
            if code in (200, 301, 302):
                is_ok = True
                detail = f"Proxy operativo (HTTP {code_str})"
            elif code > 0:
                detail = f"Proxy respondió HTTP {code_str}"

        # Guardar en memoria compartida para reutilización inmediata por otros procesos
        save_cached_proxy_result(proxy_url, target, is_ok, code_str, detail, elapsed_ms, source="curl")
        return is_ok, code_str, detail
    except asyncio.TimeoutError:
        elapsed_ms = (time.perf_counter() - t0) * 1000.0
        save_cached_proxy_result(proxy_url, target, False, "0", "Timeout al probar proxy", elapsed_ms, source="curl")
        return False, "0", "Timeout al probar proxy"
    except Exception as e:
        elapsed_ms = (time.perf_counter() - t0) * 1000.0
        return False, "0", f"Fallo al probar proxy: {e}"
    finally:
        guard.release()


class ProxyChecker(BaseServiceChecker):
    """Verificador de servidores Proxy y navegación externa con serialización anti-bloqueo pfSense."""

    _proxy_lock = asyncio.Lock()

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de estado y métricas operativas del servicio."""
        # Normalizar IP y puerto para evitar puertos duplicados accidentales
        raw_ip_port = (svc.proxy_ip_port or svc.ip_host or "").strip()
        parts = [p for p in raw_ip_port.split(":") if p]
        if len(parts) >= 2:
            clean_ip_port = f"{parts[0]}:{parts[1]}"
        elif len(parts) == 1:
            clean_ip_port = f"{parts[0]}:8080"
        else:
            clean_ip_port = ""

        # Formatear proxy_url con credenciales seguras (URL-encoded) si existen
        if svc.proxy_user_pass and ":" in svc.proxy_user_pass:
            user, pwd = svc.proxy_user_pass.split(":", 1)
            user_enc = urllib.parse.quote(user)
            pwd_enc = urllib.parse.quote(pwd)
            proxy_url = f"http://{user_enc}:{pwd_enc}@{clean_ip_port}"
        elif svc.proxy_user_pass:
            proxy_url = f"http://{svc.proxy_user_pass}@{clean_ip_port}"
        else:
            proxy_url = f"http://{clean_ip_port}"

        # Determinar URL objetivo de prueba
        target_test_url = "https://core.telegram.org/bots"
        candidate_url = (svc.url_test_site or "").strip()
        if candidate_url.startswith(("http://", "https://")):
            low_url = candidate_url.lower()
            if "pfsense" not in low_url and "proxyr2" not in low_url and "127.0.0.1" not in low_url and "0.0.0.0" not in low_url:
                target_test_url = candidate_url

        # 1. Comprobar si ya existe resultado reciente en memoria compartida (hasta 180s)
        cached = get_cached_proxy_result(proxy_url, target_test_url, max_age_seconds=180.0)
        if cached:
            is_ok = bool(cached.get("is_ok", False))
            code = str(cached.get("code_str", "200" if is_ok else "0"))
            detail = str(cached.get("detail", "Proxy operativo (caché)"))
            return CheckResult(is_ok=is_ok, code=code, detail=detail)

        # 2. Si no hay caché, serializar bajo candado para que pfSense jamás reciba peticiones simultáneas del mismo usuario
        async with self._proxy_lock:
            # Re-verificar tras adquirir candado (Double-Check Locking)
            cached_post = get_cached_proxy_result(proxy_url, target_test_url, max_age_seconds=180.0)
            if cached_post:
                is_ok = bool(cached_post.get("is_ok", False))
                code = str(cached_post.get("code_str", "200" if is_ok else "0"))
                detail = str(cached_post.get("detail", "Proxy operativo (caché)"))
                return CheckResult(is_ok=is_ok, code=code, detail=detail)

            # Pequeña pausa preventiva para dar respiro al connection tracking de pfSense/Squid
            await asyncio.sleep(0.8)
            is_ok, code, detail = await check_proxy(proxy_url, target_test_url, timeout=5.0)

            # Fallback de seguridad: si falló con URL personalizada, reintentar con Telegram
            if not is_ok and target_test_url != "https://core.telegram.org/bots":
                await asyncio.sleep(0.5)
                is_ok_tg, code_tg, detail_tg = await check_proxy(proxy_url, "https://core.telegram.org/bots", timeout=5.0)
                if is_ok_tg:
                    is_ok, code, detail = is_ok_tg, code_tg, detail_tg

            return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
