"""
# ==============================================================================
# ⚙️ ORQUESTADOR CENTRAL DE MONITOREO: orchestrator.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y Abierto/Cerrado (OCP).
# Coordinación limpia del ciclo de monitoreo, concurrencia y telemetría.
# Ubicación: /opt/sonar/monitor/application/orchestrator.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import time
from pathlib import Path
from typing import Any, Dict, List, Optional

from monitor.application.lock_manager import LockManager
from monitor.application.strategies.registry import (
    MonitoringStrategyRegistry,
    get_default_strategy_registry,
)
from monitor.infrastructure.log_writer import (
    DEFAULT_LOG_DIR,
    write_consolidated_log,
)

logger = logging.getLogger("monitor.application.orchestrator")


class MonitoringOrchestrator:
    """Orquestador central desacoplado para la ejecución de evaluaciones de red y servicios."""

    def __init__(
        self,
        lock_manager: Optional[LockManager] = None,
        strategy_registry: Optional[MonitoringStrategyRegistry] = None,
    ) -> None:
        """Inicializa la instancia con sus dependencias y configuración operativa.

        Args:
            lock_manager: Parámetro operativo de entrada.
            strategy_registry: Parámetro operativo de entrada.
        """
        self.lock_mgr: LockManager = lock_manager or LockManager()
        self.registry: MonitoringStrategyRegistry = (
            strategy_registry or get_default_strategy_registry()
        )

    async def run(
        self,
        target: str = "completo",
        debug_mode: bool = False,
        capture_screenshot: bool = False,
        mode: str = "snapshot",
    ) -> Dict[str, Any]:
        """
        Ejecuta el ciclo de monitoreo delegando polimórficamente a la estrategia adecuada.
        """
        # Protección Anti-Concurrencia
        if self.lock_mgr.is_locked():
            logger.warning("Ejecución de monitoreo ya en curso. Omitiendo ciclo duplicado.")
            return {
                "target": target,
                "debug_mode": debug_mode,
                "report_servicios": None,
                "report_sedes": None,
                "report_network": None,
                "screenshot_file": None,
                "pcap_file": None,
                "log_file": DEFAULT_LOG_DIR / "servicelog.txt",
                "elapsed_seconds": 0.0,
                "total_items_checked": 0,
                "skipped": True,
            }

        with self.lock_mgr.context():
            t0 = time.perf_counter()
            clean_target = target.strip().lower()

            # Resolución polimórfica de estrategia (OCP)
            strategy = self.registry.get_strategy(clean_target)
            result = await strategy.execute(clean_target, mode=mode, debug_mode=debug_mode)

            elapsed_time = round(time.perf_counter() - t0, 2)
            log_file_path = write_consolidated_log(result.logs)

            # Captura web panorámica opcional
            screenshot_file: Optional[Path] = None
            if capture_screenshot and clean_target in (
                "servicios",
                "sedes",
                "completo",
                "monitoreo",
                "caidas",
                "incidentes",
                "fallas",
            ):
                try:
                    from monitor.tools.web_screenshot import capture_web_dashboard
                    screenshot_file = await capture_web_dashboard(mode="full")
                except Exception as sc_err:
                    logger.warning(f"Aviso: No se pudo generar captura web: {sc_err}")

            return {
                "target": clean_target,
                "debug_mode": debug_mode,
                "report_servicios": result.report_servicios,
                "report_sedes": result.report_sedes,
                "report_network": result.report_network,
                "screenshot_file": screenshot_file,
                "pcap_file": result.pcap_file,
                "log_file": log_file_path,
                "elapsed_seconds": elapsed_time,
                "total_items_checked": result.total_items,
                "skipped": False,
            }


# Instancia singleton del orquestador
_DEFAULT_ORCHESTRATOR: Optional[MonitoringOrchestrator] = None


def get_default_orchestrator() -> MonitoringOrchestrator:
    """Obtiene la instancia canónica de MonitoringOrchestrator."""
    global _DEFAULT_ORCHESTRATOR
    if _DEFAULT_ORCHESTRATOR is None:
        _DEFAULT_ORCHESTRATOR = MonitoringOrchestrator()
    return _DEFAULT_ORCHESTRATOR


async def execute_monitoring(
    target: str = "completo",
    debug_mode: bool = False,
    capture_screenshot: bool = False,
    mode: str = "snapshot",
) -> Dict[str, Any]:
    """Punto de entrada de aplicación para ejecutar ciclos de monitoreo."""
    orchestrator = get_default_orchestrator()
    return await orchestrator.run(
        target=target,
        debug_mode=debug_mode,
        capture_screenshot=capture_screenshot,
        mode=mode,
    )
