"""
# ==============================================================================
# 📦 EXPORTADOR DE PROTOCOLOS DE DOMINIO: protocols/__init__.py
# ==============================================================================
"""

from monitor.domain.protocols.checker_protocol import ServiceCheckerProtocol
from monitor.domain.protocols.database_protocol import (
    SiteRepositoryProtocol,
    ServiceRepositoryProtocol,
    NetworkDeviceRepositoryProtocol,
    SnapshotRepositoryProtocol,
    TelemetryRepositoryProtocol,
)
from monitor.domain.protocols.notifier_protocol import TelegramNotifierProtocol
from monitor.domain.protocols.screenshot_protocol import ScreenshotGeneratorProtocol

__all__ = [
    "ServiceCheckerProtocol",
    "SiteRepositoryProtocol",
    "ServiceRepositoryProtocol",
    "NetworkDeviceRepositoryProtocol",
    "SnapshotRepositoryProtocol",
    "TelemetryRepositoryProtocol",
    "TelegramNotifierProtocol",
    "ScreenshotGeneratorProtocol",
]
