"""
Verificación de reporte de inventario en base de datos MariaDB (SSOT).
"""

from typing import Any, Dict


def verify_asset_in_db(host_ip_or_name: str, since_minutes: int = 15) -> Dict[str, Any]:
    """
    Verifica en MariaDB (SSOT) si el equipo reportó telemetría recientemente al receptor GLPI.
    """
    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        with conn.cursor() as cur:
            sql = """
                SELECT id, name, ip_address, operating_system, model, serial_number, 
                       last_glpi_sync_at, status
                FROM inventory_assets
                WHERE (ip_address = %s OR name = %s)
                  AND (last_glpi_sync_at >= NOW() - INTERVAL %s MINUTE OR updated_at >= NOW() - INTERVAL %s MINUTE)
                ORDER BY updated_at DESC
                LIMIT 1
            """
            cur.execute(sql, (host_ip_or_name, host_ip_or_name, since_minutes, since_minutes))
            row = cur.fetchone()

        conn.close()
        if row:
            return {"verified": True, "asset": row}
        return {"verified": False, "message": "No se encontró registro de inventario reciente"}
    except Exception as e:
        return {"verified": False, "error": str(e)}
