"""
# ==============================================================================
# 🌐 ACTION: CONSULTA IP E INTERFACES: ip_action.py
# Consulta interfaces de red, gateway, DNS y URLs de acceso al portal.
# Ubicación: /opt/sonar/bot_core/handlers/network/actions/ip_action.py
# ==============================================================================
"""
from datetime import datetime
import logging
import platform
import re
import socket
import subprocess
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.network.helpers import reply
from bot_core.security.auth import MSG_UNAUTHORIZED_ADMIN_COMMAND, is_authorized

try:
    import psutil
except ImportError:
    psutil = None

logger = logging.getLogger("bot.handlers.network.ip")


async def handle_ip(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para consultar el Hostname, interfaces de red y direcciones IP del servidor host."""
    if not is_authorized(update):
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            await reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    hostname = platform.node() or "N/A"
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    ifaces_data = []
    primary_ip = None

    if psutil:
        try:
            for iface, addrs in psutil.net_if_addrs().items():
                if iface.lower() in ("lo", "docker0"):
                    continue
                for a in addrs:
                    if a.family == socket.AF_INET and not a.address.startswith("127."):
                        ifaces_data.append({
                            "iface": iface,
                            "ip": a.address,
                            "netmask": a.netmask or "N/A",
                            "broadcast": a.broadcast or "N/A"
                        })
                        if not primary_ip and not a.address.startswith("169.254."):
                            primary_ip = a.address
        except Exception as e:
            logger.error(f"Error obteniendo interfaces con psutil: {e}")

    if not ifaces_data:
        try:
            res = subprocess.run(["ip", "-4", "addr", "show"], capture_output=True, text=True, timeout=2.0)
            current_iface = None
            for line in res.stdout.splitlines():
                m_iface = re.match(r"^\d+:\s+([a-zA-Z0-9_\-]+):", line)
                if m_iface:
                    current_iface = m_iface.group(1)
                    continue
                m_inet = re.search(r"inet\s+([0-9.]+)/(\d+)", line)
                if m_inet and current_iface and current_iface not in ("lo", "docker0"):
                    ip_val = m_inet.group(1)
                    prefix_val = m_inet.group(2)
                    if not ip_val.startswith("127."):
                        ifaces_data.append({
                            "iface": current_iface,
                            "ip": ip_val,
                            "netmask": f"/{prefix_val}",
                            "broadcast": "N/A"
                        })
                        if not primary_ip and not ip_val.startswith("169.254."):
                            primary_ip = ip_val
        except Exception as err:
            logger.error(f"Error en fallback nativo de red ip addr: {err}")

    if not ifaces_data:
        try:
            out = subprocess.check_output(["hostname", "-I"], text=True, timeout=2.0).strip()
            for piece in out.split():
                if piece and ":" not in piece and not piece.startswith("127."):
                    ifaces_data.append({
                        "iface": "eth",
                        "ip": piece,
                        "netmask": "255.255.255.0",
                        "broadcast": "N/A"
                    })
                    if not primary_ip:
                        primary_ip = piece
        except Exception:
            pass

    gw_info = "N/A"
    try:
        res = subprocess.run(["ip", "route"], capture_output=True, text=True, timeout=2.0)
        for line in res.stdout.splitlines():
            if line.startswith("default via"):
                parts = line.split()
                if len(parts) >= 5:
                    gw_info = f"<code>{parts[2]}</code> (vía <i>{parts[4]}</i>)"
                else:
                    gw_info = f"<code>{parts[2]}</code>"
                break
    except Exception:
        pass

    dns_servers = []
    try:
        with open("/etc/resolv.conf", "r") as rf:
            for line in rf:
                if line.strip().startswith("nameserver"):
                    parts = line.split()
                    if len(parts) > 1 and parts[1] not in ("127.0.0.1", "::1"):
                        dns_servers.append(parts[1])
    except Exception:
        pass
    dns_str = ", ".join(f"<code>{d}</code>" for d in dns_servers[:3]) if dns_servers else "N/A"

    ifaces_text_list = []
    for item in ifaces_data:
        ifaces_text_list.append(
            f"🔹 <b>{item['iface']}:</b> <code>{item['ip']}</code>\n"
            f"   └ <i>Máscara:</i> <code>{item['netmask']}</code>"
        )
    ifaces_formatted = "\n\n".join(ifaces_text_list) if ifaces_text_list else "<i>No se detectaron interfaces activas.</i>"

    portal_url = f"http://{primary_ip}/" if primary_ip else "http://localhost/"
    domain_url = "http://monitoreo-vs.local/"

    msg = (
        "🌐 <b>INFORMACIÓN DE RED DEL SERVIDOR</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Equipo:</b> <code>{hostname}</code>\n"
        f"⏰ <b>Hora Servidor:</b> <code>{now_str}</code>\n\n"
        "📡 <b>Direcciones IP Asignadas:</b>\n"
        f"{ifaces_formatted}\n\n"
        f"🚪 <b>Gateway (Puerta de Enlace):</b> {gw_info}\n"
        f"🧭 <b>DNS Primarios:</b> {dns_str}\n\n"
        "🔗 <b>Enlaces de Acceso al Portal:</b>\n"
        f" • IP Directa: <a href=\"{portal_url}\">{portal_url}</a>\n"
        f" • Dominio Local: <a href=\"{domain_url}\">{domain_url}</a>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )

    if update.message:
        await reply(update.message, msg)
