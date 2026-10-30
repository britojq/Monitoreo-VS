import asyncio
import urllib.parse
from monitor.checker_base import check_proxy
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig
from monitor.proxy_cache import get_cached_proxy_result


class ProxyChecker(BaseServiceChecker):
    """Verificador de servidores Proxy y navegación externa con serialización anti-bloqueo pfSense."""

    _proxy_lock = asyncio.Lock()

    async def check(self, svc: ServiceConfig) -> CheckResult:
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
