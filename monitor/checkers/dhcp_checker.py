from __future__ import annotations

from monitor.checker_base import check_dhcp
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class DhcpChecker(BaseServiceChecker):
    """Verificador de asignación de direcciones DHCP."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        is_ok, code, detail = await check_dhcp(svc.net_interface)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
