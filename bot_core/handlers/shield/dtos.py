"""
DTOs inmutables para el subsistema de Terminal Shield y control PAM.
Principio SOLID: Inmutabilidad garantizada mediante dataclasses congeladas.
"""

from __future__ import annotations
from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class ShieldCallbackPayload:
    """Payload estructurado y tipado derivado del callback_data de Telegram."""
    action: str
    target_server: str = ""
    target_pid: int = 0
    target_ppid: int = 0
    raw_data: str = ""

    @classmethod
    def from_callback_data(cls, raw: str) -> ShieldCallbackPayload:
        parts = raw.split(":")
        action = parts[0] if len(parts) > 0 else ""
        server = parts[1] if len(parts) > 1 else ""
        pid = int(parts[2]) if len(parts) > 2 and parts[2].isdigit() else 0
        ppid = int(parts[3]) if len(parts) > 3 and parts[3].isdigit() else 0
        return cls(action=action, target_server=server, target_pid=pid, target_ppid=ppid, raw_data=raw)


@dataclass(frozen=True)
class ShieldAuthResultDto:
    """Resultado inmutable de la autorización de sesión interactiva."""
    success: bool
    tty: str
    target_pid: int
    duration_minutes: int
    expiry: str
    message: str
