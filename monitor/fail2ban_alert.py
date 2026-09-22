"""
# ==============================================================================
# 🛡️ NOTIFICADOR DE BLOQUEOS FAIL2BAN: fail2ban_alert.py (@IA_ValleSeco_bot)
# Despacho en tiempo real de IPs bloqueadas/desbloqueadas hacia Telegram y MariaDB
# Ubicación: /scripts/telegram-admin-bot/monitor/fail2ban_alert.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import html
import json
import logging
import os
import socket
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, Optional

import httpx

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("monitor.fail2ban_alert")

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.core_shield import IMMUTABLE_OWNER_ID, get_core_bot_token


def load_env() -> Dict[str, str]:
    """Carga variables de entorno de base de datos desde .env."""
    env_paths = [
        Path("/var/www/monitoreo/.env"),
        BASE_DIR / "web_portal" / ".env",
    ]
    env_vars = {}
    for p in env_paths:
        if p.exists():
            try:
                for line in p.read_text(encoding="utf-8").splitlines():
                    line = line.strip()
                    if line and not line.startswith("#") and "=" in line:
                        k, v = line.split("=", 1)
                        env_vars[k.strip()] = v.strip().strip('"').strip("'")
                break
            except Exception:
                pass
    return env_vars


def log_event_to_database(
    event: str,
    description: str,
    ip_address: str,
    jail: str,
) -> bool:
    """Inserta de manera atómica el registro en MariaDB audit_logs."""
    try:
        import pymysql

        env = load_env()
        conn = pymysql.connect(
            host=env.get("DB_HOST", "127.0.0.1"),
            port=int(env.get("DB_PORT", 3306)),
            user=env.get("DB_USERNAME", "monitoreo_user"),
            password=env.get("DB_PASSWORD", "VsMonit#2026!SecureKey"),
            database=env.get("DB_DATABASE", "monitoreo_vs"),
            autocommit=True,
            connect_timeout=3,
        )
        with conn.cursor() as cur:
            now_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            cur.execute(
                """
                INSERT INTO audit_logs (
                    user_name, user_role, event, module, description,
                    ip_address, user_agent, created_at, updated_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    "fail2ban",
                    "system",
                    event,
                    "security",
                    description,
                    ip_address,
                    f"Fail2ban/{jail}",
                    now_ts,
                    now_ts,
                ),
            )
        conn.close()
        logger.info(f"Auditoría Fail2ban registrada en MariaDB: [{event}] {description}")
        return True
    except Exception as e:
        logger.debug(f"Error registrando auditoría Fail2ban en MariaDB: {e}")
        return False


def is_private_or_local_ip(ip: str) -> bool:
    """Determina si una dirección IP es privada o local."""
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


def get_network_context(ip: str) -> str:
    """Identifica el rol o contexto de red de la IP."""
    if not ip or ip in ("localhost", "127.0.0.1", "::1"):
        return "Consola Local / Loopback"
    if ip == "10.20.23.221":
        return "Servidor Desarrollo (Esclavo)"
    if ip == "10.20.23.252":
        return "Servidor Producción (Master)"
    if ip == "10.20.23.1":
        return "Router Core / Gateway Valle Seco"
    if ip.startswith("10.20.23."):
        return "Red Corporativa Valle Seco (LAN)"
    if ip.startswith("10.") or ip.startswith("192.168.") or (ip.startswith("172.") and 16 <= int(ip.split(".")[1]) <= 31):
        return "Red Privada Interna (VPN/VLAN)"
    return "IP Pública Externa"


async def get_geolocation(ip: str) -> Optional[dict]:
    """Obtiene geolocalización de IPs públicas atacantes."""
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
                        "isp": data.get("isp", ""),
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


async def send_telegram_alert(mensaje: str) -> bool:
    """Despacha la alerta a Telegram exclusivamente al Administrador."""
    bot_token = get_core_bot_token()
    if not bot_token:
        logger.error("No se pudo obtener el Bot Token para alertar Fail2ban.")
        return False

    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    proxies = load_configured_proxies()

    connection_targets = [None]
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
                        "parse_mode": "HTML",
                    },
                )
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta Fail2ban enviada exitosamente a Telegram.")
                    return True
        except Exception as e:
            logger.debug(f"Fallo enviando alerta Fail2ban con target {target}: {e}")
            continue

    logger.error("No se pudo entregar la alerta Fail2ban a Telegram tras agotar conexiones.")
    return False


def format_duration(seconds_str: str) -> str:
    """Formatea la duración del baneo en formato legible."""
    try:
        sec = int(float(seconds_str))
        if sec <= 0:
            return "Indefinido / Permanente"
        if sec < 60:
            return f"{sec} seg"
        if sec < 3600:
            return f"{sec // 60} min"
        if sec < 86400:
            return f"{sec // 3600} horas"
        return f"{sec // 86400} días"
    except Exception:
        return f"{seconds_str} seg"


async def process_fail2ban_action(
    action: str,
    jail: str,
    ip: str,
    failures: str,
    bantime: str,
    port: str = "",
) -> None:
    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    net_ctx = get_network_context(ip)
    bantime_human = format_duration(bantime)

    if action == "ban":
        geo = await get_geolocation(ip)
        ubicacion_str = ""
        if geo and (geo["city"] or geo["country"]):
            ubicacion_str = f"\n📍 <b>Ubicación:</b> {html.escape(geo['city'])}, {html.escape(geo['country'])} ({html.escape(geo['isp'])})"

        puerto_str = f"\n🚪 <b>Puerto:</b> <code>{html.escape(port)}</code>" if port else ""

        mensaje = (
            "🛡️ <b>Alerta Fail2ban: IP Bloqueada</b>\n\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
            f"🚫 <b>IP Bloqueada:</b> <code>{html.escape(ip)}</code> ({net_ctx}){ubicacion_str}\n"
            f"🔒 <b>Jail / Servicio:</b> <code>{html.escape(jail)}</code>{puerto_str}\n"
            f"⚠️ <b>Intentos Fallidos:</b> <code>{html.escape(str(failures))}</code>\n"
            f"⏱️ <b>Tiempo de Bloqueo:</b> <code>{bantime_human}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>Defensa perimetral contra ataques de fuerza bruta • Fail2ban</i>"
        )

        desc = f"IP {ip} bloqueada en servicio {jail} tras {failures} intentos fallidos ({bantime_human})"
        log_event_to_database(event="ip_banned", description=desc, ip_address=ip, jail=jail)
        await send_telegram_alert(mensaje)

    elif action == "unban":
        desc = f"IP {ip} desbloqueada en servicio {jail}"
        log_event_to_database(event="ip_unbanned", description=desc, ip_address=ip, jail=jail)


def main():
    parser = argparse.ArgumentParser(description="Fail2ban Telegram & MariaDB Notifier")
    parser.add_argument("--action", choices=["ban", "unban"], required=True)
    parser.add_argument("--jail", required=True)
    parser.add_argument("--ip", required=True)
    parser.add_argument("--failures", default="1")
    parser.add_argument("--bantime", default="3600")
    parser.add_argument("--port", default="")

    args = parser.parse_args()

    try:
        asyncio.run(
            process_fail2ban_action(
                action=args.action,
                jail=args.jail,
                ip=args.ip,
                failures=args.failures,
                bantime=args.bantime,
                port=args.port,
            )
        )
    except Exception as e:
        logger.error(f"Error procesando alerta Fail2ban: {e}")
    sys.exit(0)


if __name__ == "__main__":
    main()
