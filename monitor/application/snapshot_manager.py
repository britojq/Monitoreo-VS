"""
# ==============================================================================
# 📸 GESTIÓN DE SNAPSHOTS DE MONITOREO: snapshot_manager.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Recuperación y persistencia atómica de estados consolidados en MariaDB (SSOT).
# Ubicación: /opt/sonar/monitor/application/snapshot_manager.py
# ==============================================================================
"""

from __future__ import annotations

import json
import logging
from typing import Any, Dict, Optional, Tuple

from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.application.snapshot_manager")


def get_latest_snapshot() -> Tuple[Optional[Dict[str, Any]], Optional[str]]:
    """
    Recupera el último snapshot registrado en la tabla monitoring_snapshots de MariaDB.
    Retorna una tupla: (payload_dict, timestamp_formateado).
    """
    try:
        with db_cursor() as cur:
            cur.execute(
                "SELECT payload_json, created_at FROM monitoring_snapshots ORDER BY id DESC LIMIT 1"
            )
            row = cur.fetchone()
            if not row:
                return None, None

            payload = row.get("payload_json")
            if isinstance(payload, str):
                try:
                    payload = json.loads(payload)
                except Exception as json_err:
                    logger.warning(f"Error decodificando payload_json de snapshot: {json_err}")
                    return None, None

            created_at = row.get("created_at")
            dt_str = (
                created_at.strftime("%Y-%m-%d %H:%M:%S")
                if hasattr(created_at, "strftime")
                else str(created_at)
            )
            return payload, dt_str
    except Exception as e:
        logger.error(f"Fallo al cargar snapshot consolidado desde MariaDB: {e}")
        return None, None


def save_snapshot_payload(payload: Dict[str, Any], global_status: str = "UP") -> int:
    """
    Persiste un nuevo snapshot estructurado en la tabla monitoring_snapshots.
    Retorna el ID del registro insertado.
    """
    try:
        payload_json = json.dumps(payload, ensure_ascii=False)
        with db_cursor(autocommit=True) as cur:
            cur.execute(
                """
                INSERT INTO monitoring_snapshots (global_status, payload_json, created_at, updated_at)
                VALUES (%s, %s, NOW(), NOW())
                """,
                (global_status, payload_json),
            )
            return int(cur.lastrowid or 0)
    except Exception as e:
        logger.error(f"Error persistiendo snapshot en MariaDB: {e}")
        return 0
