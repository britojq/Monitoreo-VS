"""
DTOs inmutables para el subsistema de administración del motor de IA / Asistente Corporativo.
Principio SOLID: Estricto cumplimiento de la Regla de Oro #2.
"""

from __future__ import annotations
from dataclasses import dataclass


@dataclass(frozen=True)
class AiControlActionPayload:
    """Payload tipado para callbacks del panel de control de IA."""
    action: str

    @classmethod
    def from_callback_data(cls, raw: str) -> AiControlActionPayload:
        parts = raw.split(":", 1)
        action = parts[1] if len(parts) > 1 else ""
        return cls(action=action)
