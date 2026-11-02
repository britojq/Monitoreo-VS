"""
Notificador de eventos térmicos exclusivo al Administrador (Owner) y auditoría MariaDB.
"""
import json
import logging
from typing import Any, Dict, List, Optional
import urllib.parse

import httpx

from monitor.checkers.thermal.constants import IMMUTABLE_OWNER_ID
from monitor.core_shield import IMMUTABLE_BOT_TOKEN
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.thermal.notifier")


def record_thermal_audit_event(event: str, description: str, metadata: Dict[str, Any]) -> None:
    """Registra eventos de protección térmica directamente en MariaDB audit_logs (SSOT)."""
    try:
        with db_cursor() as cur:
            cur.execute(
                """
                INSERT INTO audit_logs (user_name, event, module, description, new_values, ip_address, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, NOW())
                """,
                (
                    "thermal_guard",
                    event,
                    "hardware_protection",
                    description,
                    json.dumps(metadata, ensure_ascii=False),
                    "127.0.0.1"
                )
            )
    except Exception as e:
        logger.warning(f"No se pudo registrar evento térmico en audit_logs: {e}")


def get_proxies_list() -> List[Optional[str]]:
    """Construye la lista de proxies corporativos desde MariaDB (SSOT)."""
    proxies: List[Optional[str]] = [None]
    try:
        with db_cursor() as cur:
            cur.execute(
                "SELECT ip_port, auth_userpass FROM monitored_proxies WHERE is_active = 1 ORDER BY letter"
            )
            for r in cur.fetchall():
                ip = (r.get("ip_port") or "").strip()
                if not ip:
                    continue
                auth = (r.get("auth_userpass") or "").strip()
                if auth and ":" in auth and auth != "USUARIO:CLAVE":
                    u, pwd = auth.split(":", 1)
                    p_url = f"http://{urllib.parse.quote(u)}:{urllib.parse.quote(pwd)}@{ip}"
                else:
                    p_url = f"http://{ip}"
                if p_url not in proxies:
                    proxies.append(p_url)
    except Exception:
        pass
    return proxies


def dispatch_telegram_alert(text: str, alert_type: str = "thermal") -> bool:
    """
    Despacha una alerta directa y exclusiva al Owner mediante el bot principal.
    Cumple estrictamente la Regla de Oro #1 (chat_id: <OWNER_TELEGRAM_ID>, jamás a grupos).
    """
    token = IMMUTABLE_BOT_TOKEN
    if not token:
        logger.error("No se pudo obtener el bot_token para la alerta de temperatura.")
        return False

    url = f"https://api.telegram.org/bot{token}/sendMessage"
    payload = {
        "chat_id": IMMUTABLE_OWNER_ID,
        "text": text,
        "parse_mode": "HTML"
    }

    proxies = get_proxies_list()
    for proxy in proxies:
        try:
            with httpx.Client(proxy=proxy, timeout=8.0) as client:
                resp = client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta de temperatura ({alert_type}) entregada exitosamente al Owner.")
                    return True
        except Exception:
            continue

    logger.error(f"Fallo el despacho de alerta de temperatura ({alert_type}).")
    return False
