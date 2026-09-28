"""
Pruebas unitarias para monitor/device_service_notifier.py
Verifica el comportamiento de alertas selectivas para servicios y dispositivos,
formato de mensajes y cumplimiento estricto de la REGLA DE ORO #1.
"""

import asyncio
from datetime import datetime, timedelta
import os
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

from monitor.device_service_notifier import (
    get_notification_target,
    format_duration,
    build_service_alert_message,
    build_device_alert_message,
    process_service_notifications,
    process_device_notifications,
    evaluate_and_notify_all,
)


class TestDeviceServiceNotifier:
    def test_format_duration(self):
        assert format_duration(0) == "0 seg"
        assert format_duration(45) == "45 seg"
        assert format_duration(60) == "1 min"
        assert format_duration(125) == "2 min 5 seg"
        assert format_duration(3600) == "1 h 0 min"
        assert format_duration(3665) == "1 h 1 min"
        assert format_duration(-10) == "0 seg"

    def test_get_notification_target_owner(self):
        """Verifica que el target 'owner' siempre retorne el ID del administrador."""
        target = get_notification_target("owner")
        assert target == "38914901"

    def test_get_notification_target_group_redirects_in_dev_or_slave(self):
        """REGLA DE ORO #1: En modo slave, testing o dev, jamás debe enviar a grupo."""
        with patch.dict(os.environ, {"TESTING": "1"}):
            target = get_notification_target("group")
            assert target == "38914901"

        with patch.dict(os.environ, {"APP_ENV": "local", "TESTING": ""}, clear=False):
            target = get_notification_target("group")
            assert target == "38914901"

    def test_get_notification_target_group_master(self):
        """En master de producción, el target 'group' debe retornar el ID del grupo corporativo."""
        with patch.dict(os.environ, {"TESTING": "", "APP_ENV": "production"}):
            with patch("pathlib.Path.exists", return_value=True):
                with patch(
                    "pathlib.Path.read_text",
                    return_value='{"owner_id": "38914901", "allowed_group_ids": ["-1001383163558"], "node_role": "master"}'
                ):
                    target = get_notification_target("group")
                    assert target == "-1001383163558"

    def test_build_service_alert_message_down(self):
        service = {
            "name": "Portal Web <Test>",
            "web_url": "https://example.com/app",
            "type": "HTTP",
            "http_code": "500 Internal Error",
        }
        msg = build_service_alert_message(service, is_down=True)
        assert "ALERTA: SERVICIO CAÍDO" in msg
        assert "Portal Web &lt;Test&gt;" in msg
        assert "https://example.com/app" in msg
        assert "500 Internal Error" in msg

    def test_build_service_alert_message_up(self):
        service = {
            "name": "Portal Web",
            "web_url": "https://example.com/app",
            "type": "HTTP",
            "latency_ms": 15.4,
        }
        msg = build_service_alert_message(service, is_down=False, duration_str="5 min 30 seg")
        assert "RECUPERACIÓN: SERVICIO OPERATIVO" in msg
        assert "Portal Web" in msg
        assert "15.4 ms" in msg
        assert "5 min 30 seg" in msg

    def test_build_device_alert_message_down(self):
        device = {
            "name": "Switch Core <1>",
            "ip": "10.20.23.1",
            "access_type": "SSH",
            "access_port": 22,
            "site_name": "Sede Principal",
        }
        msg = build_device_alert_message(device, is_down=True)
        assert "ALERTA: EQUIPO DESCONECTADO" in msg
        assert "Switch Core &lt;1&gt;" in msg
        assert "10.20.23.1" in msg
        assert "SSH (Puerto 22)" in msg
        assert "Sede Principal" in msg

    def test_build_device_alert_message_up(self):
        device = {
            "name": "Switch Core",
            "ip": "10.20.23.1",
            "access_type": "ICMP",
            "site_name": "Sede Principal",
            "latency_ms": 1.2,
        }
        msg = build_device_alert_message(device, is_down=False, duration_str="12 min")
        assert "RECUPERACIÓN: EQUIPO RESTABLECIDO" in msg
        assert "Switch Core" in msg
        assert "1.2 ms" in msg
        assert "12 min" in msg

    @pytest.mark.anyio
    async def test_process_service_notifications_transitions(self):
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        # Mock monitored_services en BD: 1 servicio con alerta activa
        db_services = [
            {
                "id": 10,
                "name": "API Externa",
                "type": "HTTP",
                "web_url": "https://api.test",
                "host_ip": "10.0.0.1",
                "telegram_alert_enabled": 1,
                "telegram_alert_target": "owner",
                "last_alert_state": "UP",
                "down_since": None,
            }
        ]
        mock_cursor.fetchall.return_value = db_services

        # Simular que el scan detecta que cayó (is_up = False)
        scan_results = [{"id": 10, "is_up": False, "http_code": "503"}]

        with patch("monitor.device_service_notifier.get_db_connection", return_value=mock_conn):
            sent = await process_service_notifications(scan_results, dispatcher=mock_dispatcher)

        assert sent == 1
        mock_dispatcher.send_text.assert_called_once()
        call_args = mock_dispatcher.send_text.call_args[0]
        assert call_args[0] == "38914901"
        assert "ALERTA: SERVICIO CAÍDO" in call_args[1]

        # Verificar que se actualizó el estado a DOWN en la BD
        mock_cursor.execute.assert_called()
        mock_conn.commit.assert_called_once()

    @pytest.mark.anyio
    async def test_process_device_notifications_recovery(self):
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        # Mock monitored_network_devices en BD: 1 equipo que estaba DOWN
        down_time = datetime.now() - timedelta(minutes=15)
        db_devices = [
            {
                "id": 5,
                "name": "Router Borde",
                "ip": "10.20.23.254",
                "access_type": "ICMP",
                "access_port": None,
                "telegram_alert_enabled": 1,
                "telegram_alert_target": "owner",
                "last_alert_state": "DOWN",
                "down_since": down_time,
                "site_name": "Sede Central",
            }
        ]
        mock_cursor.fetchall.return_value = db_devices

        # Simular que el scan detecta que se recuperó (is_up = True)
        scan_results = [{"id": 5, "is_up": True, "latency_ms": 2.5}]

        with patch("monitor.device_service_notifier.get_db_connection", return_value=mock_conn):
            sent = await process_device_notifications(scan_results, dispatcher=mock_dispatcher)

        assert sent == 1
        mock_dispatcher.send_text.assert_called_once()
        call_args = mock_dispatcher.send_text.call_args[0]
        assert call_args[0] == "38914901"
        assert "RECUPERACIÓN: EQUIPO RESTABLECIDO" in call_args[1]
        assert "15 min" in call_args[1]

    @pytest.mark.anyio
    async def test_evaluate_and_notify_all(self):
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        with patch("monitor.device_service_notifier.process_service_notifications", new_callable=AsyncMock) as m_srv:
            with patch("monitor.device_service_notifier.process_device_notifications", new_callable=AsyncMock) as m_dev:
                m_srv.return_value = 2
                m_dev.return_value = 1
                s_res, d_res = await evaluate_and_notify_all([], [], dispatcher=mock_dispatcher)
                assert s_res == 2
                assert d_res == 1
                m_srv.assert_called_once()
                m_dev.assert_called_once()
