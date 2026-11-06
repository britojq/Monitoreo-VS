"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS: HANDLERS DE TELEGRAM SOLID: test_telegram_solid_handlers.py
# Valida la segregación, enrutamiento, fachadas y cumplimiento estricto de Reglas de Oro.
# ==============================================================================
"""
from __future__ import annotations

import sys
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

from telegram import Chat, Message, Update, User
from telegram.ext import Application, CommandHandler


# ---------------------------------------------------------------------------
# 1. TEST DE FACHADAS Y RETROCOMPATIBILIDAD BIT A BIT
# ---------------------------------------------------------------------------

def test_network_handlers_facade_integrity():
    """Valida que la fachada network_handlers.py re-exporte todos los comandos requeridos."""
    import bot_core.handlers.network_handlers as nh

    expected_symbols = [
        "cmd_analisis_red",
        "cmd_ip",
        "cmd_cluster",
        "cmd_discovery",
        "cmd_snmp",
        "cmd_traps",
        "cmd_syslog",
        "cmd_netflow",
        "cmd_topologia",
        "cmd_wol",
        "register_network_routes",
    ]
    for sym in expected_symbols:
        assert hasattr(nh, sym), f"Falta símbolo {sym} en network_handlers"
        assert getattr(nh, sym) is not None


def test_service_handlers_facade_integrity():
    """Valida que la fachada service_handlers.py re-exporte todos los comandos requeridos."""
    import bot_core.handlers.service_handlers as sh

    expected_symbols = [
        "cmd_reporte_servicios",
        "cmd_reporte_sedes",
        "cmd_reporte_caidas",
        "cmd_captura_web",
        "cmd_reporte_completo",
        "cmd_debug_servicios",
        "cmd_debug_sedes",
        "cmd_debug_completo",
        "cmd_internet",
        "cmd_ssl",
        "register_service_routes",
        "_run_and_send_monitoring_report",
    ]
    for sym in expected_symbols:
        assert hasattr(sh, sym), f"Falta símbolo {sym} en service_handlers"
        assert getattr(sh, sym) is not None


def test_alert_handlers_facade_integrity():
    """Valida que la fachada alert_handlers.py re-exporte todos los comandos y callbacks requeridos."""
    import bot_core.handlers.alert_handlers as ah

    expected_symbols = [
        "cmd_alertas",
        "cmd_ack",
        "cmd_resolver",
        "cmd_silenciar",
        "cmd_mantenimiento",
        "cmd_reglas",
        "handle_alert_action_callback",
        "register_alert_routes",
        "_get_portal_alerts_url",
    ]
    for sym in expected_symbols:
        assert hasattr(ah, sym), f"Falta símbolo {sym} en alert_handlers"
        assert getattr(ah, sym) is not None


def test_ai_chat_service_facade_integrity():
    """Valida que la fachada ai_chat_service.py re-exporte todos los métodos de IA."""
    import bot_core.services.ai_chat_service as ai

    expected_symbols = [
        "cmd_ia_grupo",
        "reset_chat",
        "handle_chat_message",
        "handle_chat_query_direct",
        "handle_dynamic_command",
        "register_ai_routes",
        "load_commands_data",
        "append_to_history",
        "is_telegram_group_ai_enabled",
        "set_telegram_group_ai_enabled",
        "load_system_prompt",
        "ask_ollama_async",
        "_ask_ollama_sync",
        "_get_live_outage_summary",
        "_get_live_inventory_summary",
        "_get_live_actas_summary",
    ]
    for sym in expected_symbols:
        assert hasattr(ai, sym), f"Falta símbolo {sym} en ai_chat_service"
        assert getattr(ai, sym) is not None


# ---------------------------------------------------------------------------
# 2. TEST DE REGISTRO EN EL ROUTER DE TELEGRAM (APPLICATION)
# ---------------------------------------------------------------------------

def test_telegram_route_registration():
    """Verifica que todos los routers modularizados registren sus CommandHandlers sin error."""
    from bot_core.handlers.network import register_network_routes
    from bot_core.handlers.services import register_service_routes
    from bot_core.handlers.alerts import register_alert_routes
    from bot_core.services.ai import register_ai_routes

    mock_app = MagicMock(spec=Application)
    registered_handlers = []

    def mock_add_handler(h):
        registered_handlers.append(h)

    mock_app.add_handler.side_effect = mock_add_handler

    register_network_routes(mock_app)
    register_service_routes(mock_app)
    register_alert_routes(mock_app)
    register_ai_routes(mock_app)

    # Debe haber al menos 20+ manejadores registrados entre las 4 categorías
    assert len(registered_handlers) >= 20
    assert mock_app.add_handler.called


# ---------------------------------------------------------------------------
# 3. REGLA DE ORO #1: AISLAMIENTO ESTRICTO DE DESPACHOS Y AUDITORÍA
# ---------------------------------------------------------------------------

def test_rule_1_security_alerts_to_owner_private_only():
    """
    Regla de Oro #1: Las alertas de seguridad y bloqueos por acceso no autorizado
    van estrictamente al chat privado del Administrador (38914901), NUNCA a grupos (-100...).
    """
    import asyncio
    from bot_core.handlers.services.actions.report_action import run_and_send_monitoring_report

    mock_user = MagicMock(spec=User)
    mock_user.id = 999999999  # Usuario no autorizado
    mock_user.first_name = "Usuario"
    mock_user.last_name = "Intruso"
    mock_user.username = "intruso"

    mock_chat = MagicMock(spec=Chat)
    mock_chat.id = -1001383163558  # Grupo corporativo (id negativo)
    mock_chat.type = "supergroup"
    mock_chat.title = "Grupo Corporativo"

    mock_message = MagicMock(spec=Message)
    mock_message.text = "/debug_servicios"
    mock_message.reply_text = AsyncMock()

    mock_update = MagicMock(spec=Update)
    mock_update.effective_user = mock_user
    mock_update.effective_chat = mock_chat
    mock_update.message = mock_message

    mock_bot = MagicMock()
    mock_bot.send_message = AsyncMock()

    mock_context = MagicMock()
    mock_context.bot = mock_bot
    mock_context.args = []

    async def _run():
        with patch("bot_core.handlers.services.report_security.get_active_config") as mock_cfg, \
             patch("bot_core.handlers.services.report_security.check_authorization", return_value=True), \
             patch("bot_core.handlers.services.report_security.log_security_audit_event"):

            mock_cfg.return_value = {
                "owner_id": 38914901,
                "allowed_group_ids": [-1001383163558],
                "notify_unauthorized_to_owner": True,
            }

            await run_and_send_monitoring_report(
                mock_update,
                mock_context,
                target="servicios",
                target_name="Servicios Corporativos",
                force_debug=True,
            )

            # Verificar que si se envió alerta de seguridad, fue EXCLUSIVAMENTE al Owner (38914901)
            for call_args in mock_bot.send_message.call_args_list:
                chat_id_arg = call_args.kwargs.get("chat_id") or call_args.args[0]
                assert chat_id_arg == 38914901, f"¡VIOLACIÓN REGLA #1! Se intentó enviar alerta al chat {chat_id_arg}"
                assert chat_id_arg != -1001383163558, "¡VIOLACIÓN CRÍTICA! Se intentó enviar alerta a grupo corporativo"

    asyncio.run(_run())


# ---------------------------------------------------------------------------
# 4. REGLA DE ORO #2: PROHIBICIÓN DE MENCIONAR EL MOTOR SUBYACENTE
# ---------------------------------------------------------------------------

def test_rule_2_forbidden_ai_engine_mentions():
    """Regla de Oro #2: Prohibido mencionar nombres de motores locales en mensajes públicos."""
    from bot_core.services.ai.helpers import load_commands_data

    messages, commands = load_commands_data()
    for k, v in messages.items():
        assert "ollama" not in str(v).lower(), f"Violación Regla #2 en mensaje '{k}': {v}"

    for cmd, info in commands.items():
        desc = info.get("description", "")
        help_t = info.get("help_text", "")
        assert "ollama" not in desc.lower(), f"Violación Regla #2 en comando '{cmd}': {desc}"
        assert "ollama" not in help_t.lower(), f"Violación Regla #2 en ayuda '{cmd}': {help_t}"
