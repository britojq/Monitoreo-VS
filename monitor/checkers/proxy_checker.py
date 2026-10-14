from __future__ import annotations

import urllib.parse
from monitor.checker_base import check_proxy
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class ProxyChecker(BaseServiceChecker):
    """Verificador de servidores Proxy y navegación externa."""

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

        is_ok, code, detail = await check_proxy(proxy_url, target_test_url)

        # Fallback de seguridad: si falló con URL personalizada, reintentar con Telegram
        if not is_ok and target_test_url != "https://core.telegram.org/bots":
            is_ok_tg, code_tg, detail_tg = await check_proxy(proxy_url, "https://core.telegram.org/bots")
            if is_ok_tg:
                is_ok, code, detail = is_ok_tg, code_tg, detail_tg

        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
