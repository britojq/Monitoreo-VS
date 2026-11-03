"""
Pruebas Unitarias para la Suite de Verificadores y Motores de Red Desacoplados (SOLID Fase 3).
Verifica que todos los paquetes modulares y sus fachadas ultradelgadas funcionen
respetando tipado, contratos y aislamiento sin regresiones.
"""
import pytest
from unittest.mock import patch, MagicMock


class TestNetworkAnalyzerModular:
    """Verifica el paquete monitor.checkers.analyzer y su fachada."""

    def test_network_host_dto(self):
        from monitor.checkers.analyzer.dtos import NetworkHost
        host = NetworkHost(
            ip="192.168.1.10",
            hostname="host10",
            mac="00:11:22:33:44:55",
            vendor="Cisco Systems",
            rx=512,
            tx=512,
            total_traffic=1024,
            latency_ms=1.5,
            is_local=True,
            is_authorized=True,
        )
        assert host.ip == "192.168.1.10"
        assert host.mac == "00:11:22:33:44:55"
        assert host.vendor == "Cisco Systems"
        assert host.hostname == "host10"
        assert host.is_local is True
        assert host.total_traffic == 1024

    def test_oui_resolver_subnet_check(self):
        from monitor.checkers.analyzer.oui_resolver import is_ip_in_local_subnet
        assert is_ip_in_local_subnet("192.168.1.50", "192.168.1.1", "255.255.255.0") is True
        assert is_ip_in_local_subnet("192.168.1.1", "192.168.1.1", "255.255.255.0") is False

    def test_text_report_builder(self):
        from monitor.checkers.analyzer.text_report_builder import generate_telegram_summary
        from monitor.checkers.analyzer.dtos import NetworkHost
        host = NetworkHost(ip="192.168.1.1", mac="00:00:0C:07:AC:01", rx=100, tx=200, latency_ms=1.2)
        summary = generate_telegram_summary(
            hosts_local_con_mac=[host],
            hosts_local_sin_mac=[],
            hosts_externos=[],
            total_suspicious=0,
            storm_alert=False,
            interface="eth0",
            my_ip="192.168.1.220",
            my_mask="255.255.255.0",
            pkt_count=100,
            duration_seconds=10,
            fecha_str="2026-10-30",
            hora_str="12:00:00",
        )
        assert "REPORTE DE ANÁLISIS DE RED" in summary
        assert "192.168.1.1" in summary

    def test_analyzer_facade_exports(self):
        import monitor.network_analyzer as facade
        assert hasattr(facade, "execute_network_analysis")
        assert hasattr(facade, "run_full_network_analysis")


class TestCoreShieldModular:
    """Verifica el paquete monitor.shield y su fachada."""

    def test_vault_constants(self):
        from monitor.shield.vault_key_deriver import IMMUTABLE_OWNER_ID
        assert IMMUTABLE_OWNER_ID == 38914901

    def test_secure_core_status(self):
        from monitor.shield.secure_core import is_core_operational, get_core_status
        status = get_core_status()
        assert isinstance(status, str)
        assert is_core_operational() in (True, False)

    def test_shield_facade_exports(self):
        import monitor.core_shield as facade
        assert hasattr(facade, "SecureCore")
        assert hasattr(facade, "is_core_operational")
        assert hasattr(facade, "IMMUTABLE_OWNER_ID")
        assert facade.IMMUTABLE_OWNER_ID == 38914901


class TestConfigBackupModular:
    """Verifica el paquete monitor.checkers.backup y su fachada."""

    def test_normalize_config(self):
        from monitor.checkers.backup.cisco_ssh_client import normalize_config
        raw = "! Last configuration change at 12:00:00 UTC\nhostname Router1\n! Time: Fri Oct 30 2026\n"
        cleaned = normalize_config(raw)
        assert "! Last configuration change at" not in cleaned
        assert "! Time:" not in cleaned
        assert "hostname Router1" in cleaned

    def test_unified_diff_engine(self):
        from monitor.checkers.backup.unified_diff_engine import generate_unified_diff
        old = "hostname RouterA\ninterface GigabitEthernet0/0\n"
        new = "hostname RouterA\ninterface GigabitEthernet0/1\n"
        diff_text, added, removed = generate_unified_diff(old, new, "RouterA")
        assert "-interface GigabitEthernet0/0" in diff_text
        assert "+interface GigabitEthernet0/1" in diff_text
        assert added == 1
        assert removed == 1

    def test_backup_manager_facade(self):
        from monitor.checkers.backup.backup_manager import ConfigBackupManager
        manager = ConfigBackupManager()
        sim = manager.generate_simulated_cisco_config("TEST-RTR", vlan_count=4)
        assert "hostname TEST-RTR" in sim

    def test_backup_facade_exports(self):
        import monitor.config_backup as facade
        assert hasattr(facade, "ConfigBackupManager")
        assert hasattr(facade, "backup_target_device")


class TestNetRadarModular:
    """Verifica el paquete monitor.checkers.radar y su fachada."""

    def test_format_bytes(self):
        from monitor.checkers.radar.constants import format_bytes_human
        assert format_bytes_human(500) == "500 B"
        assert format_bytes_human(1024 * 1024) == "1.00 MB"

    def test_update_detector(self):
        from monitor.checkers.radar.update_detector import detect_update_activity
        win_type, win_state = detect_update_activity("windowsupdate.microsoft.com", "80")
        assert win_type == "windows_update"

        linux_type, linux_state = detect_update_activity("archive.ubuntu.com", "80")
        assert linux_type == "linux_repo"

        none_type, none_state = detect_update_activity("google.com", "443")
        assert none_type == ""

    def test_radar_facade_exports(self):
        import monitor.net_radar_engine as facade
        assert hasattr(facade, "run_radar_cycle")
        assert hasattr(facade, "main_daemon")


class TestSslCheckerModular:
    """Verifica el paquete monitor.checkers.ssl y su fachada."""

    def test_match_hostname(self):
        from monitor.checkers.ssl.x509_parser import match_hostname
        assert match_hostname("portal.example.com", ["portal.example.com"], "portal.example.com") is True
        assert match_hostname("test.example.com", ["*.example.com"], None) is True
        assert match_hostname("other.domain.com", ["*.example.com"], None) is False

    def test_ssl_facade_exports(self):
        import monitor.ssl_checker as facade
        assert hasattr(facade, "check_and_update_certificate")
        assert hasattr(facade, "run_all_ssl_checks")


class TestDiscoveryModular:
    """Verifica el paquete monitor.checkers.discovery y su fachada."""

    def test_infer_device_type(self):
        from monitor.checkers.discovery.port_fingerprinter import infer_device_type
        assert infer_device_type("Cisco", "sw-c2960-core") == "switch"
        assert infer_device_type("MikroTik", "gw-router-01") == "router"
        assert infer_device_type("HP", "printer-laserjet-01") == "printer"

    def test_discovery_facade_exports(self):
        import monitor.network_discovery as facade
        assert hasattr(facade, "scan_subnet")
        assert hasattr(facade, "scan_all_active_subnets")


class TestThermalModular:
    """Verifica el paquete monitor.checkers.thermal y su fachada."""

    def test_thermal_constants(self):
        from monitor.checkers.thermal.constants import IMMUTABLE_OWNER_ID, AI_SERVICE_UNIT
        assert IMMUTABLE_OWNER_ID == 38914901
        assert AI_SERVICE_UNIT == "ollama.service"

    def test_thermal_facade_exports(self):
        import monitor.thermal_guard as facade
        assert hasattr(facade, "ThermalGuardian")
        assert hasattr(facade, "get_cpu_temperatures")


class TestSshAlertModular:
    """Verifica el paquete monitor.shield.ssh_alert y su fachada."""

    def test_pam_event_parser(self):
        from monitor.shield.ssh_alert.pam_event_parser import (
            is_private_or_local_ip,
            get_network_context,
            sanitize_session_token,
        )
        assert is_private_or_local_ip("192.168.1.45") is True
        assert is_private_or_local_ip("192.168.1.100") is True
        assert is_private_or_local_ip("8.8.8.8") is False
        assert get_network_context("192.168.1.1") == "Router Core / Gateway Valle Seco"
        assert get_network_context("192.168.1.220") == "Servidor Desarrollo (Esclavo)"
        assert sanitize_session_token("root", "pts/0") == "root_pts_0.ts"

    def test_ssh_alert_facade_exports(self):
        import monitor.ssh_alert as facade
        assert hasattr(facade, "process_pam_event")
