"""
Manejador de modo depuración y bloqueo global de comandos.
"""

from __future__ import annotations
import logging
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.services.config_manager import load_config, save_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.diagnostics.debug")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id in (owner_id, IMMUTABLE_OWNER_ID)


async def toggle_debug_monitor(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner activar, desactivar o consultar el Modo Depuración del Monitor (Exclusivo en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    cfg = load_config()
    current_status = bool(cfg.get("monitor_debug_mode", False))

    if context.args:
        arg = context.args[0].lower()
        if arg in ("on", "activar", "activado", "true", "1", "si"):
            cfg["monitor_debug_mode"] = True
            save_config(cfg)
            await update.message.reply_text(
                "🧪 <b>Modo Depuración del Monitor: ACTIVADO</b>\n\n"
                "Los próximos reportes incluirán información técnica detallada y el archivo consolidado <code>servicelog.txt</code> enviado exclusivamente a tu chat privado.",
                parse_mode="HTML"
            )
            return
        elif arg in ("off", "desactivar", "desactivado", "false", "0", "no"):
            cfg["monitor_debug_mode"] = False
            save_config(cfg)
            await update.message.reply_text(
                "📊 <b>Modo Depuración del Monitor: DESACTIVADO</b>\n\n"
                "El monitor operará en modo producción estándar.",
                parse_mode="HTML"
            )
            return
        elif arg in ("status", "estado", "ver"):
            estado_txt = "🟢 <b>ACTIVADO</b>" if current_status else "🔴 <b>DESACTIVADO</b>"
            await update.message.reply_text(f"🧪 <b>Estado actual del Modo Depuración:</b> {estado_txt}", parse_mode="HTML")
            return

    estado_str = "🟢 ACTIVO" if current_status else "🔴 INACTIVO"
    keyboard = [
        [
            InlineKeyboardButton("🟢 Activar Depuración", callback_data="auth_toggle_debug:on"),
            InlineKeyboardButton("🔴 Desactivar Depuración", callback_data="auth_toggle_debug:off")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await update.message.reply_text(
        f"🧪 <b>Panel de Modo Depuración (Monitor ATIT)</b>\n\n"
        f"<b>Estado Actual:</b> <code>{estado_str}</code>\n\n"
        "• <b>Activo:</b> Genera reportes exhaustivos y adjunta el archivo <code>servicelog.txt</code>.\n"
        "• <b>Inactivo:</b> Envía el resumen consolidado estándar.\n\n"
        "<i>Presiona una opción para cambiar el estado:</i>",
        parse_mode="HTML",
        reply_markup=reply_markup
    )


async def toggle_commands_lock(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner bloquear o desbloquear la ejecución de comandos para el resto de usuarios y grupos."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    cfg = load_config()
    current_status = bool(cfg.get("commands_locked_for_users", False))

    if context.args:
        arg = context.args[0].lower()
        if arg in ("on", "activar", "activado", "bloquear", "lock", "true", "1", "si"):
            cfg["commands_locked_for_users"] = True
            save_config(cfg)
            await update.message.reply_text(
                "🔒 <b>Bloqueo de Comandos: ACTIVADO</b>\n\n"
                "A partir de ahora, los demás usuarios y los grupos <b>NO podrán ejecutar comandos</b>.\n"
                "Tú como Administrador conservas el acceso total e ilimitado.",
                parse_mode="HTML"
            )
            return
        elif arg in ("off", "desactivar", "desactivado", "desbloquear", "unlock", "false", "0", "no"):
            cfg["commands_locked_for_users"] = False
            save_config(cfg)
            await update.message.reply_text(
                "🔓 <b>Bloqueo de Comandos: DESACTIVADO</b>\n\n"
                "Los usuarios y grupos autorizados <b>pueden volver a ejecutar comandos</b> normalmente.",
                parse_mode="HTML"
            )
            return
        elif arg in ("status", "estado", "ver"):
            estado_txt = "🔒 <b>ACTIVADO (Comandos bloqueados para usuarios/grupos)</b>" if current_status else "🔓 <b>DESACTIVADO (Comandos habilitados)</b>"
            await update.message.reply_text(f"🛡️ <b>Estado del Control de Comandos:</b>\n{estado_txt}", parse_mode="HTML")
            return

    estado_str = "🔒 BLOQUEADOS PARA OTROS" if current_status else "🔓 HABILITADOS PARA TODOS"
    keyboard = [
        [
            InlineKeyboardButton("🔒 Bloquear a Otros", callback_data="auth_toggle_cmd_lock:on"),
            InlineKeyboardButton("🔓 Habilitar a Todos", callback_data="auth_toggle_cmd_lock:off")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    panel_msg = (
        "🛡️ <b>Control de Acceso a Comandos (Exclusivo Owner)</b>\n\n"
        f"<b>Estado Actual:</b> <code>{estado_str}</code>\n\n"
        "• <b>Bloquear:</b> Desactiva la ejecución de comandos para el grupo y usuarios autorizados.\n"
        "• <b>Habilitar:</b> Restablece el uso de comandos a la normalidad.\n\n"
        "<i>Tú como Administrador siempre mantendrás acceso completo e ilimitado a todas las herramientas.</i>"
    )
    await update.message.reply_text(panel_msg, parse_mode="HTML", reply_markup=reply_markup)
