from __future__ import annotations

from monitor.checker_base import check_cups
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class CupsChecker(BaseServiceChecker):
    """Verificador de servidores de impresión CUPS/IPP."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        is_ok, code, detail = await check_cups(svc.cups_port_ip)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
