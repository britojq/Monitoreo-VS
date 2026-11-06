"""
Módulo de resolución OUI, validación de subred y lista blanca MAC.
"""
import ipaddress
import logging
import re
import socket
import subprocess
from typing import Dict, Set, Tuple

from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.analyzer.oui_resolver")


def is_ip_in_local_subnet(ip_str: str, my_ip: str, my_mask: str) -> bool:
    """Verifica si una IP pertenece a la misma subred local IPv4."""
    try:
        if not ip_str or not my_ip or not my_mask:
            return False
        network = ipaddress.IPv4Network(f"{my_ip}/{my_mask}", strict=False)
        target = ipaddress.IPv4Address(ip_str)
        return target in network
    except Exception:
        return False


def get_interface_ip_and_mask(interface: str) -> Tuple[str, str]:
    """Obtiene la dirección IPv4 y la máscara de red de la interfaz indicada."""
    try:
        out = subprocess.check_output(
            ["ip", "-4", "addr", "show", "dev", interface],
            text=True,
            timeout=3.0
        )
        match = re.search(r"inet\s+([0-9.]+)/([0-9]+)", out)
        if match:
            return match.group(1), match.group(2)
    except Exception as e:
        logger.warning(f"No se pudo obtener IP/máscara para {interface}: {e}")
    return "", ""


def load_mac_whitelist() -> Set[str]:
    """Carga la lista blanca de direcciones MAC autorizadas directamente desde MariaDB (SSOT)."""
    macs: Set[str] = set()
    try:
        with db_cursor() as cur:
            # 1. MACs registradas en inventario oficial
            cur.execute(
                "SELECT DISTINCT LOWER(mac_address) AS mac FROM inventory_assets "
                "WHERE mac_address IS NOT NULL AND mac_address != ''"
            )
            for r in cur.fetchall():
                m = (r.get("mac") or "").strip().lower()
                if re.match(r"^([0-9a-f]{2}[:-]){5}[0-9a-f]{2}$", m):
                    macs.add(m.replace("-", ":"))

            # 2. MACs de dispositivos de red monitoreados
            cur.execute(
                "SELECT DISTINCT LOWER(mac) AS mac FROM monitored_network_devices "
                "WHERE mac IS NOT NULL AND mac != ''"
            )
            for r in cur.fetchall():
                m = (r.get("mac") or "").strip().lower()
                if re.match(r"^([0-9a-f]{2}[:-]){5}[0-9a-f]{2}$", m):
                    macs.add(m.replace("-", ":"))

            # 3. Dispositivos descubiertos y expresamente autorizados
            cur.execute(
                "SELECT DISTINCT LOWER(mac_address) AS mac FROM discovered_devices "
                "WHERE is_authorized = 1 AND mac_address IS NOT NULL AND mac_address != ''"
            )
            for r in cur.fetchall():
                m = (r.get("mac") or "").strip().lower()
                if re.match(r"^([0-9a-f]{2}[:-]){5}[0-9a-f]{2}$", m):
                    macs.add(m.replace("-", ":"))

        logger.debug(f"Lista blanca de MACs autorizadas cargada desde MariaDB ({len(macs)} registradas)")
    except Exception as e:
        logger.warning(f"No se pudo cargar la lista de MACs desde MariaDB: {e}")
    return macs


def load_oui_database() -> Dict[str, str]:
    """Carga la base de datos de fabricantes OUI directamente desde MariaDB (SSOT)."""
    oui_dict: Dict[str, str] = {}
    try:
        with db_cursor() as cur:
            cur.execute("SELECT oui_prefix, vendor_name FROM oui_vendors WHERE oui_prefix IS NOT NULL")
            for r in cur.fetchall():
                prefix = (r.get("oui_prefix") or "").strip().lower().replace(":", "").replace("-", "")
                vendor = (r.get("vendor_name") or "").strip()
                if prefix and vendor:
                    oui_dict[prefix] = vendor
        logger.debug(f"Base de datos OUI cargada desde MariaDB ({len(oui_dict)} fabricantes)")
    except Exception as e:
        logger.warning(f"No se pudo cargar la base OUI desde MariaDB: {e}")
    return oui_dict


def resolve_hostname_sync(ip_str: str, mac_str: str, oui_db: Dict[str, str]) -> str:
    """Resuelve el hostname vía DNS inverso o fabricante OUI."""
    # 1. Reverse DNS
    try:
        host, _, _ = socket.gethostbyaddr(ip_str)
        if host and host != ip_str:
            return host
    except Exception:
        pass

    # 2. Fabricante OUI
    if mac_str and mac_str not in ("desconocida", "N/A (externo)", ""):
        clean_mac = mac_str.lower().replace(":", "").replace("-", "")
        if len(clean_mac) >= 6:
            prefix = clean_mac[:6]
            if prefix in oui_db:
                return oui_db[prefix]

    return "desconocido"
