"""
# ==============================================================================
# 🚨 HANDLERS DE ALERTAS E INCIDENTES: alert_handlers.py
# Comandos y Callbacks de Telegram para ACK, Silenciamiento, Resolución y Reglas.
# Principio de Responsabilidad Única (S) y Segregación de Dominio.
# ==============================================================================
"""

from __future__ import annotations

import html
import logging
import sys
from datetime import datetime, timedelta
from typing import Any, Dict, List, Optional

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.utils.telegram_helpers import safe_reply_html
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot.handlers.alert")


async def _reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Invoca safe_reply_html dinámicamente respetando cualquier patch en bot.safe_reply_html."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


def _get_config() -> Dict[str, Any]:
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    from monitor.config_parser import MonitorConfigLoader
    return MonitorConfigLoader().raw_bot


async def cmd_alertas(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra la lista de alertas activas o el detalle de un incidente específico."""
    if not update.message:
        return

    cfg = _get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    args = context.args or []
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()

        if args and args[0].isdigit():
            aid = int(args[0])
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM alerts WHERE id = %s", (aid,))
                alert = cur.fetchone()

            if not alert:
                await _reply(update.message, f"🔍 Alerta #<code>{aid}</code> no encontrada en el registro.")
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
                    InlineKeyboardButton("🌐 Portal Web", url=f"http://192.168.1.220/admin/alerts?search={aid}")
                ]
            ]
            await _reply(update.message, msg, reply_markup=InlineKeyboardMarkup(keyboard))
            return

        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, severity, status, entity_name, condition_type, current_escalation_level, fired_at
                FROM alerts
                WHERE status IN ('firing', 'acknowledged', 'suppressed')
                ORDER BY FIELD(severity, 'emergency', 'critical', 'warning', 'info'), fired_at DESC
                LIMIT 15
            """)
            alerts = cur.fetchall()

        if not alerts:
            await _reply(
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

        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_alertas: {e}")
        await _reply(update.message, f"❌ Error consultando alertas: {html.escape(str(e))}")
    finally:
        try:
            conn.close()
        except Exception:
            pass


async def cmd_ack(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reconoce un incidente activo."""
    if not update.message:
        return

    args = context.args or []
    if not args or not args[0].isdigit():
        await _reply(update.message, "⚠️ Debe indicar el ID de la alerta a reconocer. Ej: <code>/ack 1 [notas opcionales]</code>")
        return

    aid = int(args[0])
    notes = " ".join(args[1:]) if len(args) > 1 else "Reconocido desde Telegram"

    try:
        from monitor.alert_engine import AlertEngine
        engine = AlertEngine()
        user_id = update.effective_user.id if update.effective_user else 1
        ok = engine.acknowledge_alert(aid, user_id=user_id, notes=notes)

        if ok:
            user_name = update.effective_user.first_name if update.effective_user else "Operador"
            await _reply(
                update.message,
                f"👁️ <b>Alerta #{aid} Reconocida con Éxito</b>\n\n"
                f"El incidente ha pasado a estado <code>ACKNOWLEDGED</code> por el usuario <b>{html.escape(user_name)}</b>."
            )
        else:
            await _reply(update.message, f"⚠️ La alerta #{aid} no existe, ya fue reconocida o ya está resuelta.")
    except Exception as e:
        await _reply(update.message, f"❌ Error reconociendo alerta: {html.escape(str(e))}")


async def cmd_resolver(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Resuelve manualmente una alerta (Exclusivo Administrador)."""
    if not update.message:
        return

    cfg = _get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if not (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id):
        await _reply(update.message, "⛔ La resolución manual de incidentes está reservada para el Administrador.")
        return

    args = context.args or []
    if not args or not args[0].isdigit():
        await _reply(update.message, "⚠️ Especifique el ID de la alerta a resolver. Ej: <code>/resolver 1 [notas opcionales]</code>")
        return

    aid = int(args[0])
    notes = " ".join(args[1:]) if len(args) > 1 else "Cerrado manualmente por Administrador vía Telegram"

    try:
        from monitor.alert_engine import AlertEngine
        engine = AlertEngine()
        ok = engine.resolve_alert(aid, notes=notes)
        if ok:
            await _reply(
                update.message,
                f"🏁 <b>Alerta #{aid} Marcada como Resuelta</b>\n\n"
                f"El incidente ha sido cerrado y archivado en MariaDB (SSOT)."
            )
        else:
            await _reply(update.message, f"⚠️ La alerta #{aid} no existe o ya se encuentra resuelta.")
    except Exception as e:
        await _reply(update.message, f"❌ Error resolviendo alerta: {html.escape(str(e))}")


async def cmd_silenciar(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Silencia temporalmente una alerta por N minutos."""
    if not update.message:
        return

    cfg = _get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if not (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id):
        await _reply(update.message, "⛔ El silenciamiento de alertas está reservado para el Administrador.")
        return

    args = context.args or []
    if not args or not args[0].isdigit():
        await _reply(update.message, "⚠️ Uso: <code>/silenciar &lt;id_alerta&gt; [minutos=60] [motivo]</code>")
        return

    aid = int(args[0])
    minutes = int(args[1]) if len(args) > 1 and args[1].isdigit() else 60
    notes = " ".join(args[2:]) if len(args) > 2 else f"Silenciada por {minutes}m desde Telegram"

    try:
        from monitor.alert_engine import AlertEngine
        engine = AlertEngine()
        ok = engine.silence_alert(aid, minutes=minutes, user_id=user_id, notes=notes)
        if ok:
            await _reply(
                update.message,
                f"🛡️ <b>Alerta #{aid} Silenciada</b>\n\n"
                f"Se ha creado una ventana de supresión temporal por <b>{minutes} minutos</b>."
            )
        else:
            await _reply(update.message, f"⚠️ No se encontró la alerta #{aid}.")
    except Exception as e:
        await _reply(update.message, f"❌ Error silenciando alerta: {html.escape(str(e))}")


async def cmd_mantenimiento(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Gestiona o lista ventanas de mantenimiento."""
    if not update.message:
        return

    cfg = _get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    args = context.args or []
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()

        if not args:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT id, title, entity_type, entity_id, starts_at, ends_at, is_active
                    FROM maintenance_windows
                    WHERE ends_at >= NOW() - INTERVAL 2 HOUR
                    ORDER BY starts_at DESC
                    LIMIT 10
                """)
                windows = cur.fetchall()

            if not windows:
                await _reply(
                    update.message,
                    "🛠️ <b>VENTANAS DE MANTENIMIENTO</b>\n\n"
                    "No hay ventanas de mantenimiento programadas ni en curso."
                )
                return

            lines = ["🛠️ <b>VENTANAS DE MANTENIMIENTO PROGRAMADAS</b>\n"]
            now = datetime.now()
            for w in windows:
                s_at = w["starts_at"]
                e_at = w["ends_at"]
                is_running = (s_at and e_at and s_at <= now <= e_at)
                st_icon = "🟢 En Curso" if is_running else ("⏳ Programado" if s_at > now else "🏁 Finalizado")
                s_str = s_at.strftime("%d/%m %H:%M") if s_at else "N/A"
                e_str = e_at.strftime("%d/%m %H:%M") if e_at else "N/A"
                lines.append(
                    f"• <b>{html.escape(w['title'])}</b> [{st_icon}]\n"
                    f"   └ Entidad: <code>{w['entity_type']}</code> | {s_str} &rarr; {e_str}"
                )
            await _reply(update.message, "\n".join(lines))
            return

        if not is_admin:
            await _reply(update.message, "⛔ La creación de ventanas de mantenimiento está reservada para el Administrador.")
            return

        entity = args[0].lower()
        minutes = int(args[1]) if len(args) > 1 and args[1].isdigit() else 60
        title = " ".join(args[2:]) if len(args) > 2 else f"Mantenimiento {entity} ({minutes}m)"

        now = datetime.now()
        ends = now + timedelta(minutes=minutes)
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")
        ends_str = ends.strftime("%Y-%m-%d %H:%M:%S")

        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO maintenance_windows
                (title, description, entity_type, starts_at, ends_at, is_active, created_at, updated_at)
                VALUES (%s, 'Programado desde Telegram', %s, %s, %s, 1, %s, %s)
            """, (title, entity, now_str, ends_str, now_str, now_str))

        await _reply(
            update.message,
            f"🛠️ <b>Ventana de Mantenimiento Programada</b>\n\n"
            f"• <b>Título:</b> {html.escape(title)}\n"
            f"• <b>Entidad:</b> <code>{entity}</code>\n"
            f"• <b>Duración:</b> {minutes} minutos (hasta {ends.strftime('%d/%m/%Y %H:%M')})\n"
            f"• <b>Estado:</b> 🟢 Activa inmediatamente"
        )

    except Exception as e:
        logger.error(f"Error en cmd_mantenimiento: {e}")
        await _reply(update.message, f"❌ Error: {html.escape(str(e))}")
    finally:
        try:
            conn.close()
        except Exception:
            pass


async def cmd_reglas(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra las reglas de monitoreo y alerta configuradas."""
    if not update.message:
        return

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()

        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, name, entity_type, condition_type, severity, threshold_value, comparison, cooldown_minutes
                FROM alert_rules
                WHERE is_active = 1
                ORDER BY id ASC
            """)
            rules = cur.fetchall()

        if not rules:
            await _reply(update.message, "ℹ️ No hay reglas de alerta configuradas en la base de datos.")
            return

        sev_map = {
            "emergency": "🆘",
            "critical": "🚨",
            "warning": "⚠️",
            "info": "ℹ️"
        }
        lines = [
            f"📋 <b>REGLAS CORPORATIVAS DE ALERTA ({len(rules)})</b>\n"
        ]
        for r in rules:
            ico = sev_map.get(r["severity"], "🚨")
            thr = f" ({r['comparison']} {r['threshold_value']})" if r.get("threshold_value") is not None else ""
            lines.append(f"{ico} <b>{html.escape(r['name'])}</b>\n   └ <code>{r['entity_type']}</code> &rarr; <code>{r['condition_type']}</code>{thr} [Cooldown: {r['cooldown_minutes']}m]")

        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_reglas: {e}")
        await _reply(update.message, f"❌ Error consultando reglas: {html.escape(str(e))}")
    finally:
        try:
            conn.close()
        except Exception:
            pass


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

    cfg = _get_config()
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
    portal_url = "http://192.168.1.220/admin/alerts"

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
                    InlineKeyboardButton("🌐 Ver en Portal Web", url=f"{portal_url}?search={aid}")
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
                    InlineKeyboardButton("🌐 Ver en Portal Web", url=f"{portal_url}?search={aid}")
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
                    InlineKeyboardButton("🌐 Ver en Portal Web", url=f"{portal_url}?search={aid}")
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
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
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
        finally:
            try:
                conn.close()
            except Exception:
                pass


def register_alert_routes(application: Any) -> None:
    """Registra los comandos y callbacks del sistema de alertas e incidentes."""
    from telegram.ext import CallbackQueryHandler, CommandHandler
    application.add_handler(CommandHandler(["alertas", "alerta", "alerts"], cmd_alertas))
    application.add_handler(CommandHandler(["ack", "reconocer"], cmd_ack))
    application.add_handler(CommandHandler(["resolver", "resolve"], cmd_resolver))
    application.add_handler(CommandHandler(["silenciar", "silence", "mute"], cmd_silenciar))
    application.add_handler(CommandHandler(["mantenimiento", "maint"], cmd_mantenimiento))
    application.add_handler(CommandHandler(["reglas", "rules"], cmd_reglas))
    application.add_handler(CallbackQueryHandler(handle_alert_action_callback, pattern=r"^alert_act:"))
    logger.info("Rutas de Alertas e Incidentes registradas exitosamente.")
