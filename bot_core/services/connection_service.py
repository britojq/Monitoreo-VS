"""
# ==============================================================================
# 🌐 SERVICIO DE CONEXIONES Y RED RESILIENTE: connection_service.py
# Monitoreo de rutas directas, proxies corporativos, conmutación autónoma (Failover).
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import os
import sys
import time
import urllib.parse
from typing import Any, Dict, List, Tuple

import httpx
from telegram.ext import Application

from bot_core.services.config_manager import load_config
from monitor.core_shield import (
    IMMUTABLE_BOT_TOKEN,
    IMMUTABLE_OWNER_ID,
)
from monitor.proxy_cache import (
    ProxyInflightGuard,
    get_cached_proxy_result,
    save_cached_proxy_result,
)

logger = logging.getLogger("bot.services.connection")

ACTIVE_CONNECTION_LABEL = "Conexión Directa"
ACTIVE_PROXY_URL: str | None = None
_LAST_SUCCESSFUL_POLL_TIME = time.time()
_CONSECUTIVE_NETWORK_ERRORS = 0
_FAILOVER_LOCK = asyncio.Lock()


def load_proxies_list() -> List[Dict[str, Any]]:
    """Carga la lista de proxies directamente desde MariaDB (SSOT)."""
    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT letter, name, ip_port, auth_userpass, is_active "
                    "FROM monitored_proxies WHERE is_active = 1 ORDER BY letter ASC"
                )
                rows = cur.fetchall()
                if rows:
                    db_proxies = []
                    for r in rows:
                        ip = (r.get("ip_port") or "").strip()
                        if not ip:
                            continue
                        auth = (r.get("auth_userpass") or "").strip()
                        name = r.get("name") or f"Proxy {r.get('letter', '')}"
                        if auth and ":" in auth and auth != "USUARIO:CLAVE":
                            user, pwd = auth.split(":", 1)
                            user_enc = urllib.parse.quote(user)
                            pwd_enc = urllib.parse.quote(pwd)
                            url = f"http://{user_enc}:{pwd_enc}@{ip}"
                        else:
                            url = f"http://{ip}"
                        db_proxies.append({
                            "name": name,
                            "url": url,
                            "enabled": True
                        })
                    if db_proxies:
                        return db_proxies
        finally:
            conn.close()
    except Exception as e:
        logger.debug(f"No se pudieron leer proxies desde MariaDB ({e}). Intentando respaldo.")

    # Respaldo de contingencia: config.json
    bot_mod = sys.modules.get("bot")
    cfg = getattr(bot_mod, "CONFIG", None) if bot_mod else load_config()
    custom_proxies = cfg.get("proxies") if cfg else []
    if custom_proxies and isinstance(custom_proxies, list) and len(custom_proxies) > 0:
        return custom_proxies

    return []


def select_working_connection(bot_token: str) -> str | None:
    """
    Evalúa la conectividad con Telegram Bot API al arranque:
    1. Prueba conexión directa con timeout corto (3.0s).
    2. Si falla directa, prueba en orden los proxies configurados en MariaDB (SSOT).
    3. Retorna la URL del proxy funcional (o None si la conexión directa funciona).
    """
    global ACTIVE_CONNECTION_LABEL, ACTIVE_PROXY_URL
    url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Probar conexión directa
    try:
        r = httpx.get(url, timeout=3.0)
        if r.status_code == 200 and r.json().get("ok"):
            logger.info("🌐 Conexión DIRECTA a Telegram verificada exitosamente.")
            ACTIVE_CONNECTION_LABEL = "Conexión Directa"
            ACTIVE_PROXY_URL = None
            return None
    except Exception as e:
        logger.warning(f"Conexión directa a Telegram no disponible ({e}). Evaluando proxies corporativos...")

    # 2. Probar proxies
    proxies = load_proxies_list()
    for p in proxies:
        if not p.get("enabled", True):
            continue
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if not p_url:
            continue

        try:
            r = httpx.get(url, proxy=p_url, timeout=3.5)
            if r.status_code == 200 and r.json().get("ok"):
                logger.info(f"🔄 Conectividad exitosa con Telegram vía [{p_name}]: {p_url}")
                ACTIVE_CONNECTION_LABEL = f"Proxy: {p_name}"
                ACTIVE_PROXY_URL = p_url
                return p_url
        except Exception as e:
            logger.info(f"Proxy [{p_name}] no disponible: {e}")

    logger.warning("⚠️ No se pudo verificar ningún proxy ni conexión directa. Intentando conexión estándar...")
    ACTIVE_CONNECTION_LABEL = "Conexión Estándar (Sin verificar)"
    ACTIVE_PROXY_URL = None
    return None


def restart_bot_process(reason: str = "Conmutación de Red") -> None:
    """
    Reinicia limpiamente el proceso del bot de forma instantánea mediante os.execv.
    Garantiza que todos los sockets se liberen y que el bot inicie con transporte nuevo
    utilizando el proxy o la conexión directa operativa.
    """
    logger.warning(f"🚀 REINICIO AUTÓNOMO DE CONEXIÓN: {reason}. Reiniciando bot...")
    try:
        sys.stdout.flush()
        sys.stderr.flush()
    except Exception:
        pass
    python_bin = sys.executable
    os.execv(python_bin, [python_bin] + sys.argv)


async def async_evaluate_best_connection(bot_token: str) -> Tuple[str | None, str, bool]:
    """
    Evalúa de forma asíncrona la mejor conexión a Telegram disponible en tiempo real:
    1. Prioridad 1: Conexión Directa a Internet.
    2. Prioridad 2: Proxies corporativos configurados (Squid, pfSense).
    Retorna (proxy_url, connection_label, is_functional).
    """
    url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Probar conexión directa
    try:
        async with httpx.AsyncClient(timeout=3.5) as client:
            r = await client.get(url)
            if r.status_code == 200 and r.json().get("ok"):
                return None, "Conexión Directa", True
    except Exception:
        pass

    # 2. Probar proxies corporativos
    proxies = load_proxies_list()
    for p in proxies:
        if not p.get("enabled", True):
            continue
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if not p_url:
            continue
        try:
            async with httpx.AsyncClient(proxy=p_url, timeout=4.0) as client:
                r = await client.get(url)
                if r.status_code == 200 and r.json().get("ok"):
                    return p_url, f"Proxy: {p_name}", True
        except Exception:
            continue

    return ACTIVE_PROXY_URL, ACTIVE_CONNECTION_LABEL, False


async def trigger_connection_failover(reason: str = "Aviso de red") -> None:
    """Evalúa rutas y reinicia el proceso del bot de forma limpia si la conexión actual falló."""
    global _CONSECUTIVE_NETWORK_ERRORS, _LAST_SUCCESSFUL_POLL_TIME
    if _FAILOVER_LOCK.locked():
        return
    async with _FAILOVER_LOCK:
        bot_mod = sys.modules.get("bot")
        cfg = getattr(bot_mod, "CONFIG", None) if bot_mod else load_config()
        bot_token = cfg.get("bot_token", IMMUTABLE_BOT_TOKEN) if cfg else IMMUTABLE_BOT_TOKEN
        best_url, best_label, is_ok = await async_evaluate_best_connection(bot_token)
        if is_ok:
            if best_url != ACTIVE_PROXY_URL or best_label != ACTIVE_CONNECTION_LABEL:
                restart_bot_process(f"Conmutación a ruta operativa [{best_label}] debido a: {reason}")
            else:
                logger.info(f"✅ Ruta actual [{best_label}] verificada y operativa. Reseteando contadores de error.")
                _CONSECUTIVE_NETWORK_ERRORS = 0
                _LAST_SUCCESSFUL_POLL_TIME = time.time()
        else:
            logger.warning(f"⚠️ Ninguna ruta de internet o proxy responde ({reason}). Esperando próximo ciclo...")


async def runtime_connection_watchdog(app: Application) -> None:
    """
    Centinela asíncrono de conectividad del Bot (Revisión cada 10 minutos):
    1. Verifica cada 600 segundos (10 min) la conectividad hacia Telegram Bot API.
    2. Si estamos en un Proxy y la Conexión Directa se restableció -> Reinicia para volver a Directa.
    3. Si la conexión actual dejó de responder -> Busca la mejor ruta viva y reinicia el bot en 1 segundo.
    """
    global _CONSECUTIVE_NETWORK_ERRORS, _LAST_SUCCESSFUL_POLL_TIME
    CHECK_INTERVAL_SECONDS = 600
    logger.info("🛡️ Centinela Autorreparable de Red (Watchdog activo, revisión cada 10 min).")
    bot_mod = sys.modules.get("bot")
    cfg = getattr(bot_mod, "CONFIG", None) if bot_mod else load_config()
    bot_token = cfg.get("bot_token", IMMUTABLE_BOT_TOKEN) if cfg else IMMUTABLE_BOT_TOKEN

    while True:
        try:
            await asyncio.sleep(CHECK_INTERVAL_SECONDS)

            if ACTIVE_PROXY_URL is not None:
                try:
                    async with httpx.AsyncClient(timeout=3.5) as client:
                        r = await client.get(f"https://api.telegram.org/bot{bot_token}/getMe")
                        if r.status_code == 200 and r.json().get("ok"):
                            logger.info("🌐 Conexión DIRECTA a internet restablecida.")
                            restart_bot_process("Retorno a Conexión Directa restablecida")
                except Exception:
                    pass

            is_current_alive = False
            try:
                async with httpx.AsyncClient(proxy=ACTIVE_PROXY_URL, timeout=4.0) as client:
                    r = await client.get(f"https://api.telegram.org/bot{bot_token}/getMe")
                    if r.status_code == 200 and r.json().get("ok"):
                        is_current_alive = True
                        _LAST_SUCCESSFUL_POLL_TIME = time.time()
                        _CONSECUTIVE_NETWORK_ERRORS = 0
            except Exception:
                is_current_alive = False

            if not is_current_alive:
                logger.warning("⚠️ Chequeo periódico de 10 min detectó que la conexión actual no responde.")
                await trigger_connection_failover(reason="Chequeo periódico de 10 minutos fallido")

        except asyncio.CancelledError:
            break
        except Exception as e:
            logger.error(f"Error en centinela de red watchdog: {e}")


async def _check_endpoint_health(name: str, tg_url: str, proxy_url: str | None = None, timeout: float = 6.5) -> Dict[str, Any]:
    """Verifica la conectividad y latencia hacia Telegram (directa o vía proxy) con protección de caché."""
    guard = None
    if proxy_url:
        cached = get_cached_proxy_result(proxy_url, tg_url, max_age_seconds=60.0)
        if cached and cached.get("is_ok"):
            elapsed_ms = int(cached.get("latency_ms", 0))
            code = int(cached.get("code_str", 200) or 200)
            return {
                "name": name,
                "ok": True,
                "status_code": code,
                "elapsed_ms": elapsed_ms,
                "detail": f"Operativo (HTTP {code}, {elapsed_ms} ms)"
            }

        guard = ProxyInflightGuard(proxy_url, tg_url)
        waited_cached = await guard.acquire_or_wait(timeout=timeout)
        if waited_cached and waited_cached.get("is_ok"):
            elapsed_ms = int(waited_cached.get("latency_ms", 0))
            code = int(waited_cached.get("code_str", 200) or 200)
            return {
                "name": name,
                "ok": True,
                "status_code": code,
                "elapsed_ms": elapsed_ms,
                "detail": f"Operativo (HTTP {code}, {elapsed_ms} ms)"
            }

    t0 = time.perf_counter()
    try:
        async with httpx.AsyncClient(proxy=proxy_url, verify=False, timeout=timeout) as client:
            r = await client.get(tg_url)
            elapsed_ms = int((time.perf_counter() - t0) * 1000)
            is_ok = (r.status_code == 200 and (r.json().get("ok") if "json" in r.headers.get("content-type", "") else True))
            if not is_ok and proxy_url:
                try:
                    r_fb = await client.get("https://core.telegram.org/bots")
                    if r_fb.status_code in (200, 301, 302):
                        is_ok = True
                        elapsed_ms = int((time.perf_counter() - t0) * 1000)
                        r = r_fb
                except Exception:
                    pass
            detail_str = f"Operativo (HTTP {r.status_code}, {elapsed_ms} ms)" if is_ok else f"HTTP {r.status_code} ({elapsed_ms} ms)"
            if proxy_url:
                save_cached_proxy_result(
                    proxy_url, tg_url, is_ok, str(r.status_code),
                    detail_str, elapsed_ms, source="bot_endpoint"
                )
            return {
                "name": name,
                "ok": is_ok,
                "status_code": r.status_code,
                "elapsed_ms": elapsed_ms,
                "detail": detail_str
            }
    except Exception:
        if proxy_url:
            try:
                async with httpx.AsyncClient(proxy=proxy_url, verify=False, timeout=4.5) as client_fb:
                    r_fb = await client_fb.get("https://core.telegram.org/bots")
                    if r_fb.status_code in (200, 301, 302):
                        elapsed_ms = int((time.perf_counter() - t0) * 1000)
                        detail_str = f"Operativo (HTTP {r_fb.status_code}, {elapsed_ms} ms)"
                        save_cached_proxy_result(
                            proxy_url, tg_url, True, str(r_fb.status_code),
                            detail_str, elapsed_ms, source="bot_endpoint"
                        )
                        return {
                            "name": name,
                            "ok": True,
                            "status_code": r_fb.status_code,
                            "elapsed_ms": elapsed_ms,
                            "detail": detail_str
                        }
            except Exception:
                pass

        elapsed_ms = int((time.perf_counter() - t0) * 1000)
        detail_str = f"Inalcanzable (Timeout / Error tras {elapsed_ms} ms)"
        if proxy_url:
            save_cached_proxy_result(
                proxy_url, tg_url, False, "0",
                detail_str, elapsed_ms, source="bot_endpoint"
            )
        return {
            "name": name,
            "ok": False,
            "status_code": 0,
            "elapsed_ms": elapsed_ms,
            "detail": detail_str
        }
