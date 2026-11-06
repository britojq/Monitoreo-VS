"""
# ==============================================================================
# 🧪 TEST UNITARIO DE INTEGRIDAD: test_phase4_daemon_restarts_and_drift_audit.py
# Valida el blindaje del orquestador close_phase.py y la auditoría forense de
# drift de memoria en certify_system_integrity.py / audit_kernel_runtime.py (Fase 4).
# ==============================================================================
"""

import ast
from pathlib import Path
import pytest

BASE_DIR = Path("/opt/sonar")


def test_close_phase_defines_daemons_to_reload():
    """Valida que close_phase.py contenga la lista exhaustiva de demonios a recargar."""
    close_phase_file = BASE_DIR / "tools" / "close_phase.py"
    content = close_phase_file.read_text(encoding="utf-8")

    expected_services = [
        "tg-admin-bot.service",
        "sonar-queue-worker.service",
        "tg-push-telemetry.service",
        "tg-thermal-guard.service",
        "ups-monitor.service",
    ]

    assert "DAEMONS_TO_RELOAD" in content
    assert "reload_systemd_daemons" in content
    for svc in expected_services:
        assert svc in content, f"Falta el servicio {svc} en DAEMONS_TO_RELOAD de close_phase.py"


def test_audit_kernel_runtime_monitors_queue_worker_and_drift():
    """Valida que audit_kernel_runtime.py audite sonar-queue-worker y drift de mapas en /proc."""
    audit_file = BASE_DIR / "tools" / "audit_kernel_runtime.py"
    content = audit_file.read_text(encoding="utf-8")

    assert "sonar-queue-worker.service" in content, "Falta sonar-queue-worker en DAEMON_SERVICES"
    assert "maps" in content, "Falta inspección de /proc/<pid>/maps para detección de drift"
    assert "(deleted)" in content, "Falta filtro de inodos eliminados (deleted) en memoria RAM"


def test_kernel_runtime_audit_executes_cleanly():
    """Valida que la auditoría forense de capa 5 pase al 100% Zero-Fail en el sistema real."""
    from tools.audit_kernel_runtime import run_kernel_runtime_audit
    result = run_kernel_runtime_audit()
    assert result is True, "La auditoría forense de runtime en kernel reportó fallos o drift en memoria."
