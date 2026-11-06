"""
DTOs inmutables para el subsistema de migración de token y activación DRM.
"""

from __future__ import annotations
from dataclasses import dataclass

MIGRAR_TOKEN_WAITING = 1


@dataclass(frozen=True)
class TokenMigrationStateDto:
    """Estado inmutable de la conversación de migración de token."""
    started_at: float
    is_expired: bool
