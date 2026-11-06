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
        """Obtiene todas las sedes activas con sus equipos de telecomunicaciones.

        Returns:
            List[SiteEntity]: Colección de entidades SiteEntity activas en MariaDB (SSOT).
        """
        ...

    def get_by_letter(self, letter: str) -> Optional[SiteEntity]:
        """Obtiene una sede por su letra identificadora.

        Args:
            letter: Letra única asignada a la sede (e.g. 'A', 'B', 'V').

        Returns:
            Optional[SiteEntity]: Entidad de la sede si existe, None en caso contrario.
        """
        ...


@runtime_checkable
class ServiceRepositoryProtocol(Protocol):
    """Protocolo para persistencia y lectura de Servicios monitoreados."""

    def get_all_active(self) -> List[ServiceEntity]:
        """Obtiene todos los servicios corporativos activos.

        Returns:
            List[ServiceEntity]: Lista de entidades ServiceEntity habilitadas para sondeo.
        """
        ...


@runtime_checkable
class NetworkDeviceRepositoryProtocol(Protocol):
    """Protocolo para consulta del censo de dispositivos en MariaDB (SSOT)."""

    def get_all_active(self) -> List[NetworkDeviceEntity]:
        """Obtiene todos los dispositivos de red activos.

        Returns:
            List[NetworkDeviceEntity]: Lista de equipos de red registrados y activos.
        """
        ...

    def get_by_ip(self, ip: str) -> Optional[NetworkDeviceEntity]:
        """Busca un dispositivo por su dirección IP.

        Args:
            ip: Dirección IPv4 del dispositivo.

        Returns:
            Optional[NetworkDeviceEntity]: Dispositivo encontrado o None si no existe en el censo.
        """
        ...


@runtime_checkable
class SnapshotRepositoryProtocol(Protocol):
    """Protocolo para almacenamiento y recuperación de snapshots del clúster."""

    def save_snapshot(self, snapshot: SnapshotDTO) -> int:
        """Guarda un snapshot consolidado en la tabla monitoring_snapshots.

        Args:
            snapshot: DTO con la estructura completa del snapshot a persistir.

        Returns:
            int: Identificador numérico generado (ID) del snapshot insertado.
        """
        ...

    def get_latest_snapshot(self) -> Optional[SnapshotDTO]:
        """Obtiene el último snapshot registrado en la base de datos.

        Returns:
            Optional[SnapshotDTO]: DTO con el snapshot más reciente o None si la tabla está vacía.
        """
        ...


@runtime_checkable
class TelemetryRepositoryProtocol(Protocol):
    """Protocolo para persistencia de telemetría en las tablas de histórico.

    Cumple con la integridad de telemetría y replicación en MariaDB SSOT.
    """

    def record_service_history(self, telemetry: ServiceTelemetryDTO) -> None:
        """Inserta telemetría histórica de servicios en service_check_histories.

        Args:
            telemetry: DTO con las métricas y estado del servicio.
        """
        ...

    def record_site_history(self, telemetry: SiteTelemetryDTO) -> None:
        """Inserta telemetría histórica de sedes en site_check_histories.

        Args:
            telemetry: DTO con las métricas y estado de la sede física.
        """
        ...

    def record_device_history(self, telemetry: DeviceTelemetryDTO) -> None:
        """Inserta telemetría histórica de dispositivos de red en network_device_check_histories.

        Args:
            telemetry: DTO con las métricas y estado del equipo de red.
        """
        ...
