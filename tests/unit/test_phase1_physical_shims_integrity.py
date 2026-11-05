"""
# ==============================================================================
# 🧪 TEST UNITARIO DE INTEGRIDAD: test_phase1_physical_shims_integrity.py
# Valida la existencia física, compilación, importación y paridad de las 35
# fachadas resilientes (defense-in-depth) en /opt/sonar/monitor/.
# ==============================================================================
"""

import ast
import importlib
from pathlib import Path
import pytest

MONITOR_DIR = Path("/opt/sonar/monitor")

SHIM_CANONICAL_MAP = {
    "agent_deployer.py": "monitor.deployment.agent_deployer",
    "boot_alert.py": "monitor.alerts.boot_alert",
    "cli_activar.py": "monitor.cli.cli_activar",
    "config_backup.py": "monitor.checkers.backup",
    "core_shield.py": "monitor.shield.secure_core",
    "cron_runner.py": "monitor.daemons.cron_runner",
    "database_backup.py": "monitor.tools.database_backup",
    "dependencies.py": "monitor.tools.dependencies",
    "device_service_notifier.py": "monitor.alerts.device_service_notifier",
    "diagnostico_produccion.py": "monitor.tools.diagnostico_produccion",
    "fail2ban_alert.py": "monitor.alerts.fail2ban_alert",
    "git_network.py": "monitor.gitops.git_network",
    "lifecycle_manager.py": "monitor.tools.lifecycle_manager",
    "monitor_db.py": "monitor.infrastructure.monitor_db",
    "net_radar_engine.py": "monitor.checkers.radar",
    "netflow_collector.py": "monitor.daemons.netflow_collector",
    "network_analyzer.py": "monitor.checkers.analyzer",
    "network_discovery.py": "monitor.checkers.discovery",
    "predictive_analyzer.py": "monitor.tools.predictive_analyzer",
    "proxy_cache.py": "monitor.infrastructure.proxy_cache",
    "push_telemetry_daemon.py": "monitor.daemons.push_telemetry_daemon",
    "self_heal_environment.py": "monitor.tools.self_heal_environment",
    "shield_dialog.py": "monitor.shield.ui.shield_dialog",
    "snmp_activator.py": "monitor.cli.snmp_activator",
    "snmp_trap_receiver.py": "monitor.daemons.snmp_trap_receiver",
    "ssh_alert.py": "monitor.shield.ssh_alert",
    "ssl_checker.py": "monitor.checkers.ssl",
    "syslog_receiver.py": "monitor.daemons.syslog_receiver",
    "system_cleaner.py": "monitor.tools.system_cleaner",
    "system_optimizer.py": "monitor.tools.system_optimizer",
    "telegram_dispatcher.py": "monitor.alerts.telegram_dispatcher",
    "thermal_guard.py": "monitor.checkers.thermal",
    "topology_builder.py": "monitor.tools.topology_builder",
    "web_screenshot.py": "monitor.tools.web_screenshot",
    "wol_sender.py": "monitor.tools.wol_sender",
}


def test_all_physical_shims_exist_on_disk():
    """Verifica que todos los 35 shims físicos existan en /opt/sonar/monitor/."""
    assert MONITOR_DIR.is_dir(), f"El directorio {MONITOR_DIR} no existe."
    for shim_file in SHIM_CANONICAL_MAP.keys():
        shim_path = MONITOR_DIR / shim_file
        assert shim_path.is_file(), f"Falta el shim físico en disco: {shim_path}"
        assert shim_path.stat().st_size > 0, f"El shim físico está vacío: {shim_path}"


def test_all_physical_shims_are_valid_python_syntax():
    """Verifica que el AST de cada shim físico sea sintácticamente válido."""
    for shim_file in SHIM_CANONICAL_MAP.keys():
        shim_path = MONITOR_DIR / shim_file
        code = shim_path.read_text(encoding="utf-8")
        parsed = ast.parse(code, filename=str(shim_path))
        assert isinstance(parsed, ast.Module), f"El archivo {shim_file} no produjo un ast.Module válido."


def test_all_physical_shims_have_base_dir_injection():
    """Verifica que cada shim físico inyecte BASE_DIR en sys.path para ejecución independiente."""
    for shim_file in SHIM_CANONICAL_MAP.keys():
        shim_path = MONITOR_DIR / shim_file
        code = shim_path.read_text(encoding="utf-8")
        assert "BASE_DIR" in code or "sys.path" in code, (
            f"El shim {shim_file} no contiene inyección resiliente de BASE_DIR en sys.path."
        )


@pytest.mark.parametrize("shim_file,canonical_pkg", list(SHIM_CANONICAL_MAP.items()))
def test_shim_import_and_parity(shim_file: str, canonical_pkg: str):
    """Verifica la importación dinámica y la disponibilidad de símbolos."""
    mod_name = f"monitor.{shim_file[:-3]}"
    
    # shield_dialog requiere PyQt5, en entorno headless lanza SystemExit(0)
    if shim_file == "shield_dialog.py":
        try:
            importlib.import_module(mod_name)
        except SystemExit:
            pass  # Comportamiento esperado cuando PyQt5 no está instalado en headless
        return

    shim_module = importlib.import_module(mod_name)
    assert shim_module is not None, f"No se pudo importar {mod_name}"

    canonical_module = importlib.import_module(canonical_pkg)
    assert canonical_module is not None, f"No se pudo importar el canónico {canonical_pkg}"

    # Validar que los atributos públicos del canónico existan en el shim
    canonical_attrs = [attr for attr in dir(canonical_module) if not attr.startswith("__")]
    for attr in canonical_attrs:
        assert hasattr(shim_module, attr), (
            f"El shim {mod_name} no re-exporta el atributo '{attr}' de {canonical_pkg}"
        )
