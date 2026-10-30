#!/usr/bin/env python3
"""
==============================================================================
🌐 MONITOR WEB SYNC ENGINE - Fachada Delegadora SOLID
Ubicación: /opt/sonar/monitor/monitor_web_sync.py
Deconstruido en paquetes modulares bajo monitor/sync/.
Mantiene paridad de contratos e interfaces para servicios y crons.
==============================================================================
"""

import asyncio
import sys
from pathlib import Path
from typing import Any, Optional

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# Re-export de conector MariaDB SSOT
from monitor.monitor_db import get_db_connection

# Re-exports de programación y rate limit
from monitor.sync.scheduler import (
    get_last_scan_time,
    update_last_scan_time,
    should_run_web_scan,
    update_cluster_status as _update_cluster_status,
)

# Re-exports de sondas de red y SSL
from monitor.sync.probes import (
    create_permissive_ssl_context,
    SSL_PERMISSIVE_CTX,
    get_known_proxy_auth,
    check_proxy_service,
    check_ping,
    check_wan_quality,
    check_tcp_port,
    check_web_service,
)

# Re-exports de evaluadores de red
from monitor.sync.evaluators import (
    evaluate_service,
    evaluate_site,
    evaluate_proxy,
    evaluate_network_device,
)

# Re-exports de cluster y sanitización
from monitor.sync.cluster import (
    clean_mysql_dt as _clean_mysql_dt,
    SyncFromMasterAction,
)
from monitor.sync.actions import RunFullScanAction

def sync_conf_to_db() -> None:
    """MariaDB es la única fuente de verdad (SSOT). Ya no se usan archivos planos .conf."""
    pass

async def run_full_scan() -> dict:
    """Ejecuta el escaneo NOC completo delegando en RunFullScanAction."""
    return await RunFullScanAction().execute()

async def sync_from_master() -> bool:
    """Ejecuta la sincronización clúster desde el nodo Master delegando en SyncFromMasterAction."""
    return await SyncFromMasterAction().execute()

if __name__ == "__main__":
    force_run = ("--force" in sys.argv or "-f" in sys.argv)
    force_local = ("--local" in sys.argv or "--force-local" in sys.argv)

    should_run, interval_min, remaining = should_run_web_scan(force=force_run)
    if not should_run:
        print(f"⏳ Escaneo web en espera (frecuencia configurada: {interval_min} min). Faltan {int(remaining)}s para el próximo ciclo.")
        sys.exit(0)

    # Determinar rol del nodo
    node_role = "master"
    try:
        from bot_core.services.config_manager import load_config
        cfg = load_config()
        node_role = str(cfg.get("node_role", "master")).lower()
    except Exception:
        pass

    if node_role in ("slave", "dev") and not force_local:
        success = asyncio.run(sync_from_master())
        sys.exit(0 if success else 1)
    else:
        asyncio.run(run_full_scan())
