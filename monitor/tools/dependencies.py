"""
# ==============================================================================
# 🧩 RESOLUCIÓN Y FÁBRICA DE DEPENDENCIAS: dependencies.py
# Raíz de composición funcional para Clean Architecture en Python.
# Cumple con el Principio de Inversión de Dependencias (D) e Inyección Explícita.
# ==============================================================================
"""

from __future__ import annotations

from typing import Optional

from monitor.application.use_cases.check_all_services_use_case import CheckAllServicesUseCase
from monitor.application.use_cases.check_all_sites_use_case import CheckAllSitesUseCase
from monitor.application.use_cases.process_ups_telemetry_use_case import ProcessUpsTelemetryUseCase
from monitor.application.use_cases.sync_telemetry_snapshot_use_case import SyncTelemetrySnapshotUseCase
from monitor.domain.protocols.database_protocol import (
    NetworkDeviceRepositoryProtocol,
    ServiceRepositoryProtocol,
    SiteRepositoryProtocol,
    SnapshotRepositoryProtocol,
    TelemetryRepositoryProtocol,
)
from monitor.domain.protocols.notifier_protocol import TelegramNotifierProtocol
from monitor.domain.protocols.screenshot_protocol import ScreenshotGeneratorProtocol
from monitor.infrastructure.database.mariadb_network_device_repository import MariaDBNetworkDeviceRepository
from monitor.infrastructure.database.mariadb_service_repository import MariaDBServiceRepository
from monitor.infrastructure.database.mariadb_site_repository import MariaDBSiteRepository
from monitor.infrastructure.database.mariadb_snapshot_repository import MariaDBSnapshotRepository
from monitor.infrastructure.database.mariadb_telemetry_repository import MariaDBTelemetryRepository
from monitor.infrastructure.telegram.telegram_notifier import TelegramNotifier
from monitor.infrastructure.web.playwright_screenshot_client import PlaywrightScreenshotClient

# Instancias singleton cacheadas para repositorios de infraestructura
_SITE_REPO: Optional[SiteRepositoryProtocol] = None
_SERVICE_REPO: Optional[ServiceRepositoryProtocol] = None
_NETWORK_DEVICE_REPO: Optional[NetworkDeviceRepositoryProtocol] = None
_SNAPSHOT_REPO: Optional[SnapshotRepositoryProtocol] = None
_TELEMETRY_REPO: Optional[TelemetryRepositoryProtocol] = None
_NOTIFIER: Optional[TelegramNotifierProtocol] = None
_SCREENSHOT_CLIENT: Optional[ScreenshotGeneratorProtocol] = None


def get_site_repository() -> SiteRepositoryProtocol:
    """Fábrica de repositorio de Sedes."""
    global _SITE_REPO
    if _SITE_REPO is None:
        _SITE_REPO = MariaDBSiteRepository()
    return _SITE_REPO


def get_service_repository() -> ServiceRepositoryProtocol:
    """Fábrica de repositorio de Servicios."""
    global _SERVICE_REPO
    if _SERVICE_REPO is None:
        _SERVICE_REPO = MariaDBServiceRepository()
    return _SERVICE_REPO


def get_network_device_repository() -> NetworkDeviceRepositoryProtocol:
    """Fábrica de repositorio de Dispositivos de Red."""
    global _NETWORK_DEVICE_REPO
    if _NETWORK_DEVICE_REPO is None:
        _NETWORK_DEVICE_REPO = MariaDBNetworkDeviceRepository()
    return _NETWORK_DEVICE_REPO


def get_snapshot_repository() -> SnapshotRepositoryProtocol:
    """Fábrica de repositorio de Snapshots de Monitoreo."""
    global _SNAPSHOT_REPO
    if _SNAPSHOT_REPO is None:
        _SNAPSHOT_REPO = MariaDBSnapshotRepository()
    return _SNAPSHOT_REPO


def get_telemetry_repository() -> TelemetryRepositoryProtocol:
    """Fábrica de repositorio de Telemetría e Históricos."""
    global _TELEMETRY_REPO
    if _TELEMETRY_REPO is None:
        _TELEMETRY_REPO = MariaDBTelemetryRepository()
    return _TELEMETRY_REPO


def get_telegram_notifier() -> TelegramNotifierProtocol:
    """Fábrica de despachador de notificaciones Telegram."""
    global _NOTIFIER
    if _NOTIFIER is None:
        _NOTIFIER = TelegramNotifier()
    return _NOTIFIER


def get_screenshot_generator() -> ScreenshotGeneratorProtocol:
    """Fábrica de cliente generador de capturas Playwright."""
    global _SCREENSHOT_CLIENT
    if _SCREENSHOT_CLIENT is None:
        _SCREENSHOT_CLIENT = PlaywrightScreenshotClient()
    return _SCREENSHOT_CLIENT


# Fábricas de Casos de Uso (Application Layer)
def get_check_all_sites_use_case(
    site_repo: Optional[SiteRepositoryProtocol] = None,
    telemetry_repo: Optional[TelemetryRepositoryProtocol] = None,
) -> CheckAllSitesUseCase:
    """Construye e inyecta el caso de uso para chequeo integral de sedes."""
    return CheckAllSitesUseCase(
        site_repo=site_repo or get_site_repository(),
        telemetry_repo=telemetry_repo or get_telemetry_repository(),
    )


def get_check_all_services_use_case(
    service_repo: Optional[ServiceRepositoryProtocol] = None,
    telemetry_repo: Optional[TelemetryRepositoryProtocol] = None,
) -> CheckAllServicesUseCase:
    """Construye e inyecta el caso de uso para chequeo integral de servicios."""
    return CheckAllServicesUseCase(
        service_repo=service_repo or get_service_repository(),
        telemetry_repo=telemetry_repo or get_telemetry_repository(),
    )


def get_sync_telemetry_snapshot_use_case(
    snapshot_repo: Optional[SnapshotRepositoryProtocol] = None,
    telemetry_repo: Optional[TelemetryRepositoryProtocol] = None,
) -> SyncTelemetrySnapshotUseCase:
    """Construye e inyecta el caso de uso para sincronización de snapshot de telemetría."""
    return SyncTelemetrySnapshotUseCase(
        snapshot_repo=snapshot_repo or get_snapshot_repository(),
        telemetry_repo=telemetry_repo or get_telemetry_repository(),
    )


def get_process_ups_telemetry_use_case(
    notifier: Optional[TelegramNotifierProtocol] = None,
) -> ProcessUpsTelemetryUseCase:
    """Construye e inyecta el caso de uso para procesamiento de telemetría y alarmas de UPS."""
    return ProcessUpsTelemetryUseCase(
        notifier=notifier or get_telegram_notifier(),
    )
