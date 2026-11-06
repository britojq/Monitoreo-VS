"""
Paquete modular GitOps (Actualizaciones, Rollback y Circuit Breaker).
"""

from bot_core.handlers.gitops.dtos import UpdateActionPayload
from bot_core.handlers.gitops.update_handler import (
    cmd_actualizar,
    cmd_estado_deploy,
    cmd_log_actualizacion,
    handle_update_callback,
    _send_last_deployment_audit_doc,
)
from bot_core.handlers.gitops.rollback_handler import cmd_rollback
from bot_core.handlers.gitops.circuit_breaker import (
    cmd_desbloquear_update,
    cmd_reiniciar_bot,
)

__all__ = [
    "UpdateActionPayload",
    "cmd_actualizar",
    "cmd_estado_deploy",
    "cmd_log_actualizacion",
    "handle_update_callback",
    "_send_last_deployment_audit_doc",
    "cmd_rollback",
    "cmd_desbloquear_update",
    "cmd_reiniciar_bot",
]
