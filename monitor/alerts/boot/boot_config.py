"""
Gestión de parámetros y configuración de arranque/parada desde MariaDB SSOT.
"""

import logging
import socket
import subprocess
from typing import Any, Dict

from monitor.shield.secure_core import IMMUTABLE_OWNER_ID, get_core_bot_token

logger = logging.getLogger("monitor.alerts.boot.config")


def get_bot_setting(key: str, default: str = "0") -> str:
    """Consulta una clave en MariaDB bot_settings (SSOT)."""
    try:
        from monitor.infrastructure.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT setting_value FROM bot_settings WHERE setting_key = %s LIMIT 1", (key,))
                row = cur.fetchone()
                if row and row[0] is not None:
                    return str(row[0]).strip()
        finally:
            conn.close()
    except Exception as e:
        logger.warning(f"Error consultando bot_settings ({key}): {e}")
    return default


def set_bot_setting(key: str, value: str) -> bool:
    """Actualiza o inserta una clave en MariaDB bot_settings (SSOT)."""
    try:
        from monitor.infrastructure.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    INSERT INTO bot_settings (setting_key, setting_value, updated_at)
                    VALUES (%s, %s, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    """,
                    (key, str(value))
                )
            conn.commit()
            return True
        finally:
            conn.close()
    except Exception as e:
        logger.warning(f"Error actualizando bot_settings ({key} = {value}): {e}")
        return False


def load_config() -> Dict[str, Any]:
    """Carga configuración desde MariaDB vía config_manager (SSOT) y fuerza parámetros inmutables."""
    try:
        from bot_core.services.config_manager import load_config as load_bot_config
        cfg = load_bot_config()
    except Exception as e:
        logger.error(f"Error leyendo configuración desde MariaDB SSOT: {e}")
        cfg = {}
    # Blindaje inmutable de Owner ID y Bot Token dinámico verificado
    cfg["owner_id"] = IMMUTABLE_OWNER_ID
    cfg["bot_token"] = get_core_bot_token()
    return cfg


def get_local_ips() -> str:
    """Obtiene las direcciones IP locales activas del sistema."""
    try:
        res = subprocess.run(["hostname", "-I"], capture_output=True, text=True, timeout=3)
        ips = res.stdout.strip()
        if ips:
            return ips
    except Exception:
        pass

    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "No asignada (sin red detectada)"
