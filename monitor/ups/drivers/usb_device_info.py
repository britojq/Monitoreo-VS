"""
# ==============================================================================
# 🔋 UPS USB DEVICE INFO: usb_device_info.py
# Extracción de descriptores de hardware USB vía sysfs de Linux
# Ubicación: /opt/sonar/monitor/ups/drivers/usb_device_info.py
# ==============================================================================
"""

from __future__ import annotations

from pathlib import Path
from typing import Dict


def get_usb_device_info(port: str) -> Dict[str, str]:
    """Extrae metadatos USB (vendor, model, serial) mediante /sys en Linux si es un puerto ttyUSB o ttyACM."""
    info = {"usb_vendor": "", "usb_model": "", "usb_serial": ""}
    port_name = Path(port).name
    sys_path = Path(f"/sys/class/tty/{port_name}/device")
    if not sys_path.exists():
        return info

    try:
        parent = sys_path
        for _ in range(4):
            parent = parent.parent
            manufacturer_file = parent / "manufacturer"
            product_file = parent / "product"
            serial_file = parent / "serial"
            if product_file.exists() and not info["usb_model"]:
                info["usb_model"] = product_file.read_text(encoding="utf-8", errors="ignore").strip()
            if manufacturer_file.exists() and not info["usb_vendor"]:
                info["usb_vendor"] = manufacturer_file.read_text(encoding="utf-8", errors="ignore").strip()
            if serial_file.exists() and not info["usb_serial"]:
                info["usb_serial"] = serial_file.read_text(encoding="utf-8", errors="ignore").strip()
    except Exception:
        pass

    return info
