# monitor/sync/evaluators/service_evaluator.py
from __future__ import annotations

from typing import Any, Dict, Optional
from monitor.checkers.registry import CheckerRegistry
from monitor.parsers import ServiceConfig
from monitor.infrastructure.proxy_cache import normalize_proxy_key
from monitor.sync.dtos.scan_dtos import ServiceEvaluationResult
from monitor.sync.probes.proxy_probe import get_known_proxy_auth


async def evaluate_service(s: Dict[str, Any], proxy_cache: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """Evalúa el estado operacional delegando en la estrategia especializada correspondiente (OCP / Strategy Pattern / ARC-03)."""
    stype = (s.get("type") or "WEB").upper().strip()
    ip = s.get("host_ip") or ""
    url = s.get("web_url") or ""
    port = s.get("port")
    creds = s.get("credentials") or ""
    if creds == "USUARIO:CLAVE":
        creds = ""

    # Caso especial de caché en memoria de ronda de proxies para evitar saturar pfSense
    if stype == "PROXY" and proxy_cache:
        proxy_target = ip
        if ":" not in proxy_target and proxy_target:
            proxy_target = f"{proxy_target}:{port or 8080}"
        norm_target = normalize_proxy_key(proxy_target)
        norm_ip = normalize_proxy_key(ip)
        if norm_target in proxy_cache:
            is_up, latency = proxy_cache[norm_target]
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
                http_code=200 if is_up else 0,
            ).to_dict()
        elif norm_ip in proxy_cache:
            is_up, latency = proxy_cache[norm_ip]
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
                http_code=200 if is_up else 0,
            ).to_dict()

    # Delegación polimórfica en la estrategia registrada (Strategy Pattern / ARC-03)
    checker = CheckerRegistry.get(stype)

    svc_config = ServiceConfig(
        id=s.get("id"),
        letter=s.get("letter") or f"S{s.get('id', 0)}",
        service_type=stype,
        name=s.get("name") or "Servicio",
        scope=(s.get("scope") or "corporativo").lower(),
        web_url=url or (f"http://{ip}" if stype == "WEB" and ip else ""),
        ip_host=ip,
        cups_port_ip=f"{ip}:{port or 631}" if stype == "CUPS" else "",
        ldap_port_ip=f"{ip}:{port or 389}" if stype == "LDAP" else "",
        smtp_port=str(port or 25) if stype == "SMTP" else "",
        proxy_user_pass=creds or (get_known_proxy_auth(f"{ip}:{port or 8080}") if stype == "PROXY" else ""),
        proxy_ip_port=f"{ip}:{port or 8080}" if stype == "PROXY" else "",
        allow_insecure_ssl=bool(s.get("allow_insecure_ssl", False)),
    )

    result = await checker.check(svc_config)

    http_code = int(result.code) if result.code.isdigit() and int(result.code) > 0 else None

    return ServiceEvaluationResult(
        id=s["id"],
        letter=s.get("letter") or f"S{s['id']}",
        name=s["name"],
        type=stype,
        scope=(s.get("scope") or "corporativo").lower(),
        host_ip=ip,
        web_url=url,
        status="ACTIVO" if result.is_ok else "APAGADO",
        is_up=result.is_ok,
        latency_ms=getattr(result, "latency_ms", 0.0),
        http_code=http_code,
    ).to_dict()
