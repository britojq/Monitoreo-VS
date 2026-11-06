"""
# ==============================================================================
# 📋 ACTIONS: SYSLOG Y NETFLOW: telemetry_action.py
# Comandos /syslog y /netflow para análisis de tráfico y eventos en tiempo real.
# Ubicación: /opt/sonar/bot_core/handlers/network/actions/telemetry_action.py
# ==============================================================================
"""
import html
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.network.helpers import reply
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.network.telemetry")


async def handle_syslog(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Consulta y filtra eventos recientes recibidos en la consola Syslog centralizada."""
    if not update.message:
        return

    args = context.args or []
    query_text = None
    device_ip = None

    if len(args) >= 2 and args[0].lower() in ("device", "ip", "equipo"):
        device_ip = args[1].strip()
    elif args:
        query_text = " ".join(args).strip()

    try:
        with db_cursor() as cur:
            sql = "SELECT id, source_ip, hostname, severity, program, message, received_at FROM syslog_events WHERE 1=1"
            params = []
            if device_ip:
                sql += " AND source_ip = %s"
                params.append(device_ip)
            if query_text:
                sql += " AND (message LIKE %s OR program LIKE %s OR hostname LIKE %s)"
                term = f"%{query_text}%"
                params.extend([term, term, term])
            sql += " ORDER BY id DESC LIMIT 8"
            cur.execute(sql, tuple(params))
            events = cur.fetchall()

            cur.execute("SELECT COUNT(*) as total, COUNT(DISTINCT source_ip) as sources FROM syslog_events")
            stats = cur.fetchone() or {"total": 0, "sources": 0}

        lines = [
            "📋 <b>CONSOLA CENTRALIZADA DE SYSLOG (LIVE)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Total Eventos:</b> <code>{stats.get('total', 0)}</code> | <b>Fuentes Activas:</b> <code>{stats.get('sources', 0)}</code>",
            f"• <b>Puerto de Escucha:</b> <code>UDP 514</code> (RFC 3164 / RFC 5424)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ÚLTIMOS EVENTOS CAPTURADOS:</b>"
        ]

        if not events:
            lines.append("<i>No se encontraron eventos en syslog coincidentes.</i>")
        else:
            sev_names = {0: "EMERG", 1: "ALERT", 2: "CRIT", 3: "ERR", 4: "WARN", 5: "NOTICE", 6: "INFO", 7: "DEBUG"}
            for ev in events:
                s_lvl = ev.get("severity", 6)
                s_name = sev_names.get(s_lvl, str(s_lvl))
                s_emoji = "🚨" if s_lvl <= 1 else "🔴" if s_lvl == 2 else "⚠️" if s_lvl == 3 else "ℹ️"
                dt_str = ev["received_at"].strftime("%H:%M:%S") if ev.get("received_at") else ""
                clean_msg = html.escape((ev.get("message") or "")[:90])
                prog = html.escape(ev.get("program") or "syslog")
                lines.append(
                    f"{s_emoji} <b>[{s_name}]</b> <code>{prog}</code> ({dt_str})\n"
                    f"   └ 📍 <code>{ev['source_ip']}</code>: <i>{clean_msg}</i>"
                )

        lines.append("\n💡 <i>Uso: <code>/syslog &lt;patrón&gt;</code> o <code>/syslog device &lt;ip&gt;</code></i>")
        await reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_syslog: {e}")
        await reply(update.message, f"❌ Error consultando syslog: {html.escape(str(e))}")


async def handle_netflow(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el análisis de tráfico y Top Talkers capturados vía NetFlow v5."""
    if not update.message:
        return

    args = context.args or []
    filter_type = args[0].lower() if args else "top"

    try:
        with db_cursor() as cur:
            cur.execute("SELECT COUNT(*) as total_flows, SUM(bytes) as total_bytes, SUM(packets) as total_pkts FROM netflow_records")
            flow_stats = cur.fetchone() or {"total_flows": 0, "total_bytes": 0, "total_pkts": 0}

            cur.execute("SELECT MAX(window_start) as latest_win FROM netflow_top_talkers")
            win_row = cur.fetchone()
            latest_win = win_row["latest_win"] if win_row else None

            talkers = []
            if latest_win:
                if filter_type in ("src", "source", "emisores"):
                    cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s AND rank_type = 'src_ip' ORDER BY bytes DESC LIMIT 6", (latest_win,))
                elif filter_type in ("dst", "dest", "receptores"):
                    cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s AND rank_type = 'dst_ip' ORDER BY bytes DESC LIMIT 6", (latest_win,))
                elif filter_type in ("proto", "protocolo"):
                    cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s AND rank_type = 'protocol' ORDER BY bytes DESC LIMIT 5", (latest_win,))
                else:
                    cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s ORDER BY bytes DESC LIMIT 8", (latest_win,))
                talkers = cur.fetchall()

        total_mb = round((flow_stats.get("total_bytes") or 0) / 1048576, 2)

        lines = [
            "🌊 <b>ANÁLISIS DE TRÁFICO NETFLOW V5 & TOP TALKERS</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Flujos Totales:</b> <code>{flow_stats.get('total_flows', 0)}</code> ventanas (1m)",
            f"• <b>Volumen Acumulado:</b> <code>{total_mb} MB</code> ({flow_stats.get('total_pkts', 0)} paquetes)",
            f"• <b>Puerto Colector:</b> <code>UDP 2055</code> (Activo)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"<b>RANKING TOP TALKERS ({filter_type.upper()}):</b>"
        ]

        if not talkers:
            lines.append("<i>No hay datos de flujos materializados todavía en la última ventana de 5m.</i>")
        else:
            for tk in talkers:
                rtype = tk.get("rank_type")
                rval = tk.get("rank_value")
                b_mb = round((tk.get("bytes") or 0) / 1048576, 2)
                pct = tk.get("percentage") or 0.0
                type_icon = "📤" if rtype == "src_ip" else ("📥" if rtype == "dst_ip" else "⚡")
                lines.append(f"{type_icon} <b>{html.escape(str(rval))}</b>: <code>{b_mb} MB</code> ({pct}%) | {tk.get('packets')} pkts")

        lines.append("\n💡 <i>Filtros: <code>/netflow src</code> | <code>/netflow dst</code> | <code>/netflow proto</code></i>")
        await reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_netflow: {e}")
        await reply(update.message, f"❌ Error consultando NetFlow: {html.escape(str(e))}")
