"""
# ==============================================================================
# 🧹 ESTRATEGIA DE LIMPIEZA DE SISTEMA: cleanup_strategy.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP) y OCP.
# Mantenimiento y depuración de temporales y logs rotados.
# Ubicación: /opt/sonar/monitor/application/strategies/cleanup_strategy.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from monitor.application.strategies.contracts import (
    MonitoringResultDto,
    MonitoringStrategyProtocol,
)

logger = logging.getLogger("monitor.strategies.cleanup")


class SystemCleanupStrategy(MonitoringStrategyProtocol):
    """Estrategia para el saneamiento y mantenimiento de temporales.

    Ejecuta tareas de limpieza profunda del sistema eliminando capturas PCAP obsoletas,
    bitácoras rotadas de depuración y liberando espacio en disco.
    """

    def can_handle(self, target: str) -> bool:
        """Determina si la cadena de entrada corresponde a una petición de saneamiento.

        Args:
            target: Cadena con el comando o target.

        Returns:
            bool: True si el target es una orden de limpieza ('limpiar', 'clean', etc.).
        """
        return target.strip().lower() in ("limpiar", "clean", "limpieza", "limpiador", "cleanup")

    async def execute(
        self,
        target: str,
        mode: str = "snapshot",
        debug_mode: bool = False,
    ) -> MonitoringResultDto:
        """Ejecuta las operaciones de saneamiento y retorna el informe generado.

        Args:
            target: Comando específico suministrado.
            mode: Modo de ejecución (no aplicable a limpieza).
            debug_mode: Booleano que activa información detallada en consola.

        Returns:
            MonitoringResultDto: DTO consolidado con el resumen de la limpieza efectuada.
        """
        from monitor.system_cleaner import run_system_cleanup

        rep_clean = await run_system_cleanup()

        return MonitoringResultDto(
            target="limpiar",
            report_servicios=rep_clean,
            logs=[rep_clean],
            total_items=1,
        )
