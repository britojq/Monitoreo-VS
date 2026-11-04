"""
# ==============================================================================
# 🧪 COMPOSITION ROOT & FACHADA DE CHEQUEO DE PROTOCOLOS: checker_base.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y Open/Closed (OCP)
# Orquesta y expone las estrategias modulares de verificación en monitor/checkers/
# Ubicación: /opt/sonar/monitor/checker_base.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

from typing import Any, Tuple

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
from monitor.infrastructure.network_utils import get_active_network_interface

__all__ = (
    "CheckerRegistry",
    "BaseServiceChecker",
    "CheckResult",
    "get_active_network_interface",
    "check_ping",
    "check_web",
    "check_dns",
    "check_smtp",
    "check_dhcp",
    "check_cups",
    "check_ldap",
    "check_proxy",
    "markdown_to_telegram_html",
    "execute_service_check",
    "WebChecker",
    "DnsChecker",
    "SmtpChecker",
    "DhcpChecker",
    "CupsChecker",
    "LdapChecker",
    "PingChecker",
    "ProxyChecker",
)


def markdown_to_telegram_html(text: str) -> str:
    """Convierte Markdown estándar a HTML compatible con Telegram (delegado a formatter)."""
    from monitor.formatters.telegram_formatter import markdown_to_telegram_html as _fmt
    return _fmt(text)


async def execute_service_check(svc: Any) -> CheckResult:
    """
    Composition Root: resuelve y ejecuta la estrategia de verificación correspondiente
    a partir del catálogo desacoplado en CheckerRegistry.
    """
    svc_type = getattr(svc, "service_type", None) or getattr(svc, "tipo_servicio", "PING")
    checker = CheckerRegistry.get(svc_type)
    return await checker.check(svc)
