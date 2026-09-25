#!/usr/bin/env python3
"""
Tests de Integración para Telemetría Push, NetFlow, Syslog y SNMP Traps (Fase 6)
Verifica:
1. Esquema e integridad de las 4 tablas de telemetría push en MariaDB.
2. Procesamiento y agregación en FlowBuffer para NetFlow v5.
3. Decodificación y clasificación de mensajes Syslog (RFC 5424 y RFC 3164).
4. Resolución y diccionario de Traps SNMP (MIB-II, Cisco, UPS RFC 1628).
5. Cumplimiento estricto de la REGLA DE ORO #1 (despacho exclusivo al Administrador privado).
"""

import asyncio
import socket
import struct
import sys
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_web_sync import get_db_connection
from monitor.netflow_collector import FlowBuffer, NETFLOW_V5_HEADER_FMT, NETFLOW_V5_RECORD_FMT
from monitor.syslog_receiver import parse_syslog_message, OWNER_PRIVATE_CHAT_ID as SYSLOG_OWNER
from monitor.snmp_trap_receiver import resolve_trap_info, TRAP_DICTIONARY


class TestTelemetryPushIntegration(unittest.TestCase):

    def setUp(self):
        self.db = get_db_connection()

    def tearDown(self):
        try:
            self.db.close()
        except Exception:
            pass

    def test_01_database_tables_exist(self):
        """Verifica que las tablas de Fase 6 existen en MariaDB."""
        expected_tables = [
            'netflow_records',
            'syslog_events',
            'netflow_top_talkers',
            'snmp_traps_received',
        ]
        with self.db.cursor() as cur:
            cur.execute("SHOW TABLES WHERE Tables_in_monitoreo_vs IN ('netflow_records', 'syslog_events', 'netflow_top_talkers', 'snmp_traps_received');")
            rows = cur.fetchall()
            existing = [list(r.values())[0] for r in rows]

        for table in expected_tables:
            self.assertIn(table, existing, f"La tabla {table} no existe en MariaDB.")

    def test_02_netflow_v5_buffer_aggregation(self):
        """Prueba la agregación de flujos NetFlow v5 en memoria por FlowBuffer."""
        buf = FlowBuffer()

        async def run_flow_test():
            await buf.add_flow(
                exporter_ip="10.20.0.1",
                src_ip="10.20.23.50",
                dst_ip="8.8.8.8",
                src_port=44332,
                dst_port=53,
                protocol=17,
                byte_count=120,
                packet_count=2,
                timestamp=1700000000.0
            )
            await buf.add_flow(
                exporter_ip="10.20.0.1",
                src_ip="10.20.23.50",
                dst_ip="8.8.8.8",
                src_port=44332,
                dst_port=53,
                protocol=17,
                byte_count=240,
                packet_count=3,
                timestamp=1700000005.0
            )

        asyncio.run(run_flow_test())
        self.assertEqual(len(buf.aggregated), 1, "Ambos flujos con misma clave deben agregarse en 1 entrada.")
        key = list(buf.aggregated.keys())[0]
        bytes_total, pkts_total = buf.aggregated[key]
        self.assertEqual(bytes_total, 360)
        self.assertEqual(pkts_total, 5)

    def test_03_syslog_rfc5424_and_rfc3164_parsing(self):
        """Prueba el parseo de mensajes Syslog bajo ambos estándares RFC."""
        # 1. RFC 5424
        raw_rfc5424 = "<165>1 2026-09-25T08:00:00Z router-core-01 BGP 1234 ID47 [exampleSDID@32473 iut=\"3\"] BGP neighbor 10.20.0.2 DOWN"
        parsed_5424 = parse_syslog_message(raw_rfc5424, "10.20.0.1")
        self.assertEqual(parsed_5424["severity"], 5)  # 165 % 8 = 5 (Notice)
        self.assertEqual(parsed_5424["facility"], 20) # 165 // 8 = 20 (local4)
        self.assertEqual(parsed_5424["hostname"], "router-core-01")
        self.assertEqual(parsed_5424["program"], "BGP")

        # 2. RFC 3164 (BSD / Cisco)
        raw_rfc3164 = "<189>Sep 25 08:15:30 sw-valle-seco %LINK-3-UPDOWN: Interface GigabitEthernet0/1, changed state to down"
        parsed_3164 = parse_syslog_message(raw_rfc3164, "10.20.23.10")
        self.assertEqual(parsed_3164["severity"], 5)  # 189 % 8 = 5
        self.assertEqual(parsed_3164["facility"], 23) # 189 // 8 = 23 (local7)
        self.assertEqual(parsed_3164["hostname"], "sw-valle-seco")
        self.assertIn("GigabitEthernet0/1", parsed_3164["message"])

    def test_04_snmp_trap_dictionary_resolution(self):
        """Prueba la resolución de OIDs de Traps SNMP en TRAP_DICTIONARY."""
        # linkDown
        info_linkdown = resolve_trap_info("1.3.6.1.6.3.1.1.5.3")
        self.assertEqual(info_linkdown["type"], "linkDown")
        self.assertEqual(info_linkdown["severity"], "critical")

        # UPS on battery (RFC 1628)
        info_ups = resolve_trap_info("1.3.6.1.2.1.33.2.0.1")
        self.assertEqual(info_ups["type"], "upsTrapOnBattery")
        self.assertEqual(info_ups["severity"], "critical")

        # Trap desconocido
        info_unknown = resolve_trap_info("1.3.6.1.4.1.99999.1.0")
        self.assertEqual(info_unknown["type"], "genericTrap")
        self.assertEqual(info_unknown["severity"], "info")

    def test_05_golden_rule_1_syslog_owner_chat_id(self):
        """Verifica que el chat ID de despacho de seguridad sea estrictamente el ID privado del Owner."""
        self.assertEqual(SYSLOG_OWNER, 38914901)
        self.assertGreater(SYSLOG_OWNER, 0, "El chat ID jamás debe ser negativo (grupo).")


if __name__ == '__main__':
    unittest.main()
