"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS ORQUESTADOR Y ESTRATEGIAS: test_monitoring_orchestrator_solid.py
# Valida la arquitectura desacoplada SOLID (SRP, OCP, DIP) del motor de monitoreo.
# ==============================================================================
"""

import asyncio
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

from monitor.application.lock_manager import LockManager
from monitor.application.orchestrator import MonitoringOrchestrator, execute_monitoring
from monitor.application.strategies.cleanup_strategy import SystemCleanupStrategy
from monitor.application.strategies.contracts import (
    MonitoringResultDto,
    MonitoringStrategyProtocol,
)
from monitor.application.strategies.full_strategy import FullMonitoringStrategy
from monitor.application.strategies.network_strategy import NetworkAnalysisStrategy
from monitor.application.strategies.outages_strategy import OutagesMonitoringStrategy
from monitor.application.strategies.registry import (
    MonitoringStrategyRegistry,
    get_default_strategy_registry,
)
from monitor.application.strategies.services_strategy import ServicesMonitoringStrategy
from monitor.application.strategies.sites_strategy import SitesMonitoringStrategy


class TestStrategyRegistryOCP:
    """Valida el principio Abierto/Cerrado (OCP) del registro de estrategias."""

    def test_default_registry_contains_all_core_strategies(self):
        """Verifica que el catálogo por defecto incluya las 6 estrategias principales."""
        registry = MonitoringStrategyRegistry()
        assert len(registry._strategies) == 6

        # Verificación polimórfica de resolución
        assert isinstance(registry.get_strategy("servicios"), ServicesMonitoringStrategy)
        assert isinstance(registry.get_strategy("sedes"), SitesMonitoringStrategy)
        assert isinstance(registry.get_strategy("completo"), FullMonitoringStrategy)
        assert isinstance(registry.get_strategy("caidas"), OutagesMonitoringStrategy)
        assert isinstance(registry.get_strategy("analisis_red"), NetworkAnalysisStrategy)
        assert isinstance(registry.get_strategy("limpiador"), SystemCleanupStrategy)

    def test_unknown_target_falls_back_to_full_strategy(self):
        """Un objetivo desconocido debe resolverse limpiamente a la estrategia completa por defecto."""
        registry = MonitoringStrategyRegistry()
        strategy = registry.get_strategy("target_inexistente_xyz")
        assert isinstance(strategy, FullMonitoringStrategy)

    def test_open_closed_extensibility_with_custom_strategy(self):
        """Valida que se puedan añadir nuevas estrategias por extensión limpia sin modificar código existente."""
        class CustomUpsStrategy:
            def can_handle(self, target: str) -> bool:
                return target == "ups_custom"

            async def execute(self, target: str, mode: str = "snapshot", debug_mode: bool = False) -> MonitoringResultDto:
                return MonitoringResultDto(target="ups_custom", total_items=1)

        registry = MonitoringStrategyRegistry()
        custom = CustomUpsStrategy()
        assert isinstance(custom, MonitoringStrategyProtocol)

        registry.register(custom)
        resolved = registry.get_strategy("ups_custom")
        assert resolved is custom


class TestStrategyTargetAliases:
    """Valida los alias reconocidos por cada estrategia concreta."""

    def test_services_strategy_aliases(self):
        strategy = ServicesMonitoringStrategy()
        assert strategy.can_handle("servicios")
        assert strategy.can_handle("servicio")
        assert strategy.can_handle("services")
        assert not strategy.can_handle("sedes")

    def test_sites_strategy_aliases(self):
        strategy = SitesMonitoringStrategy()
        assert strategy.can_handle("sedes")
        assert strategy.can_handle("sede")
        assert strategy.can_handle("sites")
        assert not strategy.can_handle("servicios")

    def test_outages_strategy_aliases(self):
        strategy = OutagesMonitoringStrategy()
        assert strategy.can_handle("caidas")
        assert strategy.can_handle("incidentes")
        assert strategy.can_handle("fallas")
        assert strategy.can_handle("outages")
        assert not strategy.can_handle("completo")

    def test_network_strategy_aliases(self):
        strategy = NetworkAnalysisStrategy()
        assert strategy.can_handle("analisis_red")
        assert strategy.can_handle("red")
        assert strategy.can_handle("trafico")
        assert strategy.can_handle("network")
        assert not strategy.can_handle("limpiador")

    def test_cleanup_strategy_aliases(self):
        strategy = SystemCleanupStrategy()
        assert strategy.can_handle("limpiador")
        assert strategy.can_handle("limpieza")
        assert strategy.can_handle("cleanup")
        assert strategy.can_handle("clean")
        assert not strategy.can_handle("completo")


class TestMonitoringOrchestratorExecution:
    """Valida el orquestador central con inyección de dependencias y dobles de prueba."""

    def test_run_delegates_to_resolved_strategy(self):
        """Verifica que run() delegue la ejecución a la estrategia provista por el registry."""
        mock_strategy = MagicMock()
        mock_strategy.can_handle.return_value = True
        mock_strategy.execute = AsyncMock(
            return_value=MonitoringResultDto(
                target="servicios",
                total_items=12,
                report_servicios="Reporte OK",
                logs=["Log 1", "Log 2"],
            )
        )

        mock_registry = MagicMock(spec=MonitoringStrategyRegistry)
        mock_registry.get_strategy.return_value = mock_strategy

        mock_lock = MagicMock(spec=LockManager)
        mock_lock.is_locked.return_value = False
        mock_lock.context.return_value.__enter__.return_value = None
        mock_lock.context.return_value.__exit__.return_value = None

        with patch("monitor.application.orchestrator.write_consolidated_log", return_value=Path("/tmp/test.log")):
            orchestrator = MonitoringOrchestrator(
                lock_manager=mock_lock,
                strategy_registry=mock_registry,
            )
            result = asyncio.run(orchestrator.run(target="servicios", debug_mode=True))

        mock_registry.get_strategy.assert_called_once_with("servicios")
        mock_strategy.execute.assert_awaited_once_with("servicios", mode="snapshot", debug_mode=True)

        assert result["target"] == "servicios"
        assert result["skipped"] is False
        assert result["total_items_checked"] == 12
        assert result["report_servicios"] == "Reporte OK"

    def test_run_skips_when_lock_is_active(self):
        """Verifica que el orquestador omita el ciclo si hay un bloqueo concurrente activo."""
        mock_lock = MagicMock(spec=LockManager)
        mock_lock.is_locked.return_value = True

        mock_registry = MagicMock(spec=MonitoringStrategyRegistry)

        orchestrator = MonitoringOrchestrator(
            lock_manager=mock_lock,
            strategy_registry=mock_registry,
        )

        result = asyncio.run(orchestrator.run(target="completo"))
        assert result["skipped"] is True
        assert result["total_items_checked"] == 0
        mock_registry.get_strategy.assert_not_called()
