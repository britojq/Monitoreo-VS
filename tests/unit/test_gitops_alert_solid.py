# tests/unit/test_gitops_alert_solid.py
import pytest
from monitor.gitops.dtos import (
    UpdateCheckResultDto,
    DeploymentStatusDto,
    SmokeTestResultDto,
)
from monitor.gitops.services import UpdateLockService
from monitor.alerts.services.storm_suppressor import compute_fingerprint
from monitor.alerts.cli_formatter import format_table
import monitor.gitops as gitops_pkg
import monitor.alerts as alerts_pkg

def test_gitops_dtos():
    dto = UpdateCheckResultDto(
        success=True,
        is_locked=False,
        has_update=True,
        local_hash="abc1234",
        remote_hash="def5678",
    )
    d = dto.to_dict()
    assert d["success"] is True
    assert d["local_hash"] == "abc1234"
    assert d["has_update"] is True

def test_compute_fingerprint_deterministic():
    fp1 = compute_fingerprint("service", 1, "is_down")
    fp2 = compute_fingerprint("service", 1, "is_down")
    fp3 = compute_fingerprint("service", 2, "is_down")
    assert fp1 == fp2
    assert fp1 != fp3
    assert len(fp1) == 64

def test_format_table_empty():
    res = format_table([])
    assert "No hay alertas activas" in res

def test_gitops_package_exports():
    expected_attrs = [
        "is_update_locked",
        "set_update_lock",
        "clear_update_lock",
        "notify_owner_git_failure",
        "check_updates",
        "build_update_dashboard",
        "run_post_deploy_smoke_test",
        "collect_db_census",
        "get_system_services_status",
        "save_deployment_audit_manifest",
        "set_pending_restart_notification",
        "execute_git_update",
        "execute_rollback",
        "get_deployment_status",
        "build_deployment_dashboard",
        "restart_service_delayed",
        "auto_update_worker",
    ]
    for attr in expected_attrs:
        assert hasattr(gitops_pkg, attr), f"Falta el atributo canónico '{attr}' en monitor.gitops"

def test_alerts_package_exports():
    expected_attrs = [
        "compute_fingerprint",
        "AlertEngine",
        "format_table",
        "main_cli",
    ]
    for attr in expected_attrs:
        assert hasattr(alerts_pkg, attr), f"Falta el atributo canónico '{attr}' en monitor.alerts"
