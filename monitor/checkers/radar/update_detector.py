"""
Detector de firmas de actualización (Windows Update, repositorios Linux) y resolución OS.
"""
import logging
import socket
from typing import Tuple

from monitor.checkers.radar.constants import (
    LINUX_REPO_DOMAINS,
    WINDOWS_UPDATE_DOMAINS,
)
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.radar.update_detector")


def detect_update_activity(domain: str, port: str = "") -> Tuple[str, str]:
    """
    Analiza un dominio o puerto y determina si corresponde a Windows Update o Repositorios Linux.
    Retorna (tipo_actualización, estado):
    - tipo: 'windows_update', 'linux_repo', o ''
    - estado: 'downloading', 'checking', o 'none'
    """
    if not domain and port != "7680":
        return "", "none"

    domain_lower = domain.lower()

    if port == "7680" or any(w in domain_lower for w in WINDOWS_UPDATE_DOMAINS):
        if any(k in domain_lower for k in ["delivery.mp", "download.windowsupdate", "fe3.delivery", "tlu.dl"]):
            return "windows_update", "downloading"
        return "windows_update", "checking"

    if any(l in domain_lower for l in LINUX_REPO_DOMAINS):
        if any(k in domain_lower for k in ["archive", "ports", "mirror", "repo", "download"]):
            return "linux_repo", "downloading"
        return "linux_repo", "checking"

    return "", "none"


def infer_os_and_hostname(ip: str, mac: str, vendor: str, conn=None) -> Tuple[str, str]:
    """Estima el sistema operativo y resuelve el nombre de host."""
    hostname = ""
    os_detected = "Desconocido"

    try:
        with db_cursor() as cur:
            cur.execute("""
                SELECT name, brand, model, type, os 
                FROM inventory_assets 
                WHERE ip_address = %s OR (mac_address = %s AND mac_address != '') 
                LIMIT 1
            """, (ip, mac))
            asset_row = cur.fetchone()
            if asset_row:
                if asset_row.get("name"):
                    hostname = asset_row["name"]
                if asset_row.get("os"):
                    os_detected = asset_row["os"]
                elif asset_row.get("type") in ("switch", "router"):
                    os_detected = f"Equipo de Red ({asset_row['type'].capitalize()})"
                elif asset_row.get("type") == "server":
                    os_detected = "Servidor Linux"
                elif asset_row.get("type") in ("desktop", "laptop"):
                    os_detected = "Estación de Trabajo"

            if not hostname:
                cur.execute("SELECT hostname, device_type FROM discovered_devices WHERE ip_address = %s LIMIT 1", (ip,))
                row = cur.fetchone()
                if row:
                    if row.get("hostname"):
                        hostname = row["hostname"]
                    dev_t = row.get("device_type", "")
                    if os_detected == "Desconocido":
                        if dev_t in ("switch", "router"):
                            os_detected = f"Equipo de Red ({dev_t.capitalize()})"
                        elif dev_t == "printer":
                            os_detected = "Impresora de Red"
                        elif dev_t == "server":
                            os_detected = "Servidor Linux"
    except Exception:
        pass

    if not hostname:
        try:
            h, _, _ = socket.gethostbyaddr(ip)
            if h and h != ip:
                hostname = h
        except Exception:
            pass

    text = f"{hostname} {vendor}".lower()
    if os_detected == "Desconocido":
        if any(w in text for w in ["win", "desktop-", "laptop-", "pc-", "dell", "hp inc", "workstation"]):
            os_detected = "Windows 7/11"
        elif any(w in text for w in ["cisco", "catalyst", "mikrotik", "fortinet", "router", "switch"]):
            os_detected = "Cisco IOS / Router"
        elif any(w in text for w in ["debian", "ubuntu", "srv", "server", "linux", "vmware", "qemu"]):
            os_detected = "Linux Server"
        elif any(w in text for w in ["printer", "laserjet", "epson", "xerox", "canon"]):
            os_detected = "Impresora"

    return os_detected, hostname
