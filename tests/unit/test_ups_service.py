"""
Pruebas unitarias para monitor/ups_service.py
Verifica el cálculo de batería, generación de alertas por corte eléctrico,
recuperación con tiempo de corte y cumplimiento de la REGLA DE ORO #1.
"""

import asyncio
from datetime import datetime, timedelta
import os
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

from monitor.ups_service import (
    calculate_battery_percentage,
    format_duration,
    get_notification_target,
    build_ups_outage_alert,
    build_ups_recovery_alert,
    build_ups_battery_low_alert,
    poll_and_process_ups,
)


class TestUpsService:
    def test_calculate_battery_percentage(self):
        assert calculate_battery_percentage(2.25) == 100
        assert calculate_battery_percentage(2.23) == 100
        assert calculate_battery_percentage(2.00) == 50
        assert calculate_battery_percentage(1.75) == 0
        assert calculate_battery_percentage(1.60) == 0

    def test_format_duration(self):
        assert format_duration(30) == "30 seg"
        assert format_duration(60) == "1 min"
        assert format_duration(95) == "1 min 35 seg"
        assert format_duration(3600) == "1 h 0 min"

    def test_get_notification_target_rule_1(self):
        """REGLA DE ORO #1: En modo esclavo, dev o test, el target grupal se redirige al Administrador."""
        with patch.dict(os.environ, {"TESTING": "1"}):
            target = get_notification_target("group")
            assert target == "38914901"

    def test_build_ups_outage_alert(self):
        device = {"name": "UPS ZTG LV6KL", "model": "6kVA"}
        telemetry = {
            "battery_percent": 95,
            "battery_voltage": 2.22,
            "load_percent": 12,
            "temperature_c": 44.5,
        }
        msg = build_ups_outage_alert(device, telemetry)
        assert "CORTE ELÉCTRICO / APAGÓN DETECTADO" in msg
        assert "MODO BATERÍA" in msg
        assert "95%" in msg
        assert "12%" in msg
        assert "44.5 °C" in msg

    def test_build_ups_recovery_alert(self):
        device = {"name": "UPS ZTG LV6KL"}
        telemetry = {
            "input_voltage": 222.5,
            "output_voltage": 208.0,
            "frequency": 60.0,
            "battery_percent": 90,
        }
        msg = build_ups_recovery_alert(device, telemetry, "18 min 40 seg")
        assert "FLUIDO ELÉCTRICO RESTABLECIDO" in msg
        assert "222.5 VAC" in msg
        assert "18 min 40 seg" in msg
        assert "90%" in msg

    @pytest.mark.anyio
    async def test_poll_and_process_ups_transitions(self):
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        device_row = {
            "id": 1,
            "name": "UPS ZTG LV6KL",
            "model": "6kVA",
            "serial_port": "/dev/ttyS0",
            "baud_rate": 2400,
            "telegram_alert_enabled": 1,
            "telegram_alert_target": "owner",
            "last_alert_state": "NORMAL",
            "outage_since": None,
        }
        mock_cursor.fetchone.return_value = device_row

        # Simular que el UPS reporta corte de red (is_on_battery = True)
        mock_telemetry = {
            "input_voltage": 0.0,
            "input_fault_voltage": 0.0,
            "output_voltage": 208.0,
            "load_percent": 8,
            "frequency": 60.0,
            "battery_voltage": 2.20,
            "battery_percent": 90,
            "temperature_c": 43.0,
            "is_online": True,
            "is_on_battery": True,
            "is_battery_low": False,
            "is_bypass": False,
            "is_ups_failed": False,
            "beeper_on": True,
        }

        with patch("monitor.ups_service.get_db_connection", return_value=mock_conn):
            with patch("monitor.ups_service.query_ups_serial", return_value=mock_telemetry):
                res = await poll_and_process_ups(dispatcher=mock_dispatcher)

        assert res is not None
        mock_dispatcher.send_text.assert_called_once()
        call_args = mock_dispatcher.send_text.call_args[0]
        assert call_args[0] == "38914901"
        assert "CORTE ELÉCTRICO / APAGÓN DETECTADO" in call_args[1]
        mock_conn.commit.assert_called()

    @pytest.mark.anyio
    async def test_cmd_ups(self):
        import bot
        mock_update = MagicMock()
        mock_context = MagicMock()
        mock_update.message = MagicMock()

        replies = []
        async def mock_reply(msg, text, **kwargs):
            replies.append(text)

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        device_row = {
            "id": 1,
            "name": "UPS ZTG LV6KL - Sede Valle Seco",
            "model": "ZTG LV6KL 6kVA",
            "input_voltage": 224.8,
            "output_voltage": 207.8,
            "frequency": 60.0,
            "load_percent": 8,
            "battery_percent": 100,
            "battery_voltage": 2.25,
            "temperature_c": 43.0,
            "is_online": 1,
            "is_on_battery": 0,
            "telegram_alert_enabled": 1,
            "telegram_alert_target": "owner",
            "last_seen_at": "2026-09-28 11:30:00",
        }
        mock_cursor.fetchone.return_value = device_row

        with patch("monitor.monitor_db.get_db_connection", return_value=mock_conn):
            with patch.object(bot, "safe_reply_html", side_effect=mock_reply):
                await bot.cmd_ups(mock_update, mock_context)

        assert len(replies) == 1
        assert "ESTADO DE ENERGÍA Y RESPALDO ELÉCTRICO" in replies[0]
        assert "224.8 VAC" in replies[0]
        assert "207.8 VAC" in replies[0]
        assert "100%" in replies[0]
        assert "8%" in replies[0]

