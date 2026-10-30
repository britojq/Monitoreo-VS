"""
Manejador de Rollback seguro y reversión atómica de código y base de datos.
"""

from __future__ import annotations
import asyncio
import html
import logging
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.services.config_manager import load_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.gitops.rollback")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id == owner_id


async def cmd_rollback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Revierte actualizaciones del sistema de forma segura (Exclusivo en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    from monitor.system_updater import execute_rollback, restart_service_delayed, safe_deliver_update_result

    if context.args:
        arg = context.args[0].lower()
        if arg in ("now", "apply", "aplicar", "force", "si", "confirm"):
            wait_msg = await update.message.reply_text(
                "⏳ <b>[1/3] Iniciando proceso de Rollback forzado...</b>",
                parse_mode="HTML"
            )

            async def progress_hook(status_text: str) -> None:
                try:
                    await wait_msg.edit_text(status_text, parse_mode="HTML")
                except Exception as e_hook:
                    logger.debug(f"Aviso actualizando wait_msg rollback: {e_hook}")

            try:
                res = await execute_rollback(
                    bot_instance=context.bot,
                    auto_restart=False,
                    progress_callback=progress_hook
                )
            except Exception as e_exec:
                logger.error(f"Error imprevisto ejecutando rollback: {e_exec}", exc_info=True)
                res = f"❌ <b>Error inesperado durante el rollback:</b>\n<pre>{html.escape(str(e_exec))}</pre>"

            await safe_deliver_update_result(update.message, res, edit_msg=wait_msg)
            asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
            return

    keyboard = [
        [
            InlineKeyboardButton("⚠️ Confirmar Reversión Inmediata", callback_data="update_act:rollback_confirm"),
            InlineKeyboardButton("❌ Cancelar", callback_data="update_act:status")
        ]
    ]
    prompt_text = (
        "⚠️ <b>¿Confirma que desea revertir el sistema a la versión anterior?</b>\n\n"
        "• Repositorio Git volverá a <code>HEAD@{1}</code>.\n"
        "• MariaDB restaurará el último snapshot comprimido.\n"
        "• El portal web se sincronizará y se purgará la caché.\n"
        "• El bot se reiniciará automáticamente."
    )
    await update.message.reply_text(prompt_text, parse_mode="HTML", reply_markup=InlineKeyboardMarkup(keyboard))
