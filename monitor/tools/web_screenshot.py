"""
# ==============================================================================
# 📸 FAÇADE DE CAPTURAS WEB: web_screenshot.py (@IA_ValleSeco_bot)
# Façade de compatibilidad hacia PlaywrightScreenshotClient (Clean Architecture).
# Ubicación: /opt/sonar/monitor/tools/web_screenshot.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
from pathlib import Path
from typing import Optional

from monitor.domain.dto.screenshot_config_dto import ScreenshotConfigDTO
from monitor.infrastructure.web.playwright_screenshot_client import PlaywrightScreenshotClient

logger = logging.getLogger("monitor.tools.screenshot")

# Instancia singleton del cliente de capturas industrial
_DEFAULT_CLIENT: Optional[PlaywrightScreenshotClient] = None


def get_screenshot_client() -> PlaywrightScreenshotClient:
    """Retorna la instancia global del cliente Playwright configurado."""
    global _DEFAULT_CLIENT
    if _DEFAULT_CLIENT is None:
        _DEFAULT_CLIENT = PlaywrightScreenshotClient()
    return _DEFAULT_CLIENT


async def capture_web_dashboard(mode: str = "full", timeout: float = 15.0) -> Optional[Path]:
    """
    Captura una imagen PNG en alta definición del dashboard web.
    Mantiene compatibilidad de firma con el bot y despachadores de Laravel.
    """
    client = get_screenshot_client()
    result_str = await client.capture(mode=mode, timeout=timeout)
    if result_str:
        return Path(result_str)
    return None


# Alias canónicos de compatibilidad
take_dashboard_screenshot = capture_web_dashboard
