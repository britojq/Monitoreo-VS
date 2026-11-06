"""
# ==============================================================================
# 🧪 PAQUETE MODULAR DE CHEQUEO DE PROTOCOLOS: monitor/checkers/__init__.py
# Orquesta y expone las estrategias modulares de verificación.
# ==============================================================================
"""
from typing import Any

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.checkers.cups_checker import CupsChecker, check_cups
from monitor.checkers.dhcp_checker import DhcpChecker, check_dhcp
from monitor.checkers.dns_checker import DnsChecker, check_dns
from monitor.checkers.ldap_checker import LdapChecker, check_ldap
from monitor.checkers.ping_checker import PingChecker, check_ping
from monitor.checkers.proxy_checker import ProxyChecker, check_proxy
from monitor.checkers.registry import CheckerRegistry
from monitor.checkers.smtp_checker import SmtpChecker, check_smtp
from monitor.checkers.web_checker import WebChecker, check_web


async def execute_service_check(svc: Any) -> CheckResult:
    """Ejecuta la estrategia de verificación según el service_type del servicio."""
    checker = CheckerRegistry.get(svc.service_type)
    return await checker.check(svc)


__all__ = [
    "BaseServiceChecker",
    "CheckResult",
    "CheckerRegistry",
    "CupsChecker",
    "DhcpChecker",
    "DnsChecker",
    "LdapChecker",
    "PingChecker",
    "ProxyChecker",
    "SmtpChecker",
    "WebChecker",
    "check_cups",
    "check_dhcp",
    "check_dns",
    "check_ldap",
    "check_ping",
    "check_proxy",
    "check_smtp",
    "check_web",
    "execute_service_check",
]
