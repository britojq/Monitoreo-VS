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
from monitor.core_shield import IMMUTABLE_OWNER_ID, IMMUTABLE_BOT_TOKEN


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
        async with httpx.AsyncClient(timeout=3.0) as client:
            resp = await client.get(f"http://ip-api.com/json/{ip}")
            if resp.status_code == 200:
                data = resp.json()
                if data.get("status") == "success":
                    return {
                        "city": data.get("city", ""),
                        "country": data.get("country", ""),
                        "isp": data.get("isp", "")
                    }
    except Exception as e:
        logger.debug(f"Error consultando geolocalización para {ip}: {e}")
    return None


def load_configured_proxies() -> list[dict]:
    """Carga la lista de proxies configurados desde config.json o bot.conf."""
    config_p = BASE_DIR / "config" / "config.json"
    bot_conf_p = BASE_DIR / "config" / "bot.conf"
    proxies = []

    if config_p.exists():
        try:
            with open(config_p, "r", encoding="utf-8") as f:
                data = json.load(f)
                custom_proxies = data.get("proxies", [])
                if custom_proxies:
                    return custom_proxies
        except Exception:
            pass

    if bot_conf_p.exists():
        try:
            content = bot_conf_p.read_text(encoding="utf-8")
            data = {}
            for line in content.splitlines():
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    data[k.strip()] = v.strip().strip('"').strip("'")
            for suffix in ["A", "B", "C"]:
                ip = data.get(f"DEFAULTPROXY{suffix}")
                creds = data.get(f"USUARIOCLAVEDEFAULT{suffix}")
                if ip and ip not in ("0.0.0.0", ""):
                    url = f"http://{creds}@{ip}" if creds and ":" in creds else f"http://{ip}"
                    proxies.append({"url": url, "auth": bool(creds)})
        except Exception:
            pass

    return proxies


async def send_ssh_alert() -> int:
    """Construye y envía la alerta de conexión SSH al Owner con conmutación automática Directo / Proxies."""
    pam_type = os.getenv("PAM_TYPE", "")
    pam_service = os.getenv("PAM_SERVICE", "")

    # Solo actuar ante apertura de sesión de SSH
    if pam_type != "open_session" or pam_service != "sshd":
        return 0

    user_login = os.getenv("PAM_USER", "desconocido")
    ip_origin = os.getenv("PAM_RHOST", "") or "desconocida/local"
    tty_ssh = os.getenv("PAM_TTY", "") or "ssh"
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
        "🔑 <b>Acceso SSH Detectado</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"👤 <b>Usuario:</b> <code>{html.escape(user_login)}</code>\n"
        f"🌐 <b>IP Origen:</b> <code>{html.escape(ip_origin)}</code>{ubicacion_str}\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
        f"💻 <b>Terminal:</b> <code>{html.escape(tty_ssh)}</code>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "<i>Notificación de seguridad en tiempo real • PAM</i>"
    )

    url = f"https://api.telegram.org/bot{IMMUTABLE_BOT_TOKEN}/sendMessage"
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
                    via = "DIRECTO" if not target else f"PROXY ({target})"
                    logger.info(f"Alerta SSH entregada al Owner exitosamente vía {via}.")
                    return 0
        except Exception as e:
            logger.debug(f"Fallo envío SSH vía {'DIRECTO' if not target else target}: {e}")

    logger.warning("No se pudo entregar la alerta SSH tras probar conexión directa y todos los proxies.")
    return 0


def main():
    try:
        asyncio.run(send_ssh_alert())
    except Exception as e:
        logger.error(f"Excepción en ssh_alert: {e}")
    sys.exit(0)


if __name__ == "__main__":
    main()
