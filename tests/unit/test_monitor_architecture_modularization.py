"""
Pruebas unitarias de arquitectura limpia y modularización para monitor/.
Verifica que los módulos organizados en subpaquetes (tools, alerts, cli, deployment, gitops, infrastructure)
conserven 100% de paridad en memoria e interoperabilidad arquitectónica.
"""

import unittest
import importlib

MODULE_PAIRS = [
    ("monitor.database_backup", "monitor.tools.database_backup"),
    ("monitor.dependencies", "monitor.tools.dependencies"),
    ("monitor.self_heal_environment", "monitor.tools.self_heal_environment"),
    ("monitor.lifecycle_manager", "monitor.tools.lifecycle_manager"),
    ("monitor.topology_builder", "monitor.tools.topology_builder"),
    ("monitor.predictive_analyzer", "monitor.tools.predictive_analyzer"),
    ("monitor.cron_runner", "monitor.daemons.cron_runner"),
    ("monitor.boot_alert", "monitor.alerts.boot_alert"),
    ("monitor.fail2ban_alert", "monitor.alerts.fail2ban_alert"),
    ("monitor.device_service_notifier", "monitor.alerts.device_service_notifier"),
    ("monitor.telegram_dispatcher", "monitor.alerts.telegram_dispatcher"),
    ("monitor.cli_activar", "monitor.cli.cli_activar"),
    ("monitor.snmp_activator", "monitor.cli.snmp_activator"),
    ("monitor.agent_deployer", "monitor.deployment.agent_deployer"),
    ("monitor.git_network", "monitor.gitops.git_network"),
    ("monitor.monitor_db", "monitor.infrastructure.monitor_db"),
    ("monitor.proxy_cache", "monitor.infrastructure.proxy_cache"),
]


class TestMonitorArchitectureModularization(unittest.TestCase):
    """Verificación de paridad arquitectónica y contratos de extensión en monitor/."""

    def test_modular_package_in_memory_identity(self) -> None:
        """Verifica que las fachadas delegadoras y los módulos canónicos compartan identidad en sys.modules."""
        for facade_name, canonical_name in MODULE_PAIRS:
            with self.subTest(facade=facade_name, canonical=canonical_name):
                facade_mod = importlib.import_module(facade_name)
                canonical_mod = importlib.import_module(canonical_name)
                self.assertIs(
                    facade_mod,
                    canonical_mod,
                    f"Fallo de paridad en memoria: {facade_name} no es idéntico a {canonical_name}"
                )

    def test_canonical_tools_functions_exist(self) -> None:
        """Verifica la presencia de contratos clave en monitor.tools."""
        from monitor.tools.database_backup import create_db_snapshot, list_backups
        from monitor.tools.lifecycle_manager import LifecycleManager
        from monitor.tools.topology_builder import TopologyBuilder

        self.assertTrue(callable(create_db_snapshot))
        self.assertTrue(callable(list_backups))
        self.assertTrue(callable(LifecycleManager))
        self.assertTrue(callable(TopologyBuilder))

    def test_canonical_alerts_functions_exist(self) -> None:
        """Verifica la presencia de contratos clave en monitor.alerts."""
        from monitor.alerts.boot_alert import handle_start, handle_stop
        from monitor.alerts.fail2ban_alert import log_event_to_database, is_private_or_local_ip
        from monitor.alerts.device_service_notifier import evaluate_and_notify_all

        self.assertTrue(callable(handle_start))
        self.assertTrue(callable(handle_stop))
        self.assertTrue(callable(log_event_to_database))
        self.assertTrue(callable(is_private_or_local_ip))
        self.assertTrue(callable(evaluate_and_notify_all))
