"""
# ==============================================================================
# 🛠️ HELPERS: bot_core/handlers/services/helpers.py
# Utilidades de configuración, respuesta segura y resolución de grupos.
# ==============================================================================
"""
from __future__ import annotations

import logging
import sys
from typing import Any, Dict

from bot_core.services.config_manager import load_config
from bot_core.utils.telegram_helpers import safe_reply_html

logger = logging.getLogger("bot.handlers.services.helpers")


def get_active_config() -> Dict[str, Any]:
    """Obtiene la configuración activa del bot desde memoria o SSOT."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


async def reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Invoca safe_reply_html dinámicamente respetando cualquier patch en bot.safe_reply_html."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


async def resolve_group_info(bot: Any, group_id: int) -> str:
    """Resuelve dinámicamente el título de un grupo de Telegram."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "_resolve_group_info"):
        return await bot_mod._resolve_group_info(bot, group_id)
    try:
        chat = await bot.get_chat(chat_id=group_id)
        return chat.title or str(group_id)
    except Exception:
        return str(group_id)
