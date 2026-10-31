"""
Paquete modular de protección física y supervisión térmica de hardware.
"""
from monitor.checkers.thermal.constants import (
    AI_SERVICE_UNIT,
    CHECK_INTERVAL_SECONDS,
    DEFAULT_COOLDOWN_SECONDS,
    DEFAULT_TEMP_CRITICAL,
    DEFAULT_TEMP_RECOVERY,
    DEFAULT_TEMP_WARNING,
    IMMUTABLE_OWNER_ID,
)
from monitor.checkers.thermal.sensor_reader import (
    get_cpu_temperatures,
    get_local_ip_addresses,
)
from monitor.checkers.thermal.systemd_ai_controller import (
    is_ai_service_active,
    start_ai_service,
    stop_ai_service,
)
from monitor.checkers.thermal.thermal_guardian import (
    ThermalGuardian,
    print_cli_status,
    test_telegram_alert,
)
from monitor.checkers.thermal.thermal_notifier import (
    dispatch_telegram_alert,
    get_proxies_list,
    record_thermal_audit_event,
)

__all__ = [
    "DEFAULT_TEMP_WARNING",
    "DEFAULT_TEMP_CRITICAL",
    "DEFAULT_TEMP_RECOVERY",
    "DEFAULT_COOLDOWN_SECONDS",
    "CHECK_INTERVAL_SECONDS",
    "AI_SERVICE_UNIT",
    "IMMUTABLE_OWNER_ID",
    "get_local_ip_addresses",
    "get_cpu_temperatures",
    "is_ai_service_active",
    "stop_ai_service",
    "start_ai_service",
    "record_thermal_audit_event",
    "get_proxies_list",
    "dispatch_telegram_alert",
    "ThermalGuardian",
    "print_cli_status",
    "test_telegram_alert",
]
