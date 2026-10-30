"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD DOMAIN DTOS: dtos.py
# DTOs inmutables para sesiones de terminal, procesos e infracciones
# Ubicación: /opt/sonar/monitor/shield/domain/dtos.py
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class TerminalSessionDto:
    """Información de sesión de terminal interactiva."""
    pid: int
    ppid: int
    user: str
    tty: str
    display: str = ""
    proc_name: str = ""
    parent_name: str = ""


@dataclass(frozen=True)
class ProcessContextDto:
    """Contexto de proceso del sistema para inspección de seguridad."""
    pid: int
    name: str
    cmdline: str
    is_protected: bool


@dataclass(frozen=True)
class InfractionRecordDto:
    """Registro de infracciones de seguridad acumuladas en memoria volátil."""
    count: int
    last_ts: float
    last_reason: str
    updated_at: str


@dataclass(frozen=True)
class AuthWindowDto:
    """Ventana de mantenimiento interactivo concedida."""
    success: bool
    expiry: str
    minutes: int
    tty: str

    def to_dict(self) -> dict:
        return {
            "success": self.success,
            "expiry": self.expiry,
            "minutes": self.minutes,
            "tty": self.tty,
        }
