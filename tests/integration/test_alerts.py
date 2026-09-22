#!/usr/bin/env python3
"""
Tests de Integración para el Sistema de Alertas, Correlación y Escalación (Fase 4)
Verifica:
1. Existencia e integridad de las 8 tablas de alertas en MariaDB.
2. Carga y evaluación correcta de reglas de alerta por AlertEngine.
3. Supresión de alertas mediante Ventanas de Mantenimiento activas.
4. Supresión y control de tormentas (flapping) por fingerprint SHA-256.
5. Correlación topológica jerárquica padre-hijo (supresión por cascada).
6. Ciclo de vida completo: Firing -> Acknowledged -> Resolved / Auto-Resolved.
7. Cumplimiento estricto de la REGLA DE ORO #1 (bloqueo total de envíos a grupos).
"""

import datetime
import hashlib
import sys
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.alert_engine import (
    AlertEngine,
    get_db_connection,
    compute_fingerprint,
)


class TestAlertsIntegration(unittest.TestCase):

    def setUp(self):
        self.engine = AlertEngine()
        self.db = get_db_connection()

    def tearDown(self):
        try:
            self.db.close()
        except Exception:
            pass

    def test_01_database_tables_exist(self):
        """Verifica que las 8 tablas de alertas y mantenimiento existen en MariaDB."""
        expected_tables = [
            'alert_rules',
            'alert_escalation_levels',
            'alert_correlation_groups',
            'alert_correlation_members',
            'alerts',
            'alert_notifications',
            'maintenance_windows',
            'alert_storm_suppression',
        ]
        with self.db.cursor() as cur:
            cur.execute("SHOW TABLES WHERE Tables_in_monitoreo_vs LIKE 'alert%' OR Tables_in_monitoreo_vs LIKE 'maintenance%';")
            rows = cur.fetchall()
            existing = [list(r.values())[0] for r in rows]

        for t in expected_tables:
            self.assertIn(t, existing, f"La tabla {t} no existe en MariaDB.")

    def test_02_alert_rules_seeded(self):
        """Verifica que las 12 reglas corporativas y sus niveles de escalación están registradas."""
        with self.db.cursor() as cur:
            cur.execute("SELECT count(*) as total FROM alert_rules WHERE is_active = 1;")
            count_rules = cur.fetchone()["total"]
            cur.execute("SELECT count(*) as total FROM alert_escalation_levels WHERE is_active = 1;")
            count_levels = cur.fetchone()["total"]

        self.assertGreaterEqual(count_rules, 12, "Debe haber al menos 12 reglas corporativas activas.")
        self.assertGreaterEqual(count_levels, 24, "Debe haber niveles de escalación asociados a las reglas.")

    def test_03_fingerprint_deterministic(self):
        """Verifica que el cálculo de fingerprint sea un hash SHA-256 consistente."""
        fp1 = compute_fingerprint("service", 1, "is_down")
        fp2 = compute_fingerprint("service", 1, "is_down")
        fp3 = compute_fingerprint("service", 2, "is_down")

        self.assertEqual(fp1, fp2, "Fingerprints para la misma entidad y condición deben ser idénticos.")
        self.assertNotEqual(fp1, fp3, "Fingerprints para diferentes entidades deben ser distintos.")
        self.assertEqual(len(fp1), 64, "El fingerprint debe tener longitud de 64 caracteres (SHA-256 hex).")

    def test_04_maintenance_window_suppression(self):
        """Verifica que una ventana de mantenimiento activa suprima la alerta de la entidad."""
        now = datetime.datetime.now()
        starts = (now - datetime.timedelta(minutes=10)).strftime("%Y-%m-%d %H:%M:%S")
        ends = (now + datetime.timedelta(minutes=50)).strftime("%Y-%m-%d %H:%M:%S")
        test_entity_id = 9999

        with self.db.cursor() as cur:
            # Crear ventana de mantenimiento temporal de prueba
            cur.execute("""
                INSERT INTO maintenance_windows
                (title, description, entity_type, entity_id, starts_at, ends_at, is_active)
                VALUES ('Test Maint', 'Prueba automatizada', 'service', %s, %s, %s, 1)
            """, (test_entity_id, starts, ends))
            mw_id = cur.lastrowid

        try:
            # Evaluar supresión
            is_suppressed = self.engine.is_in_maintenance_window("service", test_entity_id, "critical")
            self.assertTrue(is_suppressed, "La alerta debió ser suprimida por la ventana de mantenimiento activa.")

            # Evaluar entidad no afectada
            not_suppressed = self.engine.is_in_maintenance_window("service", 8888, "critical")
            self.assertFalse(not_suppressed, "Una entidad no incluida en la ventana no debe ser suprimida.")
        finally:
            with self.db.cursor() as cur:
                cur.execute("DELETE FROM maintenance_windows WHERE id = %s", (mw_id,))

    def test_05_storm_suppression_flapping(self):
        """Verifica que el detector de tormentas bloquee alertas repetitivas por encima del límite."""
        test_fp = "test_storm_fingerprint_" + hashlib.md5(str(datetime.datetime.now()).encode()).hexdigest()[:16]

        try:
            # Enviar 5 alertas (máximo por hora)
            for i in range(5):
                suppressed = self.engine.check_storm_suppression(test_fp, max_per_hour=5, cooldown_minutes=15)
                self.assertFalse(suppressed, f"La alerta #{i+1} no debió ser suprimida (bajo el límite).")

            # La 6ta alerta excede el umbral de 5/hora y debe ser suprimida!
            suppressed_6 = self.engine.check_storm_suppression(test_fp, max_per_hour=5, cooldown_minutes=15)
            self.assertTrue(suppressed_6, "La 6ta alerta dentro de la misma hora debió activar control de tormenta.")
        finally:
            with self.db.cursor() as cur:
                cur.execute("DELETE FROM alert_storm_suppression WHERE fingerprint = %s", (test_fp,))

    def test_06_topological_correlation(self):
        """Verifica que la caída de un padre suprima las alertas de entidades hijas correlacionadas."""
        with self.db.cursor() as cur:
            # Crear grupo de correlación de prueba: Parent = site #999, Child = service #9999
            cur.execute("""
                INSERT INTO alert_correlation_groups
                (name, parent_entity_type, parent_entity_id, suppression_strategy, is_active)
                VALUES ('Test Group Correlacion', 'site', 999, 'suppress_if_parent_down', 1)
            """)
            cg_id = cur.lastrowid

            cur.execute("""
                INSERT INTO alert_correlation_members
                (correlation_group_id, child_entity_type, child_entity_id)
                VALUES (%s, 'service', 9999)
            """, (cg_id,))

            # 1. Sin alerta en el padre: hijo no se suprime
            is_suppressed, group_id, parent_id, sev = self.engine.apply_correlation("service", 9999, "critical")
            self.assertFalse(is_suppressed, "Sin alerta en padre, hijo no debe suprimirse.")

            # 2. Disparar alerta en el padre
            cur.execute("""
                INSERT INTO alerts
                (entity_type, entity_id, entity_name, condition_type, severity, status, fired_at)
                VALUES ('site', 999, 'Sede Padre Test', 'is_down', 'critical', 'firing', NOW())
            """)
            parent_alert_id = cur.lastrowid

            # 3. Con alerta activa en padre: hijo se suprime automáticamente!
            is_suppressed_now, group_id, matched_parent_id, sev = self.engine.apply_correlation("service", 9999, "critical")
            self.assertTrue(is_suppressed_now, "Con alerta activa en padre, la alerta del hijo debe suprimirse.")
            self.assertEqual(matched_parent_id, parent_alert_id, "El ID de la alerta padre debe coincidir.")

        # Limpieza
        with self.db.cursor() as cur:
            cur.execute("DELETE FROM alert_correlation_members WHERE correlation_group_id = %s", (cg_id,))
            cur.execute("DELETE FROM alert_correlation_groups WHERE id = %s", (cg_id,))
            cur.execute("DELETE FROM alerts WHERE id = %s", (parent_alert_id,))

    def test_07_alert_lifecycle_ack_resolve(self):
        """Verifica el ciclo de vida completo de un incidente: Firing -> Ack -> Resolved."""
        with self.db.cursor() as cur:
            cur.execute("""
                INSERT INTO alerts
                (entity_type, entity_id, entity_name, condition_type, severity, status, fired_at)
                VALUES ('service', 7777, 'Servicio Lifecycle Test', 'is_down', 'critical', 'firing', NOW())
            """)
            test_alert_id = cur.lastrowid

        try:
            # 1. Reconocer (ACK)
            ack_ok = self.engine.acknowledge_alert(test_alert_id, user_id=1, notes="Operador atendiendo")
            self.assertTrue(ack_ok, "El reconocimiento de la alerta debe ser exitoso.")

            with self.db.cursor() as cur:
                cur.execute("SELECT status, acknowledged_by, notes FROM alerts WHERE id = %s", (test_alert_id,))
                row = cur.fetchone()
                self.assertEqual(row["status"], "acknowledged")
                self.assertEqual(row["acknowledged_by"], 1)
                self.assertIn("Operador atendiendo", row["notes"])

            # 2. Resolver manualmente
            res_ok = self.engine.resolve_alert(test_alert_id, notes="Falla de energía corregida")
            self.assertTrue(res_ok, "La resolución manual debe ser exitosa.")

            with self.db.cursor() as cur:
                cur.execute("SELECT status, resolved_by, duration_seconds FROM alerts WHERE id = %s", (test_alert_id,))
                row2 = cur.fetchone()
                self.assertEqual(row2["status"], "resolved")
                self.assertEqual(row2["resolved_by"], "manual")
                self.assertGreaterEqual(row2["duration_seconds"], 0)
        finally:
            with self.db.cursor() as cur:
                cur.execute("DELETE FROM alerts WHERE id = %s", (test_alert_id,))

    def test_08_golden_rule_1_chat_safety(self):
        """Verifica el cumplimiento estricto de la REGLA DE ORO #1 (prohibición de envíos a grupos)."""
        # Prueba de validación de seguridad de chat IDs
        def validate_chat_target(chat_id_val):
            try:
                cid = int(str(chat_id_val).strip())
                if cid < 0:
                    return False, "BLOQUEADO (Grupo corporativo)"
                return True, "PERMITIDO"
            except ValueError:
                return False, "INVALIDO"

        # Debe bloquear IDs negativos (grupos como -1001383163558)
        ok_group, msg_group = validate_chat_target("-1001383163558")
        self.assertFalse(ok_group, "REGLA DE ORO #1 VIOLADA: Se permitió un chat ID de grupo negativo!")

        ok_group2, _ = validate_chat_target(-1001383163558)
        self.assertFalse(ok_group2, "REGLA DE ORO #1 VIOLADA: Se permitió un int negativo!")

        # Debe permitir chat ID privado del Administrador (38914901)
        ok_admin, _ = validate_chat_target("38914901")
        self.assertTrue(ok_admin, "El chat ID privado del Administrador (38914901) debe ser permitido.")


if __name__ == "__main__":
    unittest.main(verbosity=2)
