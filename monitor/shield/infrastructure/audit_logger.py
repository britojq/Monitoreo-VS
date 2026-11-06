"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD AUDIT LOGGER: audit_logger.py
# Registro estructurado de auditoría en tabla audit_logs de MariaDB y limpieza segura
# Ubicación: /opt/sonar/monitor/shield/infrastructure/audit_logger.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import subprocess
from datetime import datetime
from pathlib import Path

from monitor.infrastructure.monitor_db import get_db_connection

logger = logging.getLogger("monitor.terminal_shield")


def log_event_to_database(
    user_name: str,
    event: str,
    description: str,
    ip_address: str = "127.0.0.1",
    user_agent: str = "Sentinel Shield",
    user_role: str = "admin",
) -> bool:
    """Registra el evento de seguridad en la tabla audit_logs de MariaDB."""
    try:
        conn = get_db_connection(connect_timeout=3)
        with conn.cursor() as cur:
            now_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            cur.execute(
                """
                INSERT INTO audit_logs (
                    user_name, user_role, event, module, description,
                    ip_address, user_agent, created_at, updated_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    user_name,
                    user_role,
                    event,
                    "pam",
                    description,
                    ip_address,
                    user_agent,
                    now_ts,
                    now_ts,
                ),
            )
        conn.close()
        return True
    except Exception as e:
        logger.debug(f"Error registrando en MariaDB: {e}")
        return False


def safe_unlink(p: Path) -> None:
    """Elimina de forma segura un archivo flag, soportando permisos root en /dev/shm."""
    try:
        p.unlink(missing_ok=True)
    except PermissionError:
        try:
            p.write_text("", encoding="utf-8")
        except Exception:
            pass
        try:
            subprocess.run(
                ["sudo", "rm", "-f", str(p)],
                check=False,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )
        except Exception:
            pass
    except Exception:
        pass
