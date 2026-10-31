"""
# ==============================================================================
# 🛰️ ACTIONS: CLÚSTER Y AUTO-DISCOVERY: cluster_discovery_action.py
# Comandos /cluster y /discovery (Detección Anti-Rogue).
# Ubicación: /opt/sonar/bot_core/handlers/network/actions/cluster_discovery_action.py
# ==============================================================================
"""
import html
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.network.helpers import get_active_config, reply
from bot_core.security.auth import (
    MSG_UNAUTHORIZED_ADMIN_COMMAND,
    check_authorization,
    require_private_chat,
)
from monitor.core_shield import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.network.cluster_discovery")


async def handle_cluster(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el estado del clúster Master/Slave y el token de autenticación (Exclusivo Owner en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id:
        await reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    role = cfg.get("node_role", "master").upper()
    master_url = cfg.get("master_api_url", "http://192.168.1.250")
    token = cfg.get("cluster_token", "No configurado")
    last_sync = cfg.get("cluster_last_sync_at", "N/A")
    status = cfg.get("cluster_last_sync_status", "N/A")

    role_emoji = "👑" if role == "MASTER" else "🔄"
    text = (
        f"{role_emoji} <b>Arquitectura de Clúster (Master / Slave)</b>\n\n"
        f"🏷️ <b>Rol del Nodo:</b> <code>{role}</code>\n"
        f"🌐 <b>Servidor Master:</b> <code>{html.escape(master_url)}</code>\n"
        f"📊 <b>Estado de Enlace:</b> <code>{html.escape(status)}</code>\n"
        f"⏰ <b>Última Sincronía:</b> <code>{html.escape(str(last_sync))}</code>\n\n"
        f"🔑 <b>Token Secreto de Clúster (toque para copiar):</b>\n"
        f"<code>{html.escape(token)}</code>\n\n"
        f"<i>Copie este token en el servidor Esclavo para autorizar la sincronización de telemetría sin colisiones en pfSense.</i>"
    )
    await reply(update.message, text)


async def handle_discovery(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reporte de Auto-Discovery y Detección Anti-Rogue."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    try:
        with db_cursor() as cur:
            cur.execute("SELECT COUNT(*) as total FROM discovered_devices")
            total = cur.fetchone()["total"]
            cur.execute("SELECT COUNT(*) as pending FROM discovered_devices WHERE classification_status = 'pendiente'")
            pending = cur.fetchone()["pending"]
            cur.execute("SELECT COUNT(*) as auth FROM discovered_devices WHERE is_authorized = 1")
            auth_count = cur.fetchone()["auth"]
            cur.execute("SELECT COUNT(*) as rogue FROM discovered_devices WHERE classification_status = 'rogue'")
            rogue = cur.fetchone()["rogue"]

            cur.execute("""
                SELECT ip_address, mac_address, vendor, device_type, classification_status, is_authorized, last_seen
                FROM discovered_devices
                ORDER BY last_seen DESC
                LIMIT 8
            """)
            recent_devices = cur.fetchall()

        text = (
            "🛰️ <b>Auto-Discovery de Red & Anti-Rogue</b>\n\n"
            f"📊 <b>Resumen de Dispositivos:</b>\n"
            f"• Total en inventario: <b>{total}</b>\n"
            f"• ✅ Autorizados: <b>{auth_count}</b>\n"
            f"• ⏳ Pendientes de clasificar: <b>{pending}</b>\n"
            f"• 🚫 Intrusos (Rogue): <b>{rogue}</b>\n\n"
            "🔍 <b>Últimos Dispositivos Vistos:</b>\n"
        )

        if not recent_devices:
            text += "<i>No hay dispositivos registrados aún.</i>\n"
        else:
            for d in recent_devices:
                status_icon = "🚫" if d["classification_status"] == "rogue" else ("✅" if d["is_authorized"] else "⏳")
                vendor = d["vendor"] or "Desconocido"
                text += f"{status_icon} <code>{d['ip_address']}</code> - {d['mac_address']} ({html.escape(vendor)})\n"

        text += "\n<i>Gestione subredes y autorizaciones en el portal web: /admin/discovery</i>"
        await reply(update.message, text)
    except Exception as e:
        logger.error(f"Error en cmd_discovery: {e}")
        await reply(update.message, f"❌ Error consultando auto-discovery: {html.escape(str(e))}")
