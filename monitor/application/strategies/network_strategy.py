"""
# ==============================================================================
# 🌐 ESTRATEGIA DE ANÁLISIS DE RED: network_strategy.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y OCP.
# Captura y telemetría de flujos de paquetes LAN (PCAP / tshark).
# Ubicación: /opt/sonar/monitor/application/strategies/network_strategy.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Optional

from monitor.application.strategies.contracts import (
    MonitoringResultDto,
    MonitoringStrategyProtocol,
)

logger = logging.getLogger("monitor.strategies.network")


class NetworkAnalysisStrategy(MonitoringStrategyProtocol):
    """Estrategia para el análisis profundo de tráfico y captura de red."""

    def can_handle(self, target: str) -> bool:
        return target.strip().lower() in ("analisis_red", "red", "trafico", "network")

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        from monitor.network_analyzer import run_full_network_analysis

        rep_txt, pcap, _, _ = await run_full_network_analysis(capture_duration=60)
        pcap_path = Path(pcap) if pcap else None

        return MonitoringResultDto(
            target="analisis_red",
            report_network=rep_txt,
            pcap_file=pcap_path,
            logs=[rep_txt],
            total_items=1,
        )
