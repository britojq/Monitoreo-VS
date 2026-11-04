"""
# ==============================================================================
# 🌐 UTILIDADES DE RED E INFRAESTRUCTURA: network_utils.py
# Detección dinámica y validación de interfaces de red del sistema
# Ubicación: /opt/sonar/monitor/infrastructure/network_utils.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import logging
from pathlib import Path

logger = logging.getLogger(__name__)


def get_active_network_interface(configured_iface: str = "") -> str:
    """
    Verifica si la interfaz configurada existe físicamente en el sistema (/sys/class/net/).
    Si está mal configurada o no existe, detecta y corrige automáticamente a la interfaz activa.
    """
    clean_iface = (configured_iface or "").strip()
    if clean_iface and Path(f"/sys/class/net/{clean_iface}").exists():
        return clean_iface

    # 1. Intentar detectar interfaz de ruta por defecto
    try:
        with open("/proc/net/route", "r") as f:
            for line in f:
                fields = line.strip().split()
                if len(fields) >= 2 and fields[1] == "00000000":
                    candidate = fields[0]
                    if Path(f"/sys/class/net/{candidate}").exists():
                        if clean_iface and clean_iface not in ("eth0", candidate):
                            logger.warning(
                                f"[!] ADVERTENCIA: La interfaz '{clean_iface}' no existe. "
                                f"Corrigiendo automáticamente a la interfaz activa detectada: '{candidate}'"
                            )
                        return candidate
    except Exception:
        pass

    # 2. Buscar primera interfaz activa no-loopback/no-virtual en /sys/class/net/
    try:
        net_dir = Path("/sys/class/net")
        if net_dir.exists():
            for iface_p in net_dir.iterdir():
                iname = iface_p.name
                if iname not in ("lo", "virbr0", "docker0") and not iname.startswith("veth"):
                    if clean_iface and clean_iface not in ("eth0", iname):
                        logger.warning(
                            f"[!] ADVERTENCIA: La interfaz '{clean_iface}' no existe. "
                            f"Corrigiendo automáticamente a la interfaz detectada: '{iname}'"
                        )
                    return iname
    except Exception:
        pass

    return clean_iface or "eth0"
