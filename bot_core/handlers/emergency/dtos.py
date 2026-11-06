"""
DTOs inmutables para el subsistema de protocolos de contingencia y emergencia.
"""

from __future__ import annotations
from dataclasses import dataclass


@dataclass(frozen=True)
class EmergencyActionPayload:
    """Payload tipado para callbacks del panel de emergencia."""
    action: str

    @classmethod
    def from_callback_data(cls, raw: str) -> EmergencyActionPayload:
        action = raw.replace("emergencia:", "")
        return cls(action=action)
