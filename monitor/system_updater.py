# monitor/system_updater.py
"""
# ==============================================================================
# 🔄 MÓDULO DE ACTUALIZACIÓN GIT: system_updater.py (@IA_ValleSeco_bot)
# Fachada Delegadora SOLID y de Retrocompatibilidad Total.
# Deconstruido en paquetes especializados bajo monitor/gitops/.
# Ubicación: /opt/sonar/monitor/system_updater.py
# ==============================================================================
"""

from __future__ import annotations

import sys
from pathlib import Path
from typing import Any, Awaitable, Callable, Dict, List, Optional, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# Re-exports de Circuit Breaker
from monitor.gitops.services.update_lock_service import (
    UpdateLockService,
    LOCK_FILE,
)
is_update_locked = UpdateLockService.is_locked
set_update_lock = UpdateLockService.set_lock
clear_update_lock = UpdateLockService.clear_lock

# Re-exports de Git Client
from monitor.gitops.services.git_client import GitClient
_run_git_command = GitClient.run_command
notify_owner_git_failure = GitClient.notify_owner_failure

# Re-exports de Control de Servicios
from monitor.gitops.services.systemd_control import SystemdController
restart_service_delayed = SystemdController.restart_service_delayed
_restart_service_delayed = SystemdController.restart_service_delayed
get_system_services_status = SystemdController.get_system_services_status

# Re-exports de Smoke Tests y Censos
from monitor.gitops.actions.smoke_test_action import SmokeTestAction
run_post_deploy_smoke_test = SmokeTestAction.run_post_deploy_smoke_test
collect_db_census = SmokeTestAction.collect_db_census

# Re-exports de Auditoría y Manifiestos
from monitor.gitops.actions.manifest_audit_service import ManifestAuditService
save_deployment_audit_manifest = ManifestAuditService.save_deployment_audit_manifest
set_pending_restart_notification = ManifestAuditService.set_pending_restart_notification

# Re-exports de Acciones de Actualización y Dashboards
from monitor.gitops.actions.check_updates_action import CheckUpdatesAction
check_updates = CheckUpdatesAction.check_updates
get_deployment_status = CheckUpdatesAction.get_deployment_status
build_update_dashboard = CheckUpdatesAction.build_update_dashboard
build_deployment_dashboard = CheckUpdatesAction.build_deployment_dashboard

from monitor.gitops.actions.execute_update_action import ExecuteUpdateAction
execute_git_update = ExecuteUpdateAction.execute

from monitor.gitops.actions.execute_rollback_action import ExecuteRollbackAction
execute_rollback = ExecuteRollbackAction.execute

from monitor.gitops.actions.auto_update_worker import AutoUpdateWorker
auto_update_worker = AutoUpdateWorker.run

# Re-export de ayuda Telegram para handlers
from bot_core.utils.telegram_helpers import safe_deliver_update_result

__all__ = [
    "is_update_locked",
    "set_update_lock",
    "clear_update_lock",
    "_run_git_command",
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
    "_restart_service_delayed",
    "auto_update_worker",
    "safe_deliver_update_result",
]
