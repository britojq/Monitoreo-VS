"""
# ==============================================================================
# 🔍 ACTIONS: DEBUG DE MONITOREO: debug_action.py
# Comandos de diagnóstico exhaustivo exclusivo para el Administrador (Owner).
# Ubicación: /opt/sonar/bot_core/handlers/services/actions/debug_action.py
# ==============================================================================
"""
from __future__ import annotations

import logging
from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.services.actions.report_action import run_and_send_monitoring_report
from bot_core.security.auth import require_private_chat

logger = logging.getLogger("bot.handlers.services.debug")


async def cmd_debug_servicios(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte exhaustivo de servicios."""
    if not await require_private_chat(update, context):
        return

    await run_and_send_monitoring_report(
        update, context,
        target="servicios",
        target_name="Servicios Corporativos (Debug Directo)",
        force_debug=True
    )


async def cmd_debug_sedes(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte exhaustivo de sedes."""
    if not await require_private_chat(update, context):
        return

    await run_and_send_monitoring_report(
        update, context,
        target="sedes",
        target_name="Sedes y Equipos (Debug Directo)",
        force_debug=True
    )


async def cmd_debug_completo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte técnico integral exhaustivo."""
    if not await require_private_chat(update, context):
        return

    await run_and_send_monitoring_report(
        update, context,
        target="completo",
        target_name="Servicios y Sedes (Debug Directo)",
        force_debug=True
    )
