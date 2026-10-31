"""Fachada ultradelgada de retrocompatibilidad para respaldo de configuraciones WAN/LAN."""
from monitor.checkers.backup import (  # noqa: F401
    ConfigBackupManager, calculate_hash, generate_unified_diff,
    get_diff_for_configuration, normalize_config, backup_target_device,
    backup_all_configured_devices, backup_network_device_by_id,
)
from monitor.checkers.backup.backup_cli import main

if __name__ == "__main__":
    main()

