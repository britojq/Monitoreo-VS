"""
# ==============================================================================
# 🏢 REPOSITORIO MARIADB DE SEDES: mariadb_site_repository.py
# Implementación de SiteRepositoryProtocol sobre MariaDB (SSOT).
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Dict, List, Optional

from monitor.domain.entities.site import EquipmentEntity, SiteEntity
from monitor.domain.protocols.database_protocol import SiteRepositoryProtocol
from monitor.infrastructure.database.mariadb_connection import MariaDBConnectionPool

logger = logging.getLogger("infrastructure.repository.site")


class MariaDBSiteRepository(SiteRepositoryProtocol):
    """Repositorio para gestionar sedes y sus equipos desde MariaDB."""

    def __init__(self, pool: type[MariaDBConnectionPool] = MariaDBConnectionPool) -> None:
        self._pool = pool

    def get_all_active(self) -> List[SiteEntity]:
        """Extrae todas las sedes y equipos activos desde MariaDB (SSOT)."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT * FROM monitored_sites WHERE is_active = 1 "
                    "AND name NOT IN ('NO CONFIGURADO', 'SIN CONFIGURAR') "
                    "ORDER BY sort_order, id"
                )
                site_rows = cur.fetchall()
                if not site_rows:
                    return []

                cur.execute(
                    "SELECT d.*, s.letter as site_letter FROM monitored_site_devices d "
                    "JOIN monitored_sites s ON d.monitored_site_id = s.id "
                    "WHERE d.is_active = 1 AND d.name NOT IN ('NO CONFIGURADO', 'SIN CONFIGURAR') "
                    "ORDER BY d.monitored_site_id, d.device_number, d.id"
                )
                dev_rows = cur.fetchall()

                dev_by_site_id: Dict[int, List[EquipmentEntity]] = {}
                for idx, d in enumerate(dev_rows):
                    site_id = d["monitored_site_id"]
                    dnum = int(d["device_number"]) if d.get("device_number") is not None else (idx + 1)
                    eq = EquipmentEntity(
                        id=d.get("id"),
                        name=(d.get("name") or "").strip(),
                        ip_host=(d.get("ip") or "").strip(),
                        site_letter=(d.get("site_letter") or "").strip().upper(),
                        num=dnum,
                        is_active=bool(d.get("is_active", 1)),
                        equipment_type=str(d.get("device_type") or "GENERIC").upper(),
                        snmp_community=d.get("snmp_community"),
                        mac_address=d.get("mac"),
                    )
                    dev_by_site_id.setdefault(site_id, []).append(eq)

                sites: List[SiteEntity] = []
                for s in site_rows:
                    site_id = s["id"]
                    raw_letter = (s.get("letter") or "").strip().upper()
                    letter = raw_letter or f"ST{site_id}"
                    site_eq = tuple(dev_by_site_id.get(site_id, []))

                    sites.append(
                        SiteEntity(
                            id=site_id,
                            name=(s.get("name") or "").strip(),
                            letter=letter,
                            ip_host=(s.get("ip") or "").strip(),
                            is_active=bool(s.get("is_active", 1)),
                            subnet=s.get("subnet"),
                            gateway=s.get("gateway"),
                            equipment=site_eq,
                        )
                    )
                return sites
        finally:
            conn.close()

    def get_by_letter(self, letter: str) -> Optional[SiteEntity]:
        """Busca una sede específica por su identificador de letra."""
        clean_letter = letter.strip().upper()
        for site in self.get_all_active():
            if site.letter == clean_letter:
                return site
        return None
