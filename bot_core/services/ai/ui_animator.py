"""
# ==============================================================================
# ⏳ ANIMADOR DE ESPERA TELEGRAM UI: bot_core/services/ai/ui_animator.py
# Animación de estado con emojis rotativos y acción de typing en Telegram.
# ==============================================================================
"""
from __future__ import annotations

import asyncio
import logging
from typing import Any

from telegram.constants import ChatAction

logger = logging.getLogger("bot.services.ai.animator")


async def animate_waiting_message(bot: Any, chat_id: int, message_obj: Any, stop_event: asyncio.Event) -> None:
    """Anima el mensaje de espera con reloj rotativo e indicador de escritura."""
    clocks = ["🕐", "🕑", "🕒", "🕓", "🕔", "🕕", "🕖", "🕗", "🕘", "🕙", "🕚", "🕛"]
    base_text = "<i>Estoy haciendo un análisis de lo que indicas, espere por favor puedo tardar unos segundos...</i>"
    idx = 0

    while not stop_event.is_set():
        try:
            await bot.send_chat_action(chat_id=chat_id, action=ChatAction.TYPING)
        except Exception:
            pass

        try:
            await asyncio.wait_for(stop_event.wait(), timeout=2.5)
            break
        except asyncio.TimeoutError:
            pass

        idx = (idx + 1) % len(clocks)
        clock_emoji = clocks[idx]

        try:
            await message_obj.edit_text(f"{clock_emoji} {base_text}", parse_mode="HTML")
        except Exception:
            pass
