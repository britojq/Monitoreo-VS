"""
# ==============================================================================
# 💬 MANEJADORES DE CHAT E INTERACCIÓN IA: chat_handlers.py
# Handlers de Telegram para mensajes conversacionales, reseteo y control de grupo.
# Ubicación: /opt/sonar/bot_core/services/ai/chat_handlers.py
# ==============================================================================
"""
from __future__ import annotations

import logging
import re
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.security.auth import check_authorization
from bot_core.services.ai.chat_pipeline import execute_chat_pipeline
from bot_core.services.ai.helpers import get_active_config
from bot_core.services.ai.inference_facade import (
    is_telegram_group_ai_enabled,
    set_telegram_group_ai_enabled,
)

logger = logging.getLogger("bot.services.ai.handlers")


async def reset_chat(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reinicia el historial de conversación con el asistente virtual."""
    cfg = get_active_config()
    allow_all = bool(cfg.get("ollama_allow_all", False))

    if not allow_all and not await check_authorization(update, context):
        return

    context.chat_data.pop("history", None)
    context.chat_data.pop("active_entity", None)
    if update.message:
        await update.message.reply_text("✅ Historial de conversación y memoria activa reiniciados.")


async def cmd_ia_grupo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando de control exclusivo del Owner para activar/desactivar la IA en el Grupo Corporativo."""
    if not update.message:
        return

    user = update.effective_user
    cfg = get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))

    if not user or user.id != owner_id:
        await update.message.reply_text("⛔ Acceso restringido: Este comando es exclusivo del Administrador Principal.")
        return

    args = context.args or []
    if not args:
        current_status = is_telegram_group_ai_enabled()
        status_str = "🟢 <b>ACTIVADO</b>" if current_status else "🔴 <b>DESACTIVADO (Pausado)</b>"
        await update.message.reply_text(
            f"🤖 <b>Control de Asistente IA en Grupo Corporativo</b>\n\n"
            f"• Estado de la Línea 2 (Grupo): {status_str}\n\n"
            f"<b>Comandos de gestión:</b>\n"
            f"• <code>/ia_grupo on</code> ➔ Activa la atención en el grupo (responde a menciones).\n"
            f"• <code>/ia_grupo off</code> ➔ Desactiva la IA en el grupo (silencio total).\n"
            f"• <code>/ia_grupo status</code> ➔ Muestra el estado actual.\n\n"
            f"<i>Nota: También puede conmutarlo desde el Portal Web en Configuración Avanzada.</i>",
            parse_mode="HTML",
        )
        return

    subcmd = args[0].strip().lower()
    if subcmd in ("on", "activar", "habilitar", "1"):
        success = set_telegram_group_ai_enabled(True, updated_by=f"{user.first_name or 'Owner'} [Telegram]")
        if success:
            await update.message.reply_text(
                "✅ <b>Línea 2 de IA Activada para el Grupo Corporativo</b>\n\n"
                "El Asistente inteligente responderá cuando los miembros lo mencionen o respondan a sus mensajes en el grupo corporativo.\n"
                "<i>La Línea 1 (su chat privado) se mantiene permanente e irrestricta.</i>",
                parse_mode="HTML",
            )
        else:
            await update.message.reply_text("❌ Error al persistir la activación en MariaDB SSOT.")

    elif subcmd in ("off", "desactivar", "pausar", "0"):
        success = set_telegram_group_ai_enabled(False, updated_by=f"{user.first_name or 'Owner'} [Telegram]")
        if success:
            await update.message.reply_text(
                "⛔ <b>Línea 2 de IA Desactivada para el Grupo Corporativo</b>\n\n"
                "El Asistente permanecerá en silencio absoluto en el grupo corporativo (cero consumo de CPU).\n"
                "<i>Los 2 hilos de inferencia quedan dedicados a su chat privado.</i>",
                parse_mode="HTML",
            )
        else:
            await update.message.reply_text("❌ Error al persistir la desactivación en MariaDB SSOT.")

    elif subcmd in ("status", "estado", "check"):
        current_status = is_telegram_group_ai_enabled()
        status_str = "🟢 <b>ACTIVADO</b>" if current_status else "🔴 <b>DESACTIVADO (Pausado)</b>"
        await update.message.reply_text(f"📊 <b>Estado de la Línea 2 de IA (Grupo):</b> {status_str}", parse_mode="HTML")
    else:
        await update.message.reply_text(
            "⚠️ Parámetro no reconocido. Use <code>/ia_grupo on</code>, <code>/ia_grupo off</code> o <code>/ia_grupo status</code>.",
            parse_mode="HTML",
        )


async def handle_chat_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja mensajes de texto normales y los envía al motor local de IA respetando las 2 líneas autorizadas."""
    if not update.message or not update.message.text:
        return

    chat = update.effective_chat
    user = update.effective_user
    if not chat or not user:
        return

    cfg = get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))

    # LÍNEA 1: CHAT PRIVADO DEL OWNER
    if chat.type == "private":
        if user.id != owner_id or chat.id != owner_id:
            logger.warning("Intento de interacción no autorizada en chat privado: chat_id=%s, user_id=%s", chat.id, user.id)
            await check_authorization(update, context)
            return

        raw_user_text = update.message.text.strip()
        if not raw_user_text:
            return

        await execute_chat_pipeline(update, context, raw_user_text)
        return

    # LÍNEA 2: GRUPO CORPORATIVO (CONDICIONAL)
    if chat.type in ("group", "supergroup") or chat.id < 0:
        if not is_telegram_group_ai_enabled():
            return

        message_text = update.message.text.strip()
        bot_username = (context.bot.username or "").lower()
        bot_id = context.bot.id

        is_targeted = False
        if bot_username and f"@{bot_username}" in message_text.lower():
            is_targeted = True
        elif update.message.reply_to_message:
            reply_from = update.message.reply_to_message.from_user
            if reply_from and reply_from.id == bot_id:
                is_targeted = True

        if not is_targeted:
            return

        clean_text = message_text
        if bot_username:
            clean_text = re.sub(rf"@{re.escape(bot_username)}", "", clean_text, flags=re.IGNORECASE).strip()

        if not clean_text:
            clean_text = "Hola, soy el asistente virtual del centro de operaciones. ¿En qué puedo apoyarte?"

        await execute_chat_pipeline(update, context, clean_text, is_group_context=True)
        return


async def handle_chat_query_direct(update: Update, context: ContextTypes.DEFAULT_TYPE, user_text: str) -> None:
    """Procesa una consulta directa (ej. /ia <pregunta>) respetando el filtrado exclusivo del Owner o Grupo."""
    if not update.message or not user_text:
        return

    chat = update.effective_chat
    user = update.effective_user
    if not chat or not user:
        return

    cfg = get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))

    if chat.type == "private":
        if user.id != owner_id or chat.id != owner_id:
            await update.message.reply_text(
                "⛔ Acceso restringido: El asistente de IA está disponible exclusivamente para el chat privado del Administrador del Centro de Operaciones ATIT."
            )
            return
        await execute_chat_pipeline(update, context, user_text.strip())
        return

    if chat.type in ("group", "supergroup") or chat.id < 0:
        if not is_telegram_group_ai_enabled():
            return
        await execute_chat_pipeline(update, context, user_text.strip(), is_group_context=True)
        return
