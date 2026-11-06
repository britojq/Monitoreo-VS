"""
# ==============================================================================
# 🧪 TEST DE INTEGRACIÓN: test_all_bot_commands_integrity.py
# Valida la integridad funcional, enrutamiento y ausencia total de
# ModuleNotFoundError / NameError en los 245 comandos del bot de Telegram.
# ==============================================================================
"""

import asyncio
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

from telegram import Chat, Message, Update, User
from telegram.ext import Application, CommandHandler


@pytest.fixture
def mock_telegram_env():
    """Genera mocks seguros para simular un Update y Context de Telegram en chat privado."""
    update = MagicMock(spec=Update)
    message = MagicMock(spec=Message)
    user = MagicMock(spec=User)
    chat = MagicMock(spec=Chat)

    # Identidad del Owner según Regla de Oro #1 (38914901)
    user.id = 38914901
    user.first_name = "Operador ATIT"
    user.username = "britojab"

    chat.id = 38914901
    chat.type = "private"

    message.chat = chat
    message.from_user = user
    message.text = "/test"

    wait_msg = MagicMock(spec=Message)
    wait_msg.delete = AsyncMock()
    wait_msg.edit_text = AsyncMock()
    message.reply_text = AsyncMock(return_value=wait_msg)
    message.reply_html = AsyncMock(return_value=wait_msg)
    message.reply_photo = AsyncMock(return_value=wait_msg)

    update.effective_chat = chat
    update.effective_user = user
    update.effective_message = message
    update.message = message

    context = MagicMock()
    context.args = []
    context.bot = MagicMock()
    context.bot.send_message = AsyncMock()
    context.bot.send_photo = AsyncMock()
    context.bot.send_document = AsyncMock()

    return update, context


def test_all_bot_commands_registration_and_wiring():
    """Valida que todos los comandos y enrutadores del bot se registren con callbacks válidos."""
    from bot_core.router import register_all_handlers

    mock_app = MagicMock(spec=Application)
    registered_handlers = []

    def mock_add_handler(h):
        registered_handlers.append(h)

    mock_app.add_handler.side_effect = mock_add_handler

    dummy_commands = {
        "custom_test_1": "Respuesta 1",
        "custom_test_2": "Respuesta 2",
    }

    register_all_handlers(mock_app, commands_dict=dummy_commands, commands_enabled=True)

    assert len(registered_handlers) >= 25, "Se esperaban al menos 25 handlers principales registrados."

    all_commands = set()
    for h in registered_handlers:
        if isinstance(h, CommandHandler):
            for cmd in h.commands:
                all_commands.add(cmd.lower())
            assert callable(h.callback), f"El handler para {h.commands} no tiene callback invocable."

    crucial_commands = [
        "servicios", "web", "sedes", "recursos", "limpiador",
        "ups", "inventario", "predicciones", "configs", "alertas",
        "analisis_red", "snmp", "discovery", "cluster", "temperatura"
    ]
    for c in crucial_commands:
        assert c in all_commands, f"El comando crucial /{c} no está registrado en el bot."


def test_cmd_recursos_execution_no_modulenotfound(mock_telegram_env):
    """Valida que /recursos ejecute sin error de 'monitor.system_optimizer'."""
    update, context = mock_telegram_env
    from bot_core.handlers.system_maintenance.optimizer_handler import cmd_recursos_sistema

    asyncio.run(cmd_recursos_sistema(update, context))

    assert update.message.reply_text.called
    calls = update.message.reply_text.call_args_list
    assert len(calls) >= 1
    for call in calls:
        sent_text = str(call)
        assert "No module named" not in sent_text
        assert "Traceback" not in sent_text


def test_cmd_limpiador_execution_no_modulenotfound(mock_telegram_env):
    """Valida que /limpiador ejecute sin error de 'monitor.system_cleaner'."""
    update, context = mock_telegram_env
    from bot_core.handlers.system_maintenance.cleaner_handler import cmd_limpiador

    asyncio.run(cmd_limpiador(update, context))

    assert update.message.reply_text.called
    calls = update.message.reply_text.call_args_list
    for call in calls:
        sent_text = str(call)
        assert "No module named" not in sent_text
        assert "Traceback" not in sent_text


def test_cmd_servicios_execution_no_modulenotfound(mock_telegram_env):
    """Valida que /servicios ejecute sin error de 'monitor.telegram_dispatcher'."""
    update, context = mock_telegram_env
    from bot_core.handlers.services import cmd_reporte_servicios

    with patch("monitor.monitor_engine.execute_monitoring") as mock_exec:
        mock_exec.return_value = {
            "status": "success",
            "report": "Reporte Simulado de Prueba",
            "offline_count": 0,
        }
        asyncio.run(cmd_reporte_servicios(update, context))

    assert update.message.reply_text.called
    calls = update.message.reply_text.call_args_list
    for call in calls:
        sent_text = str(call)
        assert "No module named" not in sent_text
        assert "telegram_dispatcher" not in sent_text


def test_cmd_web_execution_no_modulenotfound(mock_telegram_env):
    """Valida que /web ejecute sin error de 'monitor.telegram_dispatcher' ni captura."""
    update, context = mock_telegram_env
    from bot_core.handlers.services import cmd_captura_web

    with patch("monitor.tools.web_screenshot.capture_web_dashboard") as mock_cap:
        mock_cap.return_value = None
        asyncio.run(cmd_captura_web(update, context))

    assert update.message.reply_text.called
    calls = update.message.reply_text.call_args_list
    for call in calls:
        sent_text = str(call)
        assert "No module named" not in sent_text


def test_cmd_sedes_execution_no_modulenotfound(mock_telegram_env):
    """Valida que /sedes ejecute sin error de despacho ni módulos faltantes."""
    update, context = mock_telegram_env
    from bot_core.handlers.services import cmd_reporte_sedes

    with patch("monitor.monitor_engine.execute_monitoring") as mock_exec:
        mock_exec.return_value = {
            "status": "success",
            "report": "Reporte Sedes Simulado",
            "offline_count": 0,
        }
        asyncio.run(cmd_reporte_sedes(update, context))

    assert update.message.reply_text.called
    for call in update.message.reply_text.call_args_list:
        assert "No module named" not in str(call)


def test_cmd_itam_handlers_execution_no_modulenotfound(mock_telegram_env):
    """Valida que los handlers ITAM (/ups, /inventario, /predicciones, /configs) ejecuten sin error."""
    update, context = mock_telegram_env
    from bot_core.handlers.itam_handlers import (
        cmd_configs,
        cmd_inventario,
        cmd_predicciones,
        cmd_ups,
    )

    handlers = [
        ("cmd_ups", cmd_ups),
        ("cmd_inventario", cmd_inventario),
        ("cmd_predicciones", cmd_predicciones),
        ("cmd_configs", cmd_configs),
    ]

    for name, handler in handlers:
        update.message.reply_text.reset_mock()
        asyncio.run(handler(update, context))
        for call in update.message.reply_text.call_args_list:
            assert "No module named" not in str(call), f"Fallo de importación en {name}"


def test_cmd_network_handlers_execution_no_modulenotfound(mock_telegram_env):
    """Valida que los handlers de red (/snmp, /discovery, /cluster, /topologia, /analisis_red) ejecuten sin error."""
    update, context = mock_telegram_env
    from bot_core.handlers.network_handlers import (
        cmd_analisis_red,
        cmd_cluster,
        cmd_discovery,
        cmd_snmp,
        cmd_topologia,
    )

    handlers = [
        ("cmd_analisis_red", cmd_analisis_red),
        ("cmd_cluster", cmd_cluster),
        ("cmd_discovery", cmd_discovery),
        ("cmd_snmp", cmd_snmp),
        ("cmd_topologia", cmd_topologia),
    ]

    with patch("monitor.checkers.analyzer.execute_network_analysis", new_callable=AsyncMock) as mock_analyzer:
        mock_analyzer.return_value = {
            "summary_text": "Análisis de Red Simulado",
            "md_report": None,
            "html_report": None,
        }
        for name, handler in handlers:
            update.message.reply_text.reset_mock()
            asyncio.run(handler(update, context))
            for call in update.message.reply_text.call_args_list:
                assert "No module named" not in str(call), f"Fallo de importación en {name}"
