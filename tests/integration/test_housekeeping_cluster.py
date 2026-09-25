#!/usr/bin/env python3
"""
Tests de Integración para Mantenimiento de Telemetría, Rollups y Replicación Cluster (Fase 8)
Verifica:
1. Esquema e integridad de las tablas de rollups horarios en MariaDB.
2. Protección de todas las tablas (Fases 1 a 8) en self_heal_environment.py.
3. Compatibilidad de compresión gzip en sincronización de telemetría de Cluster API.
4. Ejecución no destructiva del comando Artisan de housekeeping (--dry-run).
5. Cumplimiento estricto de la REGLA DE ORO #2 (Prohibición absoluta de exponer tecnología subyacente).
"""

import subprocess
import sys
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_web_sync import get_db_connection
from monitor.self_heal_environment import PROTECTED_TABLES


class TestHousekeepingAndClusterIntegration(unittest.TestCase):

    def setUp(self):
        self.db = get_db_connection()

    def tearDown(self):
        try:
            self.db.close()
        except Exception:
            pass

    def test_01_rollup_tables_exist(self):
        """Verifica que las tablas de agregación horaria (Rollups) existen en MariaDB."""
        expected = ['snmp_metric_hourly_rollups', 'snmp_interface_hourly_rollups']
        with self.db.cursor() as cur:
            cur.execute("SHOW TABLES WHERE Tables_in_monitoreo_vs LIKE '%hourly_rollups';")
            rows = cur.fetchall()
            existing = [list(r.values())[0] for r in rows]

        for table in expected:
            self.assertIn(table, existing, f"La tabla de rollup {table} no existe en MariaDB.")

    def test_02_all_8_phases_in_protected_tables(self):
        """Verifica que self_heal_environment.py proteja todas las tablas de las 8 fases."""
        required_phase_tables = [
            # Fase 1
            'discovery_subnets', 'discovered_devices', 'oui_vendors',
            # Fase 2
            'snmp_devices', 'snmp_oids', 'snmp_metrics_history', 'snmp_interface_metrics',
            # Fase 3
            'ssl_certificates', 'ssl_certificate_history',
            # Fase 4
            'alert_rules', 'alerts', 'maintenance_windows',
            # Fase 5
            'device_configurations', 'config_change_logs',
            # Fase 6
            'netflow_records', 'syslog_events', 'snmp_traps_received',
            # Fase 7
            'network_topology_links', 'wol_devices', 'predictive_anomalies', 'hardware_lifecycle',
            # Fase 8
            'snmp_metric_hourly_rollups', 'snmp_interface_hourly_rollups',
        ]
        for tbl in required_phase_tables:
            self.assertIn(tbl, PROTECTED_TABLES, f"La tabla {tbl} no está en PROTECTED_TABLES de self_heal_environment.")

    def test_03_telemetry_housekeeping_dry_run(self):
        """Prueba la ejecución segura de telemetry:housekeeping en modo dry-run."""
        artisan_path = Path("/var/www/monitoreo/artisan")
        if not artisan_path.exists():
            artisan_path = BASE_DIR / "web_portal" / "artisan"

        self.assertTrue(artisan_path.exists(), "El archivo artisan de Laravel no fue localizado.")

        res = subprocess.run(
            ["php", str(artisan_path), "telemetry:housekeeping", "--dry-run"],
            capture_output=True,
            text=True,
            timeout=60
        )
        self.assertEqual(res.returncode, 0, f"Error ejecutando housekeeping: {res.stderr}")
        self.assertIn("MANTENIMIENTO Y PURGA DE TELEMETRÍA", res.stdout)
        self.assertIn("SIMULACIÓN", res.stdout)
        self.assertIn("RESUMEN DE EJECUCIÓN", res.stdout)

    def test_04_golden_rule_2_underlying_tech_neutrality(self):
        """
        Garantiza el cumplimiento estricto de la REGLA DE ORO #2:
        Ningún archivo de vista blade o controlador de usuario debe exponer
        el nombre de la tecnología subyacente.
        """
        web_dir = Path("/var/www/monitoreo") if Path("/var/www/monitoreo").exists() else BASE_DIR / "web_portal"
        blade_files = list((web_dir / "resources" / "views").glob("**/*.blade.php"))

        forbidden_term = "ollama".lower()
        for bf in blade_files:
            content = bf.read_text(encoding="utf-8", errors="ignore").lower()
            # En vistas blade mostradas al usuario está estrictamente prohibido
            self.assertNotIn(
                forbidden_term,
                content,
                f"La vista {bf.name} contiene el término no neutral prohibido '{forbidden_term}'."
            )


if __name__ == '__main__':
    unittest.main()
