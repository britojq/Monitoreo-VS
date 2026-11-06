"""
# ==============================================================================
# 🚏 ENRUTADOR DE RUTAS DE ALERTAS: router.py
# Registra los comandos y callbacks de gestión de incidentes en Telegram.
# Ubicación: /opt/sonar/bot_core/handlers/alerts/router.py
# ==============================================================================
"""
from __future__ import annotations

import logging
from typing import Any
from telegram.ext import CallbackQueryHandler, CommandHandler

from bot_core.handlers.alerts.actions.alert_callback_action import handle_alert_action_callback
from bot_core.handlers.alerts.actions.alert_lifecycle_action import (
    handle_ack as cmd_ack,
    handle_reglas as cmd_reglas,
    handle_resolver as cmd_resolver,
    handle_silenciar as cmd_silenciar,
)
from bot_core.handlers.alerts.actions.alert_list_action import handle_alertas as cmd_alertas
from bot_core.handlers.alerts.actions.maintenance_action import handle_mantenimiento as cmd_mantenimiento

logger = logging.getLogger("bot.handlers.alerts.router")


def register_alert_routes(application: Any) -> None:
    """Registra los comandos y callbacks del sistema de alertas e incidentes."""
    application.add_handler(CommandHandler(["alertas", "alerta", "alerts"], cmd_alertas))
    application.add_handler(CommandHandler(["ack", "reconocer"], cmd_ack))
    application.add_handler(CommandHandler(["resolver", "resolve"], cmd_resolver))
    application.add_handler(CommandHandler(["silenciar", "silence", "mute"], cmd_silenciar))
    application.add_handler(CommandHandler(["mantenimiento", "maint"], cmd_mantenimiento))
    application.add_handler(CommandHandler(["reglas", "rules"], cmd_reglas))
    application.add_handler(CallbackQueryHandler(handle_alert_action_callback, pattern=r"^alert_act:"))
    logger.info("Rutas de Alertas e Incidentes registradas exitosamente.")
