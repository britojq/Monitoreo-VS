"""
# ==============================================================================
# 🚨 ENTIDAD DE ALERTA DEL SISTEMA: alert.py
# Modelado inmutable de alertas e incidencias de telemetría y seguridad.
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Optional


@dataclass(frozen=True)
class AlertEntity:
    """Entidad inmutable que representa una alerta o evento en el sistema."""
    id: Optional[int]
    event_type: str  # SERVICE_DOWN, DEVICE_DOWN, OUTAGE, SECURITY, etc.
    severity: str    # INFO, WARNING, CRITICAL, RECOVERY
    title: str
    message: str
    target_identifier: str  # IP, Hostname, Nombre de servicio
    target_chat_type: str = "owner"  # "owner" o "group"
    is_dispatched: bool = False
    created_at: Optional[datetime] = None
    resolved_at: Optional[datetime] = None

    @property
    def is_security_event(self) -> bool:
        """Determina si es un evento de seguridad que bajo Regla de Oro #1 no debe ir a grupos."""
        return self.event_type.upper() in {"SECURITY", "LOGIN", "AUTH_FAIL", "PAM", "SSH", "AUDIT"}
