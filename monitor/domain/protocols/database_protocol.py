"""
# ==============================================================================
# 🗄️ CONTRATOS DE BASE DE DATOS Y REPOSITORIOS: database_protocol.py
# Inversión de Dependencias (D) y Segregación de Interfaces (I).
# Las capas de dominio y aplicación dependen de estos protocolos, no de MariaDB/pymysql.
# ==============================================================================
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional, Protocol, runtime_checkable
from monitor.domain.entities.site import SiteEntity
from monitor.domain.entities.service import ServiceEntity
from monitor.domain.entities.network_device import NetworkDeviceEntity
from monitor.application.dtos.telemetry_dto import (
    DeviceTelemetryDTO,
    ServiceTelemetryDTO,
    SiteTelemetryDTO,
    SnapshotDTO,
)


@runtime_checkable
class SiteRepositoryProtocol(Protocol):
    """Protocolo para persistencia y lectura de Sedes físicas."""
    def get_all_active(self) -> List[SiteEntity]:
        """Obtiene todas las sedes activas con sus equipos de telecomunicaciones."""
        ...

    def get_by_letter(self, letter: str) -> Optional[SiteEntity]:
        """Obtiene una sede por su letra identificadora."""
        ...


@runtime_checkable
class ServiceRepositoryProtocol(Protocol):
    """Protocolo para persistencia y lectura de Servicios monitoreados."""
    def get_all_active(self) -> List[ServiceEntity]:
        """Obtiene todos los servicios corporativos activos."""
        ...


@runtime_checkable
class NetworkDeviceRepositoryProtocol(Protocol):
    """Protocolo para consulta del censo de dispositivos en MariaDB (SSOT)."""
    def get_all_active(self) -> List[NetworkDeviceEntity]:
        """Obtiene todos los dispositivos de red activos."""
        ...

    def get_by_ip(self, ip: str) -> Optional[NetworkDeviceEntity]:
        """Busca un dispositivo por su dirección IP."""
        ...


@runtime_checkable
class SnapshotRepositoryProtocol(Protocol):
    """Protocolo para almacenamiento y recuperación de snapshots del clúster."""
    def save_snapshot(self, snapshot: SnapshotDTO) -> int:
        """Guarda un snapshot consolidado en la tabla monitoring_snapshots."""
        ...

    def get_latest_snapshot(self) -> Optional[SnapshotDTO]:
        """Obtiene el último snapshot registrado en la base de datos."""
        ...


@runtime_checkable
class TelemetryRepositoryProtocol(Protocol):
    """
    Protocolo para persistencia de telemetría en las 4 tablas de histórico.
    Cumple con la Regla de Oro #3 (Integridad de telemetría y replicación).
    """
    def record_service_history(self, telemetry: ServiceTelemetryDTO) -> None:
        """Inserta en service_check_histories."""
        ...

    def record_site_history(self, telemetry: SiteTelemetryDTO) -> None:
        """Inserta en site_check_histories."""
        ...

    def record_device_history(self, telemetry: DeviceTelemetryDTO) -> None:
        """Inserta en network_device_check_histories."""
        ...
