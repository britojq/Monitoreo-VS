"""
# ==============================================================================
# 📊 ESTRATEGIA DE MONITOREO COMPLETO: full_strategy.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y OCP.
# Evaluación holística simultánea de Servicios y Sedes.
# Ubicación: /opt/sonar/monitor/application/strategies/full_strategy.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
from typing import List, Optional

from monitor.application.snapshot_manager import get_latest_snapshot
from monitor.application.strategies.contracts import (
    MonitoringResultDto,
    MonitoringStrategyProtocol,
)
from monitor.formatters.service_report_compiler import compile_services_from_snapshot
from monitor.formatters.site_report_compiler import compile_sedes_from_snapshot
from monitor.monitor_sedes import run_sedes_check
from monitor.monitor_servicios import run_services_check

logger = logging.getLogger("monitor.strategies.full")


class FullMonitoringStrategy(MonitoringStrategyProtocol):
    """Estrategia para el chequeo unificado (Servicios + Sedes)."""

    def can_handle(self, target: str) -> bool:
        return target.strip().lower() in ("completo", "monitoreo", "all", "full")

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        logs: List[str] = []
        report_s: Optional[str] = None
        report_sd: Optional[str] = None

        if mode == "snapshot":
            snapshot, snapshot_dt = get_latest_snapshot()
            if snapshot:
                report_s, _, logs_s = compile_services_from_snapshot(snapshot, snapshot_dt, debug_mode)
                report_sd, _, logs_sd = compile_sedes_from_snapshot(snapshot, snapshot_dt, debug_mode)
                logs.extend(logs_s)
                logs.extend(logs_sd)

        # Fallback o modo ondemand
        if report_s is None or report_sd is None:
            (rep_s, _, logs_s, _), (rep_sd, _, logs_sd, _) = await asyncio.gather(
                run_services_check(debug_mode=debug_mode),
                run_sedes_check(debug_mode=debug_mode),
            )
            report_s = rep_s
            report_sd = rep_sd
            logs.extend(logs_s)
            logs.extend(logs_sd)

        return MonitoringResultDto(
            target="completo",
            report_servicios=report_s,
            report_sedes=report_sd,
            logs=logs,
            total_items=len(logs),
        )
