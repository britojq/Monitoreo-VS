"""
Pruebas unitarias de modularización y paridad de fachadas para monitor.daemons.
Verifica que los demonios y receptores modularizados conserven 100% retrocompatibilidad
y cumplan estrictamente con la Regla de Oro #1 (despacho exclusivo al chat privado del Administrador).
"""

import unittest

import monitor.daemons.syslog_receiver as daemon_syslog
import monitor.syslog_receiver as facade_syslog

import monitor.daemons.netflow_collector as daemon_netflow
import monitor.netflow_collector as facade_netflow

import monitor.daemons.snmp_trap_receiver as daemon_trap
import monitor.snmp_trap_receiver as facade_trap

import monitor.daemons.push_telemetry_daemon as daemon_push
import monitor.push_telemetry_daemon as facade_push


class TestMonitorDaemonsModularization(unittest.TestCase):
    """Verificación de paridad arquitectónica y contratos de extensión en monitor.daemons."""

    def test_syslog_receiver_parity_and_golden_rule_1(self) -> None:
        """Verifica que syslog_receiver conserve paridad y despacho exclusivo al Owner (Regla #1)."""
        self.assertIs(facade_syslog.parse_syslog_message, daemon_syslog.parse_syslog_message)
        self.assertIs(facade_syslog.store_syslog_event, daemon_syslog.store_syslog_event)
        self.assertIs(facade_syslog.run_syslog_receiver, daemon_syslog.run_syslog_receiver)
        self.assertEqual(facade_syslog.DEFAULT_SYSLOG_PORT, daemon_syslog.DEFAULT_SYSLOG_PORT)

        # Regla de Oro #1: Owner ID obligatorio (38914901), prohibido id negativo de grupo
        self.assertEqual(daemon_syslog.OWNER_PRIVATE_CHAT_ID, 38914901)
        self.assertEqual(facade_syslog.OWNER_PRIVATE_CHAT_ID, 38914901)
        self.assertGreater(daemon_syslog.OWNER_PRIVATE_CHAT_ID, 0)

    def test_netflow_collector_parity(self) -> None:
        """Verifica que netflow_collector conserve paridad y buffer global compartido."""
        self.assertIs(facade_netflow.FlowBuffer, daemon_netflow.FlowBuffer)
        self.assertIs(facade_netflow.GLOBAL_BUFFER, daemon_netflow.GLOBAL_BUFFER)
        self.assertIs(facade_netflow.run_netflow_collector, daemon_netflow.run_netflow_collector)
        self.assertEqual(facade_netflow.DEFAULT_NETFLOW_PORT, daemon_netflow.DEFAULT_NETFLOW_PORT)
        self.assertEqual(facade_netflow.NETFLOW_V5_HEADER_FMT, daemon_netflow.NETFLOW_V5_HEADER_FMT)

    def test_snmp_trap_receiver_parity_and_golden_rule_1(self) -> None:
        """Verifica que snmp_trap_receiver conserve paridad, diccionario de traps y Regla #1."""
        self.assertIs(facade_trap.resolve_trap_info, daemon_trap.resolve_trap_info)
        self.assertIs(facade_trap.TRAP_DICTIONARY, daemon_trap.TRAP_DICTIONARY)
        self.assertIs(facade_trap.run_trap_receiver, daemon_trap.run_trap_receiver)
        self.assertEqual(facade_trap.DEFAULT_TRAP_PORT, daemon_trap.DEFAULT_TRAP_PORT)

        # Regla de Oro #1
        self.assertEqual(daemon_trap.OWNER_PRIVATE_CHAT_ID, 38914901)
        self.assertEqual(facade_trap.OWNER_PRIVATE_CHAT_ID, 38914901)
        self.assertGreater(daemon_trap.OWNER_PRIVATE_CHAT_ID, 0)

    def test_push_telemetry_daemon_parity(self) -> None:
        """Verifica que el supervisor de telemetría conserve paridad de funciones y main."""
        self.assertIs(facade_push.write_status, daemon_push.write_status)
        self.assertIs(facade_push.main_supervisor, daemon_push.main_supervisor)
        self.assertIs(facade_push.print_status, daemon_push.print_status)
        self.assertIs(facade_push.main, daemon_push.main)
