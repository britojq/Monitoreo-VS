"""
# ==============================================================================
# 🌐 ESTRATEGIA DE SERVICIOS: services_strategy.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y OCP.
# Evaluación de servicios web HTTP/TCP y proxy corporativos.
# Ubicación: /opt/sonar/monitor/application/strategies/services_strategy.py
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
from monitor.formatters.service_report_compiler import compile_services_from_snapshot
from monitor.monitor_servicios import run_services_check

logger = logging.getLogger("monitor.strategies.services")


class ServicesMonitoringStrategy(MonitoringStrategyProtocol):
    """Estrategia para el chequeo exclusivo de Servicios Corporativos.

    Monitorea el estado de disponibilidad y tiempos de respuesta de servicios web,
    puertos TCP y servidores proxy corporativos.
    """

    def can_handle(self, target: str) -> bool:
        """Determina si la solicitud corresponde a servicios corporativos.

        Args:
            target: Cadena con el comando o target.

        Returns:
            bool: True si el target es 'servicios', 'servicio' o 'services'.
        """
        return target.strip().lower() in ("servicios", "servicio", "services")

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        """Ejecuta la evaluación de servicios corporativos y compila el informe.

        Args:
            target: Identificador del comando solicitado.
            mode: 'snapshot' para leer del snapshot o 'ondemand' para sondeo en vivo.
            debug_mode: Booleano para activar trazas de depuración.

        Returns:
            MonitoringResultDto: DTO con el informe formateado de servicios corporativos.
        """
        logs: List[str] = []
        report_text: Optional[str] = None

        if mode == "snapshot":
            snapshot, snapshot_dt = get_latest_snapshot()
            if snapshot:
                report_text, _, logs_s = compile_services_from_snapshot(
                    snapshot, snapshot_dt, debug_mode=debug_mode
                )
                logs.extend(logs_s)

        # Fallback o modo ondemand
        if report_text is None:
            report_text, _, logs_s, _ = await run_services_check(debug_mode=debug_mode)
            logs.extend(logs_s)

        return MonitoringResultDto(
            target="servicios",
            report_servicios=report_text,
            logs=logs,
            total_items=len(logs),
        )
