"""
# ==============================================================================
# 🚨 ESTRATEGIA DE CAÍDAS E INCIDENTES: outages_strategy.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y OCP.
# Evaluación y consolidación exclusiva de caídas e incidencias activas.
# Ubicación: /opt/sonar/monitor/application/strategies/outages_strategy.py
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
from monitor.parsers import get_formatted_datetime
from monitor.formatters.service_report_compiler import compile_services_from_snapshot
from monitor.formatters.site_report_compiler import compile_sedes_from_snapshot
from monitor.monitor_sedes import run_sedes_check
from monitor.monitor_servicios import run_services_check

logger = logging.getLogger("monitor.strategies.outages")


class OutagesMonitoringStrategy(MonitoringStrategyProtocol):
    """Estrategia para la extracción y reporte de incidencias y enlaces caídos.

    Filtra los resultados de monitoreo para aislar exclusivamente los servicios y
    sedes que presentan fallos críticos (estado ❌) o degradación operativa.
    """

    def can_handle(self, target: str) -> bool:
        """Determina si la solicitud corresponde a una consulta de caídas o incidentes.

        Args:
            target: Cadena con el comando o target.

        Returns:
            bool: True si el target es 'caidas', 'incidentes', 'fallas' u 'outages'.
        """
        return target.strip().lower() in ("caidas", "incidentes", "fallas", "outages")

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        """Ejecuta el filtrado de caídas a partir del snapshot o sondeo en vivo.

        Args:
            target: Identificador del comando solicitado.
            mode: 'snapshot' para leer del snapshot o 'ondemand' para sondeo en vivo.
            debug_mode: Booleano para activar trazas de depuración.

        Returns:
            MonitoringResultDto: DTO con el informe formateado de incidentes o mensaje de normalidad.
        """
        logs: List[str] = []
        caidas_lines: List[str] = []
        snapshot_dt: Optional[str] = None
        is_live = False

        if mode == "snapshot":
            snapshot, snapshot_dt = get_latest_snapshot()
            if snapshot:
                rep_s, _, logs_s = compile_services_from_snapshot(snapshot, snapshot_dt, debug_mode)
                rep_sd, _, logs_sd = compile_sedes_from_snapshot(snapshot, snapshot_dt, debug_mode)
                logs.extend(logs_s)
                logs.extend(logs_sd)
                for l in (rep_s or "").splitlines():
                    if "❌" in l:
                        caidas_lines.append(l)
                for l in (rep_sd or "").splitlines():
                    if "❌" in l:
                        caidas_lines.append(l)

        # Si no hubo snapshot o se forzó ondemand
        if not logs:
            is_live = True
            (rep_s, _, logs_s, _), (rep_sd, _, logs_sd, _) = await asyncio.gather(
                run_services_check(debug_mode=debug_mode),
                run_sedes_check(debug_mode=debug_mode),
            )
            logs.extend(logs_s)
            logs.extend(logs_sd)
            for l in (rep_s or "").splitlines():
                if "❌" in l:
                    caidas_lines.append(l)
            for l in (rep_sd or "").splitlines():
                if "❌" in l:
                    caidas_lines.append(l)

        fecha_str, hora_str = get_formatted_datetime()
        snapshot_header = f"\n🕒 Snapshot: {snapshot_dt}" if snapshot_dt else ""
        live_label = " (EN VIVO)" if is_live else ""

        if caidas_lines:
            report_text = (
                f"🚨 **REPORTE DE INCIDENTES Y SERVICIOS CAÍDOS{live_label}**\n"
                f"📅 *{fecha_str} • {hora_str}*{snapshot_header}\n\n"
                "━━━━━━━━━━━━━━━━━━━━\n"
                + "\n".join(caidas_lines)
                + "\n━━━━━━━━━━━━━━━━━━━━"
            )
        else:
            report_text = (
                f"✅ **SIN INCIDENTES ACTIVOS{live_label}**\n"
                f"📅 *{fecha_str} • {hora_str}*{snapshot_header}\n\n"
                "Todos los servicios y enlaces se encuentran operando con normalidad."
            )

        return MonitoringResultDto(
            target="caidas",
            report_servicios=report_text,
            logs=logs,
            total_items=len(logs),
        )
