# monitor/sync/__init__.py
"""
Paquete modular de sincronización, telemetría y monitoreo web S.O.N.A.R.
Construido bajo principios SOLID y tipado estricto Python 3.13.
"""
from monitor.sync.actions import RunFullScanAction
from monitor.sync.cluster import SyncFromMasterAction, clean_mysql_dt
from monitor.sync.evaluators import (
    evaluate_network_device,
    evaluate_proxy,
    evaluate_service,
    evaluate_site,
)
from monitor.sync.probes import (
    SSL_PERMISSIVE_CTX,
    check_ping,
    check_proxy_service,
    check_tcp_port,
    check_wan_quality,
    check_web_service,
    create_permissive_ssl_context,
    get_known_proxy_auth,
)
from monitor.sync.scheduler import (
    get_last_scan_time,
    should_run_web_scan,
    update_cluster_status,
    update_last_scan_time,
)


async def run_full_scan() -> dict:
    """Ejecuta el escaneo NOC completo delegando en RunFullScanAction."""
    return await RunFullScanAction().execute()


async def sync_from_master() -> dict:
    """Ejecuta la sincronización desde el servidor master."""
    return await SyncFromMasterAction().execute()


__all__ = [
    "RunFullScanAction",
    "SyncFromMasterAction",
    "clean_mysql_dt",
    "evaluate_service",
    "evaluate_site",
    "evaluate_proxy",
    "evaluate_network_device",
    "create_permissive_ssl_context",
    "SSL_PERMISSIVE_CTX",
    "get_known_proxy_auth",
    "check_proxy_service",
    "check_ping",
    "check_wan_quality",
    "check_tcp_port",
    "check_web_service",
    "get_last_scan_time",
    "update_last_scan_time",
    "should_run_web_scan",
    "update_cluster_status",
    "run_full_scan",
    "sync_from_master",
]
