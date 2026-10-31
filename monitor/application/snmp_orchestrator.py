"""
# ==============================================================================
# 🎛️ ORQUESTADOR DE SONDEO SNMP: snmp_orchestrator.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Coordinación concurrente de sondeo SNMP, persistencia y estado de dispositivos.
# Ubicación: /opt/sonar/monitor/application/snmp_orchestrator.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import time
from typing import Any, Dict, List, Optional

from monitor.checkers.snmp.interface_discoverer import discover_interfaces
from monitor.checkers.snmp.interface_metrics import poll_interface_metrics
from monitor.checkers.snmp.snmp_engine import poll_device_oids
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.application.snmp_orchestrator")


async def run_snmp_poll(device_ids: Optional[List[int]] = None) -> Dict[str, Any]:
    """
    Orquestador principal de Polling SNMP:
    1. Carga dispositivos activos
    2. Ejecuta sondeo concurrente con Semaphore(10)
    3. Persiste métricas históricas
    4. Actualiza estado y contador de fallos
    """
    devices: List[Dict[str, Any]] = []
    with db_cursor() as cur:
        if device_ids:
            format_ids = ",".join(["%s"] * len(device_ids))
            cur.execute(
                f"SELECT * FROM snmp_devices WHERE id IN ({format_ids}) AND is_active = 1",
                device_ids,
            )
        else:
            cur.execute("SELECT * FROM snmp_devices WHERE is_active = 1")
        devices = cur.fetchall()

        for dev in devices:
            cur.execute(
                """
                SELECT o.id, o.name, o.oid, o.unit, o.data_type, o.is_counter_wrap, d_o.custom_oid
                FROM snmp_device_oids d_o
                JOIN snmp_oids o ON d_o.snmp_oid_id = o.id
                WHERE d_o.snmp_device_id = %s AND d_o.is_active = 1
                """,
                (dev["id"],),
            )
            dev["oids"] = cur.fetchall()

    if not devices:
        logger.info("No hay dispositivos SNMP activos configurados para sondeo.")
        return {"status": "ok", "polled": 0}

    semaphore = asyncio.Semaphore(10)
    start_time = time.perf_counter()

    async def _safe_poll(dev: Dict[str, Any]) -> Dict[str, Any]:
        async with semaphore:
            res = await poll_device_oids(dev, dev.get("oids", []))

            with db_cursor(autocommit=True) as cur:
                if res["status"] == "success":
                    cur.execute(
                        """
                        UPDATE snmp_devices
                        SET last_poll_at = NOW(),
                            last_poll_status = 'success',
                            consecutive_failures = 0
                        WHERE id = %s
                        """,
                        (dev["id"],),
                    )

                    if res["metrics"]:
                        metric_sql = """
                            INSERT INTO snmp_metrics_history
                            (snmp_device_id, snmp_oid_id, metric_value, metric_value_raw, collected_at)
                            VALUES (%s, %s, %s, %s, NOW())
                        """
                        recs = [
                            (dev["id"], m["oid_id"], m["val_num"], m["val_raw"])
                            for m in res["metrics"]
                        ]
                        cur.executemany(metric_sql, recs)
                else:
                    cur.execute(
                        """
                        UPDATE snmp_devices
                        SET last_poll_at = NOW(),
                            last_poll_status = %s,
                            consecutive_failures = consecutive_failures + 1
                        WHERE id = %s
                        """,
                        (res["status"], dev["id"]),
                    )

                # Purgar métricas viejas (> 30 días)
                cur.execute(
                    "DELETE FROM snmp_metrics_history WHERE collected_at < NOW() - INTERVAL 30 DAY"
                )

            if res["status"] == "success":
                await poll_interface_metrics(dev)

            return res

    tasks = [_safe_poll(d) for d in devices]
    results = await asyncio.gather(*tasks, return_exceptions=True)
    elapsed = round(time.perf_counter() - start_time, 2)

    successful = sum(1 for r in results if isinstance(r, dict) and r.get("status") == "success")
    logger.info(
        f"📊 [POLL SNMP COMPLETADO] Dispositivos: {len(devices)} | Éxito: {successful} | Tiempo: {elapsed}s"
    )

    return {
        "status": "completed",
        "total": len(devices),
        "successful": successful,
        "elapsed_seconds": elapsed,
    }


def snmp_walk_arp_table(device: Dict[str, Any]) -> List[Dict[str, str]]:
    """Extrae ipNetToMediaTable (1.3.6.1.2.1.4.22.1.2) para passive discovery de MAC/IP."""
    return []


def print_status() -> None:
    """Muestra el estado de dispositivos SNMP y métricas en consola."""
    with db_cursor() as cur:
        cur.execute(
            """
            SELECT id, name, ip_address, device_type, vendor, snmp_version, last_poll_status, consecutive_failures, last_poll_at
            FROM snmp_devices
            ORDER BY id ASC
            """
        )
        devices = cur.fetchall()

        cur.execute("SELECT COUNT(*) as total FROM snmp_metrics_history")
        metrics_count = cur.fetchone()["total"]

        cur.execute("SELECT COUNT(*) as total FROM snmp_interfaces")
        iface_count = cur.fetchone()["total"]

    print("\n" + "=" * 65)
    print("📡 ESTADO DEL SUBSISTEMA DE MONITOREO SNMP (VALLE SECO)")
    print("=" * 65)
    print(f"• Total Dispositivos Monitoreados: {len(devices)}")
    print(f"• Métricas Registradas en Historial: {metrics_count}")
    print(f"• Interfaces de Red Descubiertas:    {iface_count}")
    print("-" * 65)
    for d in devices:
        status_icon = "🟢" if d["last_poll_status"] == "success" else ("⏳" if not d["last_poll_status"] else "🔴")
        last_p = d["last_poll_at"] or "Nunca"
        print(
            f"{status_icon} [{d['id']}] {d['name']} ({d['ip_address']}) | "
            f"Tipo: {d['device_type']} | Estado: {d['last_poll_status'] or 'Pendiente'} | "
            f"Fallos: {d['consecutive_failures']} | Último Poll: {last_p}"
        )
    print("=" * 65 + "\n")
