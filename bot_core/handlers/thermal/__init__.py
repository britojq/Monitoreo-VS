"""
Paquete modular de telemetría térmica y guardián de CPU.
"""

from bot_core.handlers.thermal.dtos import ThermalMetricsDto
from bot_core.handlers.thermal.thermal_handler import (
    format_thermal_dashboard_html,
    cmd_temperatura,
    handle_thermal_callback,
)

__all__ = [
    "ThermalMetricsDto",
    "format_thermal_dashboard_html",
    "cmd_temperatura",
    "handle_thermal_callback",
]
