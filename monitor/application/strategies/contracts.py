"""
# ==============================================================================
# 📜 CONTRATOS Y DTOs DE ESTRATEGIAS DE MONITOREO: contracts.py
# Arquitectura SOLID - Principio Abierto/Cerrado (OCP) e Inversión de Dependencias (DIP).
# Define el contrato formal para estrategias polimórficas de monitoreo.
# Ubicación: /opt/sonar/monitor/application/strategies/contracts.py
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional, Protocol, runtime_checkable


@dataclass(frozen=True)
class MonitoringResultDto:
    """Resultado inmutable generado por cualquier estrategia de monitoreo.

    Attributes:
        target: Identificador canónico del objetivo ejecutado (e.g., 'servicios', 'sedes').
        report_servicios: Texto formateado con el informe de servicios, si aplica.
        report_sedes: Texto formateado con el informe de sedes y enlaces, si aplica.
        report_network: Texto formateado con el análisis de flujos o tráfico, si aplica.
        pcap_file: Ruta opcional al archivo .pcap generado en capturas de red.
        logs: Lista cronológica de líneas de log capturadas durante la ejecución.
        total_items: Cantidad total de elementos evaluados en la estrategia.
    """
    target: str
    report_servicios: Optional[str] = None
    report_sedes: Optional[str] = None
    report_network: Optional[str] = None
    pcap_file: Optional[Path] = None
    logs: List[str] = field(default_factory=list)
    total_items: int = 0


@runtime_checkable
class MonitoringStrategyProtocol(Protocol):
    """Protocolo formal para estrategias de evaluación de monitoreo."""

    def can_handle(self, target: str) -> bool:
        """Determina si la estrategia es capaz de procesar el objetivo indicado.

        Args:
            target: Cadena identificadora del comando o modo solicitado.

        Returns:
            bool: True si la estrategia soporta el objetivo; False en caso contrario.
        """
        ...

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        """Ejecuta la evaluación y compila el resultado estandarizado.

        Args:
            target: Objetivo específico de monitoreo solicitado.
            mode: Modo de operación ('snapshot' para caché SSOT o 'ondemand' en vivo).
            debug_mode: Booleano que activa nivel de detalle adicional en logs.

        Returns:
            MonitoringResultDto: Objeto inmutable con los informes y bitácora generados.

        Raises:
            Exception: Si ocurre un error irrecuperable durante la ejecución.
        """
        ...
