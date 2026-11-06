# monitor/shield/__init__.py
"""
Paquete modular de seguridad de hardware, escudo de terminal y tokens S.O.N.A.R.
"""
from monitor.shield.actions.auth_tty_action import execute_auth
from monitor.shield.actions.enforce_sudo_action import execute_enforce_sudo
from monitor.shield.actions.kill_process_action import execute_kill
from monitor.shield.actions.lockdown_action import execute_lockdown, execute_unlock
from monitor.shield.actions.terminal_open_worker import process_terminal_open
from monitor.shield.domain.proc_inspector import (
    get_process_name,
    is_protected_process,
    is_recent_alert,
)
from monitor.shield.domain.tty_resolver import (
    clean_tty_name,
    is_grace_period_active,
    is_lockdown_active,
    resolve_tty,
)
from monitor.shield.infrastructure.audit_logger import (
    log_event_to_database,
    safe_unlink,
)
from monitor.shield.infrastructure.master_override import (
    execute_master_override,
    is_master_override_active,
)
from monitor.shield.infrastructure.notifier import (
    load_configured_proxies,
    send_telegram_alert,
)
from monitor.shield.secure_core import (
    CHALLENGE_FILE,
    IMMUTABLE_OWNER_ID,
    SecureCore,
    activate_hardware_first_boot,
    get_core_bot_token,
    get_core_status,
    get_node_telemetry,
    get_sentinel_token,
    is_core_operational,
    migrate_core_token,
    trip_deadman_switch,
)
from monitor.shield.ui.infraction_counter import (
    record_infraction,
    reset_infractions,
)
from monitor.shield.ui.kdialog_adapter import launch_kdialog_fallback
from monitor.shield.ui.pyqt_dialog_adapter import launch_shield_dialog

__all__ = [
    "SecureCore",
    "execute_auth",
    "execute_enforce_sudo",
    "execute_kill",
    "execute_lockdown",
    "execute_unlock",
    "process_terminal_open",
    "is_protected_process",
    "clean_tty_name",
    "resolve_tty",
    "get_process_name",
    "is_grace_period_active",
    "is_lockdown_active",
    "is_master_override_active",
    "execute_master_override",
    "is_recent_alert",
    "record_infraction",
    "reset_infractions",
    "launch_shield_dialog",
    "launch_kdialog_fallback",
    "log_event_to_database",
    "load_configured_proxies",
    "send_telegram_alert",
    "safe_unlink",
    "CHALLENGE_FILE",
    "IMMUTABLE_OWNER_ID",
    "activate_hardware_first_boot",
    "get_core_bot_token",
    "get_core_status",
    "get_node_telemetry",
    "get_sentinel_token",
    "is_core_operational",
    "migrate_core_token",
    "trip_deadman_switch",
]
