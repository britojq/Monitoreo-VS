"""
DTOs inmutables para el subsistema de mantenimiento del sistema.
"""

from __future__ import annotations
from dataclasses import dataclass


@dataclass(frozen=True)
class CleanerActionPayload:
    """Payload tipado para las acciones del limpiador de almacenamiento."""
    action: str
    target: str = ""

    @classmethod
    def from_callback_data(cls, raw: str) -> CleanerActionPayload:
        parts = raw.split(":", 1)
        action = parts[1] if len(parts) > 1 else ""
        return cls(action=action)


@dataclass(frozen=True)
class OptimizerActionPayload:
    """Payload tipado para las acciones de optimización de RAM y CPU."""
    action: str

    @classmethod
    def from_callback_data(cls, raw: str) -> OptimizerActionPayload:
        parts = raw.split(":", 1)
        action = parts[1] if len(parts) > 1 else ""
        return cls(action=action)
