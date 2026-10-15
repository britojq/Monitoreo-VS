"""
# ==============================================================================
# 🖧 REPOSITORIO MARIADB DE DISPOSITIVOS DE RED: mariadb_network_device_repository.py
# Implementación de NetworkDeviceRepositoryProtocol sobre MariaDB (SSOT).
# Cumple estrictamente con la Regla de Oro #7 y Regla de Oro #9.
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import List, Optional

from monitor.domain.entities.network_device import NetworkDeviceEntity
from monitor.domain.protocols.database_protocol import NetworkDeviceRepositoryProtocol
from monitor.infrastructure.database.mariadb_connection import MariaDBConnectionPool

logger = logging.getLogger("infrastructure.repository.network_device")


class MariaDBNetworkDeviceRepository(NetworkDeviceRepositoryProtocol):
    """Repositorio para gestionar dispositivos de red desde MariaDB."""

    def __init__(self, pool: type[MariaDBConnectionPool] = MariaDBConnectionPool) -> None:
        self._pool = pool

    def get_all_active(self) -> List[NetworkDeviceEntity]:
        """Extrae todos los dispositivos de red activos del censo legítimo."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT d.*, s.name as site_name FROM monitored_network_devices d "
                    "LEFT JOIN monitored_sites s ON d.site_id = s.id "
                    "WHERE d.is_active = 1 AND d.name NOT LIKE '%NO CONFIGURADO%' "
                    "AND d.ip != '0.0.0.0' "
                    "ORDER BY d.sort_order, d.id"
                )
                rows = cur.fetchall()
                devices: List[NetworkDeviceEntity] = []
                for r in rows:
                    devices.append(
                        NetworkDeviceEntity(
                            id=r.get("id"),
                            label_name=(r.get("name") or "").strip(),  # Nombre Etiqueta (SSOT)
                            ip_address=(r.get("ip") or "").strip(),
                            hostname=(r.get("hostname") or "").strip() or None,  # Nombre Técnico
                            mac_address=(r.get("mac") or "").strip() or None,
                            device_type=str(r.get("device_type") or "GENERIC").upper(),
                            site_id=r.get("site_id"),
                            site_name=(r.get("site_name") or "").strip() or None,
                            is_active=bool(r.get("is_active", 1)),
                            assigned_to=(r.get("assigned_to") or "").strip() or None,
                            notes=r.get("notes"),
                        )
                    )
                return devices
        finally:
            conn.close()

    def get_by_ip(self, ip: str) -> Optional[NetworkDeviceEntity]:
        """Busca un dispositivo por su dirección IP."""
        clean_ip = ip.strip()
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT d.*, s.name as site_name FROM monitored_network_devices d "
                    "LEFT JOIN monitored_sites s ON d.site_id = s.id "
                    "WHERE d.ip = %s AND d.is_active = 1 LIMIT 1",
                    (clean_ip,),
                )
                r = cur.fetchone()
                if not r:
                    return None
                return NetworkDeviceEntity(
                    id=r.get("id"),
                    label_name=(r.get("name") or "").strip(),
                    ip_address=(r.get("ip") or "").strip(),
                    hostname=(r.get("hostname") or "").strip() or None,
                    mac_address=(r.get("mac") or "").strip() or None,
                    device_type=str(r.get("device_type") or "GENERIC").upper(),
                    site_id=r.get("site_id"),
                    site_name=(r.get("site_name") or "").strip() or None,
                    is_active=bool(r.get("is_active", 1)),
                    assigned_to=(r.get("assigned_to") or "").strip() or None,
                    notes=r.get("notes"),
                )
        finally:
            conn.close()
