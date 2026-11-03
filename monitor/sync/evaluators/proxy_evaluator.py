# monitor/sync/evaluators/proxy_evaluator.py
from typing import Any, Dict
from monitor.sync.probes.proxy_probe import check_proxy_service
from monitor.sync.dtos.scan_dtos import ProxyEvaluationResult

async def evaluate_proxy(p: Dict[str, Any]) -> Dict[str, Any]:
    """Evalúa el estado operacional y telemetría de proxy.

    Args:
        p: Parámetro operativo de entrada.
    """
    is_up, latency = await check_proxy_service(p.get("ip_port") or "", p.get("auth_userpass"), p.get("test_url") or "")
    return ProxyEvaluationResult(
        id=p["id"],
        letter=p["letter"],
        name=p["name"],
        ip_port=p.get("ip_port") or "",
        status="ACTIVO" if is_up else "APAGADO",
        is_up=is_up,
        latency_ms=latency,
    ).to_dict()
