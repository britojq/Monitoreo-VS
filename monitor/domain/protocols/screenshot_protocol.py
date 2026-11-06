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
        """Genera una captura del dashboard en el modo especificado.

        Args:
            mode: Modo de captura ('full' para dashboard completo, 'services' o 'sites').

        Returns:
            Optional[str]: Ruta absoluta del archivo de imagen generado o None si falla.

        Raises:
            Exception: Si el navegador sin cabeza o el servicio de captura no responde.
        """
        ...
