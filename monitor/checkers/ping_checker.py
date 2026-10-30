from __future__ import annotations

from monitor.checker_base import check_ping
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class PingChecker(BaseServiceChecker):
    """Verificador genérico de presencia y conectividad ICMP (Ping)."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        is_ok, code, detail = await check_ping(svc.ip_host)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
