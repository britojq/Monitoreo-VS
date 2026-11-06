"""
Despachador de alertas SSH/PAM exclusivamente al chat del Administrador y auditoría MariaDB.
"""
from datetime import datetime
import logging
from typing import Optional

import httpx

from monitor.shield.secure_core import get_core_bot_token, get_core_owner_id
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.shield.ssh_alert.dispatcher")


def log_event_to_database(
    user_name: str,
    event: str,
    description: str,
    ip_address: Optional[str] = None,
    user_agent: Optional[str] = None,
    user_role: str = "admin"
) -> bool:
    """Inserta de manera atómica el registro de auditoría en MariaDB audit_logs."""
    try:
        with db_cursor() as cur:
            now_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            cur.execute(
                """
                INSERT INTO audit_logs (
                    user_name, user_role, event, module, description,
                    ip_address, user_agent, created_at, updated_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    user_name,
                    user_role,
                    event,
                    "pam",
                    description,
                    ip_address or "127.0.0.1",
                    user_agent or "PAM Subsystem",
                    now_ts,
                    now_ts,
                ),
            )
        logger.info(f"Auditoría PAM registrada en MariaDB: [{event}] {description[:60]}")
        return True
    except Exception as e:
        logger.debug(f"No se pudo registrar auditoría en MariaDB: {e}")
        return False


def load_configured_proxies() -> list:
    """Carga los proxies configurados en MariaDB monitored_proxies (SSOT)."""
    try:
        from monitor.checkers.thermal.thermal_notifier import get_proxies_list
        return [p for p in get_proxies_list() if p is not None]
    except Exception:
        return []


async def send_telegram_alert(mensaje: str, reply_markup: Optional[dict] = None) -> bool:
    """Despacha de forma asíncrona y segura la alerta a Telegram del Administrador (Owner)."""
    bot_token = get_core_bot_token()
    owner_id = get_core_owner_id()
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
        "chat_id": owner_id,
        "text": mensaje,
        "parse_mode": "HTML",
    }
    if reply_markup:
        payload["reply_markup"] = reply_markup

    for target in connection_targets:
        try:
            async with httpx.AsyncClient(proxy=target, timeout=7.0) as client:
                resp = await client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta PAM enviada exitosamente (Destino: {target or 'Directo'}).")
                    return True
        except Exception as e:
            logger.debug(f"Fallo al enviar alerta PAM con proxy {target}: {e}")
            continue

    logger.error("No se pudo entregar la alerta PAM tras probar conexión directa y proxies.")
    return False
