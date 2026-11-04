"""
# ==============================================================================
# 📂 ESTRATEGIA DE VERIFICACIÓN LDAP: ldap_checker.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from typing import Tuple

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


async def check_ldap(ip: str, port: str = "389", timeout: float = 3.5) -> Tuple[bool, str, str]:
    """Verifica conexión TCP al puerto LDAP (389 / 636)."""
    if not ip or ip.startswith("0.0.0.0"):
        return False, "0", "IP LDAP no configurada"

    port_int = int(port) if port.isdigit() else 389
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(ip, port_int), timeout=timeout
        )
        writer.close()
        await writer.wait_closed()
        return True, "200", f"Puerto LDAP ({ip}:{port_int}) abierto y respondiendo"
    except asyncio.TimeoutError:
        return False, "0", f"Timeout conectando al puerto LDAP {port_int}"
    except Exception as e:
        return False, "0", f"Fallo al conectar a LDAP ({ip}:{port_int}): {e}"


class LdapChecker(BaseServiceChecker):
    """Verificador de directorios y catálogos LDAP/Active Directory."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de conectividad al puerto LDAP."""
        port = getattr(svc, "ldap_port_ip", "") or getattr(svc, "port", "") or "389"
        is_ok, code, detail = await check_ldap(svc.ip_host, str(port))
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
