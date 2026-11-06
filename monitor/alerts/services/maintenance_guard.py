# monitor/alerts/services/maintenance_guard.py
import json
from datetime import datetime
from typing import Any

class MaintenanceGuard:
    """Servicio desacoplado para validar ventanas de mantenimiento activas."""

    @staticmethod
    def is_in_maintenance_window(db: Any, entity_type: str, entity_id: int, severity: str) -> bool:
        """Verifica si la entidad se encuentra bajo una ventana de mantenimiento activa."""
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        sql = """
            SELECT id, suppress_severities FROM maintenance_windows
            WHERE is_active = 1
              AND starts_at <= %s AND ends_at >= %s
              AND (entity_type = 'all' OR (entity_type = %s AND (entity_id IS NULL OR entity_id = %s)))
            LIMIT 1
        """
        with db.cursor() as cur:
            cur.execute(sql, (now, now, entity_type, entity_id))
            row = cur.fetchone()
            if not row:
                return False

            suppress = row.get("suppress_severities")
            if not suppress:
                return True
            try:
                if isinstance(suppress, str):
                    suppress = json.loads(suppress)
                if isinstance(suppress, list) and len(suppress) > 0:
                    return severity in suppress
                return True
            except Exception:
                return True
