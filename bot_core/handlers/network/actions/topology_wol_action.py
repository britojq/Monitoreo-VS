"""
# ==============================================================================
# 🗺️ ACTIONS: TOPOLOGÍA Y WAKE-ON-LAN: topology_wol_action.py
# Comandos /topologia y /wol para mapeo de enlaces y encendido remoto de equipos.
# Ubicación: /opt/sonar/bot_core/handlers/network/actions/topology_wol_action.py
# ==============================================================================
"""
import html
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.network.helpers import get_active_config, reply
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.network.topology_wol")


async def handle_topologia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra un resumen de la topología de red física y lógica descubierta (Fase 7)."""
    if not update.message:
        return

    try:
        with db_cursor() as cur:
            cur.execute("SELECT COUNT(*) as total_devs FROM snmp_devices WHERE is_active = 1")
            snmp_row = cur.fetchone()
            snmp_cnt = snmp_row["total_devs"] if snmp_row else 0

            cur.execute("SELECT COUNT(*) as total_links, SUM(CASE WHEN link_status = 'up' THEN 1 ELSE 0 END) as up_links FROM network_topology_links")
            link_stats = cur.fetchone() or {"total_links": 0, "up_links": 0}

            cur.execute("""
                SELECT l.link_type, l.link_status, s.name as src_name, COALESCE(t.name, l.target_hostname) as tgt_name
                FROM network_topology_links l
                JOIN snmp_devices s ON l.source_device_id = s.id
                LEFT JOIN snmp_devices t ON l.target_device_id = t.id
                ORDER BY l.link_status ASC, l.id ASC LIMIT 6
            """)
            sample_links = cur.fetchall()

        total_links = link_stats.get("total_links") or 0
        up_links = link_stats.get("up_links") or 0

        lines = [
            "🗺️ <b>TOPOLOGÍA VISUAL DE RED (VALLE SECO)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Dispositivos SNMP:</b> <code>{snmp_cnt}</code> equipos",
            f"• <b>Enlaces Descubiertos:</b> <code>{total_links}</code>",
            f"• <b>Enlaces Operativos (UP):</b> <code>{up_links}</code>",
            f"• <b>Protocolos:</b> <code>CDP / LLDP / FDB Inferred</code>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ENLACES PRINCIPALES:</b>"
        ]

        if not sample_links:
            lines.append("<i>No hay enlaces registrados en la topología.</i>")
        else:
            for lk in sample_links:
                status_icon = "🟢" if lk.get("link_status") == "up" else "🔴"
                ltype = (lk.get("link_type") or "manual").upper()
                s_name = lk.get("src_name", "Core")
                t_name = lk.get("tgt_name", "Equipo")
                lines.append(f"{status_icon} <b>{html.escape(s_name)}</b> ──[{ltype}]──> <b>{html.escape(t_name)}</b>")

        lines.append("\n🌐 <i>Visualiza el grafo interactivo completo en: <b>Portal Web -> Topología Visual</b></i>")
        await reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_topologia: {e}")
        await reply(update.message, f"❌ Error consultando topología: {html.escape(str(e))}")


async def handle_wol(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Envía Magic Packets Wake-on-LAN o lista equipos registrados (Fase 7)."""
    if not update.message:
        return

    cfg = get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    user_id = update.effective_user.id if update.effective_user else 0
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    args = context.args or []

    try:
        from monitor.tools.wol_sender import WolManager
        manager = WolManager()

        if not args:
            devices = manager.list_devices()
            lines = [
                "⚡ <b>DISPOSITIVOS WAKE-ON-LAN (ENCENDIDO REMOTO)</b>",
                "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
            ]
            if not devices:
                lines.append("<i>No hay dispositivos registrados para Wake-on-LAN.</i>")
                lines.append("<i>Usa el portal web para registrar nuevos equipos.</i>")
            else:
                for d in devices:
                    last_w = d["last_woken_at"].strftime("%Y-%m-%d %H:%M") if d.get("last_woken_at") else "Nunca"
                    lines.append(f"• <b>[ID: {d['id']:02d}]</b> {html.escape(d['name'])}")
                    lines.append(f"  MAC: <code>{d['mac_address']}</code> | IP: <code>{d.get('ip_address') or 'N/A'}</code>")
                    lines.append(f"  Último encendido: <i>{last_w}</i>")
                    lines.append("")

            if is_admin:
                lines.append("💡 <i>Para encender un equipo: <code>/wol &lt;id|mac|nombre&gt;</code></i>")
            await reply(update.message, "\n".join(lines))
            return

        if not is_admin:
            await reply(update.message, "⛔ <i>Acción denegada: Sólo administradores pueden transmitir Magic Packets Wake-on-LAN.</i>")
            return

        target = " ".join(args)
        res = manager.wake(target)

        if res.get("status") == "ok":
            msg = (
                f"⚡ <b>MAGIC PACKET TRANSMITIDO CON ÉXITO</b>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"• <b>Equipo:</b> {html.escape(res['name'])}\n"
                f"• <b>MAC Destino:</b> <code>{res['mac']}</code>\n"
                f"• <b>Transmisión:</b> Broadcast UDP 9 vía <code>{res['broadcast']}</code>\n"
                f"• <b>Hora:</b> <code>{res['woken_at']}</code>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"<i>La orden de encendido fue enviada a la red física de Valle Seco.</i>"
            )
        else:
            msg = f"❌ Error enviando Wake-on-LAN: {html.escape(res.get('message', 'Fallo de red'))}"

        await reply(update.message, msg)

    except Exception as e:
        logger.error(f"Error en cmd_wol: {e}")
        await reply(update.message, f"❌ Error en comando WoL: {html.escape(str(e))}")
