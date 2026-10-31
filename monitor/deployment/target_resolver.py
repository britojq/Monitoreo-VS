"""
Resolución de endpoints y conectividad de red para despliegue de agentes.
"""

import socket
from typing import Optional, Tuple
from urllib.parse import urlparse

DEV_SERVER_URL = "http://192.168.1.220/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96"
PROD_SERVER_URL = "http://192.168.1.250/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96"
DEV_DEPLOY_BASE = "http://192.168.1.220"
PROD_DEPLOY_BASE = "http://192.168.1.250"


def is_port_open(host: str, port: int, timeout: float = 2.0) -> bool:
    """Comprueba rápidamente si un puerto TCP está abierto antes de conectar."""
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True
    except (socket.timeout, ConnectionRefusedError, OSError):
        return False


def get_target_url(
    target: str = "dev",
    server_url: Optional[str] = None,
    deploy_base: Optional[str] = None,
) -> Tuple[str, str]:
    """Retorna (server_url, deploy_base_url) según argumentos dinámicos o entorno seleccionado."""
    if server_url and deploy_base:
        return server_url, deploy_base
    if server_url and not deploy_base:
        parsed = urlparse(server_url)
        base = f"{parsed.scheme}://{parsed.netloc}" if parsed.netloc else server_url
        return server_url, base

    norm = str(target).lower().strip()
    if norm in ("prod", "production", "produccion"):
        return PROD_SERVER_URL, PROD_DEPLOY_BASE
    if norm in ("auto", "current"):
        return DEV_SERVER_URL, DEV_DEPLOY_BASE
    return DEV_SERVER_URL, DEV_DEPLOY_BASE
