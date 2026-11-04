"""
# ==============================================================================
# 🌐 ESTRATEGIA DE VERIFICACIÓN WEB (HTTP/HTTPS): web_checker.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from typing import Tuple

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


async def check_web(url: str, timeout: float = 4.0) -> Tuple[bool, str, str]:
    """
    Verifica disponibilidad de una aplicación web vía HTTP/HTTPS utilizando
    curl -k --ciphers 'DEFAULT:@SECLEVEL=0' -s -o /dev/null -w '%{http_code}'
    para soportar servidores heredados y certificados SSL antiguos/corporativos.
    """
    if not url or url.startswith("0.0.0.0"):
        return False, "0", "URL inválida o no configurada"

    target_url = url if url.startswith(("http://", "https://")) else f"http://{url}"

    try:
        proc = await asyncio.create_subprocess_exec(
            "curl",
            "-k",
            "--ciphers", "DEFAULT:@SECLEVEL=0",
            "-s",
            "-o", "/dev/null",
            "-w", "%{http_code}",
            "--connect-timeout", "3",
            "--max-time", str(int(timeout)),
            target_url,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout + 1.0)
        code_str = stdout.decode("utf-8", errors="ignore").strip()

        if code_str.isdigit():
            code = int(code_str)
            if code in (200, 301, 302, 307, 308, 401, 403):
                return True, code_str, f"HTTP {code_str} OK"
            elif code > 0:
                return False, code_str, f"HTTP {code_str} Error"

        return False, code_str or "0", "Sin respuesta (Timeout / Caído)"
    except asyncio.TimeoutError:
        return False, "0", "Timeout al conectar al sitio web"
    except Exception as e:
        return False, "0", f"Error de conexión: {e}"


class WebChecker(BaseServiceChecker):
    """Verificador de aplicaciones y portales web vía HTTP/HTTPS."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de estado y métricas operativas del servicio."""
        is_ok, code, detail = await check_web(svc.web_url)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
