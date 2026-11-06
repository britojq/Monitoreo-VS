"""
# ==============================================================================
# 🏢 ESTRATEGIA DE SEDES Y ENLACES: sites_strategy.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y OCP.
# Evaluación de sedes físicas, conmutadores y calidad de enlaces WAN.
# Ubicación: /opt/sonar/monitor/application/strategies/sites_strategy.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import List, Optional

from monitor.application.snapshot_manager import get_latest_snapshot
from monitor.application.strategies.contracts import (
    MonitoringResultDto,
    MonitoringStrategyProtocol,
)
from monitor.formatters.site_report_compiler import compile_sedes_from_snapshot
from monitor.monitor_sedes import run_sedes_check

logger = logging.getLogger("monitor.strategies.sites")


class SitesMonitoringStrategy(MonitoringStrategyProtocol):
    """Estrategia para el chequeo exclusivo de Sedes y Enlaces de Telecomunicaciones.

    Evalúa la disponibilidad, latencia y pérdida de paquetes de los enlaces WAN
    y routers de cada sede física de la organización.
    """

    def can_handle(self, target: str) -> bool:
        """Determina si la solicitud corresponde a sedes y enlaces de telecomunicaciones.

        Args:
            target: Cadena con el comando o target.

        Returns:
            bool: True si el target es 'sedes', 'sede' o 'sites'.
        """
        return target.strip().lower() in ("sedes", "sede", "sites")

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        """Ejecuta la evaluación de sedes físicas y enlaces y compila el informe.

        Args:
            target: Identificador del comando solicitado.
            mode: 'snapshot' para leer del snapshot o 'ondemand' para sondeo en vivo.
            debug_mode: Booleano para activar trazas de depuración.

        Returns:
            MonitoringResultDto: DTO con el informe formateado de sedes y enlaces WAN.
        """
        logs: List[str] = []
        report_text: Optional[str] = None

        if mode == "snapshot":
            snapshot, snapshot_dt = get_latest_snapshot()
            if snapshot:
                report_text, _, logs_sd = compile_sedes_from_snapshot(
                    snapshot, snapshot_dt, debug_mode=debug_mode
                )
                logs.extend(logs_sd)

        # Fallback o modo ondemand
        if report_text is None:
            report_text, _, logs_sd, _ = await run_sedes_check(debug_mode=debug_mode)
            logs.extend(logs_sd)

        return MonitoringResultDto(
            target="sedes",
            report_sedes=report_text,
            logs=logs,
            total_items=len(logs),
        )
