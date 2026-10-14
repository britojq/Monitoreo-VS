"""
Pruebas unitarias para la arquitectura polimórfica de verificadores de red (Patrón Strategy - Principio OCP).
"""

import pytest
import asyncio
from unittest.mock import AsyncMock, patch

from monitor.config_parser import ServiceConfig
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.checkers.registry import CheckerRegistry
from monitor.checkers.web_checker import WebChecker
from monitor.checkers.dns_checker import DnsChecker
from monitor.checkers.proxy_checker import ProxyChecker
from monitor.checkers.ping_checker import PingChecker
from monitor.monitor_servicios import verify_single_service


class DummyCustomChecker(BaseServiceChecker):
    """Verificador personalizado para probar el Principio Abierto/Cerrado (OCP)."""
    async def check(self, svc: ServiceConfig) -> CheckResult:
        return CheckResult(is_ok=True, code="200", detail="Custom check OK")


class TestCheckersPolymorphic:

    def test_registry_contains_standard_protocols(self):
        """Verifica que el registro cargue todas las estrategias estándar."""
        assert isinstance(CheckerRegistry.get("WEB"), WebChecker)
        assert isinstance(CheckerRegistry.get("DNS"), DnsChecker)
        assert isinstance(CheckerRegistry.get("PROXY"), ProxyChecker)
        assert isinstance(CheckerRegistry.get("PING"), PingChecker)

    def test_registry_fallback_to_ping_for_unknown_protocol(self):
        """Verifica que protocolos desconocidos hagan fallback seguro a PingChecker."""
        checker = CheckerRegistry.get("UNKNOWN_PROTOCOL_XYZ")
        assert isinstance(checker, PingChecker)

    def test_open_closed_principle_extensibility(self):
        """
        Demuestra el Principio Abierto/Cerrado (OCP):
        Se puede registrar una nueva estrategia sin modificar el código central.
        """
        CheckerRegistry.register("CUSTOM_NTP", DummyCustomChecker())
        checker = CheckerRegistry.get("CUSTOM_NTP")
        assert isinstance(checker, DummyCustomChecker)

    @pytest.mark.anyio
    async def test_verify_single_service_with_web_strategy_mocked(self):
        """Verifica que verify_single_service mantenga el contrato de retorno intacto."""
        svc = ServiceConfig(
            letter="A",
            name="Portal Web Corporativo",
            service_type="WEB",
            web_url="http://10.20.23.221",
        )

        with patch("monitor.checkers.web_checker.check_web", new_callable=AsyncMock) as mock_check:
            mock_check.return_value = (True, "200", "HTTP 200 OK")

            letter, is_ok, log_text, state_label = await verify_single_service(svc)

            assert letter == "A"
            assert is_ok is True
            assert state_label == "ACTIVO"
            assert "SERVICIO: [A] Portal Web Corporativo (WEB)" in log_text
            assert "HTTP 200 OK" in log_text

    @pytest.mark.anyio
    async def test_verify_single_service_failure_label(self):
        """Verifica que ante fallo de servicio, el estado devuelva APAGADO."""
        svc = ServiceConfig(
            letter="B",
            name="DNS Local",
            service_type="DNS",
            ip_host="10.20.0.1",
            dns_test_host="empresa.local",
        )

        with patch("monitor.checkers.dns_checker.check_dns", new_callable=AsyncMock) as mock_check:
            mock_check.return_value = (False, "0", "Timeout DNS")

            letter, is_ok, log_text, state_label = await verify_single_service(svc)

            assert letter == "B"
            assert is_ok is False
            assert state_label == "APAGADO"
            assert "Timeout DNS" in log_text
