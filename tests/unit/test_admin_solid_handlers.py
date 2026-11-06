"""
Suite de Pruebas Unitarias para la Deconstrucción SOLID de admin_handlers.py (Fase 2).
Verifica contratos, DTOs inmutables, enrutamiento desacoplado y erradicación de NameError.
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from telegram import Update, User, Chat, Message, CallbackQuery
from telegram.ext import Application, ContextTypes

from bot_core.handlers.shield.dtos import ShieldCallbackPayload, ShieldAuthResultDto
from bot_core.handlers.shield.actions import (
    AuthorizeTerminalAction,
    KillTerminalAction,
    LockdownAction,
    UnlockAction,
    SoyYoAction,
)
from bot_core.handlers.shield.router import handle_shield_callback
from bot_core.handlers.system_maintenance.dtos import CleanerActionPayload, OptimizerActionPayload
from bot_core.handlers.thermal.dtos import ThermalMetricsDto
from bot_core.handlers.thermal.thermal_handler import format_thermal_dashboard_html
from bot_core.handlers.ai_control.ai_admin_handler import format_ai_control_panel_html
from bot_core.handlers.crons.dtos import CronActionPayload
from bot_core.handlers.crons.cron_handler import _format_cron_panel_text, _get_cron_keyboard
from bot_core.handlers.groups_guard.dtos import GroupAuditItemDto
from bot_core.handlers.rbac.dtos import RbacActionPayload
from bot_core.handlers.token_migration.dtos import MIGRAR_TOKEN_WAITING
from bot_core.handlers.diagnostics.dtos import EndpointHealthResultDto, BroadcastDeliveryResultDto
from bot_core.handlers.admin_router import register_admin_routes


def test_shield_callback_payload_parsing():
    raw = "shield_auth:CENCARATIT:1234:5678"
    payload = ShieldCallbackPayload.from_callback_data(raw)
    assert payload.action == "shield_auth"
    assert payload.target_server == "CENCARATIT"
    assert payload.target_pid == 1234
    assert payload.target_ppid == 5678


def test_authorize_terminal_action_executes_without_name_error():
    """Valida que execute_auth se ejecute sin NameError (reparación definitiva de línea 1744)."""
    import asyncio

    async def _run():
        action = AuthorizeTerminalAction()
        payload = ShieldCallbackPayload("shield_auth", "CENCARATIT", 9999, 8888, "raw")

        update = MagicMock(spec=Update)
        query = MagicMock(spec=CallbackQuery)
        query.answer = AsyncMock()
        query.edit_message_text = AsyncMock()
        update.callback_query = query

        context = MagicMock(spec=ContextTypes.DEFAULT_TYPE)

        with patch("monitor.terminal_shield.execute_auth") as mock_auth:
            mock_auth.return_value = {"tty": "pts/2", "expiry": "18:00:00"}
            await action.execute(payload, update, context)

            mock_auth.assert_called_once_with(minutes=30, tty=None, pid=9999)
            query.answer.assert_called_once_with("✅ Sesión autorizada por 30 minutos.")
            query.edit_message_text.assert_called_once()
            text_sent = query.edit_message_text.call_args[0][0]
            assert "SESIÓN AUTORIZADA POR EL ADMINISTRADOR" in text_sent
            assert "pts/2" in text_sent

    asyncio.run(_run())


def test_kill_terminal_action_executes_correctly():
    import asyncio

    async def _run():
        action = KillTerminalAction()
        payload = ShieldCallbackPayload("shield_kill", "CENCARATIT", 1234, 5678, "raw")

        update = MagicMock(spec=Update)
        query = MagicMock(spec=CallbackQuery)
        user = MagicMock(spec=User)
        user.username = "test_owner"
        query.from_user = user
        query.answer = AsyncMock()
        query.edit_message_text = AsyncMock()
        update.callback_query = query

        context = MagicMock(spec=ContextTypes.DEFAULT_TYPE)

        with patch("monitor.terminal_shield.execute_kill") as mock_kill:
            mock_kill.return_value = {"killed": ["bash (PID 1234)"], "infractions": 1, "reboot_triggered": False}
            await action.execute(payload, update, context)

            mock_kill.assert_called_once_with(1234, 5678)
            query.answer.assert_called_once_with("🚫 Neutralizando terminal...")
            assert query.edit_message_text.called

    asyncio.run(_run())


def test_thermal_dashboard_formatting():
    with patch("monitor.thermal_guard.get_cpu_temperatures") as mock_temp, \
         patch("monitor.thermal_guard.is_ai_service_active", return_value=False):
        mock_temp.return_value = {
            "badge": "🟢",
            "level": "NORMAL",
            "max_temp": 42.0,
            "package_temp": 40.0,
            "avg_temp": 39.5,
            "core_temps": {"Core 0": 42.0, "Core 1": 38.0}
        }
        text, markup = format_thermal_dashboard_html()
        assert "DIAGNÓSTICO TÉRMICO" in text
        assert "42.0°C" in text
        assert markup is not None


def test_ai_control_panel_formatting():
    with patch("monitor.thermal_guard.is_ai_service_active", return_value=False):
        text, markup = format_ai_control_panel_html()
        assert "CONTROL Y ADMINISTRACIÓN DEL ASISTENTE CORPORATIVO" in text
        assert "ollama" not in text.lower(), "Regla #2: No mencionar la tecnología de IA subyacente"
        assert markup is not None


def test_cron_panel_formatting():
    text = _format_cron_panel_text()
    assert "Control de Envíos Programados por Cron" in text
    keyboard = _get_cron_keyboard()
    assert keyboard is not None


def test_register_admin_routes_wires_all_submodules():
    app_mock = MagicMock(spec=Application)
    app_mock.add_handler = MagicMock()

    register_admin_routes(app_mock)

    assert app_mock.add_handler.call_count >= 20, "Debe registrar todos los handlers desacoplados"
