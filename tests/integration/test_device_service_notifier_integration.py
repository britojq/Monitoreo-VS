#!/usr/bin/env python3
"""
Tests de Integración para Alertas Selectivas de Servicios y Dispositivos
Ubicación: tests/integration/test_device_service_notifier_integration.py
Verifica:
1. Existencia de columnas en MariaDB (monitored_services, monitored_network_devices, monitored_site_devices).
2. Ciclo de vida DOWN -> UP en base de datos en tiempo real.
3. Cumplimiento estricto de la REGLA DE ORO #1 (bloqueo total de envíos a grupos en modo slave/desarrollo).
"""

import asyncio
from datetime import datetime
import os
import sys
import unittest
from pathlib import Path
from unittest.mock import AsyncMock

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_db import get_db_connection
from monitor.device_service_notifier import (
    get_notification_target,
    process_service_notifications,
    process_device_notifications,
)


class TestDeviceServiceNotifierIntegration(unittest.TestCase):
    def setUp(self):
        self.db = get_db_connection()

    def tearDown(self):
        try:
            self.db.close()
        except Exception:
            pass

    def test_01_database_columns_exist(self):
        """Verifica que las columnas requeridas existen en las 3 tablas de monitoreo."""
        required_cols = {
            "telegram_alert_enabled",
            "telegram_alert_target",
            "last_alert_state",
            "down_since",
        }
        tables = [
            "monitored_services",
            "monitored_network_devices",
            "monitored_site_devices",
        ]

        with self.db.cursor() as cur:
            for tbl in tables:
                cur.execute(f"DESCRIBE {tbl}")
                cols = {row["Field"] for row in cur.fetchall()}
                missing = required_cols - cols
                self.assertEqual(
                    len(missing),
                    0,
                    f"Faltan columnas en {tbl}: {missing}",
                )

    def test_02_golden_rule_1_target_in_slave_mode(self):
        """REGLA DE ORO #1: En nodo esclavo o dev, cualquier target a grupo debe redirigirse al Administrador."""
        target = get_notification_target("group")
        self.assertEqual(target, "38914901")

    def test_03_service_alert_flow(self):
        """Prueba ciclo completo de alerta de servicio DOWN y posterior recuperación UP."""
        async def _run():
            with self.db.cursor() as cur:
                cur.execute('DELETE FROM monitored_services WHERE name = "TEST_INTEG_SRV"')
                cur.execute('''
                    INSERT INTO monitored_services 
                    (letter, name, type, scope, host_ip, web_url, is_active, telegram_alert_enabled, telegram_alert_target, last_alert_state, created_at, updated_at)
                    VALUES ("TIS", "TEST_INTEG_SRV", "HTTP", "corporativo", "127.0.0.1", "http://127.0.0.1:8888", 1, 1, "group", "UP", NOW(), NOW())
                ''')
                cur.execute('SELECT id FROM monitored_services WHERE name = "TEST_INTEG_SRV"')
                sid = cur.fetchone()['id']
                self.db.commit()

            try:
                mock_disp = AsyncMock()
                mock_disp.send_text.return_value = (True, None)

                # 1. Simular caída
                results_down = [{'id': sid, 'is_up': False, 'http_code': '500 Internal Error'}]
                sent = await process_service_notifications(results_down, dispatcher=mock_disp)
                self.assertEqual(sent, 1)

                with self.db.cursor() as cur:
                    cur.execute('SELECT last_alert_state, down_since FROM monitored_services WHERE id = %s', (sid,))
                    row = cur.fetchone()
                    self.assertEqual(row['last_alert_state'], 'DOWN')
                    self.assertIsNotNone(row['down_since'])

                # 2. Simular scan continuo en caída (no debe haber spam)
                mock_disp.reset_mock()
                sent_dup = await process_service_notifications(results_down, dispatcher=mock_disp)
                self.assertEqual(sent_dup, 0)
                mock_disp.send_text.assert_not_called()

                # 3. Simular recuperación
                mock_disp.reset_mock()
                results_up = [{'id': sid, 'is_up': True, 'latency_ms': 18.2}]
                sent_rec = await process_service_notifications(results_up, dispatcher=mock_disp)
                self.assertEqual(sent_rec, 1)

                with self.db.cursor() as cur:
                    cur.execute('SELECT last_alert_state, down_since FROM monitored_services WHERE id = %s', (sid,))
                    row = cur.fetchone()
                    self.assertEqual(row['last_alert_state'], 'UP')
                    self.assertIsNone(row['down_since'])
            finally:
                with self.db.cursor() as cur:
                    cur.execute('DELETE FROM monitored_services WHERE id = %s', (sid,))
                    self.db.commit()

        asyncio.run(_run())

    def test_04_device_alert_flow(self):
        """Prueba ciclo completo de alerta de equipo de red DOWN y posterior recuperación UP."""
        async def _run():
            with self.db.cursor() as cur:
                cur.execute('DELETE FROM monitored_network_devices WHERE name = "TEST_INTEG_DEV"')
                cur.execute('''
                    INSERT INTO monitored_network_devices 
                    (device_number, name, ip, access_type, access_port, is_active, telegram_alert_enabled, telegram_alert_target, last_alert_state, created_at, updated_at)
                    VALUES (998, "TEST_INTEG_DEV", "192.0.2.201", "ICMP", NULL, 1, 1, "group", "UP", NOW(), NOW())
                ''')
                cur.execute('SELECT id FROM monitored_network_devices WHERE name = "TEST_INTEG_DEV"')
                did = cur.fetchone()['id']
                self.db.commit()

            try:
                mock_disp = AsyncMock()
                mock_disp.send_text.return_value = (True, None)

                # 1. Simular caída
                results_down = [{'id': did, 'is_up': False}]
                sent = await process_device_notifications(results_down, dispatcher=mock_disp)
                self.assertEqual(sent, 1)

                with self.db.cursor() as cur:
                    cur.execute('SELECT last_alert_state, down_since FROM monitored_network_devices WHERE id = %s', (did,))
                    row = cur.fetchone()
                    self.assertEqual(row['last_alert_state'], 'DOWN')
                    self.assertIsNotNone(row['down_since'])

                # 2. Simular scan continuo en caída (no spam)
                mock_disp.reset_mock()
                sent_dup = await process_device_notifications(results_down, dispatcher=mock_disp)
                self.assertEqual(sent_dup, 0)
                mock_disp.send_text.assert_not_called()

                # 3. Simular recuperación
                mock_disp.reset_mock()
                results_up = [{'id': did, 'is_up': True, 'latency_ms': 5.5}]
                sent_rec = await process_device_notifications(results_up, dispatcher=mock_disp)
                self.assertEqual(sent_rec, 1)

                with self.db.cursor() as cur:
                    cur.execute('SELECT last_alert_state, down_since FROM monitored_network_devices WHERE id = %s', (did,))
                    row = cur.fetchone()
                    self.assertEqual(row['last_alert_state'], 'UP')
                    self.assertIsNone(row['down_since'])
            finally:
                with self.db.cursor() as cur:
                    cur.execute('DELETE FROM monitored_network_devices WHERE id = %s', (did,))
                    self.db.commit()

        asyncio.run(_run())


if __name__ == "__main__":
    unittest.main()
