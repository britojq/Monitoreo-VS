"""
# ==============================================================================
# 🖨️ ESTRATEGIA DE VERIFICACIÓN CUPS: cups_checker.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from typing import Tuple

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig
from monitor.infrastructure.validators import validate_hostname, validate_ipv4


async def check_cups(cups_ip_port: str, timeout: float = 4.0) -> Tuple[bool, str, str]:
    """Verifica la cola de impresión de un servidor CUPS."""
    if not cups_ip_port or cups_ip_port.startswith("0.0.0.0"):
        return False, "0", "Servidor CUPS no configurado"

    cups_host = cups_ip_port.split(":")[0].strip()
    if not (validate_ipv4(cups_host) or validate_hostname(cups_host)):
        return False, "0", f"Servidor CUPS con formato de host inválido: {cups_host}"

    try:
        proc = await asyncio.create_subprocess_exec(
            "lpstat", "-h", cups_ip_port, "-t", "-o",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")
        if "Ready to print" in out_str or "scheduler is running" in out_str or proc.returncode == 0:
            return True, "200", "Servidor CUPS operativo"
        return False, "0", f"CUPS no responde: {out_str[:100]}"
    except asyncio.TimeoutError:
        return False, "0", "Timeout en consulta CUPS"
    except Exception as e:
        return False, "0", f"Error consultando CUPS: {e}"


class CupsChecker(BaseServiceChecker):
    """Verificador de servidores de impresión CUPS/IPP."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de estado de impresión."""
        is_ok, code, detail = await check_cups(svc.cups_port_ip)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
