"""
Conversación guiada e interactiva para la migración de token de Telegram.
"""

from __future__ import annotations
import asyncio
import html
import time
from telegram import Update
from telegram.ext import ContextTypes, ConversationHandler

from bot_core.handlers.token_migration.dtos import MIGRAR_TOKEN_WAITING
from monitor.core_shield import IMMUTABLE_OWNER_ID, migrate_core_token


async def cmd_migrar_token_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Inicia el flujo conversacional interactivo para la migración de token (Exclusivo en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return ConversationHandler.END

    if user.id != IMMUTABLE_OWNER_ID:
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return ConversationHandler.END

    context.user_data["migrar_token_ts"] = time.time()

    await update.message.reply_text(
        "🔄 <b>Migración de Identidad del Bot (Token de Telegram)</b>\n\n"
        "Este proceso re-cifrará el nuevo token con los estándares de seguridad locales sin exponerlo en texto plano.\n\n"
        "1️⃣ Ve a @BotFather y copia tu nuevo Token de bot.\n"
        "2️⃣ <b>Pega y envía el nuevo Token</b> como respuesta a este mensaje.\n\n"
        "⏳ <i>Tienes 2 minutos para responder. Envía <code>/cancelar</code> para abortar.</i>",
        parse_mode="HTML"
    )
    return MIGRAR_TOKEN_WAITING


async def handle_new_token_input(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Procesa, valida con Telegram y re-cifra el nuevo token de forma segura."""
    user = update.effective_user
    if not user or user.id != IMMUTABLE_OWNER_ID or not update.message:
        return ConversationHandler.END

    start_ts = context.user_data.get("migrar_token_ts", 0)
    if time.time() - start_ts > 120:
        await update.message.reply_text(
            "⏱️ <b>Operación Expirada:</b> Ha transcurrido el tiempo límite de 2 minutos. La migración ha sido cancelada.",
            parse_mode="HTML"
        )
        return ConversationHandler.END

    new_token = update.message.text.strip() if update.message.text else ""

    if new_token.lower() in ("/cancelar", "cancelar", "/cancel", "abortar"):
        await update.message.reply_text("🛑 <b>Migración cancelada:</b> Se mantiene el token actual sin modificaciones.", parse_mode="HTML")
        return ConversationHandler.END

    status_msg = await update.message.reply_text("🔄 Validando nuevo token contra la API oficial de Telegram...")
    ok, msg = migrate_core_token(new_token)

    if ok:
        try:
            await status_msg.edit_text(
                f"{msg}\n\n"
                "🚀 <b>Reiniciando servicio en 3 segundos para aplicar la nueva identidad...</b>\n"
                "<i>El bot reanudará la atención automáticamente bajo el nuevo token.</i>",
                parse_mode="HTML"
            )
        except Exception:
            await update.message.reply_text(f"{msg}\n\n🚀 <b>Reiniciando servicio para aplicar la nueva identidad...</b>")

        # SEC-002: Erradicado os.system. Despacho desacoplado y auditable mediante SystemdController
        from monitor.gitops.services.systemd_control import SystemdController

        actor_id = update.effective_user.id if update.effective_user else None
        asyncio.create_task(
            SystemdController.restart_service_delayed(
                delay_seconds=2.5,
                service="tg-admin-bot.service",
                actor_id=actor_id,
                reason="token_migration_applied",
            )
        )
        return ConversationHandler.END
    else:
        try:
            await status_msg.edit_text(
                f"❌ <b>Fallo en la Validación:</b>\n\n{html.escape(msg)}\n\n"
                "<i>La operación fue abortada. El token original sigue activo y protegido.</i>",
                parse_mode="HTML"
            )
        except Exception:
            await update.message.reply_text(
                f"❌ <b>Fallo en la Validación:</b>\n\n{html.escape(msg)}\n\n"
                "<i>La operación fue abortada. El token original sigue activo y protegido.</i>",
                parse_mode="HTML"
            )
        return ConversationHandler.END


async def cancel_migrar_token(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Cancela el flujo de migración de token."""
    if update.message:
        await update.message.reply_text("🛑 <b>Operación cancelada.</b>", parse_mode="HTML")
    return ConversationHandler.END
