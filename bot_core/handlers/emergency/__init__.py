"""
Paquete modular de protocolos de contingencia y emergencia.
"""

from bot_core.handlers.emergency.dtos import EmergencyActionPayload
from bot_core.handlers.emergency.emergency_handler import (
    _build_emergency_panel,
    _delayed_emergency_stop,
    cmd_emergencia,
    handle_emergency_callback,
)
from bot_core.handlers.emergency.reboot_handler import cmd_reinicia

__all__ = [
    "EmergencyActionPayload",
    "_build_emergency_panel",
    "_delayed_emergency_stop",
    "cmd_emergencia",
    "handle_emergency_callback",
    "cmd_reinicia",
]
