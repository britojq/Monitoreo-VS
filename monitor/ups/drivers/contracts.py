"""
# ==============================================================================
# 🔋 UPS DRIVER CONTRACT: contracts.py
# Protocolo tipado para controladores de comunicación física con UPS
# Ubicación: /opt/sonar/monitor/ups/drivers/contracts.py
# ==============================================================================
"""

from __future__ import annotations

from typing import Any, Dict, Optional, Protocol


class UpsDriverProtocol(Protocol):
    """Contrato formal para drivers de comunicación industrial con UPS."""

    def query_status(self, port: str, baud: int, timeout: float = 1.0) -> Optional[Dict[str, Any]]:
        """Consulta el estado en tiempo real (voltajes, frecuencias, carga, batería)."""
        ...

    def query_info(self, port: str, baud: int, timeout: float = 0.5) -> Optional[Dict[str, Any]]:
        """Consulta marca, modelo y firmware del equipo."""
        ...

    def query_rating(self, port: str, baud: int, timeout: float = 0.5) -> Optional[Dict[str, Any]]:
        """Consulta especificaciones nominales de placa del equipo."""
        ...
