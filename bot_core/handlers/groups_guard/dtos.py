"""
DTOs inmutables para la auditoría y control de grupos (Regla de Oro #1).
"""

from __future__ import annotations
from dataclasses import dataclass


@dataclass(frozen=True)
class GroupAuditItemDto:
    """Información inmutable de un grupo auditado."""
    group_id: int
    title: str
    role_label: str
    is_allowed: bool
