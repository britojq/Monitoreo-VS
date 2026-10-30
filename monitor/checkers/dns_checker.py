from __future__ import annotations

from monitor.checker_base import check_dns
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class DnsChecker(BaseServiceChecker):
    """Verificador de resolución de nombres DNS corporativos."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        is_ok, code, detail = await check_dns(svc.ip_host, svc.dns_test_host)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
