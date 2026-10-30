"""
==============================================================================
📊 PROVEEDOR DE TELEMETRÍA E HISTÓRICO SSOT: ai_telemetry_provider.py
Principio de Responsabilidad Única (SRP) y Única Fuente de Verdad (SSOT).
Consulta exclusivamente MariaDB para suministrar telemetría viva al asistente.
==============================================================================
"""

from __future__ import annotations

import logging
from typing import List

from bot_core.contracts.ai_contracts import (
    ActasSummaryDTO,
    AiTelemetryProviderProtocol,
    InventorySummaryDTO,
    OutageSummaryDTO,
)

logger = logging.getLogger("bot.services.ai_telemetry")


class MariaDbAiTelemetryProvider(AiTelemetryProviderProtocol):
    """Proveedor oficial de telemetría de red, servicios e inventario desde MariaDB SSOT."""

    def get_live_outage_summary(self) -> OutageSummaryDTO:
        """Obtiene el consolidado en vivo de servicios caídos desde MariaDB."""
        # Intento 1: Snapshot en memoria si está disponible
        try:
            from monitor.monitor_engine import get_latest_snapshot
            payload, _ = get_latest_snapshot()
            if payload:
                summary = payload.get("summary", {})
                serv_total = int(summary.get("services_total", 0))
                serv_up = int(summary.get("services_online", 0))
                down = [
                    str(s.get("name"))
                    for s in payload.get("services", [])
                    if not s.get("is_up")
                ]
                if serv_total > 0:
                    return OutageSummaryDTO(
                        serv_total=serv_total,
                        serv_up=serv_up,
                        down_services=down,
                    )
        except Exception:
            pass

        # Intento 2: Consulta directa a MariaDB SSOT
        try:
            from monitor.monitor_db import get_db_connection
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute("SELECT name, is_active FROM monitored_services WHERE is_active = 1")
                    rows = cur.fetchall()
                    serv_total = len(rows)
                    down: List[str] = []
                    for r in rows:
                        cur.execute(
                            "SELECT status FROM service_check_histories WHERE service_name = %s ORDER BY checked_at DESC LIMIT 1",
                            (r["name"],)
                        )
                        h = cur.fetchone()
                        if h and h.get("status") != "UP":
                            down.append(str(r["name"]))
                    serv_up = serv_total - len(down)
                    return OutageSummaryDTO(
                        serv_total=serv_total,
                        serv_up=serv_up,
                        down_services=down,
                    )
            finally:
                conn.close()
        except Exception as exc:
            logger.warning("Error consultando telemetría de servicios en vivo: %s", exc)

        return OutageSummaryDTO(serv_total=0, serv_up=0, down_services=[])

    def get_live_inventory_summary(self) -> InventorySummaryDTO:
        """Consulta en tiempo real en MariaDB SSOT el total de activos y equipos con serial."""
        try:
            from monitor.monitor_db import get_db_connection
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute("SELECT COUNT(*) AS total FROM inventory_assets")
                    tot_row = cur.fetchone()
                    total = int(tot_row.get("total", 0)) if tot_row else 0

                    cur.execute(
                        "SELECT COUNT(*) AS with_serial FROM inventory_assets "
                        "WHERE serial_number IS NOT NULL AND serial_number != ''"
                    )
                    ser_row = cur.fetchone()
                    with_ser = int(ser_row.get("with_serial", 0)) if ser_row else 0
                    return InventorySummaryDTO(total_assets=total, with_serial=with_ser)
            finally:
                conn.close()
        except Exception as exc:
            logger.warning("Error consultando resumen de inventario en vivo: %s", exc)

        return InventorySummaryDTO(total_assets=0, with_serial=0)

    def get_live_actas_summary(self) -> ActasSummaryDTO:
        """Consulta en tiempo real en MariaDB SSOT las actas de asignación escaneadas en PDF."""
        try:
            from monitor.monitor_db import get_db_connection
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        "SELECT assignment_code, custodian_name, document_pdf_path "
                        "FROM inventory_assignments "
                        "WHERE document_pdf_path IS NOT NULL AND document_pdf_path != ''"
                    )
                    acta_rows = cur.fetchall()

                    cur.execute("SELECT COUNT(*) AS total FROM inventory_assets")
                    tot_row = cur.fetchone()
                    all_assets_count = int(tot_row.get("total", 0)) if tot_row else 0

                    items = [
                        f"• {a.get('assignment_code')}: Responsable: {a.get('custodian_name')} | Archivo: {a.get('document_pdf_path')}"
                        for a in acta_rows
                    ]
                    return ActasSummaryDTO(
                        acta_count=len(acta_rows),
                        total_assets=all_assets_count,
                        items=items,
                    )
            finally:
                conn.close()
        except Exception as exc:
            logger.warning("Error consultando actas en vivo: %s", exc)

        return ActasSummaryDTO(acta_count=0, total_assets=0, items=[])

    def get_subnets_summary(self) -> List[str]:
        """Consulta los segmentos de red operativos en MariaDB SSOT."""
        try:
            from monitor.monitor_db import get_db_connection
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        "SELECT s.subnet, COALESCE(si.name, 'Infraestructura General') AS site_name "
                        "FROM discovery_subnets s "
                        "LEFT JOIN monitored_sites si ON s.site_id = si.id "
                        "WHERE s.is_active = 1"
                    )
                    rows = cur.fetchall()
                    if rows:
                        return [f"• {sn.get('subnet')} ({sn.get('site_name')})" for sn in rows]
            finally:
                conn.close()
        except Exception as exc:
            logger.warning("Error consultando subredes en vivo: %s", exc)

        return []
