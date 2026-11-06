"""
Paquete modular de respaldo de configuraciones WAN/LAN.
"""
from monitor.checkers.backup.backup_manager import ConfigBackupManager
from monitor.checkers.backup.cisco_ssh_client import (
    calculate_hash,
    fetch_cisco_config_ssh,
    normalize_config,
)
from monitor.checkers.backup.pfsense_ssh_client import fetch_pfsense_config_ssh
from monitor.checkers.backup.unified_diff_engine import (
    generate_unified_diff,
    get_diff_for_configuration,
)
from monitor.checkers.backup.backup_orchestrator import (
    backup_all_configured_devices,
    backup_network_device_by_id,
    backup_snmp_device_by_id,
    backup_target_device,
    get_backups_summary,
    list_latest_backups,
    record_backup_failure,
    save_device_backup,
)

__all__ = [
    "ConfigBackupManager",
    "normalize_config",
    "calculate_hash",
    "generate_unified_diff",
    "fetch_cisco_config_ssh",
    "fetch_pfsense_config_ssh",
    "save_device_backup",
    "record_backup_failure",
    "backup_target_device",
    "backup_network_device_by_id",
    "backup_snmp_device_by_id",
    "backup_all_configured_devices",
    "get_backups_summary",
    "list_latest_backups",
    "get_diff_for_configuration",
]
