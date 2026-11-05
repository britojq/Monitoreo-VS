"""
# ==============================================================================
# 📸 ADAPTADOR DE CAPTURA PLAYWRIGHT INDUSTRIAL: playwright_screenshot_client.py
# Implementación de ScreenshotGeneratorProtocol bajo Clean Architecture y SOLID.
# Soporte de SSL resiliente para certificados corporativos locales e intranet.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
from pathlib import Path
import time
from typing import Optional

from playwright.async_api import async_playwright

from monitor.domain.dto.screenshot_config_dto import ScreenshotConfigDTO
from monitor.domain.protocols.screenshot_protocol import ScreenshotGeneratorProtocol

logger = logging.getLogger("infrastructure.web.screenshot")


class PlaywrightScreenshotClient(ScreenshotGeneratorProtocol):
    """Adaptador de infraestructura desacoplado para capturas web con Chromium."""

    def __init__(self, config: Optional[ScreenshotConfigDTO] = None) -> None:
        self.config: ScreenshotConfigDTO = config or ScreenshotConfigDTO.create_default()
        self._memory_cache: dict[str, tuple[float, Path]] = {}
        self._ensure_cache_dir()

    def _ensure_cache_dir(self) -> None:
        """Asegura la existencia y permisos correctos del directorio de caché."""
        try:
            self.config.cache_dir.mkdir(parents=True, exist_ok=True)
            self.config.cache_dir.chmod(0o755)
        except Exception as e:
            logger.debug(f"Aviso asegurando directorio de caché: {e}")

    def normalize_mode(self, mode: str) -> str:
        """Normaliza el modo solicitado a los identificadores canónicos."""
        m = (mode or "full").lower().strip()
        if m in ("web", "global", "pantalla", "dashboard", "full"):
            return "full"
        if m in ("servicio", "serv", "servicios"):
            return "servicios"
        if m in ("sede", "sitios", "sitio", "sedes"):
            return "sedes"
        if m in ("caida", "alerta", "alertas", "incidente", "caidas", "incidentes"):
            return "caidas"
        return "full"

    async def capture(self, mode: str = "full", timeout: Optional[float] = None) -> Optional[str]:
        """
        Ejecuta la captura web con Playwright y retorna la ruta del archivo generado.
        Cumple 100% con ScreenshotGeneratorProtocol (LSP).
        """
        canonical_mode = self.normalize_mode(mode)
        effective_timeout = timeout or self.config.timeout_seconds
        now = time.time()

        # 1. Comprobar caché en memoria
        if canonical_mode in self._memory_cache:
            cache_time, cache_file = self._memory_cache[canonical_mode]
            if (now - cache_time) < self.config.cache_ttl_seconds and cache_file.exists():
                return str(cache_file)

        # 2. Comprobar caché en disco (soporte para llamadas inter-proceso PHP / Python)
        pattern = f"capture_{canonical_mode}_*.png"
        for disk_file in sorted(
            self.config.cache_dir.glob(pattern),
            key=lambda p: p.stat().st_mtime,
            reverse=True,
        ):
            try:
                mtime = disk_file.stat().st_mtime
                if (now - mtime) < self.config.cache_ttl_seconds and disk_file.stat().st_size > 1024:
                    self._memory_cache[canonical_mode] = (mtime, disk_file)
                    return str(disk_file)
            except Exception:
                pass

        target_file = self.config.cache_dir / f"capture_{canonical_mode}_{int(now)}.png"

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
                    ],
                )

                extra_headers: dict[str, str] = {}
                if self.config.auth_secret:
                    extra_headers["X-Bot-Capture-Secret"] = self.config.auth_secret

                # Contexto con soporte explícito para SSL corporativo e intranet
                context = await browser.new_context(
                    viewport={
                        "width": self.config.viewport_width,
                        "height": self.config.viewport_height,
                    },
                    device_scale_factor=self.config.device_scale_factor,
                    color_scheme=self.config.color_scheme,
                    extra_http_headers=extra_headers,
                    ignore_https_errors=self.config.ignore_https_errors,
                )

                page = await context.new_page()

                try:
                    await page.goto(
                        self.config.base_url,
                        wait_until="networkidle",
                        timeout=int(effective_timeout * 1000),
                    )
                    await page.wait_for_timeout(400)

                    if canonical_mode == "servicios":
                        sec = page.locator("section").nth(0)
                        await sec.screenshot(path=str(target_file))
                    elif canonical_mode == "sedes":
                        await page.evaluate("""
                            document.querySelectorAll('[id^=site-details-]').forEach(el => el.classList.remove('hidden'));
                            document.querySelectorAll('.chevron-icon').forEach(el => el.style.transform = 'rotate(180deg)');
                        """)
                        await page.wait_for_timeout(250)
                        sec = page.locator("section").nth(1)
                        await sec.screenshot(path=str(target_file))
                    elif canonical_mode == "caidas":
                        sec = page.locator("section").nth(2)
                        await sec.screenshot(path=str(target_file))
                    else:
                        await page.screenshot(path=str(target_file), full_page=False)

                finally:
                    await browser.close()

                if target_file.exists():
                    try:
                        target_file.chmod(0o644)
                    except Exception:
                        pass

                if target_file.exists() and target_file.stat().st_size > 1024:
                    self._memory_cache[canonical_mode] = (now, target_file)
                    self._cleanup_old_cache(target_file, now)
                    return str(target_file)

        except Exception as e:
            logger.error(
                f"Error generando captura web con Playwright (modo: {canonical_mode}): {e}",
                exc_info=True,
            )

        return None

    def _cleanup_old_cache(self, current_file: Path, now: float) -> None:
        """Purga capturas obsoletas mayores a 2 minutos para evitar desborde de disco."""
        try:
            for old_f in self.config.cache_dir.glob("capture_*.png"):
                if old_f != current_file and (now - old_f.stat().st_mtime) > 120:
                    try:
                        old_f.unlink()
                    except Exception:
                        pass
        except Exception:
            pass
