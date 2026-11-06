"""
# ==============================================================================
# 🛠️ HELPERS: bot_core/handlers/alerts/helpers.py
# Utilidades de configuración, URLs de portal y respuesta segura para alertas.
# ==============================================================================
"""
from __future__ import annotations

import logging
import sys
from typing import Any, Dict, Optional

from bot_core.utils.telegram_helpers import safe_reply_html

logger = logging.getLogger("bot.handlers.alerts.helpers")


def get_config() -> Dict[str, Any]:
    """Obtiene la configuración activa del bot."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    from monitor.config_parser import MonitorConfigLoader
    return MonitorConfigLoader().raw_bot


def get_portal_alerts_url(aid: Optional[int] = None) -> str:
    """Retorna la URL del portal web para alertas sin exponer IPs fijas hardcodeadas."""
    cfg = get_config()
    base = cfg.get("web_portal_url") or cfg.get("portal_url") or "http://monitoreo-vs.local"
    base = str(base).rstrip("/")
    if aid:
        return f"{base}/admin/alerts?search={aid}"
    return f"{base}/admin/alerts"


async def reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Invoca safe_reply_html dinámicamente respetando cualquier patch en bot.safe_reply_html."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)
