"""
# ==============================================================================
# 📡 ESTRATEGIA DE VERIFICACIÓN DHCP: dhcp_checker.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from typing import Tuple

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.parsers import ServiceConfig
from monitor.infrastructure.network_utils import get_active_network_interface
from monitor.infrastructure.validators import validate_network_interface


async def check_dhcp(interface: str = "eth0", timeout: float = 13.0) -> Tuple[bool, str, str]:
    """Verifica si un servidor DHCP ofrece concesiones en la red local usando broadcast con nmap."""
    if not validate_network_interface(interface):
        return False, "0", f"Nombre de interfaz de red inválido: {interface}"

    active_iface = get_active_network_interface(interface)

    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "nmap", "--script", "broadcast-dhcp-discover", "-e", active_iface,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")
        if "DHCP Message Type: DHCPOFFER" in out_str or "IP Offered:" in out_str:
            return True, "200", f"Servidor DHCP activo (DHCPOFFER recibido en {active_iface})"
        return False, "0", f"Servidor DHCP no responde en {active_iface}"
    except asyncio.TimeoutError:
        return False, "0", f"Timeout en broadcast DHCP ({active_iface})"
    except Exception as e:
        return False, "0", f"Error verificando DHCP: {e}"


class DhcpChecker(BaseServiceChecker):
    """Verificador de servidores DHCP mediante broadcast discovery."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de ofertas DHCP."""
        iface = getattr(svc, "net_interface", "") or getattr(svc, "interface", "") or "eth0"
        is_ok, code, detail = await check_dhcp(iface)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
