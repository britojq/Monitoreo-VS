"""
# ==============================================================================
# 🔋 UPS SERIAL SCANNER: serial_scanner.py
# Detección y enumeración priorizada de puertos seriales y adaptadores USB
# Ubicación: /opt/sonar/monitor/ups/drivers/serial_scanner.py
# ==============================================================================
"""

from __future__ import annotations

import glob
from pathlib import Path
from typing import List


def list_system_serial_ports() -> List[str]:
    """Retorna lista priorizada de puertos seriales detectados en el sistema operativo."""
    # Priorizar adaptadores USB ya que se conectan para sustituir o añadir puertos COM
    usb_ports = sorted(glob.glob("/dev/ttyUSB*"))
    acm_ports = sorted(glob.glob("/dev/ttyACM*"))
    # Puertos COM integrados en placa base
    s_ports = sorted(glob.glob("/dev/ttyS[0-3]"))

    ports: List[str] = []
    for p in usb_ports + acm_ports + s_ports:
        if p not in ports and Path(p).exists():
            ports.append(p)
    return ports
