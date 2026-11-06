"""
# ==============================================================================
# 🧪 TESTS UNITARIOS SOLID: test_ups_shield_solid.py
# Pruebas automatizadas de arquitectura limpia para monitor/ups/ y monitor/shield/
# Ubicación: /opt/sonar/tests/unit/test_ups_shield_solid.py
# ==============================================================================
"""

from __future__ import annotations

import os
from unittest.mock import MagicMock, patch

import pytest

# 1. Importaciones de UPS
from monitor.ups.domain.battery_policy import calculate_battery_percentage
from monitor.ups.domain.dtos import (
    BatteryMetricsDto,
    UpsHardwareInfoDto,
    UpsRatingDto,
    UpsTelemetrySnapshotDto,
)
from monitor.ups.domain.duration_formatter import (
    format_duration,
    get_notification_target,
    get_notification_targets,
)
from monitor.ups.drivers.megatec_driver import query_ups_serial

# 2. Importaciones de Terminal Shield
from monitor.shield.domain.dtos import (
    AuthWindowDto,
    ProcessContextDto,
    TerminalSessionDto,
)
from monitor.shield.domain.proc_inspector import (
    get_process_name,
    is_protected_process,
)
from monitor.shield.domain.tty_resolver import (
    clean_tty_name,
    is_lockdown_active,
)


class TestUpsSolidArchitecture:
    """Verificación de contratos y políticas del subsistema UPS."""

    def test_battery_policy_percentages(self):
        """Verifica la curva de descarga por celda para baterías AGM/GEL."""
        assert calculate_battery_percentage(2.25) == 100
        assert calculate_battery_percentage(2.23) == 100
        assert calculate_battery_percentage(1.75) == 0
        assert calculate_battery_percentage(1.70) == 0

        # Rango intermedio: 2.00V -> (2.00 - 1.75) / 0.5 = 0.5 * 100 = 50%
        assert calculate_battery_percentage(2.00) == 50

    def test_duration_formatter_and_targets(self):
        """Verifica el formateo en español y el cumplimiento de la Regla de Oro #1."""
        assert format_duration(30) == "30 seg"
        assert format_duration(60) == "1 min"
        assert format_duration(95) == "1 min 35 seg"
        assert format_duration(3600) == "1 h 0 min"
        assert format_duration(3665) == "1 h 1 min"

        # Con TESTING=1 debe devolver estrictamente solo el ID del Owner privado (38914901)
        with patch.dict(os.environ, {"TESTING": "1"}):
            targets = get_notification_targets("group")
            assert targets == ["38914901"]
            target = get_notification_target("group")
            assert target == "38914901"

    def test_ups_dtos_immutability(self):
        """Verifica que los DTOs de UPS sean inmutables y serializables."""
        dto = UpsTelemetrySnapshotDto(
            input_voltage=220.0,
            input_fault_voltage=220.0,
            output_voltage=208.0,
            load_percent=35,
            frequency=60.0,
            battery_voltage=2.24,
            battery_percent=100,
            temperature_c=38.5,
            is_online=True,
            is_on_battery=False,
            is_battery_low=False,
            is_bypass=False,
            is_ups_failed=False,
            beeper_on=False,
            raw_status_bits="00001000",
        )
        assert dto.input_voltage == 220.0
        assert dto.load_percent == 35
        d = dto.to_dict()
        assert isinstance(d, dict)
        assert d["is_online"] is True

        with pytest.raises(Exception):
            dto.load_percent = 50  # Frozen instance no permite mutación

    @patch("pathlib.Path.exists", return_value=True)
    def test_megatec_driver_parser(self, mock_exists):
        """Verifica la decodificación de trama Megatec Q1 con simulación de puerto serial."""
        fake_ser = MagicMock()
        # Trama Q1 típica: (220.0 220.0 208.0 035 60.0 2.24 38.0 00001000\r
        fake_ser.read_until.return_value = b"(220.0 220.0 208.0 035 60.0 2.24 38.0 00001000\r"

        with patch("serial.Serial", return_value=fake_ser):
            res = query_ups_serial(port="/dev/ttyUSB0", baud=2400)
            assert res is not None
            assert res["input_voltage"] == 220.0
            assert res["output_voltage"] == 208.0
            assert res["load_percent"] == 35
            assert res["frequency"] == 60.0
            assert res["battery_voltage"] == 2.24
            assert res["is_on_battery"] is False
            assert res["is_online"] is True


class TestTerminalShieldSolidArchitecture:
    """Verificación de contratos y políticas del subsistema Terminal Shield."""

    def test_tty_cleaning_and_resolution(self):
        """Verifica la sanitización de identificadores TTY."""
        assert clean_tty_name("/dev/pts/1") == "pts_1"
        assert clean_tty_name("pts/0") == "pts_0"
        assert clean_tty_name("tty1") == "tty1"
        assert clean_tty_name("none") == ""
        assert clean_tty_name("?") == ""
        assert clean_tty_name(None) == ""

    def test_shield_dtos_immutability(self):
        """Verifica la inmutabilidad de los DTOs de seguridad del terminal shield."""
        session = TerminalSessionDto(
            pid=1234,
            ppid=1200,
            user="admin",
            tty="pts/1",
            proc_name="bash",
            parent_name="konsole",
        )
        assert session.pid == 1234
        assert session.user == "admin"
        with pytest.raises(Exception):
            session.user = "root"

        auth = AuthWindowDto(
            success=True,
            expiry="14:30:00",
            minutes=30,
            tty="pts/1",
        )
        assert auth.minutes == 30
        assert auth.to_dict()["expiry"] == "14:30:00"

    def test_process_protection_heuristic(self):
        """Verifica que el proceso raíz y procesos inválidos no sean clasificados erróneamente."""
        assert is_protected_process(0) is False
        assert is_protected_process(1) is False
        assert get_process_name(-1) == "desconocido"

    def test_packages_symbol_parity(self):
        """Verifica que los paquetes canónicos expongan el 100% de la API esperada."""
        import monitor.shield as shield_pkg
        import monitor.ups as ups_pkg

        # Verificación de símbolos en ups
        expected_ups_symbols = [
            "calculate_battery_percentage",
            "format_duration",
            "get_notification_targets",
            "get_notification_target",
            "query_ups_serial",
            "query_ups_info",
            "query_ups_rating",
            "query_ups_hardware",
            "scan_and_detect_ups",
            "sync_ups_to_inventory",
            "build_ups_outage_alert",
            "build_ups_fluctuation_alert",
            "build_ups_recovery_alert",
            "build_ups_battery_low_alert",
            "poll_and_process_ups",
            "run_ups_daemon",
        ]
        for s in expected_ups_symbols:
            assert hasattr(ups_pkg, s), f"Falta símbolo {s} en monitor.ups"

        # Verificación de símbolos en shield
        expected_shield_symbols = [
            "log_event_to_database",
            "load_configured_proxies",
            "send_telegram_alert",
            "safe_unlink",
            "is_master_override_active",
            "execute_master_override",
            "is_lockdown_active",
            "clean_tty_name",
            "is_grace_period_active",
            "resolve_tty",
            "get_process_name",
            "is_recent_alert",
            "record_infraction",
            "reset_infractions",
            "launch_kdialog_fallback",
            "launch_shield_dialog",
            "execute_kill",
            "is_protected_process",
            "execute_enforce_sudo",
            "execute_lockdown",
            "execute_unlock",
            "execute_auth",
            "process_terminal_open",
        ]
        for s in expected_shield_symbols:
            assert hasattr(shield_pkg, s), f"Falta símbolo {s} en monitor.shield"
