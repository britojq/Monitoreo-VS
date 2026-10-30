"""
DTOs inmutables para el subsistema de tareas programadas y cron.
"""

from __future__ import annotations
from dataclasses import dataclass
from typing import List


@dataclass(frozen=True)
class CronScheduleConfigDto:
    """Configuración tipada de horarios de cron."""
    enabled: bool
    schedules: List[str]
    updated_at: str
    updated_by: str


@dataclass(frozen=True)
class CronActionPayload:
    """Payload tipado para callbacks de control de cron."""
    action: str

    @classmethod
    def from_callback_data(cls, raw: str) -> CronActionPayload:
        action = raw.replace("cron_act:", "")
        return cls(action=action)
