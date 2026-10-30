# monitor/sync/evaluators/network_device_evaluator.py
from typing import Any, Dict
from monitor.sync.probes.network_probes import check_ping
from monitor.sync.dtos.scan_dtos import NetworkDeviceEvaluationResult

async def evaluate_network_device(d: Dict[str, Any]) -> Dict[str, Any]:
    is_up, latency = await check_ping(d.get("ip") or "")
    return NetworkDeviceEvaluationResult(
        id=d["id"],
        device_number=d.get("device_number"),
        name=d["name"],
        ip=d.get("ip") or "",
        mac=d.get("mac") or "",
        vendor_data=d.get("vendor_data") or "",
        access_type=d.get("access_type") or "SIN SOPORTE",
        access_port=d.get("access_port"),
        normal_state_msg=d.get("normal_state_msg") or "",
        error_state_msg=d.get("error_state_msg") or "",
        status="ACTIVO" if is_up else "APAGADO",
        is_up=is_up,
        latency_ms=latency,
    ).to_dict()
