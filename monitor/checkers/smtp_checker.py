from __future__ import annotations

from monitor.checker_base import check_smtp
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class SmtpChecker(BaseServiceChecker):
    """Verificador de servidores de correo saliente SMTP."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        is_ok, code, detail = await check_smtp(svc.ip_host, svc.smtp_port)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
