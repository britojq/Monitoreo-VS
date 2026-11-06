"""
# ==============================================================================
# 🧪 TEST UNITARIO DE INTEGRIDAD: test_phase3_canonical_imports_integrity.py
# Valida la canonización del 100% de las importaciones en bot_core/ y monitor/,
# erradicando acoplamientos residuales a fachadas planas intermedias.
# ==============================================================================
"""

import ast
import os
import re
from pathlib import Path
import pytest

BASE_DIR = Path("/opt/sonar")

LEGACY_FACADES = [
    "agent_deployer", "boot_alert", "cli_activar", "config_backup", "core_shield",
    "cron_runner", "database_backup", "dependencies", "device_service_notifier",
    "diagnostico_produccion", "fail2ban_alert", "git_network", "lifecycle_manager",
    "monitor_db", "net_radar_engine", "netflow_collector", "network_analyzer",
    "network_discovery", "predictive_analyzer", "proxy_cache", "push_telemetry_daemon",
    "self_heal_environment", "shield_dialog", "snmp_activator", "snmp_trap_receiver",
    "ssh_alert", "ssl_checker", "syslog_receiver", "system_cleaner", "system_optimizer",
    "telegram_dispatcher", "thermal_guard", "topology_builder", "web_screenshot", "wol_sender",
]

LEGACY_PATTERN = re.compile(
    r"from\s+monitor\.(" + "|".join(LEGACY_FACADES) + r")\b|import\s+monitor\.(" + "|".join(LEGACY_FACADES) + r")\b"
)


def test_zero_legacy_flat_imports_in_bot_core():
    """Valida que no existan importaciones planas obsoletas en bot_core/."""
    bot_core_dir = BASE_DIR / "bot_core"
    violations = []

    for root, _, files in os.walk(bot_core_dir):
        for f in files:
            if f.endswith(".py"):
                p = Path(root) / f
                for line_num, line in enumerate(p.read_text(encoding="utf-8", errors="ignore").splitlines(), 1):
                    if LEGACY_PATTERN.search(line):
                        violations.append(f"{p.relative_to(BASE_DIR)}:{line_num}: {line.strip()}")

    assert not violations, (
        f"Se detectaron {len(violations)} importaciones planas obsoletas en bot_core/:\n" +
        "\n".join(violations[:10])
    )


def test_zero_legacy_flat_imports_in_monitor_subpackages():
    """Valida que ningún submódulo dentro de monitor/ subpaquetes importe fachadas planas."""
    monitor_dir = BASE_DIR / "monitor"
    violations = []

    for root, _, files in os.walk(monitor_dir):
        # Excluir la raíz de monitor/ donde residen intencionalmente los shims de defensa en profundidad
        if Path(root) == monitor_dir:
            continue
        for f in files:
            if f.endswith(".py"):
                p = Path(root) / f
                for line_num, line in enumerate(p.read_text(encoding="utf-8", errors="ignore").splitlines(), 1):
                    if LEGACY_PATTERN.search(line):
                        violations.append(f"{p.relative_to(BASE_DIR)}:{line_num}: {line.strip()}")

    assert not violations, (
        f"Se detectaron {len(violations)} importaciones planas obsoletas en subpaquetes de monitor/:\n" +
        "\n".join(violations[:10])
    )


def test_all_bot_core_and_monitor_files_valid_ast():
    """Valida la corrección sintáctica AST de todos los archivos modificados."""
    for scan_dir in [BASE_DIR / "bot_core", BASE_DIR / "monitor"]:
        for root, _, files in os.walk(scan_dir):
            for f in files:
                if f.endswith(".py"):
                    p = Path(root) / f
                    code = p.read_text(encoding="utf-8")
                    parsed = ast.parse(code, filename=str(p))
                    assert isinstance(parsed, ast.Module), f"Fallo de AST en {p}"
