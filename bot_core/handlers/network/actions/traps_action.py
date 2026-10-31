"""
# ==============================================================================
# 🛰️ ACTIONS: SNMP TRAPS: traps_action.py
# Comando /traps para telemetría push recibida en tiempo real (UDP 162).
# Ubicación: /opt/sonar/bot_core/handlers/network/actions/traps_action.py
# ==============================================================================
"""
import html
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.network.helpers import reply
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.network.traps")


async def handle_traps(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra los últimos SNMP Traps recibidos en tiempo real por el receptor push."""
    if not update.message:
        return

    args = context.args or []
    filter_ip = args[0].strip() if args else None

    try:
        with db_cursor() as cur:
            sql = "SELECT id, source_ip, trap_oid, trap_type, severity, processed, received_at FROM snmp_traps_received WHERE 1=1"
            params = []
            if filter_ip:
                sql += " AND source_ip = %s"
                params.append(filter_ip)
            sql += " ORDER BY id DESC LIMIT 8"
            cur.execute(sql, tuple(params))
            traps = cur.fetchall()

            cur.execute("SELECT COUNT(*) as total, SUM(CASE WHEN severity IN ('critical', 'emergency') THEN 1 ELSE 0 END) as crit FROM snmp_traps_received")
            stats = cur.fetchone() or {"total": 0, "crit": 0}

        lines = [
            "🛰️ <b>SNMP TRAPS RECIBIDOS (TELEMETRÍA PUSH)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Total Histórico:</b> <code>{stats.get('total', 0)}</code> | <b>Críticos:</b> 🔴 <code>{stats.get('crit', 0) or 0}</code>",
            f"• <b>Puerto de Escucha:</b> <code>UDP 162</code> (Activo)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ÚLTIMOS TRAPS DETECTADOS:</b>"
        ]

        if not traps:
            lines.append("<i>No se han recibido traps SNMP aún o no coinciden con el filtro.</i>")
        else:
            for t in traps:
                sev = t.get("severity", "info")
                sev_emoji = "🚨" if sev == "emergency" else "🔴" if sev == "critical" else "⚠️" if sev == "warning" else "ℹ️"
                dt_str = t["received_at"].strftime("%d/%m %H:%M:%S") if t.get("received_at") else "N/A"
                proc_str = "✅" if t.get("processed") else "⏳"
                lines.append(
                    f"{sev_emoji} <b>{html.escape(t.get('trap_type') or 'genericTrap')}</b> ({proc_str})\n"
                    f"   └ 📍 <code>{t['source_ip']}</code> | ⏰ {dt_str}\n"
                    f"   └ OID: <code>{t['trap_oid']}</code>"
                )

        lines.append("\n💡 <i>Filtro por equipo: <code>/traps &lt;ip&gt;</code></i>")
        await reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_traps: {e}")
        await reply(update.message, f"❌ Error consultando traps SNMP: {html.escape(str(e))}")
