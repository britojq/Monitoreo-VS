"""
DTOs inmutables para el subsistema de telemetría térmica.
"""

from __future__ import annotations
from dataclasses import dataclass
from typing import Dict


@dataclass(frozen=True)
class ThermalMetricsDto:
    """Telemetría inmutable de temperaturas de CPU."""
    max_temp: float
    package_temp: float
    avg_temp: float
    core_temps: Dict[str, float]
    level: str
    badge: str
