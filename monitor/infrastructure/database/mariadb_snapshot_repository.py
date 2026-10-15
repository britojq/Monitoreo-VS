"""
# ==============================================================================
# 📸 REPOSITORIO MARIADB DE SNAPSHOTS: mariadb_snapshot_repository.py
# Implementación de SnapshotRepositoryProtocol sobre MariaDB (SSOT).
# ==============================================================================
"""

from __future__ import annotations

import json
import logging
from typing import Optional

from monitor.application.dtos.telemetry_dto import SnapshotDTO
from monitor.domain.protocols.database_protocol import SnapshotRepositoryProtocol
from monitor.infrastructure.database.mariadb_connection import MariaDBConnectionPool

logger = logging.getLogger("infrastructure.repository.snapshot")


class MariaDBSnapshotRepository(SnapshotRepositoryProtocol):
    """Repositorio para gestionar snapshots de monitoreo en MariaDB."""

    def __init__(self, pool: type[MariaDBConnectionPool] = MariaDBConnectionPool) -> None:
        self._pool = pool

    def save_snapshot(self, snapshot: SnapshotDTO) -> int:
        """Persiste un snapshot consolidado en la tabla monitoring_snapshots."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                payload_str = json.dumps(snapshot.payload_json, ensure_ascii=False)
                cur.execute(
                    """
                    INSERT INTO monitoring_snapshots (
                        total_sites, up_sites, down_sites,
                        total_services, up_services, down_services,
                        total_devices, up_devices, down_devices,
                        payload_json, created_at
                    ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
                    """,
                    (
                        snapshot.total_sites,
                        snapshot.up_sites,
                        snapshot.down_sites,
                        snapshot.total_services,
                        snapshot.up_services,
                        snapshot.down_services,
                        snapshot.total_devices,
                        snapshot.up_devices,
                        snapshot.down_devices,
                        payload_str,
                    ),
                )
                conn.commit()
                return cur.lastrowid or 0
        finally:
            conn.close()

    def get_latest_snapshot(self) -> Optional[SnapshotDTO]:
        """Obtiene el snapshot más reciente."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT * FROM monitoring_snapshots
                    ORDER BY id DESC LIMIT 1
                    """
                )
                row = cur.fetchone()
                if not row:
                    return None

                payload = row.get("payload_json")
                if isinstance(payload, str):
                    try:
                        payload = json.loads(payload)
                    except Exception:
                        payload = {}
                elif not isinstance(payload, dict):
                    payload = {}

                return SnapshotDTO(
                    total_sites=int(row.get("total_sites", 0)),
                    up_sites=int(row.get("up_sites", 0)),
                    down_sites=int(row.get("down_sites", 0)),
                    total_services=int(row.get("total_services", 0)),
                    up_services=int(row.get("up_services", 0)),
                    down_services=int(row.get("down_services", 0)),
                    total_devices=int(row.get("total_devices", 0)),
                    up_devices=int(row.get("up_devices", 0)),
                    down_devices=int(row.get("down_devices", 0)),
                    payload_json=payload,
                    created_at=row.get("created_at"),
                )
        finally:
            conn.close()
