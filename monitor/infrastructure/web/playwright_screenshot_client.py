"""
# ==============================================================================
# 📸 ADAPTADOR DE CAPTURA PLAYWRIGHT: playwright_screenshot_client.py
# Implementación de ScreenshotGeneratorProtocol bajo Clean Architecture.
# ==============================================================================
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Optional

from monitor.domain.protocols.screenshot_protocol import ScreenshotGeneratorProtocol

logger = logging.getLogger("infrastructure.web.screenshot")


class PlaywrightScreenshotClient(ScreenshotGeneratorProtocol):
    """Adaptador de infraestructura para captura de pantalla del dashboard web."""

    async def capture(self, mode: str = "full") -> Optional[str]:
        """
        Ejecuta la captura web con Playwright y retorna la ruta del archivo generado.
        """
        try:
            from monitor.web_screenshot import take_dashboard_screenshot
            img_path = await take_dashboard_screenshot(mode=mode)
            if img_path and Path(img_path).exists():
                return str(img_path)
            return None
        except Exception as e:
            logger.error(f"Error generando captura con Playwright (modo: {mode}): {e}")
            return None
