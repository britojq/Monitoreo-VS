"""
# ==============================================================================
# 🗃️ REGISTRO DINÁMICO DE ESTRATEGIAS: registry.py
# Arquitectura SOLID - Principio Abierto/Cerrado (OCP).
# Registro y resolución polimórfica sin cadenas de if/elif.
# Ubicación: /opt/sonar/monitor/application/strategies/registry.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import List, Optional

from monitor.application.strategies.cleanup_strategy import SystemCleanupStrategy
from monitor.application.strategies.contracts import MonitoringStrategyProtocol
from monitor.application.strategies.full_strategy import FullMonitoringStrategy
from monitor.application.strategies.network_strategy import NetworkAnalysisStrategy
from monitor.application.strategies.outages_strategy import OutagesMonitoringStrategy
from monitor.application.strategies.services_strategy import ServicesMonitoringStrategy
from monitor.application.strategies.sites_strategy import SitesMonitoringStrategy

logger = logging.getLogger("monitor.strategies.registry")


class MonitoringStrategyRegistry:
    """Catálogo y resolvedor polimórfico de estrategias de monitoreo."""

    def __init__(self) -> None:
        self._strategies: List[MonitoringStrategyProtocol] = []
        self._register_defaults()

    def register(self, strategy: MonitoringStrategyProtocol) -> None:
        """Registra una nueva estrategia de monitoreo (Extensión limpia OCP)."""
        self._strategies.append(strategy)

    def get_strategy(self, target: str) -> MonitoringStrategyProtocol:
        """
        Retorna la estrategia correspondiente para el objetivo dado.
        Si ninguna estrategia específica coincide, retorna FullMonitoringStrategy como fallback.
        """
        clean_target = target.strip().lower()
        for strategy in self._strategies:
            if strategy.can_handle(clean_target):
                return strategy

        logger.info(f"Objetivo no específico '{target}'; resolviendo a estrategia completa por defecto.")
        return FullMonitoringStrategy()

    def _register_defaults(self) -> None:
        """Registra las estrategias estándar del ecosistema S.O.N.A.R."""
        self.register(ServicesMonitoringStrategy())
        self.register(SitesMonitoringStrategy())
        self.register(OutagesMonitoringStrategy())
        self.register(NetworkAnalysisStrategy())
        self.register(SystemCleanupStrategy())
        self.register(FullMonitoringStrategy())


# Instancia singleton del registro de estrategias
_DEFAULT_REGISTRY: Optional[MonitoringStrategyRegistry] = None


def get_default_strategy_registry() -> MonitoringStrategyRegistry:
    """Obtiene o inicializa el registro canónico de estrategias."""
    global _DEFAULT_REGISTRY
    if _DEFAULT_REGISTRY is None:
        _DEFAULT_REGISTRY = MonitoringStrategyRegistry()
    return _DEFAULT_REGISTRY
