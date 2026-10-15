"""
# ==============================================================================
# 📈 DTO DE TELEMETRÍA Y SNAPSHOT: telemetry_dto.py
# Transporte inmutable para métricas de clúster, réplica e históricos.
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from typing import Any, Dict, List, Optional


@dataclass(frozen=True)
class DeviceTelemetryDTO:
    """Telemetría inmutable de un dispositivo de red."""
    device_id: int
    label_name: str
    ip_address: str
    is_up: bool
    latency_ms: Optional[float] = None
    packet_loss_percent: float = 0.0
    checked_at: Optional[datetime] = None


@dataclass(frozen=True)
class ServiceTelemetryDTO:
    """Telemetría inmutable de un servicio corporativo."""
    service_id: int
    service_name: str
    protocol: str
    target: str
    is_up: bool
    response_time_ms: Optional[float] = None
    status_code: Optional[str] = None
    error_message: Optional[str] = None
    checked_at: Optional[datetime] = None


@dataclass(frozen=True)
class SiteTelemetryDTO:
    """Telemetría inmutable de una sede física."""
    site_id: int
    site_letter: str
    site_name: str
    ip_host: str
    is_up: bool
    checked_at: Optional[datetime] = None


@dataclass(frozen=True)
class SnapshotDTO:
    """Snapshot global inmutable del clúster de monitoreo."""
    total_sites: int
    up_sites: int
    down_sites: int
    total_services: int
    up_services: int
    down_services: int
    total_devices: int
    up_devices: int
    down_devices: int
    payload_json: Dict[str, Any] = field(default_factory=dict)
    created_at: Optional[datetime] = None
