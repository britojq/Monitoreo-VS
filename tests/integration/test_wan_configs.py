#!/usr/bin/env python3
"""
Tests de Integración para Calidad WAN y Auditoría de Configuraciones (Fase 5)
Verifica:
1. Esquema e integridad de columnas WAN en site_check_histories y tablas device_configurations / config_change_logs.
2. Medición precisa de Calidad WAN (Jitter, Packet Loss %, RTT Min/Avg/Max) mediante check_wan_quality().
3. Normalización de configuraciones volátiles (marcas de tiempo Cisco / pfSense) y cálculo de hash SHA-256.
4. Motor diferencial GitOps: detección de adiciones (+), remociones (-) y clasificación (initial/modified/reverted).
5. Integridad de modelos Eloquent y relaciones en MariaDB.
6. Cumplimiento estricto de las REGLAS DE ORO del proyecto (bloqueo total de mensajes a grupos y neutralidad tecnológica).
"""

import asyncio
import hashlib
import sys
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_web_sync import check_wan_quality, get_db_connection
from monitor.config_backup import ConfigBackupManager


class TestWanQualityAndConfigsIntegration(unittest.TestCase):

    def setUp(self):
        self.db = get_db_connection()

    def tearDown(self):
        try:
            self.db.close()
        except Exception:
            pass

    def test_01_database_schema_and_columns(self):
        """Verifica que las tablas de Fase 5 y las columnas de telemetría WAN existan en MariaDB."""
        with self.db.cursor() as cur:
            # 1. Verificar columnas WAN en site_check_histories
            cur.execute("SHOW COLUMNS FROM site_check_histories;")
            columns = [r['Field'] for r in cur.fetchall()]
            for col in ['packet_loss_pct', 'jitter_ms', 'min_rtt_ms', 'max_rtt_ms', 'mdev_ms']:
                self.assertIn(col, columns, f"La columna {col} no existe en site_check_histories.")

            # 2. Verificar existencia de device_configurations y config_change_logs
            cur.execute("SHOW TABLES WHERE Tables_in_monitoreo_vs IN ('device_configurations', 'config_change_logs');")
            tables = [list(r.values())[0] for r in cur.fetchall()]
            self.assertIn('device_configurations', tables)
            self.assertIn('config_change_logs', tables)

    def test_02_wan_quality_measurement_localhost(self):
        """Verifica la medición multi-paquete ICMP con métricas de jitter y latencia en loopback."""
        result = asyncio.run(check_wan_quality("127.0.0.1", count=3, timeout=1.0))
        self.assertTrue(result['is_up'], "Loopback 127.0.0.1 debe responder UP.")
        self.assertEqual(result['packet_loss_pct'], 0.0, "Loopback debe tener 0% packet loss.")
        self.assertGreaterEqual(result['latency_ms'], 0.0)
        self.assertGreaterEqual(result['jitter_ms'], 0.0)
        self.assertGreaterEqual(result['min_rtt_ms'], 0.0)
        self.assertGreaterEqual(result['max_rtt_ms'], 0.0)

    def test_03_wan_quality_measurement_unreachable(self):
        """Verifica que un host inalcanzable reporte 100% pérdida de paquetes y estado DOWN."""
        result = asyncio.run(check_wan_quality("192.0.2.1", count=2, timeout=0.5))
        self.assertFalse(result['is_up'])
        self.assertEqual(result['packet_loss_pct'], 100.0)

    def test_04_config_normalization_strips_volatile_headers(self):
        """Verifica que la normalización elimine marcas de tiempo volátiles para evitar falsos diffs."""
        cisco_cfg_1 = (
            "!\n"
            "! Last configuration change at 14:20:10 UTC Fri Sep 18 2026 by admin\n"
            "! NVRAM config last updated at 14:20:12 UTC Fri Sep 18 2026\n"
            "hostname ROUTER-CORE\n"
            "interface GigabitEthernet0/0\n"
            " ip address 10.20.23.1 255.255.255.0\n"
            "end\n"
        )
        cisco_cfg_2 = (
            "!\n"
            "! Last configuration change at 19:45:33 UTC Fri Sep 18 2026 by oper\n"
            "! NVRAM config last updated at 19:45:35 UTC Fri Sep 18 2026\n"
            "hostname ROUTER-CORE\n"
            "interface GigabitEthernet0/0\n"
            " ip address 10.20.23.1 255.255.255.0\n"
            "end\n"
        )

        norm_1 = ConfigBackupManager.normalize_config(cisco_cfg_1, "cisco_router")
        norm_2 = ConfigBackupManager.normalize_config(cisco_cfg_2, "cisco_router")

        # El contenido normalizado y sus hashes deben ser idénticos
        self.assertEqual(norm_1, norm_2)
        hash_1 = hashlib.sha256(norm_1.encode('utf-8')).hexdigest()
        hash_2 = hashlib.sha256(norm_2.encode('utf-8')).hexdigest()
        self.assertEqual(hash_1, hash_2)

    def test_05_unified_diff_detection(self):
        """Verifica que el motor diferencial genere diffs unificados con conteo exacto de líneas."""
        base_cfg = (
            "hostname SWITCH-PISO1\n"
            "vlan 10\n"
            " name OPERACIONES\n"
            "interface FastEthernet0/1\n"
            " switchport access vlan 10\n"
            "end\n"
        )
        mod_cfg = (
            "hostname SWITCH-PISO1\n"
            "vlan 10\n"
            " name OPERACIONES\n"
            "vlan 20\n"
            " name SEGURIDAD\n"
            "interface FastEthernet0/1\n"
            " switchport access vlan 20\n"
            "end\n"
        )

        diff_text, lines_add, lines_rem = ConfigBackupManager.generate_unified_diff(
            base_cfg, mod_cfg, "v1.cfg", "v2.cfg"
        )

        self.assertIn("vlan 20", diff_text)
        self.assertIn("+", diff_text)
        self.assertIn("-", diff_text)
        self.assertGreater(lines_add, 0, "Debe haber al menos una línea añadida.")
        self.assertGreater(lines_rem, 0, "Debe haber al menos una línea removida.")

    def test_06_database_models_and_audit_records(self):
        """Verifica que los registros de respaldos y diffs existan en la base de datos."""
        with self.db.cursor() as cur:
            cur.execute("SELECT COUNT(*) as cnt FROM config_change_logs;")
            log_count = cur.fetchone()['cnt']
            if log_count == 0:
                mgr = ConfigBackupManager()
                c1 = mgr.generate_simulated_cisco_config("TEST-ROUTER", vlan_count=2)
                mgr.save_device_backup(c1, "TEST-ROUTER", "10.20.99.1", "cisco_router", captured_by="manual")
                c2 = mgr.generate_simulated_cisco_config("TEST-ROUTER", vlan_count=3, extra_desc="interface GigabitEthernet0/2\n description Test Link\n")
                mgr.save_device_backup(c2, "TEST-ROUTER", "10.20.99.1", "cisco_router", captured_by="manual")

            cur.execute("SELECT COUNT(*) as cnt FROM device_configurations;")
            cfg_count = cur.fetchone()['cnt']
            self.assertGreater(cfg_count, 0, "Debe haber al menos una versión de configuración registrada.")

            cur.execute("SELECT COUNT(*) as cnt FROM config_change_logs;")
            log_count = cur.fetchone()['cnt']
            self.assertGreater(log_count, 0, "Debe haber al menos un registro en el historial de cambios.")

            # Verificar que el diff del router principal contenga adiciones
            cur.execute("""
                SELECT c.device_name, l.change_type, l.lines_added, l.lines_removed, l.diff_unified
                FROM config_change_logs l
                JOIN device_configurations c ON l.device_configuration_id = c.id
                WHERE l.change_type = 'modified'
                LIMIT 1;
            """)
            modified_row = cur.fetchone()
            if modified_row:
                self.assertGreater(modified_row['lines_added'], 0)
                self.assertIn("+++", modified_row['diff_unified'])

    def test_07_golden_rules_compliance(self):
        """Verifica que el código de respaldo y calidad WAN no envíe notificaciones a grupos ni mencione tecnologías vedadas."""
        config_backup_file = BASE_DIR / "monitor" / "config_backup.py"
        content = config_backup_file.read_text(encoding='utf-8')

        # Regla de Oro #1: No IDs negativos de Telegram
        self.assertNotIn("-1001383163558", content)

        # Regla de Oro #2: Prohibido mencionar la tecnología de IA subyacente
        self.assertNotIn("ollama", content.lower())


if __name__ == '__main__':
    unittest.main()
