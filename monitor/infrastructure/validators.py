"""
Módulo centralizado de validación estricta de entradas de red y sistema (SEC-008).
Previene inyecciones de comandos o parámetros anómalos mediante whitelisting estricto de formatos.
"""
from __future__ import annotations

import ipaddress
import re
from typing import Any, Optional

IPV4_REGEX = re.compile(
    r"^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$"
)
OID_REGEX = re.compile(r"^[0-2](?:\.[0-9]+)+$")
HOSTNAME_REGEX = re.compile(r"^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(?:\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))*$")
IFACE_REGEX = re.compile(r"^[a-zA-Z0-9_.-]{1,16}$")


def validate_ipv4(ip: Any) -> bool:
    """Valida sintáctica y semánticamente una dirección IPv4."""
    if not ip or not isinstance(ip, str):
        return False
    ip_clean = ip.strip()
    if not IPV4_REGEX.match(ip_clean):
        return False
    try:
        ipaddress.IPv4Address(ip_clean)
        return True
    except ipaddress.AddressValueError:
        return False


def validate_oid(oid: Any) -> bool:
    """Valida la sintaxis numérica estándar de un OID SNMP (ej: 1.3.6.1.2.1.1.1)."""
    if not oid or not isinstance(oid, str):
        return False
    return bool(OID_REGEX.match(oid.strip()))


def validate_hostname(host: Any) -> bool:
    """Valida un nombre de host seguro según el estándar RFC 1123."""
    if not host or not isinstance(host, str):
        return False
    return bool(HOSTNAME_REGEX.match(host.strip()))


def validate_network_interface(iface: Any) -> bool:
    """Valida que el nombre de una interfaz de red cumpla con la convención estándar del kernel."""
    if not iface or not isinstance(iface, str):
        return False
    return bool(IFACE_REGEX.match(iface.strip()))


def validate_snmp_target(target: Any) -> bool:
    """
    Valida un objetivo SNMP: debe ser una IPv4 válida, un ID de dispositivo numérico,
    o un nombre de host institucional seguro.
    """
    if not target or not isinstance(target, str):
        return False
    target_clean = target.strip()
    if target_clean.isdigit():
        return int(target_clean) > 0
    return validate_ipv4(target_clean) or validate_hostname(target_clean)
