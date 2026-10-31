"""
Despachador de alertas de seguridad del núcleo estrictamente al chat privado del Administrador.
"""
import logging
import os
from pathlib import Path
import platform
import time
import urllib.parse
from typing import List, Optional

import httpx

from monitor.infrastructure.db_context import db_cursor
from monitor.shield.vault_key_deriver import (
    IMMUTABLE_OWNER_ID,
    get_local_ip_addresses,
)

logger = logging.getLogger("monitor.shield.shield_notifier")
BASE_DIR = Path(__file__).resolve().parent.parent.parent


def dispatch_security_alert_message(
    token: str,
    text: str,
    alert_type: str = "alert"
) -> bool:
    """
    Despacha un mensaje de alerta de seguridad DIRECTAMENTE al Administrador (Owner).
    REGLA DE ORO #1: CERO envíos grupales. Destino estricto IMMUTABLE_OWNER_ID.
    """
    debounce_file = Path(f"/tmp/.last_security_{alert_type}")
    now = int(time.time())
    if debounce_file.exists():
        try:
            last_ts = int(debounce_file.read_text().strip())
            if now - last_ts < 180:
                logger.debug(f"Alerta ({alert_type}) omitida por debounce.")
                return False
        except Exception:
            pass

    url = f"https://api.telegram.org/bot{token}/sendMessage"
    payload = {
        "chat_id": IMMUTABLE_OWNER_ID,
        "text": text,
        "parse_mode": "HTML"
    }

    proxies_to_test: List[Optional[str]] = [None]
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
                if p_url not in proxies_to_test:
                    proxies_to_test.append(p_url)
    except Exception:
        pass

    for p_target in proxies_to_test:
        try:
            with httpx.Client(proxy=p_target, timeout=8.0) as client:
                resp = client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta ({alert_type}) despachada exitosamente al Owner.")
                    try:
                        debounce_file.write_text(str(now))
                    except Exception:
                        pass
                    return True
        except Exception:
            continue

    logger.warning(f"No se pudo entregar alerta ({alert_type}) al Owner tras probar proxies.")
    return False


def send_activation_alert(serial: str, bot_token: str) -> None:
    """Envía la alerta de primer arranque para solicitar validación al Owner."""
    if not bot_token:
        return
    hostname = platform.node()
    ips_str = ", ".join(get_local_ip_addresses())
    user_name = os.getenv("USER") or os.getenv("LOGNAME") or "system"
    now_str = time.strftime("%Y-%m-%d %H:%M:%S")
    msg = (
        "🛡️ <b>[SEGURIDAD: NUEVA INSTALACIÓN DETECTADA]</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "Se ha detectado una nueva instalación o despliegue del sistema en este equipo.\n\n"
        f"🖥️ <b>Nombre del Servidor:</b> <code>{hostname}</code>\n"
        f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
        f"👤 <b>Usuario Linux:</b> <code>{user_name}</code>\n"
        f"📁 <b>Ruta Base:</b> <code>{BASE_DIR}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
        "🔑 <b>Serial de Activación:</b>\n"
        f"<code>{serial}</code>\n\n"
        "⏳ <b>Ventana de Validación:</b> <b>10 Minutos</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "💻 <b>MÉTODOS DE ACTIVACIÓN DISPONIBLES:</b>\n\n"
        "1️⃣ <b>Vía Telegram (Bot Centinela):</b>\n"
        f"<code>/activar {serial} &lt;TOKEN_BOT&gt;</code>\n\n"
        "2️⃣ <b>Vía Consola (Shell / SSH):</b>\n"
        f"<code>activar {serial} [TOKEN_BOT]</code>\n\n"
        f"<i>(o: <code>{BASE_DIR / 'tools' / 'activar'} {serial}</code>)</i>"
    )
    dispatch_security_alert_message(bot_token, msg, alert_type="first_boot")


def send_migration_alert(serial: str, bot_token: str) -> None:
    """Envía la alerta forense de copia/migración al Owner."""
    if not bot_token:
        return
    hostname = platform.node()
    ips_str = ", ".join(get_local_ip_addresses())
    user_name = os.getenv("USER") or os.getenv("LOGNAME") or "system"
    now_str = time.strftime("%Y-%m-%d %H:%M:%S")
    msg = (
        "🚨 <b>[SEGURIDAD: ALERTA DE COPIA / CLONACIÓN]</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "⚠️ <b>Se detectó una discrepancia en la huella de hardware física (Equipo Clonado o Copiado).</b>\n\n"
        f"🖥️ <b>Nombre del Servidor:</b> <code>{hostname}</code>\n"
        f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
        f"👤 <b>Usuario Ejecutor:</b> <code>{user_name}</code>\n"
        f"📁 <b>Ruta en Disco:</b> <code>{BASE_DIR}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
        "🔑 <b>Serial de Validación:</b>\n"
        f"<code>{serial}</code>\n\n"
        "⏳ <b>Ventana de Validación:</b> <b>10 Minutos</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "💻 <b>VALIDACIÓN REQUERIDA VÍA CONSOLA (SHELL):</b>\n"
        "Para autorizar la instalación en este nuevo equipo y vincularlo a su hardware, ingrese por consola (SSH o terminal física) y ejecute:\n\n"
        f"<code>activar {serial}</code>\n\n"
        f"<i>(o: <code>{BASE_DIR / 'tools' / 'activar'} {serial}</code>)</i>"
    )
    dispatch_security_alert_message(bot_token, msg, alert_type="migration")


def send_conflict_alert(bot_token: str, state: str) -> None:
    """Envía alerta de conflicto (Conflict 409) con auditoría a MariaDB."""
    if not bot_token:
        return
    hostname = platform.node()
    ips_str = ", ".join(get_local_ip_addresses())
    user_name = os.getenv("USER") or os.getenv("LOGNAME") or "system"
    now_str = time.strftime("%Y-%m-%d %H:%M:%S")

    is_official_node = (hostname.upper() in ("CENCARATIT", "MONITOREO-PROD", "MONITOREO-VS")) or (state == "OPERATIONAL")
    if is_official_node:
        try:
            with db_cursor() as cur:
                cur.execute(
                    """
                    INSERT INTO audit_logs (user_name, user_email, user_role, event, module, entity_name, entity_label, description, ip_address, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    """,
                    (
                        user_name,
                        "system@valleseco.local",
                        "system",
                        "TELEGRAM_CONFLICT_409",
                        "telegram_bot",
                        "SecureCore",
                        "Conflict409",
                        f"Colisión de socket Telegram concurrente externa. Nodo {hostname} ({ips_str}) mantiene estado OPERATIONAL.",
                        ips_str.split(",")[0].strip() if ips_str else "127.0.0.1",
                    )
                )
        except Exception:
            pass

        msg = (
            "🚨 <b>[ALERTA DE SEGURIDAD: CONFLICTO DE INSTANCIA TELEGRAM]</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "⚠️ <b>Se detectó colisión de polling concurrente (Error 409 Conflict).</b>\n\n"
            f"🖥️ <b>Servidor Activo:</b> <code>{hostname}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "🛡️ <i>El nodo oficial continuará reintentando su polling operativo.</i>"
        )
        dispatch_security_alert_message(bot_token, msg, alert_type="conflict_409")


def notify_console_activation_success(serial: str, bot_token: str) -> None:
    """Notifica al Owner por Telegram de una activación exitosa por consola."""
    if not bot_token:
        return
    hostname = platform.node()
    ips_str = ", ".join(get_local_ip_addresses())
    now_str = time.strftime("%Y-%m-%d %H:%M:%S")
    msg = (
        "✅ <b>[NOTIFICACIÓN DE SEGURIDAD: ACTIVACIÓN EXITOSA]</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"El servidor <code>{hostname}</code> ha sido validado y anclado a su hardware físico exitosamente.\n\n"
        f"🌐 <b>IP Local:</b> <code>{ips_str}</code>\n"
        f"🔑 <b>Serial Validado:</b> <code>{serial}</code>\n"
        f"⏰ <b>Hora:</b> <code>{now_str}</code>\n\n"
        "🚀 <i>El bot y todos los servicios de monitoreo están operativos en modo seguro.</i>"
    )
    dispatch_security_alert_message(bot_token, msg, alert_type="console_activation")
