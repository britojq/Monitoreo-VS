"""
# ==============================================================================
# 🧩 FACHADA COMPATIBLE DE SERVICIOS: service_provider.py
# Fachada de compatibilidad hacia monitor.dependencies.
# Cumple con el Principio de Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

from typing import Optional

from monitor.application.use_cases.check_all_services_use_case import CheckAllServicesUseCase
from monitor.application.use_cases.check_all_sites_use_case import CheckAllSitesUseCase
from monitor.application.use_cases.process_ups_telemetry_use_case import ProcessUpsTelemetryUseCase
from monitor.application.use_cases.sync_telemetry_snapshot_use_case import SyncTelemetrySnapshotUseCase
from monitor.dependencies import (
    get_check_all_services_use_case,
    get_check_all_sites_use_case,
    get_network_device_repository,
    get_process_ups_telemetry_use_case,
    get_screenshot_generator,
    get_service_repository,
    get_site_repository,
    get_snapshot_repository,
    get_sync_telemetry_snapshot_use_case,
    get_telegram_notifier,
    get_telemetry_repository,
)
from monitor.domain.protocols.database_protocol import (
    NetworkDeviceRepositoryProtocol,
    ServiceRepositoryProtocol,
    SiteRepositoryProtocol,
    SnapshotRepositoryProtocol,
    TelemetryRepositoryProtocol,
)
from monitor.domain.protocols.notifier_protocol import TelegramNotifierProtocol
from monitor.domain.protocols.screenshot_protocol import ScreenshotGeneratorProtocol


class ServiceProvider:
    """Adaptador de compatibilidad orientado a objetos sobre las fábricas de dependencies.py."""

    @property
    def site_repository(self) -> SiteRepositoryProtocol:
        """Retorna la instancia del repositorio de sedes corporativas configurado."""
        return get_site_repository()

    @property
    def service_repository(self) -> ServiceRepositoryProtocol:
        """Retorna la instancia del repositorio de servicios de infraestructura."""
        return get_service_repository()

    @property
    def network_device_repository(self) -> NetworkDeviceRepositoryProtocol:
        """Retorna la instancia del repositorio de dispositivos de red."""
        return get_network_device_repository()

    @property
    def snapshot_repository(self) -> SnapshotRepositoryProtocol:
        """Retorna la instancia del repositorio de instantáneas de telemetría."""
        return get_snapshot_repository()

    @property
    def telemetry_repository(self) -> TelemetryRepositoryProtocol:
        """Retorna la instancia del repositorio de telemetría y métricas operativas."""
        return get_telemetry_repository()

    @property
    def telegram_notifier(self) -> TelegramNotifierProtocol:
        """Retorna la instancia del notificador seguro para canal Telegram."""
        return get_telegram_notifier()

    @property
    def screenshot_generator(self) -> ScreenshotGeneratorProtocol:
        """Retorna el generador de capturas de pantalla para auditoría visual."""
        return get_screenshot_generator()

    def get_check_all_sites_use_case(self) -> CheckAllSitesUseCase:
        """Retorna el caso de uso para comprobación holística de sedes."""
        return get_check_all_sites_use_case()

    def get_check_all_services_use_case(self) -> CheckAllServicesUseCase:
        """Retorna el caso de uso para comprobación holística de servicios."""
        return get_check_all_services_use_case()

    def get_sync_telemetry_snapshot_use_case(self) -> SyncTelemetrySnapshotUseCase:
        """Retorna el caso de uso para sincronización de snapshots."""
        return get_sync_telemetry_snapshot_use_case()

    def get_process_ups_telemetry_use_case(self) -> ProcessUpsTelemetryUseCase:
        """Retorna el caso de uso para procesamiento de telemetría UPS."""
        return get_process_ups_telemetry_use_case()


# Instancia singleton predeterminada
system_services = ServiceProvider()
