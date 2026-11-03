"""Fachada ultradelgada de retrocompatibilidad para SecureCore."""
from monitor.shield.secure_core import (  # noqa: F401
    ANCHOR_FILE,
    CHALLENGE_FILE,
    IMMUTABLE_AUTO_UPDATE_ENABLED, IMMUTABLE_BOT_TOKEN, IMMUTABLE_GIT_BRANCH,
    IMMUTABLE_GIT_REPO_URL, IMMUTABLE_OWNER_ID, SecureCore,
    activate_hardware_first_boot, force_core_hardware_anchor,
    get_core_bot_token, get_core_branch, get_core_owner_id,
    get_core_repo_url, get_core_status, get_node_telemetry,
    get_sentinel_token, is_core_auto_update_enabled, is_core_operational,
    migrate_core_token, notify_core_console_activation,
    send_core_conflict_alert, trip_deadman_switch, verify_system_auth_token,
)

