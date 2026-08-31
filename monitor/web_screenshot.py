"""
# ==============================================================================
# 📸 GENERADOR DE CAPTURAS WEB: web_screenshot.py (@IA_ValleSeco_bot)
# Captura en alta definición (Full, Servicios, Sedes, Incidentes) vía Playwright
# Ubicación: /scripts/telegram-admin-bot/monitor/web_screenshot.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64)
# License: GNU Affero General Public License v3.0
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
from pathlib import Path
import time
from typing import Optional

from playwright.async_api import async_playwright

logger = logging.getLogger("monitor.screenshot")

CACHE_DIR = Path("/tmp/monitoreo_captures")
CACHE_DIR.mkdir(parents=True, exist_ok=True)

# Memoria de caché: { 'mode': (timestamp, filepath) }
_SCREENSHOT_CACHE: dict[str, tuple[float, Path]] = {}
CACHE_TTL_SECONDS = 15.0  # Reutilizar capturas de los últimos 15s si hay solicitudes continuas

DASHBOARD_URL = "http://monitoreo-vs.local/"


async def capture_web_dashboard(mode: str = "full", timeout: float = 12.0) -> Optional[Path]:
    """
    Captura una imagen PNG en alta definición del dashboard web.
    
    Modos soportados:
    - 'full' / 'web' / 'global': Vista panorámica completa de las 3 columnas (1920x1080 @2x).
    - 'servicios': Columna 1 (Servicios Activos).
    - 'sedes': Columna 2 (Sedes Regionales con equipos desplegados).
    - 'caidas' / 'incidentes': Columna 3 (Servicios Caídos y Sedes sin Conexión).
    """
    mode = (mode or "full").lower().strip()
    if mode in ("web", "global", "pantalla", "dashboard"):
        mode = "full"
    elif mode in ("servicio", "serv"):
        mode = "servicios"
    elif mode in ("sede", "sitios", "sitio"):
        mode = "sedes"
    elif mode in ("caida", "alerta", "alertas", "incidente"):
        mode = "caidas"

    # 1. Comprobar caché en memoria
    now = time.time()
    if mode in _SCREENSHOT_CACHE:
        cache_time, cache_file = _SCREENSHOT_CACHE[mode]
        if (now - cache_time) < CACHE_TTL_SECONDS and cache_file.exists():
            return cache_file

    target_file = CACHE_DIR / f"capture_{mode}_{int(now)}.png"

    try:
        async with async_playwright() as p:
            browser = await p.chromium.launch(
                headless=True,
                args=[
                    "--no-sandbox",
                    "--disable-setuid-sandbox",
                    "--disable-dev-shm-usage",
                    "--disable-accelerated-2d-canvas",
                    "--disable-gpu",
                ]
            )
            
            # Viewport 1920x1080 con escala 2x para nitidez cristalina en Telegram
            context = await browser.new_context(
                viewport={"width": 1920, "height": 1080},
                device_scale_factor=2,
                color_scheme="dark"
            )
            page = await context.new_page()
            
            await page.goto(DASHBOARD_URL, wait_until="networkidle", timeout=int(timeout * 1000))
            await page.wait_for_timeout(400)

            if mode == "servicios":
                # Captura de la Columna 1
                sec = page.locator("main section").nth(0)
                await sec.screenshot(path=str(target_file))
            elif mode == "sedes":
                # Expandir todos los acordeones de equipos en sitio
                await page.evaluate("""
                    document.querySelectorAll('[id^=site-details-]').forEach(el => el.classList.remove('hidden'));
                    document.querySelectorAll('.chevron-icon').forEach(el => el.style.transform = 'rotate(180deg)');
                """)
                await page.wait_for_timeout(250)
                # Captura de la Columna 2
                sec = page.locator("main section").nth(1)
                await sec.screenshot(path=str(target_file))
            elif mode == "caidas":
                # Captura de la Columna 3 (Incidentes)
                sec = page.locator("main section").nth(2)
                await sec.screenshot(path=str(target_file))
            else:
                # Captura panorámica global (Full Dashboard)
                await page.screenshot(path=str(target_file), full_page=False)

            await browser.close()

            if target_file.exists() and target_file.stat().st_size > 1024:
                _SCREENSHOT_CACHE[mode] = (now, target_file)
                # Limpiar capturas antiguas
                for old_f in CACHE_DIR.glob("capture_*.png"):
                    if old_f != target_file and (now - old_f.stat().st_mtime) > 120:
                        try:
                            old_f.unlink()
                        except Exception:
                            pass
                return target_file

    except Exception as e:
        logger.error(f"Error generando captura web ({mode}): {e}", exc_info=True)

    return None
