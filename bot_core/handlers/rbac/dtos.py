"""
DTOs inmutables para el subsistema RBAC y autorización de usuarios.
"""

from __future__ import annotations
from dataclasses import dataclass


@dataclass(frozen=True)
class DeniedUserSummaryDto:
    """Información inmutable de un usuario no autorizado auditado."""
    user_id: int
    username: str
    name: str
    last_seen: str


@dataclass(frozen=True)
class RbacActionPayload:
    """Payload tipado para callbacks de permisos y autorización."""
    action: str
    target_id: int

    @classmethod
    def from_callback_data(cls, raw: str) -> RbacActionPayload:
        parts = raw.split(":", 1)
        action = parts[0]
        target_id = int(parts[1]) if len(parts) > 1 and parts[1].lstrip("-").isdigit() else 0
        return cls(action=action, target_id=target_id)
