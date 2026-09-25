#!/usr/bin/env python3
"""
Tests de Integración para Auto-Discovery de Red y Anti-Rogue (Fase 1)
Verifica:
1. Esquema e integridad de las 5 tablas de Auto-Discovery en MariaDB.
2. Resolución y lookup de fabricantes OUI por prefijo MAC.
3. Clasificación heurística inteligente de tipos de dispositivos.
4. Extracción de tabla ARP del kernel de Linux.
5. Ejecución asíncrona de sweep en red local controlada.
"""

import asyncio
import sys
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_web_sync import get_db_connection
from monitor.network_discovery import (
    lookup_oui_vendor,
    infer_device_type,
    get_kernel_arp_table,
    sweep_subnet_async,
)


class TestDiscoveryIntegration(unittest.TestCase):

    def setUp(self):
        self.db = get_db_connection()

    def tearDown(self):
        try:
            self.db.close()
        except Exception:
            pass

    def test_01_database_tables_exist(self):
        """Verifica que las 5 tablas de Fase 1 existen en MariaDB."""
        expected_tables = [
            'discovery_subnets',
            'discovery_scans',
            'discovered_devices',
            'discovered_device_history',
            'oui_vendors',
        ]
        with self.db.cursor() as cur:
            cur.execute("SHOW TABLES WHERE Tables_in_monitoreo_vs LIKE 'discover%' OR Tables_in_monitoreo_vs = 'oui_vendors';")
            rows = cur.fetchall()
            existing = [list(r.values())[0] for r in rows]

        for table in expected_tables:
            self.assertIn(table, existing, f"La tabla {table} no existe en MariaDB.")

    def test_02_infer_device_type_heuristics(self):
        """Prueba la clasificación heurística de tipos de dispositivos."""
        self.assertEqual(infer_device_type("Cisco Systems", "sw-core-01"), "switch")
        self.assertEqual(infer_device_type("MikroTik", "gw-carabobo"), "router")
        self.assertEqual(infer_device_type("VMware, Inc.", "srv-db-01"), "server")
        self.assertEqual(infer_device_type("HP Inc", "laserjet-pro-m404"), "printer")
        self.assertEqual(infer_device_type("Ubiquiti Inc", "ap-valle-seco"), "ap")
        self.assertEqual(infer_device_type("APC by Schneider", "ups-datacenter"), "ups")
        self.assertEqual(infer_device_type("Unknown", "unknown-device"), "unknown")

    def test_03_oui_vendor_lookup(self):
        """Prueba la resolución de fabricante OUI."""
        vendor, prefix = lookup_oui_vendor("00:50:56:AB:CD:EF", self.db)
        self.assertEqual(prefix, "00:50:56")
        self.assertIsNotNone(vendor)

        # Prefijo inválido
        v_invalid, p_invalid = lookup_oui_vendor("XX", self.db)
        self.assertEqual(v_invalid, "Dispositivo Desconocido")
        self.assertIsNone(p_invalid)

    def test_04_get_kernel_arp_table(self):
        """Prueba que la extracción de la tabla ARP del kernel no lance excepciones y sea un dict."""
        arp_table = get_kernel_arp_table()
        self.assertIsInstance(arp_table, dict)
        for ip, mac in arp_table.items():
            self.assertEqual(len(mac), 17)
            self.assertEqual(mac.count(':'), 5)

    def test_05_sweep_subnet_loopback(self):
        """Prueba el barrido asíncrono no bloqueante en subred local/loopback."""
        results = asyncio.run(sweep_subnet_async("127.0.0.1/32", rate_limit_pps=50))
        self.assertIsInstance(results, list)


if __name__ == '__main__':
    unittest.main()
