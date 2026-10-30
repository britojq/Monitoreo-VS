from __future__ import annotations

from monitor.checker_base import check_web
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class WebChecker(BaseServiceChecker):
    """Verificador de aplicaciones y portales web vía HTTP/HTTPS."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        is_ok, code, detail = await check_web(svc.web_url)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
