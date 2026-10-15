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
        return get_site_repository()

    @property
    def service_repository(self) -> ServiceRepositoryProtocol:
        return get_service_repository()

    @property
    def network_device_repository(self) -> NetworkDeviceRepositoryProtocol:
        return get_network_device_repository()

    @property
    def snapshot_repository(self) -> SnapshotRepositoryProtocol:
        return get_snapshot_repository()

    @property
    def telemetry_repository(self) -> TelemetryRepositoryProtocol:
        return get_telemetry_repository()

    @property
    def telegram_notifier(self) -> TelegramNotifierProtocol:
        return get_telegram_notifier()

    @property
    def screenshot_generator(self) -> ScreenshotGeneratorProtocol:
        return get_screenshot_generator()

    def get_check_all_sites_use_case(self) -> CheckAllSitesUseCase:
        return get_check_all_sites_use_case()

    def get_check_all_services_use_case(self) -> CheckAllServicesUseCase:
        return get_check_all_services_use_case()

    def get_sync_telemetry_snapshot_use_case(self) -> SyncTelemetrySnapshotUseCase:
        return get_sync_telemetry_snapshot_use_case()

    def get_process_ups_telemetry_use_case(self) -> ProcessUpsTelemetryUseCase:
        return get_process_ups_telemetry_use_case()


# Instancia singleton predeterminada
system_services = ServiceProvider()
