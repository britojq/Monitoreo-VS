"""
Manejador de optimización de RAM, Swap y CPU.
Principio SOLID: Módulo autocontenido y desacoplado.
"""

from __future__ import annotations
import asyncio
import html
import logging
from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.system_maintenance.dtos import OptimizerActionPayload
from bot_core.services.config_manager import load_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.system_maintenance.optimizer")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id == owner_id


async def cmd_recursos_sistema(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Diagnóstico integral de recursos (RAM, Swap, CPU) y optimización (EXCLUSIVO OWNER en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Analizando telemetría del servidor, memoria RAM, Swap y estado del sistema...</i>",
        parse_mode="HTML"
    )

    try:
        from monitor.system_optimizer import format_optimizer_dashboard_html
        dashboard_text, keyboard = await asyncio.to_thread(format_optimizer_dashboard_html)

        try:
            await wait_msg.delete()
        except Exception:
            pass

        await update.message.reply_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
    except Exception as e:
        logger.error(f"Error generando panel de recursos: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error generando diagnóstico de recursos:</b> <code>{html.escape(str(e))}</code>", parse_mode="HTML")
        except Exception:
            await update.message.reply_text(f"❌ Error: {e}")


async def handle_optimizer_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de optimización de recursos."""
    query = update.callback_query
    if not query or not query.data:
        return

    if not _is_owner(query.from_user.id):
        await query.answer("⛔ Solo el creador del bot puede ejecutar acciones de optimización del sistema.", show_alert=True)
        return

    payload = OptimizerActionPayload.from_callback_data(query.data)
    action = payload.action

    if not action:
        await query.answer("⚠️ Solicitud inválida.")
        return

    from monitor.system_optimizer import execute_optimize_plasma, format_optimizer_dashboard_html

    if action == "refresh":
        await query.answer("🔄 Actualizando diagnóstico de recursos...")
        dashboard_text, keyboard = await asyncio.to_thread(format_optimizer_dashboard_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return

    if action == "restart_plasma":
        await query.answer("🧹 Optimizando y liberando memoria...")
        try:
            await query.edit_message_text(
                "⏳ <b>Ejecutando optimización del entorno gráfico y memoria...</b>\n"
                "<i>Por favor espera unos segundos mientras se liberan recursos y se reanuda Plasma Shell...</i>",
                parse_mode="HTML"
            )
        except Exception:
            pass

        banner, _ = await asyncio.to_thread(execute_optimize_plasma)
        dashboard_text, keyboard = await asyncio.to_thread(format_optimizer_dashboard_html)
        full_message = f"{banner}\n\n═══════════════════════════════\n\n{dashboard_text}"

        try:
            await query.edit_message_text(full_message, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            try:
                await query.message.reply_text(full_message, parse_mode="HTML", reply_markup=keyboard)
            except Exception:
                pass
