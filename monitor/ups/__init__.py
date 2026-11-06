# monitor/ups/__init__.py
"""
Paquete modular de monitoreo y gestión de energía UPS S.O.N.A.R.
"""
from monitor.ups.daemon.alert_builder import (
    build_ups_battery_low_alert,
    build_ups_fluctuation_alert,
    build_ups_outage_alert,
    build_ups_recovery_alert,
)
from monitor.ups.daemon.worker_daemon import poll_and_process_ups, run_ups_daemon
from monitor.ups.domain.battery_policy import (
    CELLS_PER_12V_BATTERY,
    TOTAL_BATTERIES,
    TOTAL_CELLS,
    V_BANK_MAX,
    V_BANK_MIN,
    V_CELL_MAX,
    V_CELL_MIN,
    calculate_battery_percentage,
)
from monitor.ups.domain.duration_formatter import (
    format_duration,
    get_notification_target,
    get_notification_targets,
)
from monitor.ups.drivers.megatec_driver import (
    query_ups_hardware,
    query_ups_info,
    query_ups_rating,
    query_ups_serial,
)
from monitor.ups.itam.detect_hardware_action import scan_and_detect_ups
from monitor.ups.itam.sync_inventory_action import sync_ups_to_inventory

__all__ = [
    "calculate_battery_percentage",
    "format_duration",
    "get_notification_target",
    "get_notification_targets",
    "build_ups_outage_alert",
    "build_ups_recovery_alert",
    "build_ups_fluctuation_alert",
    "build_ups_battery_low_alert",
    "poll_and_process_ups",
    "run_ups_daemon",
    "query_ups_info",
    "query_ups_rating",
    "query_ups_serial",
    "query_ups_hardware",
    "scan_and_detect_ups",
    "sync_ups_to_inventory",
    "CELLS_PER_12V_BATTERY",
    "TOTAL_BATTERIES",
    "TOTAL_CELLS",
    "V_BANK_MAX",
    "V_BANK_MIN",
    "V_CELL_MAX",
    "V_CELL_MIN",
]
