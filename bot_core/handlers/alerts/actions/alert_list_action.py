"""
# ==============================================================================
# 🚨 ACTIONS: LISTADO Y DETALLE DE ALERTAS: alert_list_action.py
# Consulta de incidentes activos y detalle individual de alertas.
# Ubicación: /opt/sonar/bot_core/handlers/alerts/actions/alert_list_action.py
# ==============================================================================
"""
from __future__ import annotations

import html
import logging
from typing import Any

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.alerts.helpers import get_config, get_portal_alerts_url, reply
from bot_core.security.auth import check_authorization
from monitor.core_shield import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.alerts.list")


async def handle_alertas(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra la lista de alertas activas o el detalle de un incidente específico."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    cfg = get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    args = context.args or []
    try:
        if args and args[0].isdigit():
            aid = int(args[0])
            with db_cursor() as cur:
                cur.execute("SELECT * FROM alerts WHERE id = %s", (aid,))
                alert = cur.fetchone()

            if not alert:
                await reply(update.message, f"🔍 Alerta #<code>{aid}</code> no encontrada en el registro.")
                return

            sev_icons = {
                "emergency": "🆘 [EMERGENCIA]",
                "critical": "🚨 [CRÍTICA]",
                "warning": "⚠️ [ADVERTENCIA]",
                "info": "ℹ️ [INFO]"
            }
            icon = sev_icons.get(alert["severity"], "🚨")
            fired_str = alert["fired_at"].strftime("%d/%m/%Y %H:%M:%S") if alert.get("fired_at") else "N/A"
            ack_str = alert["acknowledged_at"].strftime("%d/%m/%Y %H:%M:%S") if alert.get("acknowledged_at") else "No"
            res_str = alert["resolved_at"].strftime("%d/%m/%Y %H:%M:%S") if alert.get("resolved_at") else "Activa"

            msg = (
                f"{icon} <b>DETALLE DEL INCIDENTE #{alert['id']}</b>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🏢 <b>Entidad:</b> <code>{html.escape(alert['entity_name'] or 'N/A')}</code> ({alert['entity_type']})\n"
                f"⚙️ <b>Condición:</b> <code>{alert['condition_type']}</code>\n"
                f"📊 <b>Estado:</b> <code>{alert['status'].upper()}</code>\n"
                f"📈 <b>Nivel Escalación:</b> Nivel {alert['current_escalation_level']}\n"
                f"🕒 <b>Disparo:</b> {fired_str}\n"
                f"👁️ <b>Reconocido:</b> {ack_str}\n"
                f"🏁 <b>Resuelto:</b> {res_str}\n"
                f"📝 <b>Mensaje:</b> {html.escape(alert.get('message') or 'N/A')}\n"
                + (f"📌 <b>Notas:</b> <i>{html.escape(alert['notes'])}</i>\n" if alert.get('notes') else "")
                + f"━━━━━━━━━━━━━━━━━━━━━━\n"
                f"💡 <i>Comandos: /ack {aid} | /silenciar {aid} 60" + (f" | /resolver {aid}" if is_admin else "") + "</i>"
            )
            keyboard = [
                [
                    InlineKeyboardButton("✅ Reconocer (ACK)", callback_data=f"alert_act:ack:{aid}"),
                    InlineKeyboardButton("🔇 Silenciar 1h", callback_data=f"alert_act:silence:{aid}:60")
                ],
                [
                    InlineKeyboardButton("🏁 Resolver", callback_data=f"alert_act:resolve:{aid}"),
                    InlineKeyboardButton("🌐 Portal Web", url=get_portal_alerts_url(aid))
                ]
            ]
            await reply(update.message, msg, reply_markup=InlineKeyboardMarkup(keyboard))
            return

        with db_cursor() as cur:
            cur.execute("""
                SELECT id, severity, status, entity_name, condition_type, current_escalation_level, fired_at
                FROM alerts
                WHERE status IN ('firing', 'acknowledged', 'suppressed')
                ORDER BY FIELD(severity, 'emergency', 'critical', 'warning', 'info'), fired_at DESC
                LIMIT 15
            """)
            alerts = cur.fetchall()

        if not alerts:
            await reply(
                update.message,
                "✅ <b>SISTEMA ESTABLE: CERO ALERTAS ACTIVAS</b>\n\n"
                "No hay incidentes ni alarmas pendientes en la infraestructura."
            )
            return

        sev_map = {
            "emergency": "🆘",
            "critical": "🚨",
            "warning": "⚠️",
            "info": "ℹ️"
        }
        status_map = {
            "firing": "DISPARADA",
            "acknowledged": "RECONOCIDA",
            "suppressed": "SILENCIADA"
        }

        lines = [
            f"🚨 <b>INCIDENTES Y ALERTAS ACTIVAS ({len(alerts)})</b>\n",
            "━━━━━━━━━━━━━━━━━━━━━━"
        ]
        for a in alerts:
            ico = sev_map.get(a["severity"], "🚨")
            st = status_map.get(a["status"], a["status"].upper())
            dt = a["fired_at"].strftime("%d/%m %H:%M") if a.get("fired_at") else "N/A"
            lines.append(
                f"{ico} <b>#{a['id']} [{st}]</b> - <code>{html.escape(a['entity_name'] or 'N/A')}</code>\n"
                f"   └ <i>{a['condition_type']}</i> (Nivel {a['current_escalation_level']}) | {dt}\n"
                f"   └ Acciones: /ack_{a['id']} | /det_{a['id']}"
            )
        lines.append("━━━━━━━━━━━━━━━━━━━━━━")
        lines.append("💡 <i>Usa <code>/alertas &lt;id&gt;</code> para detalle completo.</i>")

        await reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_alertas: {e}")
        await reply(update.message, f"❌ Error consultando alertas: {html.escape(str(e))}")
