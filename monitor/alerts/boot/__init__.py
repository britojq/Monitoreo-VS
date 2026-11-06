"""
Submódulo de alertas y notificaciones de arranque y parada del sistema.
"""

from monitor.alerts.boot.boot_config import (
    get_bot_setting,
    get_local_ips,
    load_config,
    set_bot_setting,
)
from monitor.alerts.boot.boot_notifier import (
    send_shutdown_alert_fast,
    send_telegram_alert,
    wait_for_network_and_telegram,
)

__all__ = [
    "get_bot_setting",
    "set_bot_setting",
    "load_config",
    "get_local_ips",
    "send_telegram_alert",
    "wait_for_network_and_telegram",
    "send_shutdown_alert_fast",
]
