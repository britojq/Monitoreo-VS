"""
Paquete modular de mantenimiento del sistema (limpieza de almacenamiento y optimización de recursos).
"""

from bot_core.handlers.system_maintenance.dtos import CleanerActionPayload, OptimizerActionPayload
from bot_core.handlers.system_maintenance.cleaner_handler import cmd_limpiador, handle_cleaner_callback
from bot_core.handlers.system_maintenance.optimizer_handler import cmd_recursos_sistema, handle_optimizer_callback

__all__ = [
    "CleanerActionPayload",
    "OptimizerActionPayload",
    "cmd_limpiador",
    "handle_cleaner_callback",
    "cmd_recursos_sistema",
    "handle_optimizer_callback",
]
