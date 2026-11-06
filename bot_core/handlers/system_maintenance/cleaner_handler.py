"""
Manejador de limpieza de almacenamiento y cachés.
Principio SOLID: Módulo autocontenido y desacoplado.
"""

from __future__ import annotations
import html
import logging
from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.system_maintenance.dtos import CleanerActionPayload
from bot_core.services.config_manager import load_config
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.system_maintenance.cleaner")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id == owner_id


async def cmd_limpiador(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Diagnóstico de almacenamiento y limpieza interactiva del sistema (EXCLUSIVO OWNER en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Analizando almacenamiento, inodos y cachés del sistema... Por favor espere unos segundos.</i>",
        parse_mode="HTML"
    )

    try:
        from monitor.tools.system_cleaner import build_cleaner_dashboard
        dashboard_text, keyboard = await build_cleaner_dashboard()

        try:
            await wait_msg.delete()
        except Exception:
            pass

        await update.message.reply_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
    except Exception as e:
        logger.error(f"Error generando panel de limpieza: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error generando panel de limpieza:</b> <code>{html.escape(str(e))}</code>", parse_mode="HTML")
        except Exception:
            await update.message.reply_text(f"❌ Error: {e}")


async def handle_cleaner_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de limpieza."""
    query = update.callback_query
    if not query or not query.data:
        return

    if not _is_owner(query.from_user.id):
        await query.answer("⛔ Solo el creador del bot puede ejecutar acciones de limpieza del sistema.", show_alert=True)
        return

    payload = CleanerActionPayload.from_callback_data(query.data)
    action = payload.action

    if not action:
        await query.answer("⚠️ Solicitud inválida.")
        return

    from monitor.tools.system_cleaner import build_cleaner_dashboard, execute_clean_task

    if action == "refresh":
        await query.answer("🔄 Actualizando diagnóstico del sistema...")
        dashboard_text, keyboard = await build_cleaner_dashboard()
        try:
            await query.edit_message_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return

    await query.answer("⚙️ Ejecutando limpieza en el sistema...")
    result_banner = await execute_clean_task(action)
    dashboard_text, keyboard = await build_cleaner_dashboard()
    full_message = f"{result_banner}\n\n═══════════════════════════════\n\n{dashboard_text}"

    try:
        await query.edit_message_text(full_message, parse_mode="HTML", reply_markup=keyboard)
    except Exception:
        try:
            await query.message.reply_text(result_banner, parse_mode="HTML")
        except Exception:
            pass
