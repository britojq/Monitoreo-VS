"""
Pruebas unitarias de modularización y contratos canónicos para monitor.daemons.
Verifica que los demonios y receptores modularizados provean sus contratos canónicos
y cumplan estrictamente con la Regla de Oro #1 (despacho exclusivo al chat privado del Administrador).
"""

import unittest

import monitor.daemons.syslog_receiver as daemon_syslog
import monitor.daemons.netflow_collector as daemon_netflow
import monitor.daemons.snmp_trap_receiver as daemon_trap
import monitor.daemons.push_telemetry_daemon as daemon_push


class TestMonitorDaemonsModularization(unittest.TestCase):
    """Verificación de contratos canónicos en monitor.daemons."""

    def test_syslog_receiver_methods_and_golden_rule_1(self) -> None:
        """Verifica que syslog_receiver provea métodos y despacho exclusivo al Owner (Regla #1)."""
        self.assertTrue(callable(daemon_syslog.parse_syslog_message))
        self.assertTrue(callable(daemon_syslog.store_syslog_event))
        self.assertTrue(callable(daemon_syslog.run_syslog_receiver))
        self.assertEqual(daemon_syslog.DEFAULT_SYSLOG_PORT, 514)

        # Regla de Oro #1: Owner ID obligatorio (38914901), prohibido id negativo de grupo
        self.assertEqual(daemon_syslog.OWNER_PRIVATE_CHAT_ID, 38914901)
        self.assertGreater(daemon_syslog.OWNER_PRIVATE_CHAT_ID, 0)

    def test_netflow_collector_methods(self) -> None:
        """Verifica que netflow_collector provea FlowBuffer y buffer global compartido."""
        self.assertTrue(hasattr(daemon_netflow, "FlowBuffer"))
        self.assertTrue(hasattr(daemon_netflow, "GLOBAL_BUFFER"))
        self.assertTrue(callable(daemon_netflow.run_netflow_collector))
        self.assertEqual(daemon_netflow.DEFAULT_NETFLOW_PORT, 2055)
        self.assertIsNotNone(daemon_netflow.NETFLOW_V5_HEADER_FMT)

    def test_snmp_trap_receiver_methods_and_golden_rule_1(self) -> None:
        """Verifica que snmp_trap_receiver provea resolución de traps y Regla #1."""
        self.assertTrue(callable(daemon_trap.resolve_trap_info))
        self.assertTrue(isinstance(daemon_trap.TRAP_DICTIONARY, dict))
        self.assertTrue(callable(daemon_trap.run_trap_receiver))
        self.assertEqual(daemon_trap.DEFAULT_TRAP_PORT, 162)

        # Regla de Oro #1
        self.assertEqual(daemon_trap.OWNER_PRIVATE_CHAT_ID, 38914901)
        self.assertGreater(daemon_trap.OWNER_PRIVATE_CHAT_ID, 0)

    def test_push_telemetry_daemon_methods(self) -> None:
        """Verifica que el supervisor de telemetría provea funciones y ciclo supervisor."""
        self.assertTrue(callable(daemon_push.write_status))
        self.assertTrue(callable(daemon_push.main_supervisor))
        self.assertTrue(callable(daemon_push.print_status))
        self.assertTrue(callable(daemon_push.main))
