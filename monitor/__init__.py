"""
# ==============================================================================
# 📦 PAQUETE CENTRAL DE MONITOREO: monitor/__init__.py (@IA_ValleSeco_bot)
# Monitoreo de Infraestructura, Auditoría, Telemetría y Ciberseguridad S.O.N.A.R.
# Arquitectura Limpia SOLID - Enrutamiento dinámico de subpaquetes (ARCH-L1 / PEP 562)
# Ubicación: /opt/sonar/monitor/__init__.py
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import importlib
import importlib.machinery
import sys
from importlib.abc import Loader, MetaPathFinder
from typing import Any, List, Optional

# Mapeo canónico de submódulos organizados en subpaquetes dedicados (ARCH-L1)
_FACADE_MAP = {
    "monitor.agent_deployer": "monitor.deployment.agent_deployer",
    "monitor.boot_alert": "monitor.alerts.boot_alert",
    "monitor.cli_activar": "monitor.cli.cli_activar",
    "monitor.config_backup": "monitor.checkers.backup",
    "monitor.core_shield": "monitor.shield.secure_core",
    "monitor.cron_runner": "monitor.daemons.cron_runner",
    "monitor.database_backup": "monitor.tools.database_backup",
    "monitor.dependencies": "monitor.tools.dependencies",
    "monitor.device_service_notifier": "monitor.alerts.device_service_notifier",
    "monitor.diagnostico_produccion": "monitor.tools.diagnostico_produccion",
    "monitor.fail2ban_alert": "monitor.alerts.fail2ban_alert",
    "monitor.git_network": "monitor.gitops.git_network",
    "monitor.lifecycle_manager": "monitor.tools.lifecycle_manager",
    "monitor.monitor_db": "monitor.infrastructure.monitor_db",
    "monitor.net_radar_engine": "monitor.checkers.radar",
    "monitor.netflow_collector": "monitor.daemons.netflow_collector",
    "monitor.network_analyzer": "monitor.checkers.analyzer",
    "monitor.network_discovery": "monitor.checkers.discovery",
    "monitor.predictive_analyzer": "monitor.tools.predictive_analyzer",
    "monitor.proxy_cache": "monitor.infrastructure.proxy_cache",
    "monitor.push_telemetry_daemon": "monitor.daemons.push_telemetry_daemon",
    "monitor.self_heal_environment": "monitor.tools.self_heal_environment",
    "monitor.shield_dialog": "monitor.shield.ui.shield_dialog",
    "monitor.snmp_activator": "monitor.cli.snmp_activator",
    "monitor.snmp_trap_receiver": "monitor.daemons.snmp_trap_receiver",
    "monitor.ssh_alert": "monitor.shield.ssh_alert",
    "monitor.ssl_checker": "monitor.checkers.ssl",
    "monitor.syslog_receiver": "monitor.daemons.syslog_receiver",
    "monitor.system_cleaner": "monitor.tools.system_cleaner",
    "monitor.system_optimizer": "monitor.tools.system_optimizer",
    "monitor.telegram_dispatcher": "monitor.alerts.telegram_dispatcher",
    "monitor.thermal_guard": "monitor.checkers.thermal",
    "monitor.topology_builder": "monitor.tools.topology_builder",
    "monitor.web_screenshot": "monitor.tools.web_screenshot",
    "monitor.wol_sender": "monitor.tools.wol_sender",
}


class _MonitorAliasLoader(Loader):
    """Carga y delega el módulo conservando 100% de paridad e identidad en sys.modules."""

    def __init__(self, target_name: str) -> None:
        """Inicializa la instancia con sus dependencias y configuración operativa.

        Args:
            target_name: Parámetro operativo de entrada.
        """
        self.target_name = target_name

    def create_module(self, spec: Any) -> Any:
        """Crea o vincula el módulo alias dinámico en sys.modules.

        Args:
            spec: Parámetro operativo de entrada.
        """
        mod = importlib.import_module(self.target_name)
        sys.modules[spec.name] = mod
        return mod

    def exec_module(self, module: Any) -> None:
        """Ejecuta el módulo solicitado en el contexto del cargador de fachadas.

        Args:
            module: Parámetro operativo de entrada.
        """
        pass


class _MonitorFacadeFinder(MetaPathFinder):
    """Enrutador dinámico que redirige importaciones históricas a sus nuevos subpaquetes."""

    def find_spec(self, fullname: str, path: Optional[List[str]], target: Any = None) -> Any:
        """Localiza y construye la especificación de módulo para fachadas de monitor/.

        Args:
            fullname: Parámetro operativo de entrada.
            path: Parámetro operativo de entrada.
            target: Parámetro operativo de entrada.
        """
        if fullname in _FACADE_MAP:
            target_pkg = _FACADE_MAP[fullname]
            return importlib.machinery.ModuleSpec(fullname, _MonitorAliasLoader(target_pkg))
        return None


# Registrar en sys.meta_path si no existe
if not any(isinstance(finder, _MonitorFacadeFinder) for finder in sys.meta_path):
    sys.meta_path.insert(0, _MonitorFacadeFinder())


def __getattr__(name: str) -> Any:
    """Resuelve dinámicamente atributos o submódulos importados desde monitor."""
    fullname = f"monitor.{name}"
    if fullname in _FACADE_MAP:
        target = _FACADE_MAP[fullname]
        mod = importlib.import_module(target)
        sys.modules[fullname] = mod
        return mod
    raise AttributeError(f"module 'monitor' has no attribute '{name}'")


def __dir__() -> List[str]:
    """Retorna la lista de submódulos y atributos públicos soportados."""
    short_names = [k.split(".", 1)[1] for k in _FACADE_MAP.keys()]
    return sorted(list(globals().keys()) + short_names)
