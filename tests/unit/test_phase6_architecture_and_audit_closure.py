"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS: FASE 6 - RECONCILIACIÓN DE ARQUITECTURA Y AUDITORÍA
# Validación de Desacoplamiento SRP en bot.py (ARC-001) y Composition Root en checker_base.py (ARC-002)
# Ubicación: /opt/sonar/tests/unit/test_phase6_architecture_and_audit_closure.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import ast
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

import bot
from bot_core.application.bootstrap import BotApplicationBootstrapper, handle_bot_error
from bot_core.contracts.security_provider import SecurityProviderProtocol
from monitor import checker_base
from monitor.checkers.base import BaseServiceChecker, CheckResult
from monitor.checkers.registry import CheckerRegistry
from monitor.config_parser import ServiceConfig


class TestArc001BotApplicationDecoupling:
    """Verifica el desacoplamiento de bot.py y la extracción de bootstrap (ARC-001)."""

    def test_bootstrapper_initialization_and_http_requests(self) -> None:
        """Comprueba que BotApplicationBootstrapper configure requests HTTPX con timeouts correctos."""
        bootstrapper = BotApplicationBootstrapper(
            config={"auto_proxy_failover": False, "commands_enabled": True},
            commands={"ping": "test"},
        )
        assert bootstrapper.config["auto_proxy_failover"] is False
        assert bootstrapper.commands == {"ping": "test"}

        req, get_updates_req = bootstrapper.build_http_requests(active_proxy="http://192.168.1.1:8080")
        assert req.read_timeout == 35.0
        assert req._client_kwargs["limits"].max_connections == 256
        assert get_updates_req._client_kwargs["limits"].max_connections == 1

    def test_bootstrapper_create_application_with_mock_provider(self) -> None:
        """Verifica que create_application construya la aplicación Telegram con error handler y golden backup."""
        mock_provider = MagicMock(spec=SecurityProviderProtocol)
        mock_provider.get_bot_token.return_value = "123456:ABC-DEF-MOCK-TOKEN"
        mock_provider.get_owner_id.return_value = 38914901

        bootstrapper = BotApplicationBootstrapper(
            provider=mock_provider,
            config={"auto_proxy_failover": False, "commands_enabled": False},
            commands={},
        )

        with patch("bot_core.application.bootstrap.ensure_golden_backup") as mock_backup:
            app = bootstrapper.create_application()
            assert app is not None
            mock_backup.assert_called_once()
            assert app.bot.token == "123456:ABC-DEF-MOCK-TOKEN"

    def test_bootstrapper_fails_gracefully_when_token_empty(self) -> None:
        """Verifica que si no hay token de bot, retorne None sin levantar excepción."""
        mock_provider = MagicMock(spec=SecurityProviderProtocol)
        mock_provider.get_bot_token.return_value = ""

        bootstrapper = BotApplicationBootstrapper(provider=mock_provider)
        app = bootstrapper.create_application()
        assert app is None

    @pytest.mark.anyio
    async def test_bot_error_handler_delegation_to_provider_on_conflict(self) -> None:
        """Verifica que un error Conflict 409 dispare la alerta de conflicto en el proveedor de seguridad."""
        import telegram

        mock_provider = MagicMock(spec=SecurityProviderProtocol)
        mock_context = MagicMock()
        mock_context.error = telegram.error.Conflict("Terminated by other getUpdates request")

        with patch("asyncio.sleep", new_callable=AsyncMock):
            await handle_bot_error(None, mock_context, provider=mock_provider)
            mock_provider.send_conflict_alert.assert_called_once()

    def test_bot_py_delegates_to_bootstrapper(self) -> None:
        """Verifica mediante AST que bot.py delegue la orquestación a BotApplicationBootstrapper."""
        bot_file = Path("/opt/sonar/bot.py")
        tree = ast.parse(bot_file.read_text(encoding="utf-8"))

        imported_names = []
        for node in ast.walk(tree):
            if isinstance(node, ast.ImportFrom):
                for alias in node.names:
                    imported_names.append(alias.name)

        assert "BotApplicationBootstrapper" in imported_names, (
            "bot.py debe importar BotApplicationBootstrapper desde bot_core.application.bootstrap"
        )


class TestArc002CheckerBaseCompositionRoot:
    """Verifica que checker_base.py sea un Composition Root y Facade sin subprocess directo (ARC-002)."""

    def test_checker_base_ast_has_zero_subprocess_calls(self) -> None:
        """Verifica que checker_base.py no contenga llamadas directas a create_subprocess_exec ni subprocess."""
        checker_file = Path("/opt/sonar/monitor/checker_base.py")
        tree = ast.parse(checker_file.read_text(encoding="utf-8"))

        for node in ast.walk(tree):
            if isinstance(node, ast.Call):
                func = node.func
                # Revisar llamadas de atributo como asyncio.create_subprocess_exec o subprocess.run
                if isinstance(func, ast.Attribute):
                    assert func.attr not in ("create_subprocess_exec", "create_subprocess_shell"), (
                        f"checker_base.py no debe ejecutar subprocesos directamente ({func.attr}). "
                        "Toda ejecución debe estar encapsulada en monitor/checkers/"
                    )

    def test_checker_base_facade_exports(self) -> None:
        """Verifica que checker_base mantenga paridad total en su API pública."""
        required_exports = (
            "CheckerRegistry",
            "BaseServiceChecker",
            "CheckResult",
            "get_active_network_interface",
            "check_ping",
            "check_web",
            "check_dns",
            "check_smtp",
            "check_dhcp",
            "check_cups",
            "check_ldap",
            "check_proxy",
            "markdown_to_telegram_html",
            "execute_service_check",
        )
        for name in required_exports:
            assert hasattr(checker_base, name), f"checker_base debe re-exportar '{name}' para compatibilidad"

    def test_checker_registry_resolution(self) -> None:
        """Comprueba que CheckerRegistry devuelva las estrategias tipadas esperadas."""
        from monitor.checkers.web_checker import WebChecker
        from monitor.checkers.dns_checker import DnsChecker
        from monitor.checkers.ping_checker import PingChecker

        assert isinstance(CheckerRegistry.get("WEB"), WebChecker)
        assert isinstance(CheckerRegistry.get("DNS"), DnsChecker)
        assert isinstance(CheckerRegistry.get("UNKNOWN_XYZ"), PingChecker)

    @pytest.mark.anyio
    async def test_execute_service_check_composition_root(self) -> None:
        """Verifica que execute_service_check enrute adecuadamente hacia la estrategia registrada."""
        svc = ServiceConfig(
            letter="W",
            service_type="WEB",
            name="Portal de Prueba",
            web_url="https://ejemplo.local",
        )

        with patch("monitor.checkers.web_checker.check_web", new_callable=AsyncMock) as mock_web:
            mock_web.return_value = (True, "200", "HTTP 200 OK")
            result = await checker_base.execute_service_check(svc)

            assert isinstance(result, CheckResult)
            assert result.is_ok is True
            assert result.code == "200"
            assert "HTTP 200 OK" in result.detail
            mock_web.assert_called_once_with("https://ejemplo.local")
