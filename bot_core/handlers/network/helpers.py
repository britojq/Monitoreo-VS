"""
# ==============================================================================
# 🛠️ HELPERS PARA HANDLERS DE RED: helpers.py
# Funciones auxiliares de configuración y respuestas HTML seguras.
# Ubicación: /opt/sonar/bot_core/handlers/network/helpers.py
# ==============================================================================
"""
import sys
from typing import Any, Dict

from bot_core.services.config_manager import load_config
from bot_core.utils.telegram_helpers import safe_reply_html


def get_active_config() -> Dict[str, Any]:
    """Obtiene la configuración activa del bot respetando el runtime."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


async def reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Despacha mensaje seguro con escape HTML."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)
