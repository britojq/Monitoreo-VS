"""
# ==============================================================================
# 🚀 PIPELINE DE EJECUCIÓN DE CHAT: bot_core/services/ai/chat_pipeline.py
# Ciclo completo de consulta, rate-limit, animación UI e inferencia asíncrona.
# ==============================================================================
"""
from __future__ import annotations

import asyncio
import logging
from typing import Any, Dict, List, Optional

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.services.ai.helpers import append_to_history, reply
from bot_core.services.ai.inference_facade import ask_ollama_async, is_negative_or_refusal
from bot_core.services.ai.ui_animator import animate_waiting_message
from bot_core.utils.telegram_helpers import (
    check_ai_rate_limit,
    markdown_to_telegram_html,
    sanitizar_pii,
    split_message,
)

logger = logging.getLogger("bot.services.ai.pipeline")


async def execute_chat_pipeline(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    raw_user_text: str,
    is_group_context: bool = False,
) -> None:
    """Ejecuta el ciclo de consulta, inferencia y respuesta formateada al usuario."""
    if not update.message or not raw_user_text:
        return

    user = update.effective_user
    chat = update.effective_chat
    rate_key = user.id if user else (chat.id if chat else 0)

    max_reqs = 3 if is_group_context else 5
    if not check_ai_rate_limit(rate_key, max_requests=max_reqs, window_seconds=60.0):
        await update.message.reply_text("⏳ Límite de consultas a la IA excedido. Por favor espere 60 segundos.")
        return

    user_text = sanitizar_pii(raw_user_text)

    waiting_msg = None
    stop_event = asyncio.Event()
    anim_task = None

    try:
        waiting_msg = await update.message.reply_text(
            "⏳ <i>Estoy haciendo un análisis de lo que indicas, espere por favor puedo tardar unos segundos...</i>",
            parse_mode="HTML",
        )
        anim_task = asyncio.create_task(
            animate_waiting_message(context.bot, update.effective_chat.id, waiting_msg, stop_event)
        )
    except Exception as exc:
        logger.warning("No se pudo enviar el mensaje inicial de espera: %s", exc)

    if is_group_context:
        history: List[Dict[str, str]] = []
        active_entity: Optional[Dict[str, Any]] = None
    else:
        history = context.chat_data.get("history", [])
        active_entity = context.chat_data.get("active_entity")

    success = False
    answer = ""

    try:
        answer, updated_entity = await ask_ollama_async(
            user_text, history, active_entity, is_group_context=is_group_context
        )
        if not is_group_context and updated_entity:
            context.chat_data["active_entity"] = updated_entity
        success = True
    except Exception as exc:
        logger.exception("Error consultando servicio de IA: %s", exc)
        answer = "⚠️ Ocurrió un error inesperado consultando al asistente local. Intenta nuevamente."
    finally:
        stop_event.set()
        if anim_task:
            anim_task.cancel()
            try:
                await anim_task
            except (asyncio.CancelledError, Exception):
                pass

        if waiting_msg:
            try:
                await waiting_msg.delete()
            except Exception:
                pass

    if success and not is_group_context:
        if not is_negative_or_refusal(answer):
            append_to_history(context, "user", user_text)
            append_to_history(context, "assistant", answer)

    formatted_answer = markdown_to_telegram_html(answer)

    if success and not is_group_context:
        formatted_answer += (
            "\n\n<i>💡 <b>Nota:</b> El bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento "
            "hasta que uses <code>/reset_ia</code> para iniciar una nueva consulta referente a otro tema.</i>"
        )

    for chunk in split_message(formatted_answer):
        await reply(update.message, chunk, disable_web_page_preview=True)
