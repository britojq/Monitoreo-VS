"""
# ==============================================================================
# 🔘 ACTIONS: CALLBACKS DE ALERTAS: alert_callback_action.py
# Manejo de botones interactivos inline (ACK, Silencio, Resolver, Detalle).
# Ubicación: /opt/sonar/bot_core/handlers/alerts/actions/alert_callback_action.py
# ==============================================================================
"""
from __future__ import annotations

from datetime import datetime
import html
import logging
from typing import Any

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.alerts.helpers import get_config, get_portal_alerts_url
from bot_core.utils.telegram_helpers import safe_reply_html
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.alerts.callbacks")


async def handle_alert_action_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja los botones interactivos en línea de las alertas (Reconocer, Silenciar, Resolver, Detalle)."""
    query = update.callback_query
    if not query or not query.data:
        return

    parts = query.data.split(":")
    if len(parts) < 3:
        await query.answer("⚠️ Parámetros de alerta inválidos.")
        return

    action = parts[1]
    try:
        aid = int(parts[2])
    except ValueError:
        await query.answer("⚠️ ID de alerta inválido.")
        return

    cfg = get_config()
    user_id = query.from_user.id
    user_name = query.from_user.first_name or query.from_user.username or f"Usuario {user_id}"
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    authorized_users = cfg.get("authorized_users", [])
    if not (is_admin or user_id in authorized_users or str(user_id) in authorized_users):
        await query.answer("⛔ No tienes permisos para gestionar incidentes de infraestructura.", show_alert=True)
        return

    from monitor.alert_engine import AlertEngine
    engine = AlertEngine()

    if action == "ack":
        ok = engine.acknowledge_alert(aid, user_id=user_id, notes=f"Reconocido en Telegram por {user_name}")
        if ok:
            await query.answer("✅ Incidente reconocido con éxito.")
            original_text = query.message.text_html or query.message.text or ""
            if "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n✅ <b>Reconocido" in original_text:
                original_text = original_text.split("━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n✅ <b>Reconocido")[0].rstrip()
            elif "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔇 <b>Silenciado" in original_text:
                original_text = original_text.split("━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔇 <b>Silenciado")[0].rstrip()

            now_str = datetime.now().strftime("%d/%m/%Y %I:%M %p")
            ack_footer = f"\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n✅ <b>Reconocido por:</b> {html.escape(user_name)} ({now_str})"

            new_buttons = [
                [
                    InlineKeyboardButton("🔇 Silenciar 1h", callback_data=f"alert_act:silence:{aid}:60"),
                    InlineKeyboardButton("🏁 Resolver", callback_data=f"alert_act:resolve:{aid}")
                ],
                [
                    InlineKeyboardButton("📋 Detalle", callback_data=f"alert_act:detail:{aid}"),
                    InlineKeyboardButton("🌐 Ver en Portal Web", url=get_portal_alerts_url(aid))
                ]
            ]
            try:
                await query.edit_message_text(
                    text=original_text + ack_footer,
                    parse_mode="HTML",
                    reply_markup=InlineKeyboardMarkup(new_buttons)
                )
            except Exception as e:
                logger.warning(f"Error editando mensaje de alerta en ACK: {e}")
        else:
            await query.answer("⚠️ La alerta ya fue reconocida o ya se encuentra resuelta.", show_alert=True)

    elif action == "silence":
        mins = int(parts[3]) if len(parts) > 3 and parts[3].isdigit() else 60
        ok = engine.silence_alert(aid, minutes=mins, user_id=user_id, notes=f"Silenciada en Telegram por {user_name}")
        if ok:
            await query.answer(f"🔇 Incidente silenciado por {mins}m.")
            original_text = query.message.text_html or query.message.text or ""
            if "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n✅ <b>Reconocido" in original_text:
                original_text = original_text.split("━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n✅ <b>Reconocido")[0].rstrip()
            elif "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔇 <b>Silenciado" in original_text:
                original_text = original_text.split("━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔇 <b>Silenciado")[0].rstrip()

            now_str = datetime.now().strftime("%d/%m/%Y %I:%M %p")
            silence_footer = f"\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔇 <b>Silenciado {mins}m por:</b> {html.escape(user_name)} ({now_str})"

            new_buttons = [
                [
                    InlineKeyboardButton("✅ Reconocer (ACK)", callback_data=f"alert_act:ack:{aid}"),
                    InlineKeyboardButton("🏁 Resolver", callback_data=f"alert_act:resolve:{aid}")
                ],
                [
                    InlineKeyboardButton("📋 Detalle", callback_data=f"alert_act:detail:{aid}"),
                    InlineKeyboardButton("🌐 Ver en Portal Web", url=get_portal_alerts_url(aid))
                ]
            ]
            try:
                await query.edit_message_text(
                    text=original_text + silence_footer,
                    parse_mode="HTML",
                    reply_markup=InlineKeyboardMarkup(new_buttons)
                )
            except Exception as e:
                logger.warning(f"Error editando mensaje de alerta en silencio: {e}")
        else:
            await query.answer("⚠️ No se pudo silenciar la alerta.", show_alert=True)

    elif action == "resolve":
        if not is_admin:
            await query.answer("⛔ Solo el Administrador puede marcar incidentes como resueltos.", show_alert=True)
            return

        ok = engine.resolve_alert(aid, notes=f"Resuelto en Telegram por {user_name}")
        if ok:
            await query.answer("🏁 Incidente resuelto y archivado.")
            original_text = query.message.text_html or query.message.text or ""
            now_str = datetime.now().strftime("%d/%m/%Y %I:%M %p")
            resolved_footer = f"\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🏁 <b>RESUELTO por:</b> {html.escape(user_name)} ({now_str})"

            new_buttons = [
                [
                    InlineKeyboardButton("📋 Ver Detalle Final", callback_data=f"alert_act:detail:{aid}"),
                    InlineKeyboardButton("🌐 Ver en Portal Web", url=get_portal_alerts_url(aid))
                ]
            ]
            try:
                await query.edit_message_text(
                    text=original_text + resolved_footer,
                    parse_mode="HTML",
                    reply_markup=InlineKeyboardMarkup(new_buttons)
                )
            except Exception as e:
                logger.warning(f"Error editando mensaje al resolver: {e}")
        else:
            await query.answer("⚠️ La alerta ya está resuelta o no existe.", show_alert=True)

    elif action == "detail":
        try:
            with db_cursor() as cur:
                cur.execute("SELECT * FROM alerts WHERE id = %s", (aid,))
                alert = cur.fetchone()

            if not alert:
                await query.answer("🔍 Alerta no encontrada.", show_alert=True)
                return

            await query.answer()
            fired_str = alert["fired_at"].strftime("%d/%m/%Y %H:%M:%S") if alert.get("fired_at") else "N/A"
            ack_str = alert["acknowledged_at"].strftime("%d/%m/%Y %H:%M:%S") if alert.get("acknowledged_at") else "No"
            res_str = alert["resolved_at"].strftime("%d/%m/%Y %H:%M:%S") if alert.get("resolved_at") else "Activa"

            msg = (
                f"📋 <b>HISTORIAL DEL INCIDENTE #{alert['id']}</b>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🏢 <b>Entidad:</b> <code>{html.escape(alert['entity_name'] or 'N/A')}</code> ({alert['entity_type']})\n"
                f"⚙️ <b>Condición:</b> <code>{alert['condition_type']}</code>\n"
                f"📊 <b>Estado:</b> <code>{alert['status'].upper()}</code>\n"
                f"🕒 <b>Disparo:</b> {fired_str}\n"
                f"👁️ <b>Reconocido:</b> {ack_str}\n"
                f"🏁 <b>Resuelto:</b> {res_str}\n"
                f"📝 <b>Mensaje:</b> {html.escape(alert.get('message') or 'N/A')}\n"
                + (f"📌 <b>Notas:</b> <i>{html.escape(alert['notes'])}</i>\n" if alert.get('notes') else "")
                + f"━━━━━━━━━━━━━━━━━━━━━━"
            )
            await safe_reply_html(query.message, msg)
        except Exception as e:
            logger.error(f"Error en callback detail de alerta: {e}")
            await query.answer("❌ Error consultando detalle.")
