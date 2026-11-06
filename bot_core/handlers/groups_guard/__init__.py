"""
Paquete modular de auditoría y defensa contra grupos (Regla de Oro #1).
"""

from bot_core.handlers.groups_guard.dtos import GroupAuditItemDto
from bot_core.handlers.groups_guard.group_enforcer import (
    _get_known_group_candidates,
    _resolve_group_info,
    _build_groups_audit_panel,
    cmd_grupos,
    cmd_salir_grupo,
)
from bot_core.handlers.groups_guard.group_events import (
    notify_owner_bot_added_to_group,
    on_my_chat_member,
    on_new_chat_members,
)

__all__ = [
    "GroupAuditItemDto",
    "_get_known_group_candidates",
    "_resolve_group_info",
    "_build_groups_audit_panel",
    "cmd_grupos",
    "cmd_salir_grupo",
    "notify_owner_bot_added_to_group",
    "on_my_chat_member",
    "on_new_chat_members",
]
