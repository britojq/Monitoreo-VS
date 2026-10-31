"""
# ==============================================================================
# 🚏 ENRUTADOR DE RUTAS DE IA: bot_core/services/ai/router.py
# Registra los comandos conversacionales y de control de IA en Telegram.
# ==============================================================================
"""
from __future__ import annotations

import logging
from telegram.ext import Application, CommandHandler

from bot_core.services.ai.chat_handlers import cmd_ia_grupo, reset_chat

logger = logging.getLogger("bot.services.ai.router")


def register_ai_routes(application: Application) -> None:
    """Registra los comandos de IA e interactividad conversacional."""
    application.add_handler(CommandHandler(["reset_ia", "reset_chat", "olvidar"], reset_chat))
    application.add_handler(CommandHandler(["ia_grupo", "ia_group"], cmd_ia_grupo))
    logger.info("Rutas de Asistente IA registradas exitosamente.")
