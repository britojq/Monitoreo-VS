"""
DTOs inmutables para el subsistema de diagnóstico y telemetría de bot.
"""

from __future__ import annotations
from dataclasses import dataclass
from typing import List


@dataclass(frozen=True)
class EndpointHealthResultDto:
    """Resultado de comprobación de salud de un endpoint."""
    name: str
    ok: bool
    detail: str


@dataclass(frozen=True)
class BroadcastDeliveryResultDto:
    """Resultado del envío masivo de difusión."""
    success_count: int
    fail_count: int
    total_targets: int
    delivery_details: List[str]
