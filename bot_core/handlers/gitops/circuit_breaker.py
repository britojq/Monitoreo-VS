"""
Manejador de Circuit Breaker (.update_lock) y reinicio seguro del bot.
"""

from __future__ import annotations
import asyncio
import logging
from telegram import Update
from telegram.ext import ContextTypes

from bot_core.services.config_manager import load_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.gitops.circuit_breaker")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id in (owner_id, IMMUTABLE_OWNER_ID)


async def cmd_desbloquear_update(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Libera el Circuit Breaker de actualización (.update_lock)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    from monitor.system_updater import clear_update_lock, is_update_locked
    locked, _ = is_update_locked()
    clear_update_lock()

    if locked:
        msg = (
            "🔓 <b>Circuit Breaker Liberado Exitosamente</b>\n\n"
            "Se ha retirado la protección de bloqueo de despliegue.\n"
            "El sistema está listo nuevamente para ejecutar <code>/actualizar</code>."
        )
    else:
        msg = (
            "ℹ️ <b>Circuit Breaker Despejado</b>\n\n"
            "El sistema no se encontraba bloqueado. Puede ejecutar <code>/actualizar</code> con normalidad."
        )
    await update.message.reply_text(msg, parse_mode="HTML")


async def cmd_reiniciar_bot(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reinicia el servicio systemd del bot de forma segura (Exclusivo Owner)."""
    user = update.effective_user
    if not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador.")
        return

    await update.message.reply_text(
        "⚡ <b>Reiniciando servicio del bot en segundo plano...</b>\n<i>Estará de vuelta en breves segundos.</i>",
        parse_mode="HTML"
    )
    from monitor.system_updater import restart_service_delayed
    asyncio.create_task(restart_service_delayed(delay_seconds=1.5))
