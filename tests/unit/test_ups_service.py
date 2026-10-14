"""
Pruebas unitarias para monitor/ups_service.py
Verifica el cálculo de batería, generación de alertas por corte eléctrico,
recuperación con tiempo de corte y cumplimiento de la REGLA DE ORO #1.
"""

import asyncio
from datetime import datetime, timedelta
import json
import os
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

from monitor.ups_service import (
    calculate_battery_percentage,
    format_duration,
    get_notification_target,
    get_notification_targets,
    build_ups_outage_alert,
    build_ups_recovery_alert,
    build_ups_fluctuation_alert,
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

    def test_get_notification_targets_dual_dispatch_prod(self):
        """En entorno productivo (nodo master), la opción group despacha a ambos: Owner y Grupo."""
        mock_cfg = json.dumps({
            "owner_id": "38914901",
            "allowed_group_ids": ["-1001383163558"],
            "node_role": "master",
        })
        with patch("pathlib.Path.read_text", return_value=mock_cfg):
            with patch.dict(os.environ, {"TESTING": "0", "APP_ENV": "production"}):
                targets = get_notification_targets("group")
                assert "38914901" in targets
                assert "-1001383163558" in targets
                assert len(targets) == 2

    def test_get_notification_targets_safety_filter(self):
        """En entorno no productivo (ej. testing), la opción group se restringe únicamente al Owner."""
        with patch.dict(os.environ, {"TESTING": "1"}):
            targets = get_notification_targets("group")
            assert targets == ["38914901"]

    def test_get_notification_targets_owner_only(self):
        """Cuando la opción es 'owner', despacha únicamente al Administrador Privado."""
        targets = get_notification_targets("owner")
        assert targets == ["38914901"]

    def test_build_ups_outage_alert(self):
        device = {"name": "UPS ZTG LV6KL", "model": "6kVA"}
        telemetry = {
            "battery_percent": 95,
            "battery_voltage": 2.22,
            "load_percent": 12,
            "temperature_c": 44.5,
        }
        msg = build_ups_outage_alert(device, telemetry, elapsed_str="50 seg")
        assert "FALLA EN SERVICIO ELÉCTRICO CONFIRMADA" in msg
        assert "MÁS DE 45 SEG EN MODO BATERÍA" in msg
        assert "95%" in msg
        assert "12%" in msg
        assert "44.5 °C" in msg

    def test_build_ups_fluctuation_alert(self):
        device = {"name": "UPS ZTG LV6KL"}
        telemetry = {
            "input_voltage": 220.0,
            "output_voltage": 208.0,
        }
        msg = build_ups_fluctuation_alert(device, telemetry, duration_str="9 seg")
        assert "MICRO-FLUCTUACIÓN DE VOLTAJE ABSORBIDA" in msg
        assert "9 seg" in msg
        assert "≤ 45 seg" in msg

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
    async def test_poll_and_process_ups_debounce_initial_cycle(self):
        """Primer ciclo en batería (<45s): registra tiempo de inicio pero NO alerta al grupo."""
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
            "group_alert_sent": 0,
        }
        mock_cursor.fetchone.return_value = device_row

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
        # En el primer ciclo (<45s) no se envían mensajes aún
        mock_dispatcher.send_text.assert_not_called()
        mock_conn.commit.assert_called()

    @pytest.mark.anyio
    async def test_poll_and_process_ups_confirmed_outage(self):
        """Ciclo en batería que supera 45s: dispara la alerta confirmada de falla eléctrica."""
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        outage_start = datetime.now() - timedelta(seconds=50)
        device_row = {
            "id": 1,
            "name": "UPS ZTG LV6KL",
            "model": "6kVA",
            "serial_port": "/dev/ttyS0",
            "baud_rate": 2400,
            "telegram_alert_enabled": 1,
            "telegram_alert_target": "owner",
            "last_alert_state": "ON_BATTERY",
            "outage_since": outage_start,
            "group_alert_sent": 0,
        }
        mock_cursor.fetchone.return_value = device_row

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
        assert "FALLA EN SERVICIO ELÉCTRICO CONFIRMADA" in call_args[1]
        mock_conn.commit.assert_called()

    @pytest.mark.anyio
    async def test_poll_and_process_ups_micro_fluctuation_recovery(self):
        """Recuperación <= 45s: notifica micro-fluctuación exclusivamente al Owner, CERO al grupo."""
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        outage_start = datetime.now() - timedelta(seconds=9)
        device_row = {
            "id": 1,
            "name": "UPS ZTG LV6KL",
            "model": "6kVA",
            "serial_port": "/dev/ttyS0",
            "baud_rate": 2400,
            "telegram_alert_enabled": 1,
            "telegram_alert_target": "group",
            "last_alert_state": "ON_BATTERY",
            "outage_since": outage_start,
            "group_alert_sent": 0,
        }
        mock_cursor.fetchone.return_value = device_row

        mock_telemetry = {
            "input_voltage": 220.0,
            "input_fault_voltage": 220.0,
            "output_voltage": 208.0,
            "load_percent": 8,
            "frequency": 60.0,
            "battery_voltage": 2.25,
            "battery_percent": 100,
            "temperature_c": 43.0,
            "is_online": True,
            "is_on_battery": False,
            "is_battery_low": False,
            "is_bypass": False,
            "is_ups_failed": False,
            "beeper_on": False,
        }

        mock_cfg = json.dumps({
            "owner_id": "38914901",
            "allowed_group_ids": ["-1001383163558"],
            "node_role": "master",
        })
        with patch("pathlib.Path.read_text", return_value=mock_cfg):
            with patch.dict(os.environ, {"TESTING": "0", "APP_ENV": "production"}):
                with patch("monitor.ups_service.get_db_connection", return_value=mock_conn):
                    with patch("monitor.ups_service.query_ups_serial", return_value=mock_telemetry):
                        res = await poll_and_process_ups(dispatcher=mock_dispatcher)

        assert res is not None
        # Micro-fluctuación se envía ÚNICAMENTE al Owner (1 llamada), jamás al grupo
        assert mock_dispatcher.send_text.call_count == 1
        call_args = mock_dispatcher.send_text.call_args[0]
        assert call_args[0] == "38914901"
        assert "MICRO-FLUCTUACIÓN DE VOLTAJE ABSORBIDA" in call_args[1]

    @pytest.mark.anyio
    async def test_poll_and_process_ups_dual_dispatch_on_outage(self):
        """Verifica que con target='group' en producción al superar 45s se envíe a Owner y a Grupo."""
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        outage_start = datetime.now() - timedelta(seconds=55)
        device_row = {
            "id": 1,
            "name": "UPS ZTG LV6KL",
            "model": "6kVA",
            "serial_port": "/dev/ttyS0",
            "baud_rate": 2400,
            "telegram_alert_enabled": 1,
            "telegram_alert_target": "group",
            "last_alert_state": "ON_BATTERY",
            "outage_since": outage_start,
            "group_alert_sent": 0,
        }
        mock_cursor.fetchone.return_value = device_row

        mock_telemetry = {
            "input_voltage": 0.0,
            "input_fault_voltage": 0.0,
            "output_voltage": 208.0,
            "load_percent": 10,
            "frequency": 60.0,
            "battery_voltage": 2.22,
            "battery_percent": 95,
            "temperature_c": 42.0,
            "is_online": True,
            "is_on_battery": True,
            "is_battery_low": False,
            "is_bypass": False,
            "is_ups_failed": False,
            "beeper_on": True,
        }

        mock_cfg = json.dumps({
            "owner_id": "38914901",
            "allowed_group_ids": ["-1001383163558"],
            "node_role": "master",
        })
        with patch("pathlib.Path.read_text", return_value=mock_cfg):
            with patch.dict(os.environ, {"TESTING": "0", "APP_ENV": "production"}):
                with patch("monitor.ups_service.get_db_connection", return_value=mock_conn):
                    with patch("monitor.ups_service.query_ups_serial", return_value=mock_telemetry):
                        res = await poll_and_process_ups(dispatcher=mock_dispatcher)

        assert res is not None
        # En producción con target='group' y >45s, debe llamarse 2 veces: Owner + Grupo
        assert mock_dispatcher.send_text.call_count == 2
        calls = [c[0][0] for c in mock_dispatcher.send_text.call_args_list]
        assert "38914901" in calls
        assert "-1001383163558" in calls


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

    def test_query_ups_info_parsing(self):
        from monitor.ups_service import query_ups_info
        mock_ser = MagicMock()
        mock_ser.read.return_value = b"#ZTG LV6KL R1.01.55\r"

        with patch("serial.Serial", return_value=mock_ser):
            with patch("pathlib.Path.exists", return_value=True):
                res = query_ups_info("/dev/ttyS0", 2400)
                assert res is not None
                assert res["brand"] == "ZTG"
                assert "LV6KL" in res["model"]
                assert res["firmware_version"] == "R1.01.55"

    def test_query_ups_rating_parsing(self):
        from monitor.ups_service import query_ups_rating
        mock_ser = MagicMock()
        mock_ser.read.return_value = b"#208.0 028 192.0 60.0\r"

        with patch("serial.Serial", return_value=mock_ser):
            with patch("pathlib.Path.exists", return_value=True):
                res = query_ups_rating("/dev/ttyS0", 2400)
                assert res is not None
                assert res["rating_voltage"] == 208.0
                assert res["rating_current"] == 28.0
                assert res["rating_battery_voltage"] == 192.0
                assert res["rating_frequency"] == 60.0

    def test_query_ups_serial_clean_and_noise_sanitization(self):
        from monitor.ups_service import query_ups_serial
        mock_ser = MagicMock()
        # Simula trama con delimitador $ por ruido RS-232 y \r al final
        mock_ser.read_until.return_value = b"(225.0$000.0 208.0 008 59.8 2.25 41.0 00000001\r"

        with patch("serial.Serial", return_value=mock_ser):
            with patch("pathlib.Path.exists", return_value=True):
                res = query_ups_serial("/dev/ttyS0", 2400)
                assert res is not None
                assert res["input_voltage"] == 225.0
                assert res["output_voltage"] == 208.0
                assert res["is_on_battery"] is False

    def test_query_ups_serial_physical_consistency_filter(self):
        from monitor.ups_service import query_ups_serial
        mock_ser = MagicMock()
        # Simula bit de batería invertido a '1' por ruido mientras entrada comercial es 224.8V
        mock_ser.read_until.return_value = b"(224.8 000.0 208.0 008 59.8 2.25 41.0 10000001\r"

        with patch("serial.Serial", return_value=mock_ser):
            with patch("pathlib.Path.exists", return_value=True):
                res = query_ups_serial("/dev/ttyS0", 2400)
                assert res is not None
                assert res["input_voltage"] == 224.8
                # Debe suprimir el falso positivo y forzar is_on_battery a False
                assert res["is_on_battery"] is False

    def test_query_ups_serial_discard_anomalous_voltages(self):
        from monitor.ups_service import query_ups_serial
        mock_ser = MagicMock()
        # Simula trama con voltaje corrupto 2251.0V
        mock_ser.read_until.return_value = b"(2251.0 000.0 208.0 008 59.8 2.25 41.0 00000001\r"

        with patch("serial.Serial", return_value=mock_ser):
            with patch("pathlib.Path.exists", return_value=True):
                res = query_ups_serial("/dev/ttyS0", 2400)
                assert res is None

    @pytest.mark.anyio
    async def test_poll_and_process_ups_suppress_spurious_transient_under_8s(self):
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        outage_start = datetime.now() - timedelta(seconds=6)  # 6s: transitorio de 1 ciclo
        device_row = {
            "id": 1,
            "name": "UPS ZTG LV6KL",
            "model": "6kVA",
            "serial_port": "/dev/ttyS0",
            "baud_rate": 2400,
            "telegram_alert_enabled": 1,
            "telegram_alert_target": "owner",
            "last_alert_state": "ON_BATTERY",
            "outage_since": outage_start,
            "group_alert_sent": 0,
        }
        mock_cursor.fetchone.return_value = device_row
        mock_telemetry = {
            "input_voltage": 224.0,
            "input_fault_voltage": 224.0,
            "output_voltage": 208.0,
            "load_percent": 8,
            "frequency": 60.0,
            "battery_voltage": 2.25,
            "battery_percent": 100,
            "temperature_c": 41.0,
            "is_online": True,
            "is_on_battery": False,
            "is_battery_low": False,
            "is_bypass": False,
            "is_ups_failed": False,
            "beeper_on": False,
        }

        with patch("monitor.ups_service.get_db_connection", return_value=mock_conn):
            with patch("monitor.ups_service.query_ups_serial", return_value=mock_telemetry):
                res = await poll_and_process_ups(dispatcher=mock_dispatcher)

        assert res is not None
        # Transitorio espurio (< 8s) se registra en BD pero NO despacha alerta a Telegram
        mock_dispatcher.send_text.assert_not_called()

    @pytest.mark.anyio
    async def test_poll_and_process_ups_suppress_when_notify_micro_is_false(self):
        mock_dispatcher = MagicMock()
        mock_dispatcher.send_text = AsyncMock(return_value=(True, None))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        outage_start = datetime.now() - timedelta(seconds=15)
        device_row = {
            "id": 1,
            "name": "UPS ZTG LV6KL",
            "model": "6kVA",
            "serial_port": "/dev/ttyS0",
            "baud_rate": 2400,
            "telegram_alert_enabled": 1,
            "telegram_alert_target": "owner",
            "last_alert_state": "ON_BATTERY",
            "outage_since": outage_start,
            "group_alert_sent": 0,
        }
        mock_cursor.fetchone.return_value = device_row
        mock_telemetry = {
            "input_voltage": 224.0,
            "input_fault_voltage": 224.0,
            "output_voltage": 208.0,
            "load_percent": 8,
            "frequency": 60.0,
            "battery_voltage": 2.25,
            "battery_percent": 100,
            "temperature_c": 41.0,
            "is_online": True,
            "is_on_battery": False,
            "is_battery_low": False,
            "is_bypass": False,
            "is_ups_failed": False,
            "beeper_on": False,
        }
        mock_cfg = json.dumps({
            "owner_id": "38914901",
            "ups_notify_micro_fluctuations": False,
        })

        with patch("pathlib.Path.read_text", return_value=mock_cfg):
            with patch("monitor.ups_service.get_db_connection", return_value=mock_conn):
                with patch("monitor.ups_service.query_ups_serial", return_value=mock_telemetry):
                    res = await poll_and_process_ups(dispatcher=mock_dispatcher)

        assert res is not None
        # Cuando está desactivado por config, NO despacha alerta a Telegram
        mock_dispatcher.send_text.assert_not_called()



