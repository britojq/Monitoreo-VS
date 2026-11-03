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
        """Inicializa la instancia con sus dependencias y configuración operativa.

        Args:
            db_conn: Parámetro operativo de entrada.
        """
        self._db = db_conn

    @property
    def db(self):
        """Propiedad que retorna la conexión o cursor activo de base de datos."""
        if not self._db:
            self._db = get_db_connection()
        return self._db

    def _ensure_db(self):
        """Garantiza que la conexión a base de datos esté activa y disponible."""
        if self._db:
            try:
                self._db.ping(reconnect=True)
            except Exception:
                self._db = get_db_connection()

    @staticmethod
    def normalize_config(raw_config: str, device_type: str = "cisco_switch") -> str:
        """Normaliza la configuración de red eliminando líneas dinámicas y marcas de tiempo.

        Args:
            raw_config: Parámetro operativo de entrada.
            device_type: Parámetro operativo de entrada.
        """
        return normalize_config(raw_config, device_type)

    @staticmethod
    def calculate_hash(text: str) -> str:
        """Calcula el hash criptográfico SHA-256 del contenido proporcionado.

        Args:
            text: Parámetro operativo de entrada.
        """
        return calculate_hash(text)

    @staticmethod
    def generate_unified_diff(
        old_text: str,
        new_text: str,
        label_old: str = "Versión Previa",
        label_new: str = "Versión Actual"
    ) -> Tuple[str, int, int]:
        """Genera la diferencia unificada contextual entre dos versiones de texto.

        Args:
            old_text: Parámetro operativo de entrada.
            new_text: Parámetro operativo de entrada.
            label_old: Parámetro operativo de entrada.
            label_new: Parámetro operativo de entrada.
        """
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
        """Descarga la configuración activa de un dispositivo Cisco mediante SSH.

        Args:
            ip: Parámetro operativo de entrada.
            username: Parámetro operativo de entrada.
            password: Parámetro operativo de entrada.
            port: Parámetro operativo de entrada.
            secret: Parámetro operativo de entrada.
            timeout: Parámetro operativo de entrada.
        """
        return await fetch_cisco_config_ssh(ip, username, password, port, secret, timeout)

    async def fetch_pfsense_config_ssh(
        self,
        ip: str,
        username: str,
        password: str,
        port: int = 22,
        timeout: float = 20.0
    ) -> Tuple[bool, str, str]:
        """Descarga la copia de seguridad XML de un router pfSense vía SSH.

        Args:
            ip: Parámetro operativo de entrada.
            username: Parámetro operativo de entrada.
            password: Parámetro operativo de entrada.
            port: Parámetro operativo de entrada.
            timeout: Parámetro operativo de entrada.
        """
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
        """Registra y versiona el respaldo de configuración de un dispositivo en base de datos.

        Args:
            raw_config: Parámetro operativo de entrada.
            device_name: Parámetro operativo de entrada.
            device_ip: Parámetro operativo de entrada.
            device_type: Parámetro operativo de entrada.
            network_device_id: Parámetro operativo de entrada.
            snmp_device_id: Parámetro operativo de entrada.
            captured_by: Parámetro operativo de entrada.
            notes: Parámetro operativo de entrada.
        """
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
        """Registra en bitácora un fallo durante el proceso de respaldo de configuración.

        Args:
            device_name: Parámetro operativo de entrada.
            device_ip: Parámetro operativo de entrada.
            device_type: Parámetro operativo de entrada.
            error_message: Parámetro operativo de entrada.
            network_device_id: Parámetro operativo de entrada.
            snmp_device_id: Parámetro operativo de entrada.
            captured_by: Parámetro operativo de entrada.
        """
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
        """Ejecuta el flujo de respaldo sobre el dispositivo destino especificado.

        Args:
            target: Parámetro operativo de entrada.
            captured_by: Parámetro operativo de entrada.
        """
        return await backup_target_device(target, captured_by)

    def backup_network_device_by_id(self, net_id: int, captured_by: str = "manual") -> Dict[str, Any]:
        """Inicia el respaldo de un dispositivo de red referenciado por su identificador.

        Args:
            net_id: Parámetro operativo de entrada.
            captured_by: Parámetro operativo de entrada.
        """
        return backup_network_device_by_id(net_id, captured_by)

    def backup_snmp_device_by_id(self, snmp_id: int, captured_by: str = "manual") -> Dict[str, Any]:
        """Inicia el respaldo de un equipo SNMP referenciado por su identificador.

        Args:
            snmp_id: Parámetro operativo de entrada.
            captured_by: Parámetro operativo de entrada.
        """
        return backup_snmp_device_by_id(snmp_id, captured_by)

    def backup_all_configured_devices(self, captured_by: str = "cron") -> List[Dict[str, Any]]:
        """Orquesta el respaldo secuencial de todos los dispositivos configurados.

        Args:
            captured_by: Parámetro operativo de entrada.
        """
        return backup_all_configured_devices(captured_by)

    def get_backups_summary(self) -> Dict[str, Any]:
        """Obtiene el consolidado estadístico de respaldos registrados en el sistema."""
        return get_backups_summary()

    def list_latest_backups(self, limit: int = 30) -> List[Dict[str, Any]]:
        """Retorna el listado de los respaldos más recientes almacenados.

        Args:
            limit: Parámetro operativo de entrada.
        """
        return list_latest_backups(limit)

    def get_diff_for_configuration(self, config_id: int) -> Optional[Dict[str, Any]]:
        """Recupera la diferencia unificada para una versión específica de configuración.

        Args:
            config_id: Parámetro operativo de entrada.
        """
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
