"""
# ==============================================================================
# 🔋 UPS DETECT HARDWARE ACTION: detect_hardware_action.py
# Escaneo sistemático y autodescubrimiento inteligente de UPS en puertos seriales
# Ubicación: /opt/sonar/monitor/ups/itam/detect_hardware_action.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

from monitor.ups.drivers.megatec_driver import (
    query_ups_info,
    query_ups_rating,
    query_ups_serial,
)
from monitor.ups.drivers.serial_scanner import list_system_serial_ports
from monitor.ups.drivers.usb_device_info import get_usb_device_info

logger = logging.getLogger("ups.service")


def scan_and_detect_ups(
    preferred_port: Optional[str] = None,
    preferred_baud: Optional[int] = None,
    baud_candidates: Tuple[int, ...] = (2400, 9600, 1200, 4800, 19200),
    timeout: float = 0.5,
) -> Optional[Tuple[str, int, Dict[str, Any]]]:
    """
    Escanea sistemáticamente los puertos seriales y velocidades para detectar
    un UPS que responda a la trama industrial Megatec Q1.
    Retorna (puerto, baudios, telemetria) o None.
    """
    all_ports = list_system_serial_ports()

    ports_to_test: List[str] = []
    if preferred_port and Path(preferred_port).exists():
        ports_to_test.append(preferred_port)
    for p in all_ports:
        if p not in ports_to_test:
            ports_to_test.append(p)

    bauds_to_test: List[int] = []
    if preferred_baud and preferred_baud in baud_candidates:
        bauds_to_test.append(preferred_baud)
    for b in baud_candidates:
        if b not in bauds_to_test:
            bauds_to_test.append(b)

    for port in ports_to_test:
        for baud in bauds_to_test:
            try:
                res = query_ups_serial(port=port, baud=baud, timeout=timeout)
                if res and isinstance(res, dict) and res.get("is_online"):
                    logger.info(f"✨ UPS respondido exitosamente en {port} @ {baud} bps")
                    res["info"] = query_ups_info(port=port, baud=baud, timeout=timeout)
                    res["rating"] = query_ups_rating(port=port, baud=baud, timeout=timeout)
                    res["usb_meta"] = get_usb_device_info(port)
                    return port, baud, res
            except Exception as e:
                logger.debug(f"Fallo sondeo en {port} @ {baud}: {e}")
                continue

    return None
