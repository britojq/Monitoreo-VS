"""
# ==============================================================================
# 📸 DTO DE CONFIGURACIÓN DE CAPTURAS: screenshot_config_dto.py
# DTO inmutable para parametrizar el renderizador Playwright (Pilar #2 / SOLID).
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import os
from typing import Optional


@dataclass(frozen=True)
class ScreenshotConfigDTO:
    """Configuración inmutable para el motor de capturas web Playwright."""

    base_url: str
    ignore_https_errors: bool = True
    viewport_width: int = 1920
    viewport_height: int = 1080
    device_scale_factor: int = 2
    color_scheme: str = "dark"
    timeout_seconds: float = 15.0
    cache_ttl_seconds: float = 15.0
    cache_dir: Path = Path("/tmp/monitoreo_captures")
    auth_secret: Optional[str] = None

    @classmethod
    def create_default(cls) -> ScreenshotConfigDTO:
        """Fabrica la configuración por defecto leyendo el entorno corporativo de forma resiliente."""
        # 1. Resolver APP_KEY de Laravel para autenticación interna
        auth_secret = cls._resolve_app_key()

        # 2. Resolver URL base: preferir HTTPS local para evitar saltos 301 innecesarios
        base_url = os.getenv("MONITOR_DASHBOARD_URL") or "https://monitoreo-vs.local/"

        # 3. Directorio de caché
        cache_dir = Path("/tmp/monitoreo_captures")

        return cls(
            base_url=base_url,
            ignore_https_errors=True,
            viewport_width=1920,
            viewport_height=1080,
            device_scale_factor=2,
            color_scheme="dark",
            timeout_seconds=15.0,
            cache_ttl_seconds=15.0,
            cache_dir=cache_dir,
            auth_secret=auth_secret,
        )

    @staticmethod
    def _resolve_app_key() -> Optional[str]:
        """Obtiene el APP_KEY de Laravel para autenticar internamente las capturas del bot."""
        env_paths = [
            Path("/opt/sonar/web_portal/.env"),
            Path(__file__).resolve().parent.parent.parent.parent / "web_portal" / ".env",
        ]
        for env_file in env_paths:
            if env_file.exists():
                try:
                    for line in env_file.read_text(encoding="utf-8").splitlines():
                        line = line.strip()
                        if line.startswith("APP_KEY=") and "=" in line:
                            return line.split("=", 1)[1].strip().strip("\"'").strip("'")
                except Exception:
                    pass
        return None
