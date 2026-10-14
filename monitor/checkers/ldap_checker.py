from __future__ import annotations

from monitor.checker_base import check_ldap
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.config_parser import ServiceConfig


class LdapChecker(BaseServiceChecker):
    """Verificador de directorio activo y autenticación LDAP corporativo."""

    async def check(self, svc: ServiceConfig) -> CheckResult:
        is_ok, code, detail = await check_ldap(svc.ip_host, svc.ldap_port_ip)
        return CheckResult(is_ok=is_ok, code=str(code), detail=detail)
