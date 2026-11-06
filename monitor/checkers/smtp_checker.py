"""
# ==============================================================================
# 📧 ESTRATEGIA DE VERIFICACIÓN SMTP: smtp_checker.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from typing import Tuple

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.parsers import ServiceConfig


async def check_smtp(ip: str, port: str = "25", timeout: float = 4.0) -> Tuple[bool, str, str]:
    """Verifica la respuesta de un servidor SMTP conectando al socket y enviando HELO."""
    if not ip or ip.startswith("0.0.0.0"):
        return False, "0", "IP SMTP no configurada"

    port_int = int(port) if port.isdigit() else 25
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(ip, port_int), timeout=timeout
        )
        banner = await asyncio.wait_for(reader.readline(), timeout=2.0)
        writer.write(b"HELO mail.empresa.local\r\n")
        await writer.drain()
        reply = await asyncio.wait_for(reader.readline(), timeout=2.0)
        writer.write(b"QUIT\r\n")
        await writer.drain()
        writer.close()
        await writer.wait_closed()

        banner_str = banner.decode("utf-8", errors="ignore").strip()
        return True, "250", f"SMTP responde: {banner_str[:80]}"
    except asyncio.TimeoutError:
        return False, "0", "Timeout esperando respuesta SMTP"
    except Exception as e:
        return False, "0", f"Fallo al conectar a servidor SMTP ({ip}:{port_int}): {e}"


class SmtpChecker(BaseServiceChecker):
    """Verificador de servidores de correo electrónico SMTP."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de conectividad y respuesta SMTP."""
        is_ok, code, detail = await check_smtp(svc.ip_host, svc.smtp_port or "25")
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
