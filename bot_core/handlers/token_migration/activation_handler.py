"""
Manejador de activación manual de hardware para DRM (/activar).
"""

from __future__ import annotations
import html
import re
from telegram import Update
from telegram.ext import ContextTypes

from monitor.shield.secure_core import IMMUTABLE_OWNER_ID, activate_hardware_first_boot


async def cmd_activar_manual(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner activar manualmente la verificación de hardware usando /activar <SERIAL>."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if user.id != IMMUTABLE_OWNER_ID:
        return

    serial_arg = ""
    if context.args:
        serial_arg = context.args[0].strip().upper()
    elif update.message.text:
        match = re.search(r'(AUTH-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})', update.message.text, re.IGNORECASE)
        if match:
            serial_arg = match.group(1).upper()

    if not serial_arg:
        await update.message.reply_text("ℹ️ <b>Uso del comando:</b> <code>/activar AUTH-XXXX-XXXX-XXXX-XXXX</code>", parse_mode="HTML")
        return

    ok, msg = activate_hardware_first_boot(serial_arg)
    if ok:
        await update.message.reply_text(
            f"<b>{msg}</b>\n\n🎉 <b>¡Bienvenido!</b> El sistema ha completado la validación de integridad y se encuentra ahora 100% <b>OPERACIONAL</b>.",
            parse_mode="HTML"
        )
    else:
        await update.message.reply_text(
            f"❌ <b>Error de Activación:</b>\n\n<code>{html.escape(msg)}</code>\n\n"
            "<i>Verifique el Serial recibido en la alerta de emergencia e intente nuevamente dentro de la ventana de 10 minutos.</i>",
            parse_mode="HTML"
        )
