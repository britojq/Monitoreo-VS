"""
# ==============================================================================
# 🖧 ENTIDAD DE DISPOSITIVO DE RED: network_device.py
# Modelado inmutable de dispositivos de red integrados al censo MariaDB (SSOT).
# Cumple estrictamente con la Regla de Oro #7 (Nombre Etiqueta vs Hostname)
# y la Regla de Oro #9 (Cero términos vetados).
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class NetworkDeviceEntity:
    """Entidad inmutable de un dispositivo de red en MariaDB."""
    id: Optional[int]
    label_name: str  # Nombre Etiqueta (Identidad operativa principal)
    ip_address: str
    hostname: Optional[str] = None  # Nombre de Equipo (Metadato técnico secundario)
    mac_address: Optional[str] = None
    device_type: str = "GENERIC"
    site_id: Optional[int] = None
    site_name: Optional[str] = None
    is_active: bool = True
    assigned_to: Optional[str] = None  # Funcionario responsable / asignado
    notes: Optional[str] = None

    @property
    def display_identity(self) -> str:
        """Identidad principal visible según Regla de Oro #7."""
        return self.label_name.strip() if self.label_name else (self.hostname or self.ip_address)
