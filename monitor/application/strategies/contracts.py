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
    """Resultado inmutable generado por cualquier estrategia de monitoreo."""
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
        """Determina si la estrategia es capaz de procesar el objetivo indicado."""
        ...

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        """Ejecuta la evaluación y compila el resultado estandarizado."""
        ...
