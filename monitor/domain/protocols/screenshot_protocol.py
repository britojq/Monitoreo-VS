"""
# ==============================================================================
# 📸 CONTRATO DE CAPTURADOR VISUAL WEB: screenshot_protocol.py
# Inversión de Dependencias (D) para generación de capturas gráficas.
# ==============================================================================
"""

from __future__ import annotations

from typing import Optional, Protocol, runtime_checkable


@runtime_checkable
class ScreenshotGeneratorProtocol(Protocol):
    """Protocolo formal para captura visual de tableros web sin acoplar Playwright."""

    async def capture(self, mode: str = "full") -> Optional[str]:
        """
        Genera una captura del dashboard en el modo especificado.
        Retorna la ruta absoluta del archivo de imagen generado o None si falla.
        """
        ...
