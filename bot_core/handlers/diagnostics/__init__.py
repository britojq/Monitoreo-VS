"""
Paquete modular de diagnóstico del sistema y telemetría de red.
"""

from bot_core.handlers.diagnostics.dtos import (
    EndpointHealthResultDto,
    BroadcastDeliveryResultDto,
)
from bot_core.handlers.diagnostics.debug_monitor import (
    toggle_debug_monitor,
    toggle_commands_lock,
)
from bot_core.handlers.diagnostics.broadcast_handler import (
    cmd_broadcast_mensaje,
    cmd_info,
)
from bot_core.handlers.diagnostics.bot_status import bot_status

__all__ = [
    "EndpointHealthResultDto",
    "BroadcastDeliveryResultDto",
    "toggle_debug_monitor",
    "toggle_commands_lock",
    "cmd_broadcast_mensaje",
    "cmd_info",
    "bot_status",
]
