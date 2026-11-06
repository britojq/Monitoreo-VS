"""
# ==============================================================================
# 🏓 ESTRATEGIA DE VERIFICACIÓN ICMP (PING): ping_checker.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from typing import Tuple

from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.parsers import ServiceConfig
from monitor.infrastructure.validators import validate_hostname, validate_ipv4


async def check_ping(ip: str, count: int = 3, timeout: float = 3.0) -> Tuple[bool, str, str]:
    """Verifica conectividad ICMP (Ping) hacia una dirección IP."""
    if not ip or ip.startswith("0.0.0.0") or ip == "127.0.0.1":
        return False, "0", "IP no asignada o inválida (0.0.0.0)"

    clean_ip = ip.strip()
    if not (validate_ipv4(clean_ip) or validate_hostname(clean_ip)):
        return False, "0", f"Formato de host o dirección IP inválido: {ip}"

    try:
        proc = await asyncio.create_subprocess_exec(
            "ping", "-qc", str(count), "-W", "2", clean_ip,
            stdout=asyncio.subprocess.DEVNULL,
            stderr=asyncio.subprocess.DEVNULL,
        )
        code = await asyncio.wait_for(proc.wait(), timeout=timeout)
        if code == 0:
            return True, "200", f"Responde a Ping ICMP ({clean_ip})"
        return False, "0", f"Sin respuesta de Ping ({clean_ip})"
    except asyncio.TimeoutError:
        return False, "0", f"Timeout en Ping ({clean_ip})"
    except Exception as e:
        return False, "0", f"Error ejecutando Ping ({clean_ip}): {e}"


class PingChecker(BaseServiceChecker):
    """Verificador genérico de presencia y conectividad ICMP (Ping)."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        """Ejecuta la comprobación de estado y métricas operativas del servicio."""
        is_ok, code, detail = await check_ping(svc.ip_host)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
