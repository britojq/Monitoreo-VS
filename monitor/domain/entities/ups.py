"""
# ==============================================================================
# 🔋 ENTIDAD DE TELEMETRÍA UPS: ups.py
# Modelado inmutable del estado eléctrico y operativo del sistema de respaldo UPS.
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class UpsStatusEntity:
    """Entidad inmutable que representa la telemetría de una UPS."""
    status: str  # OL, OB, LB, etc.
    input_voltage: float
    output_voltage: float
    battery_percentage: float
    load_percentage: float
    is_on_battery: bool
    battery_runtime_seconds: Optional[int] = None
    temperature_celsius: Optional[float] = None
    serial_number: Optional[str] = None
    model: Optional[str] = None
    is_spurious_transient: bool = False

    @property
    def is_healthy(self) -> bool:
        """Determina si la UPS opera normalmente en línea (OL) con voltaje adecuado."""
        return not self.is_on_battery and self.battery_percentage >= 80.0
