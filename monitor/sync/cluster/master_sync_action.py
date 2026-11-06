# monitor/sync/cluster/master_sync_action.py
import json
import os
import time
from typing import Optional

from monitor.infrastructure.db_context import atomic_transaction
from monitor.infrastructure.monitor_db import get_db_connection
from monitor.sync.cluster.master_client import MasterClusterClient
from monitor.sync.cluster.table_replicators import TableReplicationService
from monitor.sync.scheduler import (
    SNAPSHOT_FILE,
    update_last_scan_time,
    update_cluster_status,
)

class SyncFromMasterAction:
    """Acción de responsabilidad única encargada de sincronizar telemetría y catálogo en Modo Esclavo/Dev."""

    def __init__(self, client: Optional[MasterClusterClient] = None):
        """Inicializa la instancia con sus dependencias y configuración operativa.

        Args:
            client: Parámetro operativo de entrada.
        """
        self.client = client or MasterClusterClient.from_config()

    async def execute(self) -> bool:
        """Ejecuta la acción de negocio o tarea programada de forma asíncrona."""
        start_time = time.perf_counter()

        success, data = await self.client.fetch_telemetry()
        if not success or not data:
            update_cluster_status("error")
            return False

        snapshot_payload = data.get("snapshot", {})

        # 1. Guardar archivo JSON estático de Laravel
        try:
            SNAPSHOT_FILE.parent.mkdir(parents=True, exist_ok=True)
            SNAPSHOT_FILE.write_text(json.dumps(snapshot_payload, indent=2, ensure_ascii=False), encoding="utf-8")
            try:
                os.chmod(SNAPSHOT_FILE, 0o666)
            except Exception:
                pass
        except Exception as e_snap:
            print(f"⚠️ [MODO ESCLAVO] Aviso escribiendo snapshot estático: {e_snap}")

        # 2. Guardar en base de datos MariaDB local bajo transacción atómica única (F-11)
        try:
            with atomic_transaction() as conn:
                with conn.cursor() as cursor:
                    try:
                        cursor.execute("SET FOREIGN_KEY_CHECKS = 0")

                        # Snapshot global
                        sql = """
                            INSERT INTO monitoring_snapshots 
                            (global_status, services_online, services_total, sites_online, sites_total, proxies_online, proxies_total, payload_json, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                        """
                        sumry = snapshot_payload.get("summary", {})
                        cursor.execute(sql, (
                            snapshot_payload.get("global_status", "OPERACIONAL"),
                            sumry.get("services_online", 0),
                            sumry.get("services_total", 0),
                            sumry.get("sites_online", 0),
                            sumry.get("sites_total", 0),
                            sumry.get("proxies_online", 0),
                            sumry.get("proxies_total", 0),
                            json.dumps(snapshot_payload, ensure_ascii=False),
                        ))

                        cfg = data.get("config", {})

                        # Modular Replicators
                        TableReplicationService.replicate_core_catalog(cursor, cfg)
                        TableReplicationService.replicate_histories(cursor, data)
                        TableReplicationService.replicate_snmp_discovery(cursor, data)
                        TableReplicationService.replicate_security_and_alerts(cursor, data)
                        TableReplicationService.replicate_netradar_and_topology(cursor, data)
                        TableReplicationService.replicate_ups(cursor, data)
                        TableReplicationService.replicate_itam(cursor, data)
                    finally:
                        try:
                            cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
                        except Exception:
                            pass
        except Exception as e_db:
            print(f"⚠️ [MODO ESCLAVO] Aviso actualizando base de datos local: {e_db}")

        elapsed = round(time.perf_counter() - start_time, 2)
        update_last_scan_time()
        update_cluster_status("ok")

        gen_at = snapshot_payload.get("timestamp", "N/A")
        g_stat = snapshot_payload.get("global_status", "N/A")
        print(f"✅ [MODO ESCLAVO] Telemetría sincronizada con éxito desde Master ({self.client.master_url}) en {elapsed}s | Snapshot: {gen_at} | Estado: {g_stat}")
        return True
