"""
Paquete modular de control y administración del Asistente Corporativo.
"""

from bot_core.handlers.ai_control.dtos import AiControlActionPayload
from bot_core.handlers.ai_control.ai_admin_handler import (
    format_ai_control_panel_html,
    cmd_ia_control,
    handle_ia_callback,
)

__all__ = [
    "AiControlActionPayload",
    "format_ai_control_panel_html",
    "cmd_ia_control",
    "handle_ia_callback",
]
