# monitor/sync/evaluators/site_evaluator.py
import asyncio
from typing import Any, Dict, List
from monitor.sync.probes.network_probes import check_wan_quality, check_ping
from monitor.sync.dtos.scan_dtos import SiteDeviceEvaluationResult, SiteEvaluationResult

async def evaluate_site(site: Dict[str, Any], devices: List[Dict[str, Any]]) -> Dict[str, Any]:
    """Evalúa el estado operacional y telemetría de site.

    Args:
        site: Parámetro operativo de entrada.
        devices: Parámetro operativo de entrada.
    """
    ip = site.get("ip") or ""
    wan = await check_wan_quality(ip, count=5)
    is_up = wan["is_up"]
    latency = wan["latency_ms"]

    # Evaluar dispositivos secundarios concurrentemente (solo configurados y activos)
    valid_devices = [
        d for d in devices
        if d.get("is_active")
        and "NO CONFIGURADO" not in (d.get("name") or "").upper()
        and d.get("ip") not in ("0.0.0.0", "127.0.0.1", "")
    ]
    dev_tasks = [check_ping(d.get("ip")) for d in valid_devices]
    dev_results = await asyncio.gather(*dev_tasks) if dev_tasks else []

    evaluated_devices = []
    for idx, d in enumerate(valid_devices):
        d_up, d_lat = dev_results[idx]
        evaluated_devices.append(
            SiteDeviceEvaluationResult(
                id=d["id"],
                device_number=d["device_number"],
                name=d["name"],
                ip=d["ip"],
                access_type=d.get("access_type") or "SIN SOPORTE",
                access_port=d.get("access_port"),
                status="ACTIVO" if d_up else "APAGADO",
                is_up=d_up,
                latency_ms=d_lat,
            ).to_dict()
        )

    return SiteEvaluationResult(
        id=site["id"],
        letter=site["letter"],
        name=site["name"],
        ip=ip,
        address=site.get("address") or "",
        status="ACTIVO" if is_up else "APAGADO",
        is_up=is_up,
        latency_ms=latency,
        packet_loss_pct=wan["packet_loss_pct"],
        jitter_ms=wan["jitter_ms"],
        min_rtt_ms=wan["min_rtt_ms"],
        max_rtt_ms=wan["max_rtt_ms"],
        mdev_ms=wan["mdev_ms"],
        devices=evaluated_devices,
    ).to_dict()
