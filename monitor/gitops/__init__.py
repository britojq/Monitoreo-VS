# monitor/gitops/__init__.py
"""
Paquete modular de GitOps, despliegue continuo y Circuit Breaker S.O.N.A.R.
Construido bajo principios SOLID y tipado estricto Python 3.13.
"""
from monitor.gitops.actions import (
    AutoUpdateWorker,
    CheckUpdatesAction,
    ExecuteRollbackAction,
    ExecuteUpdateAction,
    ManifestAuditService,
    SmokeTestAction,
)
from monitor.gitops.services.git_client import GitClient
from monitor.gitops.services.systemd_control import SystemdController
from monitor.gitops.services.update_lock_service import LOCK_FILE, UpdateLockService

# Métodos canónicos de alto nivel desacoplados
check_updates = CheckUpdatesAction.check_updates
build_update_dashboard = CheckUpdatesAction.build_update_dashboard
build_deployment_dashboard = CheckUpdatesAction.build_deployment_dashboard
get_deployment_status = CheckUpdatesAction.get_deployment_status
execute_git_update = ExecuteUpdateAction.execute
execute_rollback = ExecuteRollbackAction.execute
auto_update_worker = AutoUpdateWorker.run
is_update_locked = UpdateLockService.is_locked
set_update_lock = UpdateLockService.set_lock
clear_update_lock = UpdateLockService.clear_lock
restart_service_delayed = SystemdController.restart_service_delayed
get_system_services_status = SystemdController.get_system_services_status
notify_owner_git_failure = GitClient.notify_owner_failure
run_post_deploy_smoke_test = SmokeTestAction.run_post_deploy_smoke_test
collect_db_census = SmokeTestAction.collect_db_census
save_deployment_audit_manifest = ManifestAuditService.save_deployment_audit_manifest
set_pending_restart_notification = ManifestAuditService.set_pending_restart_notification

__all__ = [
    "AutoUpdateWorker",
    "CheckUpdatesAction",
    "ExecuteRollbackAction",
    "ExecuteUpdateAction",
    "ManifestAuditService",
    "SmokeTestAction",
    "GitClient",
    "SystemdController",
    "UpdateLockService",
    "LOCK_FILE",
    "check_updates",
    "build_update_dashboard",
    "build_deployment_dashboard",
    "get_deployment_status",
    "execute_git_update",
    "execute_rollback",
    "auto_update_worker",
    "is_update_locked",
    "set_update_lock",
    "clear_update_lock",
    "restart_service_delayed",
    "get_system_services_status",
    "notify_owner_git_failure",
    "run_post_deploy_smoke_test",
    "collect_db_census",
    "save_deployment_audit_manifest",
    "set_pending_restart_notification",
]
