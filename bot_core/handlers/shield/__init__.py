"""
Paquete modular de Terminal Shield y mitigación PAM.
"""

from bot_core.handlers.shield.dtos import ShieldCallbackPayload, ShieldAuthResultDto
from bot_core.handlers.shield.contracts import ShieldActionProtocol
from bot_core.handlers.shield.actions import (
    AuthorizeTerminalAction,
    KillTerminalAction,
    LockdownAction,
    UnlockAction,
    SoyYoAction,
)
from bot_core.handlers.shield.router import (
    cmd_lockdown,
    cmd_unlock,
    cmd_autorizar_consola,
    handle_shield_callback,
    handle_soy_yo_text_message,
)

__all__ = [
    "ShieldCallbackPayload",
    "ShieldAuthResultDto",
    "ShieldActionProtocol",
    "AuthorizeTerminalAction",
    "KillTerminalAction",
    "LockdownAction",
    "UnlockAction",
    "SoyYoAction",
    "cmd_lockdown",
    "cmd_unlock",
    "cmd_autorizar_consola",
    "handle_shield_callback",
    "handle_soy_yo_text_message",
]
