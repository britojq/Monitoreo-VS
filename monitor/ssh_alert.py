"""
Módulo de Notificación de Accesos SSH en Tiempo Real.
Ejecutado por PAM (pam_exec.so) al abrirse una sesión SSH.
Extrae usuario, IP de origen, geolocalización pública y despacha alerta a Telegram.
100% integrado dentro de /scripts/telegram-admin-bot.
"""

from __future__ import annotations

import asyncio
import html
import logging
import os
import socket
import sys
from datetime import datetime
from pathlib import Path
from typing import Optional

import httpx

# Configuración de logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("monitor.ssh_alert")

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# Importar credenciales protegidas desde el núcleo de seguridad
from monitor.core_shield import IMMUTABLE_OWNER_ID, get_core_bot_token


def is_private_or_local_ip(ip: str) -> bool:
    """Determina si una dirección IP es privada, loopback o desconocida."""
    if not ip or ip in ("desconocida/local", "localhost", "127.0.0.1", "::1"):
        return True
    if (
        ip.startswith("10.")
        or ip.startswith("192.168.")
        or ip.startswith("127.")
        or (ip.startswith("172.") and 16 <= int(ip.split(".")[1]) <= 31)
    ):
        return True
    return False


async def get_geolocation(ip: str) -> Optional[dict]:
    """Obtiene información geográfica de IPs públicas de forma rápida y segura."""
    if is_private_or_local_ip(ip):
        return None
    try:
        url = f"http://ip-api.com/json/{ip}?fields=status,country,city,isp"
        async with httpx.AsyncClient(timeout=4.0) as client:
            resp = await client.get(url)
            if resp.status_code == 200:
                data = resp.json()
                if data.get("status") == "success":
                    return {
                        "country": data.get("country", ""),
                        "city": data.get("city", ""),
                        "isp": data.get("isp", "")
                    }
    except Exception as e:
        logger.debug(f"No se pudo obtener geolocalización para {ip}: {e}")
    return None


def load_configured_proxies() -> list:
    """Carga los proxies configurados en config/config.json."""
    config_file = BASE_DIR / "config" / "config.json"
    if config_file.exists():
        try:
            with open(config_file, "r", encoding="utf-8") as f:
                data = json.load(f)
                return data.get("proxies", [])
        except Exception:
            pass
    return []


async def send_ssh_alert(user_login: str, ip_origin: str, tty_ssh: str) -> None:
    """Construye y despacha el mensaje de alerta SSH a Telegram."""
    bot_token = get_core_bot_token()
    if not bot_token:
        logger.error("No se pudo obtener el Bot Token para alertar.")
        return

    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # Obtener geolocalización si es IP pública
    geo = await get_geolocation(ip_origin)
    ubicacion_str = ""
    if geo and (geo["city"] or geo["country"]):
        ciudad = geo["city"]
        pais = geo["country"]
        isp = geo["isp"]
        ubicacion_str = f"\n📍 <b>Ubicación:</b> {html.escape(ciudad)}, {html.escape(pais)} ({html.escape(isp)})"

    mensaje = (
        "🔑 <b>Acceso SSH Detectado</b>\n\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"👤 <b>Usuario:</b> <code>{html.escape(user_login)}</code>\n"
        f"🌐 <b>IP Origen:</b> <code>{html.escape(ip_origin)}</code>{ubicacion_str}\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
        f"💻 <b>Terminal:</b> <code>{html.escape(tty_ssh)}</code>\n\n"
        "<i>Notificación de seguridad en tiempo real • PAM</i>"
    )

    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    proxies = load_configured_proxies()

    # Probar conexión DIRECTA primero, luego proxies configurados
    connection_targets = [None]  # None = Directo
    for p in proxies:
        p_url = p.get("url") if isinstance(p, dict) else p
        if p_url:
            connection_targets.append(p_url)

    for target in connection_targets:
        try:
            async with httpx.AsyncClient(proxy=target, timeout=7.0) as client:
                resp = await client.post(
                    url,
                    json={
                        "chat_id": IMMUTABLE_OWNER_ID,
                        "text": mensaje,
                        "parse_mode": "HTML"
                    }
                )
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta SSH enviada a Telegram exitosamente (Destino: {target or 'Directo'}).")
                    return
        except Exception as e:
            logger.debug(f"Fallo al enviar alerta SSH con proxy {target}: {e}")
            continue

    logger.error("No se pudo entregar la alerta SSH tras probar conexión directa y todos los proxies.")
    return 0


def main():
    pam_type = os.getenv("PAM_TYPE", "")
    pam_service = os.getenv("PAM_SERVICE", "")

    if pam_type != "open_session" or pam_service != "sshd":
        sys.exit(0)

    user_login = os.getenv("PAM_USER", "desconocido")
    ip_origin = os.getenv("PAM_RHOST", "") or "desconocida/local"
    tty_ssh = os.getenv("PAM_TTY", "") or "ssh"

    try:
        asyncio.run(send_ssh_alert(user_login, ip_origin, tty_ssh))
    except Exception as e:
        logger.error(f"Excepción en ssh_alert: {e}")
    sys.exit(0)


if __name__ == "__main__":
    main()
