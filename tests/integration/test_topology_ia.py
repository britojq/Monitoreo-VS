#!/usr/bin/env python3
"""
Tests de Integración para Topología Dinámica, Wake-on-LAN y Motor Predictivo de IA (Fase 7)
Verifica:
1. Esquema e integridad de las 4 tablas de Fase 7 en MariaDB.
2. Normalización de MAC y generación de Magic Packet WoL (102 bytes: 6x 0xFF + 16x MAC).
3. Algoritmo de regresión lineal por mínimos cuadrados y cálculo de R^2.
4. Cálculo estadístico de media y desviación estándar para detección de anomalías.
5. Constructor de topología de red y validación de estructura de grafo Cytoscape.js.
"""

import sys
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_web_sync import get_db_connection
from monitor.wol_sender import normalize_mac, create_magic_packet
from monitor.predictive_analyzer import calculate_linear_regression, calculate_stats
from monitor.topology_builder import TopologyBuilder


class TestTopologyAndPredictiveIntegration(unittest.TestCase):

    def setUp(self):
        self.db = get_db_connection()

    def tearDown(self):
        try:
            self.db.close()
        except Exception:
            pass

    def test_01_database_tables_exist(self):
        """Verifica que las tablas de Fase 7 existen en MariaDB."""
        expected_tables = [
            'network_topology_links',
            'wol_devices',
            'predictive_anomalies',
            'hardware_lifecycle',
        ]
        with self.db.cursor() as cur:
            cur.execute("SHOW TABLES WHERE Tables_in_monitoreo_vs IN ('network_topology_links', 'wol_devices', 'predictive_anomalies', 'hardware_lifecycle');")
            rows = cur.fetchall()
            existing = [list(r.values())[0] for r in rows]

        for table in expected_tables:
            self.assertIn(table, existing, f"La tabla {table} no existe en MariaDB.")

    def test_02_wol_mac_normalization_and_magic_packet(self):
        """Prueba la validación de direcciones MAC y construcción del Magic Packet."""
        # 1. Normalización de formatos diversos
        mac_plain = "001122334455"
        mac_colon = "00:11:22:33:44:55"
        mac_dash = "00-11-22-33-44-55"
        mac_dot = "0011.2233.4455"

        expected = "00:11:22:33:44:55"
        self.assertEqual(normalize_mac(mac_plain), expected)
        self.assertEqual(normalize_mac(mac_colon), expected)
        self.assertEqual(normalize_mac(mac_dash), expected)
        self.assertEqual(normalize_mac(mac_dot), expected)

        # 2. Validación de MAC inválida
        with self.assertRaises(ValueError):
            normalize_mac("00:11:22:33:44")

        # 3. Construcción del Magic Packet (6x 0xFF + 16x MAC = 102 bytes)
        packet = create_magic_packet(mac_colon)
        self.assertEqual(len(packet), 102)
        self.assertEqual(packet[:6], b"\xff" * 6)
        mac_bytes = bytes.fromhex("001122334455")
        for i in range(16):
            offset = 6 + i * 6
            self.assertEqual(packet[offset:offset + 6], mac_bytes)

    def test_03_linear_regression_calculation(self):
        """Prueba el cálculo de regresión lineal (m, b, R^2) sobre puntos de tendencia creciente."""
        # Puntos y = 2x + 10 (perfectamente lineal: R^2 = 1.0)
        points = [(0.0, 10.0), (1.0, 12.0), (2.0, 14.0), (3.0, 16.0), (4.0, 18.0)]
        m, b, r2 = calculate_linear_regression(points)
        self.assertAlmostEqual(m, 2.0, places=3)
        self.assertAlmostEqual(b, 10.0, places=3)
        self.assertAlmostEqual(r2, 1.0, places=3)

    def test_04_stats_mean_and_std_dev(self):
        """Prueba el cálculo de media y desviación estándar."""
        values = [10.0, 12.0, 23.0, 23.0, 16.0, 23.0, 21.0, 16.0]
        mean, std_dev = calculate_stats(values)
        self.assertAlmostEqual(mean, 18.0, places=2)
        self.assertGreater(std_dev, 0.0)

        # Vector vacío
        m_empty, s_empty = calculate_stats([])
        self.assertEqual(m_empty, 0.0)
        self.assertEqual(s_empty, 0.0)

    def test_05_topology_builder_graph_structure(self):
        """Prueba que el constructor de topología instancie y genere estructura de grafo válida."""
        builder = TopologyBuilder()
        try:
            graph = builder.get_cytoscape_graph()
            self.assertIsInstance(graph, dict)
            self.assertIn("nodes", graph)
            self.assertIn("edges", graph)
            self.assertIsInstance(graph["nodes"], list)
            self.assertIsInstance(graph["edges"], list)
        finally:
            builder.close()


if __name__ == '__main__':
    unittest.main()
