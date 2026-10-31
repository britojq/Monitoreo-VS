"""
Fachada ultradelgada de retrocompatibilidad para SSH & PAM Alert Dispatcher.
Arquitectura modular delegada en monitor.shield.ssh_alert.
"""
from monitor.shield.ssh_alert import (  # noqa: F401
    calculate_session_duration,
    find_interactive_terminal_pids,
    get_geolocation,
    get_network_context,
    is_private_or_local_ip,
    load_configured_proxies,
    log_event_to_database,
    process_pam_event,
    record_session_start,
    sanitize_session_token,
    send_telegram_alert,
)
from monitor.shield.ssh_alert.ssh_alert_cli import main


if __name__ == "__main__":
    main()
