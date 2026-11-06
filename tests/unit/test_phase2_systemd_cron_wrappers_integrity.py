"""
# ==============================================================================
# 🧪 TEST UNITARIO DE INTEGRIDAD: test_phase2_systemd_cron_wrappers_integrity.py
# Valida la modernización de unidades Systemd, crontabs y wrappers CLI (Fase 2)
# eliminando acoplamientos a rutas planas obsoletas.
# ==============================================================================
"""

import os
import subprocess
import sys
from pathlib import Path

BASE_DIR = Path("/opt/sonar")


def test_systemd_templates_have_canonical_execstart():
    """Valida que las plantillas systemd apunten a ejecutables canónicos modularizados."""
    telemetry_tpl = BASE_DIR / "system" / "templates" / "systemd" / "tg-push-telemetry.service"
    thermal_tpl = BASE_DIR / "system" / "templates" / "systemd" / "tg-thermal-guard.service"
    boot_tpl = BASE_DIR / "system" / "templates" / "systemd" / "boot-alert.service"

    assert "monitor/daemons/push_telemetry_daemon.py" in telemetry_tpl.read_text(encoding="utf-8")
    assert "monitor/checkers/thermal/thermal_cli.py" in thermal_tpl.read_text(encoding="utf-8")
    assert "monitor/alerts/boot_alert.py" in boot_tpl.read_text(encoding="utf-8")


def test_systemd_installed_units_have_canonical_execstart():
    """Valida que las unidades activas en /etc/systemd/system/ usen rutas canónicas."""
    installed_telemetry = Path("/etc/systemd/system/tg-push-telemetry.service")
    installed_thermal = Path("/etc/systemd/system/tg-thermal-guard.service")
    installed_boot = Path("/etc/systemd/system/boot-alert.service")

    if installed_telemetry.is_file():
        assert "monitor/daemons/push_telemetry_daemon.py" in installed_telemetry.read_text(encoding="utf-8")
    if installed_thermal.is_file():
        assert "monitor/checkers/thermal/thermal_cli.py" in installed_thermal.read_text(encoding="utf-8")
    if installed_boot.is_file():
        assert "monitor/alerts/boot_alert.py" in installed_boot.read_text(encoding="utf-8")


def test_estatus_wrapper_uses_canonical_paths():
    """Valida que /opt/sonar/tools/estatus use rutas canónicas para cada subcomando."""
    estatus_file = BASE_DIR / "tools" / "estatus"
    content = estatus_file.read_text(encoding="utf-8")

    assert "monitor/daemons/cron_runner.py" in content
    assert "monitor/tools/system_optimizer.py" in content
    assert "monitor/checkers/discovery/discovery_cli.py" in content
    assert "monitor/checkers/radar/radar_cli.py" in content
    assert "monitor/cli/snmp_cli.py" in content
    assert "monitor/checkers/ssl/ssl_cli.py" in content
    assert "monitor/checkers/backup/backup_cli.py" in content
    assert "monitor/daemons/snmp_trap_receiver.py" in content
    assert "monitor/daemons/syslog_receiver.py" in content
    assert "monitor/daemons/netflow_collector.py" in content
    assert "monitor/daemons/push_telemetry_daemon.py" in content
    assert "monitor/tools/topology_builder.py" in content
    assert "monitor/tools/wol_sender.py" in content
    assert "monitor/tools/predictive_analyzer.py" in content
    assert "monitor/tools/lifecycle_manager.py" in content


def test_temperatura_wrapper_uses_canonical_path():
    """Valida que /opt/sonar/tools/temperatura use thermal_cli.py."""
    temp_file = BASE_DIR / "tools" / "temperatura"
    assert "monitor/checkers/thermal/thermal_cli.py" in temp_file.read_text(encoding="utf-8")


def test_activar_wrapper_uses_canonical_path():
    """Valida que /opt/sonar/tools/activar use cli/cli_activar.py."""
    activar_file = BASE_DIR / "tools" / "activar"
    assert "monitor/cli/cli_activar.py" in activar_file.read_text(encoding="utf-8")


def test_cron_monitoreo_radar_uses_canonical_path():
    """Valida que /etc/cron.d/monitoreo_radar use radar_cli.py."""
    cron_file = Path("/etc/cron.d/monitoreo_radar")
    if cron_file.is_file():
        assert "monitor/checkers/radar/radar_cli.py" in cron_file.read_text(encoding="utf-8")


def test_cli_modules_direct_execution_with_base_dir():
    """Valida que los módulos CLI puedan ejecutarse directamente como scripts sin ModuleNotFoundError."""
    python_bin = BASE_DIR / "venv" / "bin" / "python"
    cli_scripts = [
        BASE_DIR / "monitor" / "checkers" / "radar" / "radar_cli.py",
        BASE_DIR / "monitor" / "checkers" / "ssl" / "ssl_cli.py",
        BASE_DIR / "monitor" / "checkers" / "backup" / "backup_cli.py",
        BASE_DIR / "monitor" / "checkers" / "discovery" / "discovery_cli.py",
        BASE_DIR / "monitor" / "checkers" / "thermal" / "thermal_cli.py",
        BASE_DIR / "monitor" / "cli" / "snmp_cli.py",
        BASE_DIR / "monitor" / "cli" / "monitor_cli.py",
        BASE_DIR / "monitor" / "deployment" / "agent_deployer.py",
        BASE_DIR / "monitor" / "shield" / "ssh_alert" / "ssh_alert_cli.py",
    ]

    for script in cli_scripts:
        res = subprocess.run(
            [str(python_bin), str(script), "--help"],
            capture_output=True,
            text=True,
            timeout=10,
        )
        assert res.returncode == 0, f"Error al ejecutar {script.name} --help:\n{res.stderr}"
        assert "No module named 'monitor'" not in res.stderr
