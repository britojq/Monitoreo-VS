"""
# ==============================================================================
# ⚙️ REPOSITORIO MARIADB DE SERVICIOS: mariadb_service_repository.py
# Implementación de ServiceRepositoryProtocol sobre MariaDB (SSOT).
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import List

from monitor.domain.entities.service import ServiceEntity
from monitor.domain.protocols.database_protocol import ServiceRepositoryProtocol
from monitor.infrastructure.database.mariadb_connection import MariaDBConnectionPool

logger = logging.getLogger("infrastructure.repository.service")


class MariaDBServiceRepository(ServiceRepositoryProtocol):
    """Repositorio para gestionar servicios monitoreados desde MariaDB."""

    def __init__(self, pool: type[MariaDBConnectionPool] = MariaDBConnectionPool) -> None:
        self._pool = pool

    def get_all_active(self) -> List[ServiceEntity]:
        """Extrae la lista de servicios activos directamente desde MariaDB (SSOT)."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT * FROM monitored_services WHERE is_active = 1 "
                    "AND name NOT IN ('NO CONFIGURADO', 'SIN CONFIGURAR') "
                    "ORDER BY sort_order, id"
                )
                rows = cur.fetchall()
                if not rows:
                    return []

                services: List[ServiceEntity] = []
                for r in rows:
                    name = (r.get("name") or "").strip()
                    stype = (r.get("type") or "WEB").strip().upper()
                    raw_ip = (r.get("host_ip") or "").strip()
                    clean_ip = raw_ip.split(":")[0] if raw_ip else ""
                    port = r.get("port")
                    web_url = (r.get("web_url") or "").strip()
                    target = web_url or clean_ip

                    services.append(
                        ServiceEntity(
                            id=r.get("id"),
                            name=name,
                            protocol=stype,
                            target=target,
                            port=int(port) if port else None,
                            is_active=bool(r.get("is_active", 1)),
                            timeout_seconds=float(r.get("timeout_seconds") or 5.0),
                            expected_status_code=int(r.get("expected_status_code") or 200),
                            path=r.get("path"),
                            description=r.get("description"),
                        )
                    )
                return services
        finally:
            conn.close()
