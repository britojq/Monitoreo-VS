"""
Manejador de activación, pausa y horarios de cron.
"""

from __future__ import annotations
import html
import logging
import re
from datetime import datetime
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.crons.dtos import CronActionPayload
from bot_core.services.config_manager import load_config, save_config
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.crons")


def _format_cron_panel_text() -> str:
    """Genera el texto informativo del panel de control de cron."""
    cfg = load_config()
    is_enabled = bool(cfg.get("cron_reports_enabled", True))
    schedules = cfg.get("cron_schedules", ["07:30", "16:00"])
    if not isinstance(schedules, list):
        schedules = ["07:30", "16:00"]
    schedules = sorted(list(set(schedules)))

    updated_at = cfg.get("cron_reports_updated_at", "N/A")
    updated_by = cfg.get("cron_reports_updated_by", "N/A")

    now = datetime.now()
    now_hm = now.strftime("%H:%M")
    next_sch = None
    next_day = "hoy"
    for s in schedules:
        if s > now_hm:
            next_sch = s
            break
    if not next_sch and schedules:
        next_sch = schedules[0]
        next_day = "mañana"

    estado_label = "🟢 <b>ACTIVADOS (Operando)</b>" if is_enabled else "🔴 <b>PAUSADOS (Silenciados)</b>"
    horarios_str = ", ".join([f"<code>{h}</code>" for h in schedules]) if schedules else "<i>Ninguno</i>"
    proximo_str = f"<code>{next_sch}</code> ({next_day})" if (next_sch and is_enabled) else ("<i>En pausa</i>" if not is_enabled else "<i>Sin programar</i>")

    return (
        "⏰ <b>Control de Envíos Programados por Cron</b>\n\n"
        f"• <b>Estado:</b> {estado_label}\n"
        f"• <b>Horarios Diarios:</b> {horarios_str}\n"
        f"• <b>Próximo Envío:</b> {proximo_str}\n\n"
        f"👤 <b>Último Cambio:</b> {html.escape(str(updated_by))}\n"
        f"📅 <b>Fecha:</b> <code>{updated_at}</code>\n\n"
        "<b>Opciones de gestión por comando:</b>\n"
        "• <code>/cron on</code> o <code>/cron off</code> (Activar / Pausar)\n"
        "• <code>/cron horario 07:30, 16:00</code> (Asignar horarios)\n"
        "• <code>/cron agregar 12:00</code> (Añadir un horario)\n"
        "• <code>/cron quitar 16:00</code> (Eliminar un horario)"
    )


def _get_cron_keyboard() -> InlineKeyboardMarkup:
    """Genera el teclado interactivo con botones inline para el control de cron."""
    cfg = load_config()
    is_enabled = bool(cfg.get("cron_reports_enabled", True))
    btn_toggle = (
        InlineKeyboardButton("🔴 Pausar Envíos Automáticos", callback_data="cron_act:pause")
        if is_enabled else
        InlineKeyboardButton("🟢 Reanudar Envíos Automáticos", callback_data="cron_act:resume")
    )
    return InlineKeyboardMarkup([
        [btn_toggle],
        [InlineKeyboardButton("🔄 Actualizar Estado", callback_data="cron_act:refresh")]
    ])


async def cmd_cron_control(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Gestiona la activación, pausa y horarios de los reportes programados por cron."""
    chat = update.effective_chat
    user = update.effective_user
    if not user or not update.message:
        return

    cfg = load_config()
    user_id = user.id
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_owner = (user_id == owner_id or user_id == IMMUTABLE_OWNER_ID)
    is_private = (chat and chat.type == "private")

    if not is_owner or not is_private:
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = f"{user.full_name} [Telegram]"

    if context.args:
        subcmd = context.args[0].lower()
        if subcmd in ("on", "activar", "activado", "enable", "start", "1", "si"):
            cfg["cron_reports_enabled"] = True
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config(cfg)
            await update.message.reply_text(
                "🟢 <b>Envíos Programados: ACTIVADOS</b>\n\n"
                "Los reportes de monitoreo se despacharán puntualmente en los horarios configurados.",
                parse_mode="HTML"
            )
            return

        elif subcmd in ("off", "desactivar", "desactivado", "pausar", "pause", "stop", "0", "no"):
            cfg["cron_reports_enabled"] = False
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config(cfg)
            await update.message.reply_text(
                "🔴 <b>Envíos Programados: PAUSADOS</b>\n\n"
                "Se han silenciado los reportes automáticos por cron.",
                parse_mode="HTML"
            )
            return

        elif subcmd in ("horario", "horarios", "set"):
            new_times = []
            for arg in context.args[1:]:
                clean_arg = arg.replace(",", " ").strip()
                for token in clean_arg.split():
                    token = token.strip()
                    if re.match(r"^([01]\d|2[0-3]):[0-5]\d$", token):
                        new_times.append(token)

            if not new_times:
                await update.message.reply_text(
                    "⚠️ Formato inválido. Especifique horarios en formato HH:MM (24h).\n"
                    "Ejemplo: <code>/cron horario 07:30 16:00</code>",
                    parse_mode="HTML"
                )
                return

            cfg["cron_schedules"] = sorted(list(set(new_times)))
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config(cfg)
            h_str = ", ".join([f"<code>{h}</code>" for h in cfg["cron_schedules"]])
            await update.message.reply_text(f"✅ <b>Nuevos Horarios de Envío Asignados:</b> {h_str}", parse_mode="HTML")
            return

        elif subcmd in ("agregar", "add", "+"):
            if len(context.args) < 2:
                await update.message.reply_text("⚠️ Especifique el horario a agregar. Ej: <code>/cron agregar 12:00</code>", parse_mode="HTML")
                return
            new_h = context.args[1].strip()
            if not re.match(r"^([01]\d|2[0-3]):[0-5]\d$", new_h):
                await update.message.reply_text("⚠️ Formato inválido. Use HH:MM en formato 24 horas (ej. 14:30).", parse_mode="HTML")
                return

            cur_sch = set(cfg.get("cron_schedules", ["07:30", "16:00"]))
            cur_sch.add(new_h)
            cfg["cron_schedules"] = sorted(list(cur_sch))
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config(cfg)
            await update.message.reply_text(f"✅ Horario <code>{new_h}</code> agregado con éxito.", parse_mode="HTML")
            return

        elif subcmd in ("quitar", "eliminar", "del", "remove", "-"):
            if len(context.args) < 2:
                await update.message.reply_text("⚠️ Especifique el horario a quitar. Ej: <code>/cron quitar 16:00</code>", parse_mode="HTML")
                return
            del_h = context.args[1].strip()
            cur_sch = list(cfg.get("cron_schedules", ["07:30", "16:00"]))
            if del_h in cur_sch:
                cur_sch.remove(del_h)
                cfg["cron_schedules"] = sorted(cur_sch)
                cfg["cron_reports_updated_at"] = now_str
                cfg["cron_reports_updated_by"] = admin_name
                save_config(cfg)
                await update.message.reply_text(f"🗑️ Horario <code>{del_h}</code> eliminado de la lista.", parse_mode="HTML")
            else:
                await update.message.reply_text(f"ℹ️ El horario <code>{del_h}</code> no estaba configurado.", parse_mode="HTML")
            return

    panel_text = _format_cron_panel_text()
    keyboard = _get_cron_keyboard()
    await update.message.reply_text(panel_text, parse_mode="HTML", reply_markup=keyboard)


async def handle_cron_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja los botones interactivos del panel de cron."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if query.from_user.id != owner_id and query.from_user.id != IMMUTABLE_OWNER_ID:
        await query.answer("⛔ Acción restringida al Administrador.", show_alert=True)
        return

    payload = CronActionPayload.from_callback_data(query.data)
    action = payload.action
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = f"{query.from_user.full_name} [Bot]"

    if action == "pause":
        cfg["cron_reports_enabled"] = False
        cfg["cron_reports_updated_at"] = now_str
        cfg["cron_reports_updated_by"] = admin_name
        save_config(cfg)
        await query.answer("🔴 Envíos programados pausados.")

    elif action == "resume":
        cfg["cron_reports_enabled"] = True
        cfg["cron_reports_updated_at"] = now_str
        cfg["cron_reports_updated_by"] = admin_name
        save_config(cfg)
        await query.answer("🟢 Envíos programados reanudados.")

    elif action == "refresh":
        await query.answer("🔄 Panel actualizado.")

    panel_text = _format_cron_panel_text()
    markup = _get_cron_keyboard()
    try:
        await query.edit_message_text(panel_text, parse_mode="HTML", reply_markup=markup)
    except Exception:
        pass
