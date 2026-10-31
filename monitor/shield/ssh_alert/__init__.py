"""
Paquete modular de alertas PAM, inicios de sesión SSH y elevaciones de privilegios sudo.
"""
from monitor.shield.ssh_alert.pam_event_parser import (
    calculate_session_duration,
    find_interactive_terminal_pids,
    get_geolocation,
    get_network_context,
    is_private_or_local_ip,
    record_session_start,
    sanitize_session_token,
)
from monitor.shield.ssh_alert.pam_processor import process_pam_event
from monitor.shield.ssh_alert.ssh_alert_dispatcher import (
    load_configured_proxies,
    log_event_to_database,
    send_telegram_alert,
)

__all__ = [
    "log_event_to_database",
    "is_private_or_local_ip",
    "get_network_context",
    "get_geolocation",
    "load_configured_proxies",
    "send_telegram_alert",
    "sanitize_session_token",
    "record_session_start",
    "calculate_session_duration",
    "find_interactive_terminal_pids",
    "process_pam_event",
]
