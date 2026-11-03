"""
Modelos de configuración de monitoreo (Servicios, Sedes, Equipos y Proxies).
"""

from dataclasses import dataclass, field
from typing import List, Optional


@dataclass
class ServiceConfig:
    """DTO de configuración que define los parámetros de chequeo de un servicio."""
    letter: str
    service_type: str  # WEB, DNS, PROXY, SMTP, DHCP, CUPS, LDAP, PING, OTRO
    name: str
    scope: str = "corporativo"  # corporativo | regional
    web_url: str = ""
    ip_host: str = ""
    cups_port_ip: str = ""
    ldap_port_ip: str = ""
    smtp_port: str = ""
    net_interface: str = ""
    dns_test_host: str = ""
    proxy_user_pass: str = ""
    proxy_ip_port: str = ""
    url_test_site: str = ""
    msg_normal: str = ""
    msg_error: str = ""
    id: Optional[int] = None


@dataclass
class EquipmentConfig:
    """DTO que encapsula la configuración de hardware o equipo de red."""
    site_letter: str
    num: int
    name: str
    ip_host: str
    msg_normal: str = ""
    msg_error: str = ""
    id: Optional[int] = None


@dataclass
class SiteConfig:
    """DTO que define los parámetros de conectividad y topología de una sede corporativa."""
    letter: str
    name: str
    ip_host: str
    msg_normal: str = ""
    msg_error: str = ""
    equipment: List[EquipmentConfig] = field(default_factory=list)
    id: Optional[int] = None


@dataclass
class ProxyConfig:
    """DTO de configuración para proxies de red corporativos."""
    letter: str
    name: str
    ip_port: str
    user_pass: str
    url: str
