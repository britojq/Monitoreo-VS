# monitor/sync/evaluators/service_evaluator.py
from typing import Any, Dict, Optional
from monitor.proxy_cache import normalize_proxy_key
from monitor.sync.probes.network_probes import (
    check_web_service,
    check_tcp_port,
    check_ping,
)
from monitor.sync.probes.proxy_probe import (
    check_proxy_service,
    get_known_proxy_auth,
)
from monitor.sync.dtos.scan_dtos import ServiceEvaluationResult

async def evaluate_service(s: Dict[str, Any], proxy_cache: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    stype = (s.get("type") or "WEB").upper()
    ip = s.get("host_ip") or ""
    url = s.get("web_url") or ""
    port = s.get("port")

    is_up = False
    latency = 0.0
    http_code: Optional[int] = None

    if stype == "WEB":
        is_up, http_code, latency = await check_web_service(url or f"http://{ip}")
    elif stype == "LDAP":
        target_port = port if port else 389
        is_up, latency = await check_tcp_port(ip, target_port)
    elif stype == "SMTP":
        target_port = port if port else 25
        is_up, latency = await check_tcp_port(ip, target_port)
    elif stype == "CUPS":
        target_port = port if port else 631
        is_up, latency = await check_tcp_port(ip, target_port)
    elif stype == "DNS":
        is_up, latency = await check_tcp_port(ip, 53)
    elif stype == "PROXY":
        proxy_target = ip
        if ":" not in proxy_target and proxy_target:
            proxy_target = f"{proxy_target}:{port or 8080}"

        norm_target = normalize_proxy_key(proxy_target)
        norm_ip = normalize_proxy_key(ip)

        # Deduplicación en ciclo: Si este proxy físico ya fue evaluado en la ronda de proxies, reutilizar en memoria
        if proxy_cache and norm_target in proxy_cache:
            is_up, latency = proxy_cache[norm_target]
        elif proxy_cache and norm_ip in proxy_cache:
            is_up, latency = proxy_cache[norm_ip]
        else:
            auth = s.get("credentials")
            if not auth or auth == "USUARIO:CLAVE":
                auth = get_known_proxy_auth(proxy_target)
            is_up, latency = await check_proxy_service(proxy_target, auth)
    else:  # PING / OTRO
        is_up, latency = await check_ping(ip)

    return ServiceEvaluationResult(
        id=s["id"],
        letter=s.get("letter") or f"S{s['id']}",
        name=s["name"],
        type=stype,
        scope=(s.get("scope") or "corporativo").lower(),
        host_ip=ip,
        web_url=url,
        status="ACTIVO" if is_up else "APAGADO",
        is_up=is_up,
        latency_ms=latency,
        http_code=http_code,
    ).to_dict()
