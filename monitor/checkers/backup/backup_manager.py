"""
Gestor de respaldo de configuraciones WAN/LAN y detección de cambios (SOLID Facade).
"""
import logging
from typing import Any, Dict, List, Optional, Tuple

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
from monitor.monitor_db import get_db_connection

logger = logging.getLogger("monitor.checkers.backup.manager")


class ConfigBackupManager:
    """Gestor unificado de respaldos de conmutadores, enrutadores y firewalls."""

    def __init__(self, db_conn=None):
        self._db = db_conn

    @property
    def db(self):
        if not self._db:
            self._db = get_db_connection()
        return self._db

    def _ensure_db(self):
        if self._db:
            try:
                self._db.ping(reconnect=True)
            except Exception:
                self._db = get_db_connection()

    @staticmethod
    def normalize_config(raw_config: str, device_type: str = "cisco_switch") -> str:
        return normalize_config(raw_config, device_type)

    @staticmethod
    def calculate_hash(text: str) -> str:
        return calculate_hash(text)

    @staticmethod
    def generate_unified_diff(
        old_text: str,
        new_text: str,
        label_old: str = "Versión Previa",
        label_new: str = "Versión Actual"
    ) -> Tuple[str, int, int]:
        return generate_unified_diff(old_text, new_text, label_old, label_new)

    async def fetch_cisco_config_ssh(
        self,
        ip: str,
        username: str,
        password: str,
        port: int = 22,
        secret: Optional[str] = None,
        timeout: float = 25.0
    ) -> Tuple[bool, str, str]:
        return await fetch_cisco_config_ssh(ip, username, password, port, secret, timeout)

    async def fetch_pfsense_config_ssh(
        self,
        ip: str,
        username: str,
        password: str,
        port: int = 22,
        timeout: float = 20.0
    ) -> Tuple[bool, str, str]:
        return await fetch_pfsense_config_ssh(ip, username, password, port, timeout)

    def save_device_backup(
        self,
        raw_config: str,
        device_name: str,
        device_ip: str,
        device_type: str = "cisco_switch",
        network_device_id: Optional[int] = None,
        snmp_device_id: Optional[int] = None,
        captured_by: str = "cron",
        notes: Optional[str] = None
    ) -> Dict[str, Any]:
        return save_device_backup(
            raw_config=raw_config,
            device_name=device_name,
            device_ip=device_ip,
            device_type=device_type,
            network_device_id=network_device_id,
            snmp_device_id=snmp_device_id,
            captured_by=captured_by,
            notes=notes
        )

    def record_backup_failure(
        self,
        device_name: str,
        device_ip: str,
        device_type: str,
        error_message: str,
        network_device_id: Optional[int] = None,
        snmp_device_id: Optional[int] = None,
        captured_by: str = "cron"
    ) -> None:
        record_backup_failure(
            device_name=device_name,
            device_ip=device_ip,
            device_type=device_type,
            error_message=error_message,
            network_device_id=network_device_id,
            snmp_device_id=snmp_device_id,
            captured_by=captured_by
        )

    async def backup_target_device(self, target: Dict[str, Any], captured_by: str = "cron") -> Dict[str, Any]:
        return await backup_target_device(target, captured_by)

    def backup_network_device_by_id(self, net_id: int, captured_by: str = "manual") -> Dict[str, Any]:
        return backup_network_device_by_id(net_id, captured_by)

    def backup_snmp_device_by_id(self, snmp_id: int, captured_by: str = "manual") -> Dict[str, Any]:
        return backup_snmp_device_by_id(snmp_id, captured_by)

    def backup_all_configured_devices(self, captured_by: str = "cron") -> List[Dict[str, Any]]:
        return backup_all_configured_devices(captured_by)

    def get_backups_summary(self) -> Dict[str, Any]:
        return get_backups_summary()

    def list_latest_backups(self, limit: int = 30) -> List[Dict[str, Any]]:
        return list_latest_backups(limit)

    def get_diff_for_configuration(self, config_id: int) -> Optional[Dict[str, Any]]:
        return get_diff_for_configuration(config_id)

    @staticmethod
    def generate_simulated_cisco_config(hostname: str, vlan_count: int = 4, extra_desc: str = "") -> str:
        """Generador sintético para tests unitarios/integración."""
        cfg = f"version 15.2\nhostname {hostname}\n"
        for v in range(1, vlan_count + 1):
            cfg += f"vlan {v * 10}\n name VLAN_{v * 10}\n!\n"
        if extra_desc:
            cfg += f"!\n{extra_desc}\n"
        cfg += "end\n"
        return cfg
