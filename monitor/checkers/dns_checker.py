"""
# ==============================================================================
# 🔍 ESTRATEGIA DE VERIFICACIÓN DNS: dns_checker.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from typing import Tuple

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig
from monitor.infrastructure.validators import validate_hostname, validate_ipv4


async def check_dns(dns_server: str, test_host: str, timeout: float = 5.0) -> Tuple[bool, str, str]:
    """Verifica resolución DNS usando dig @dns_server test_host con reintentos para enlaces WAN."""
    if not dns_server or dns_server.startswith("0.0.0.0"):
        return False, "0", "Servidor DNS no configurado"

    target_host = test_host if test_host else "1.1.1.1"

    clean_dns = dns_server.lstrip("@").strip()
    if not (validate_ipv4(clean_dns) or validate_hostname(clean_dns)):
        return False, "0", f"Servidor DNS con formato inválido: {dns_server}"
    if not (validate_ipv4(target_host) or validate_hostname(target_host)):
        return False, "0", f"Host de prueba DNS con formato inválido: {target_host}"

    try:
        proc = await asyncio.create_subprocess_exec(
            "dig", f"@{clean_dns}", target_host, "+time=2", "+tries=2",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")
        if proc.returncode == 0 and (";; Got answer:" in out_str or "ANSWER: " in out_str):
            return True, "200", "Servicio DNS responde correctamente"
        return False, "0", f"Sin respuesta DNS válida: {out_str[:120]}"
    except asyncio.TimeoutError:
        return False, "0", "Timeout en consulta DNS"
    except Exception as e:
        return False, "0", f"Error ejecutando dig: {e}"


class DnsChecker(BaseServiceChecker):
    """Verificador de servicios de resolución de nombres DNS."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de resolución DNS."""
        is_ok, code, detail = await check_dns(svc.ip_host, svc.dns_test_host)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
