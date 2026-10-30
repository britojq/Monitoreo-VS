"""
Manejador de reinicio controlado del sistema operativo (/reinicia).
"""

from __future__ import annotations
import asyncio
import logging
import subprocess
from telegram import Update
from telegram.ext import ContextTypes

from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.emergency.reboot")


async def cmd_reinicia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Ejecuta el reinicio completo del sistema operativo (sudo reboot, Exclusivo Owner en privado)."""
    user = update.effective_user
    if not user or user.id != IMMUTABLE_OWNER_ID:
        logger.warning(f"Intento NO AUTORIZADO de ejecutar /reinicia por usuario: {user.id if user else 'desconocido'}")
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            await update.message.reply_text("⛔ Comando exclusivo del Administrador.")
        return

    logger.critical(f"🛑 INSTRUCCIÓN DE REINICIO DEL SERVIDOR (/reinicia) RECIBIDA POR EL OWNER (ID: {user.id})")

    if update.message and update.effective_chat and update.effective_chat.type in ("group", "supergroup"):
        try:
            await update.message.delete()
        except Exception:
            pass

    msg_owner = (
        "🔄 <b>REINICIO DEL SERVIDOR EN PROGRESO</b>\n"
        "━━━━━━━━━━━━\n"
        "⚠️ <b>Instrucción recibida:</b> <code>/reinicia</code>\n\n"
        "💾 <i>Sincronizando buffers de disco (sync)...</i>\n"
        "⏳ <i>Ejecutando reinicio del sistema (<code>sudo reboot</code>) en 2 segundos...</i>\n"
        "━━━━━━━━━━━━\n"
        "👋 <i>El bot y todos los servicios se reactivarán automáticamente tras el arranque.</i>"
    )

    try:
        await context.bot.send_message(chat_id=IMMUTABLE_OWNER_ID, text=msg_owner, parse_mode="HTML")
    except Exception as e_send:
        logger.error(f"Error enviando notificación de reinicio al Owner: {e_send}")
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            try:
                await update.message.reply_text(msg_owner, parse_mode="HTML")
            except Exception:
                pass

    async def _execute_system_reboot() -> None:
        await asyncio.sleep(2.0)
        try:
            subprocess.run(["sync"], check=False)
            subprocess.run(["sudo", "reboot"], check=False)
        except Exception as err:
            logger.error(f"Error al ejecutar sudo reboot: {err}")

    asyncio.create_task(_execute_system_reboot())
