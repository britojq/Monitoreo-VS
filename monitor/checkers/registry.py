"""
# ==============================================================================
# 🗂️ REGISTRO DINÁMICO DE VERIFICADORES: registry.py
# Principio Abierto/Cerrado (O): Permite registrar nuevas estrategias sin tocar el código central
# ==============================================================================
"""

from __future__ import annotations

from typing import Dict
from monitor.checkers.base import BaseServiceChecker
from monitor.checkers.web_checker import WebChecker
from monitor.checkers.dns_checker import DnsChecker
from monitor.checkers.proxy_checker import ProxyChecker
from monitor.checkers.smtp_checker import SmtpChecker
from monitor.checkers.dhcp_checker import DhcpChecker
from monitor.checkers.cups_checker import CupsChecker
from monitor.checkers.ldap_checker import LdapChecker
from monitor.checkers.ping_checker import PingChecker


class CheckerRegistry:
    """Registro central de estrategias de verificación de servicios de red."""

    _checkers: Dict[str, BaseServiceChecker] = {}
    _default_checker: BaseServiceChecker = PingChecker()

    @classmethod
    def initialize_defaults(cls) -> None:
        """Carga el catálogo estándar de verificadores soportados."""
        cls._checkers = {
            "WEB": WebChecker(),
            "DNS": DnsChecker(),
            "PROXY": ProxyChecker(),
            "SMTP": SmtpChecker(),
            "DHCP": DhcpChecker(),
            "CUPS": CupsChecker(),
            "LDAP": LdapChecker(),
            "PING": PingChecker(),
        }
        cls._default_checker = PingChecker()

    @classmethod
    def register(cls, service_type: str, checker: BaseServiceChecker) -> None:
        """
        Registra o sobrescribe una estrategia de verificación para un tipo de servicio.
        Permite extender el sistema con nuevos protocolos (ej: SNMP, NTP, REDIS)
        sin modificar el orquestador principal.
        """
        cls._checkers[service_type.upper().strip()] = checker

    @classmethod
    def get(cls, service_type: str) -> BaseServiceChecker:
        """Obtiene la estrategia adecuada según el tipo de servicio (fallback a PingChecker)."""
        if not cls._checkers:
            cls.initialize_defaults()

        clean_type = (service_type or "").upper().strip()
        return cls._checkers.get(clean_type, cls._default_checker)


# Inicialización automática al cargar el módulo
CheckerRegistry.initialize_defaults()
