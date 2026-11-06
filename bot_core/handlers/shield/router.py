"""
Enrutador delgado para Terminal Shield.
Principio SOLID: Controladores delgados (<25 líneas) que delegan en Acciones.
"""

from __future__ import annotations
import html
import socket
from datetime import datetime
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.shield.actions import (
    AuthorizeTerminalAction,
    KillTerminalAction,
    LockdownAction,
    UnlockAction,
    SoyYoAction,
)
from bot_core.handlers.shield.dtos import ShieldCallbackPayload
from bot_core.services.config_manager import load_config
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

# Registro de acciones para callbacks
_SHIELD_ACTIONS = {
    "shield_kill": KillTerminalAction(),
    "shield_lockdown": LockdownAction(),
    "shield_unlock": UnlockAction(),
    "shield_auth": AuthorizeTerminalAction(),
}


def _is_admin(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed = set(cfg.get("allowed_user_ids", [])) | {IMMUTABLE_OWNER_ID, owner_id}
    return user_id in allowed


async def handle_shield_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Enrutador delgado tipado para callbacks de mitigación de Terminal Shield."""
    query = update.callback_query
    if not query or not query.data:
        return

    if not _is_admin(query.from_user.id):
        await query.answer("🛑 Acción bloqueada: No posee privilegios administrativos.", show_alert=True)
        return

    payload = ShieldCallbackPayload.from_callback_data(query.data)
    action = _SHIELD_ACTIONS.get(payload.action)
    if action:
        await action.execute(payload, update, context)
    else:
        await query.answer("Acción no reconocida.", show_alert=True)


async def handle_soy_yo_text_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Interceptor de texto para 'soy yo', 'autorizar' o 'override'."""
    await SoyYoAction.execute(update, context)


async def cmd_lockdown(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Activa el modo LOCKDOWN de contención perimetral inmediata."""
    user = update.effective_user
    if not user or not _is_admin(user.id):
        if update.message:
            await update.message.reply_text("⛔ Acceso denegado: Comando exclusivo del Administrador.")
        return

    from monitor.terminal_shield import execute_lockdown
    execute_lockdown(target_user=user.username or "admin")
    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    msg = (
        "🚨🚨 <b>MODO LOCKDOWN ACTIVADO</b> 🚨🚨\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"⏰ <b>Hora:</b> <code>{now_str}</code>\n\n"
        "🛡️ <b>Medidas de contención aplicadas de inmediato:</b>\n"
        " • Todas las consolas y emuladores (Konsole, xterm) han sido terminados.\n"
        " • Pantalla y sesiones gráficas bloqueadas.\n"
        " • Servidor VNC detenido temporalmente.\n"
        " • Ejecución de comandos interactivos y sudo restringidos.\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "<i>Para restaurar la operación normal use el botón inferior o /unlock</i>"
    )
    keyboard = [[InlineKeyboardButton("🔓 DESACTIVAR LOCKDOWN", callback_data=f"shield_unlock:{hostname}")]]
    if update.message:
        await update.message.reply_text(msg, parse_mode="HTML", reply_markup=InlineKeyboardMarkup(keyboard))


async def cmd_unlock(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Desactiva el modo LOCKDOWN y restaura los servicios interactivos."""
    user = update.effective_user
    if not user or not _is_admin(user.id):
        if update.message:
            await update.message.reply_text("⛔ Acceso denegado: Comando exclusivo del Administrador.")
        return

    from monitor.terminal_shield import execute_unlock
    execute_unlock()
    hostname = socket.gethostname()

    msg = (
        "✅ <b>MODO LOCKDOWN DESACTIVADO</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n\n"
        "Operación normal restablecida:\n"
        " • Servicio VNC reactivado.\n"
        " • Acceso administrativo y terminales desbloqueados.\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )
    if update.message:
        await update.message.reply_text(msg, parse_mode="HTML")


async def cmd_autorizar_consola(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Habilita una ventana de tiempo autorizada para que el Administrador use terminales."""
    user = update.effective_user
    if not user or not _is_admin(user.id):
        if update.message:
            await update.message.reply_text("⛔ Acceso denegado: Comando exclusivo del Administrador.")
        return

    minutes = 30
    if context.args:
        try:
            m = int(context.args[0])
            if 1 <= m <= 480:
                minutes = m
        except ValueError:
            pass

    from monitor.terminal_shield import execute_auth
    res = execute_auth(minutes)
    hostname = socket.gethostname()

    msg = (
        "🛡️ <b>VENTANA DE MANTENIMIENTO AUTORIZADA</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"⏱️ <b>Duración:</b> <code>{minutes} minutos</code>\n"
        f"⏰ <b>Válido hasta:</b> <code>{res.get('expiry', 'N/A')}</code>\n\n"
        "El Administrador puede abrir Konsole, terminales interactivas y ejecutar comandos sudo "
        "sin que se disparen alertas ni bloqueos preventivos.\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )
    if update.message:
        await update.message.reply_text(msg, parse_mode="HTML")
