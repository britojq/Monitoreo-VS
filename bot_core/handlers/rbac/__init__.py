"""
Paquete modular RBAC (Control de acceso basado en roles y permisos).
"""

from bot_core.handlers.rbac.dtos import DeniedUserSummaryDto, RbacActionPayload
from bot_core.handlers.rbac.rbac_handler import (
    _resolve_user_info,
    _format_audit_date,
    get_denied_users_summary,
    _build_permissions_panel,
    manage_permissions,
)
from bot_core.handlers.rbac.auth_callbacks import handle_auth_callback

__all__ = [
    "DeniedUserSummaryDto",
    "RbacActionPayload",
    "_resolve_user_info",
    "_format_audit_date",
    "get_denied_users_summary",
    "_build_permissions_panel",
    "manage_permissions",
    "handle_auth_callback",
]
