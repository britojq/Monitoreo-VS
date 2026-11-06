"""
DTOs inmutables para el subsistema GitOps, actualizaciones y rollback.
"""

from __future__ import annotations
from dataclasses import dataclass


@dataclass(frozen=True)
class UpdateActionPayload:
    """Payload tipado para callbacks de actualización de sistema."""
    action: str

    @classmethod
    def from_callback_data(cls, raw: str) -> UpdateActionPayload:
        action = raw.replace("update_act:", "")
        return cls(action=action)
