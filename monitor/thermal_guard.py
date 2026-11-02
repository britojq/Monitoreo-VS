"""Fachada ultradelgada de retrocompatibilidad para Thermal Guard."""
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.checkers.thermal import (  # noqa: F401
    AI_SERVICE_UNIT, DEFAULT_COOLDOWN_SECONDS, DEFAULT_TEMP_CRITICAL,
    DEFAULT_TEMP_RECOVERY, DEFAULT_TEMP_WARNING, IMMUTABLE_OWNER_ID,
    ThermalGuardian, dispatch_telegram_alert, get_cpu_temperatures,
    get_local_ip_addresses, get_proxies_list, is_ai_service_active,
    print_cli_status, record_thermal_audit_event, start_ai_service,
    stop_ai_service, test_telegram_alert,
)
from monitor.checkers.thermal.thermal_cli import main

if __name__ == "__main__":
    main()

