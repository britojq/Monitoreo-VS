"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD NOTIFIER: notifier.py
# Despacho de alertas a Telegram exclusivo al Administrador con proxies redundantes
# Ubicación: /opt/sonar/monitor/shield/infrastructure/notifier.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Optional

import httpx

from monitor.shield.secure_core import IMMUTABLE_OWNER_ID, get_core_bot_token

logger = logging.getLogger("monitor.terminal_shield")


def load_configured_proxies() -> list:
    """Obtiene proxies configurados para redundancia en Telegram desde MariaDB SSOT."""
    try:
        from monitor.checkers.thermal import get_proxies_list
        return [p for p in get_proxies_list() if p is not None]
    except Exception:
        return []


async def send_telegram_alert(mensaje: str, reply_markup: Optional[dict] = None) -> bool:
    """Despacha la alerta a Telegram exclusivamente al Administrador con botones interactivos."""
    bot_token = get_core_bot_token()
    if not bot_token:
        logger.error("No se pudo obtener el Bot Token para alertar.")
        return False

    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    proxies = load_configured_proxies()
    connection_targets = [None]
    for p in proxies:
        p_url = p.get("url") if isinstance(p, dict) else p
        if p_url:
            connection_targets.append(p_url)

    payload = {
        "chat_id": IMMUTABLE_OWNER_ID,
        "text": mensaje,
        "parse_mode": "HTML",
    }
    if reply_markup:
        payload["reply_markup"] = reply_markup

    for target in connection_targets:
        try:
            async with httpx.AsyncClient(proxy=target, timeout=6.0) as client:
                resp = await client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info("Alerta de terminal enviada exitosamente a Telegram.")
                    return True
        except Exception as e:
            logger.debug(f"Fallo enviando alerta con proxy {target}: {e}")
            continue

    logger.error("No se pudo entregar la alerta a Telegram tras agotar conexiones.")
    return False
