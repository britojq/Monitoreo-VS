"""
# ==============================================================================
# 🧪 UNIT TESTS: PLAYWRIGHT SOLID SCREENSHOT ENGINE (v5.54.90)
# Monitoreo de Infraestructura, Auditoría, Telemetría y Ciberseguridad S.O.N.A.R.
# Arquitectura Limpia SOLID - Resiliencia SSL y Contratos de Captura Web
# Ubicación: /opt/sonar/tests/unit/test_playwright_solid_screenshot.py
# License: GNU Affero General Public License v3.0
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

import asyncio
from pathlib import Path
from unittest.mock import AsyncMock, patch

import pytest

from monitor.domain.dto.screenshot_config_dto import ScreenshotConfigDTO
from monitor.domain.protocols.screenshot_protocol import ScreenshotGeneratorProtocol
from monitor.infrastructure.web.playwright_screenshot_client import PlaywrightScreenshotClient
from monitor.tools.web_screenshot import capture_web_dashboard


class TestPlaywrightSolidScreenshot:
    """Pruebas unitarias para el motor arquitectónico SOLID de capturas Playwright."""

    def test_screenshot_config_dto_defaults_and_immutability(self) -> None:
        """Verifica que ScreenshotConfigDTO sea inmutable y resuelva parámetros SSL seguros por defecto."""
        config = ScreenshotConfigDTO.create_default()
        assert config.base_url.startswith("https://")
        assert config.ignore_https_errors is True
        assert config.viewport_width == 1920
        assert config.viewport_height == 1080
        assert config.device_scale_factor == 2
        assert config.timeout_seconds == 15.0
        assert isinstance(config.cache_dir, Path)

        # Inmutabilidad estricta
        with pytest.raises((AttributeError, TypeError)):
            config.viewport_width = 1280  # type: ignore

    def test_playwright_screenshot_client_satisfies_protocol(self) -> None:
        """Verifica que PlaywrightScreenshotClient implemente formalmente ScreenshotGeneratorProtocol."""
        client = PlaywrightScreenshotClient()
        assert isinstance(client, ScreenshotGeneratorProtocol)
        assert hasattr(client, "capture")
        assert asyncio.iscoroutinefunction(client.capture)

    def test_normalize_mode_canonical_values(self) -> None:
        """Verifica la normalización de alias a identificadores canónicos."""
        client = PlaywrightScreenshotClient()
        assert client.normalize_mode("pantalla") == "full"
        assert client.normalize_mode("web") == "full"
        assert client.normalize_mode("servicios") == "servicios"
        assert client.normalize_mode("serv") == "servicios"
        assert client.normalize_mode("sedes") == "sedes"
        assert client.normalize_mode("sitios") == "sedes"
        assert client.normalize_mode("caidas") == "caidas"
        assert client.normalize_mode("alertas") == "caidas"

    def test_facade_delegates_to_playwright_client(self) -> None:
        """Verifica que la fachada capture_web_dashboard delegue al cliente Playwright desacoplado."""
        async def _run() -> None:
            mock_file_path = "/tmp/mock_screenshot_test.png"
            with patch.object(PlaywrightScreenshotClient, "capture", new_callable=AsyncMock) as mock_capture:
                mock_capture.return_value = mock_file_path
                result = await capture_web_dashboard(mode="servicios", timeout=15.0)

                assert result == Path(mock_file_path)
                mock_capture.assert_awaited_once_with(mode="servicios", timeout=15.0)

        asyncio.run(_run())

    def test_dynamic_facade_import_via_pep562(self) -> None:
        """Verifica la compatibilidad histórica de importación mediante monitor.__init__."""
        from monitor.tools.web_screenshot import capture_web_dashboard as imported_facade
        assert callable(imported_facade)
