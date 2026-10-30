"""
Manejadores de eventos de membresía en grupos y alertas al Owner.
"""

from __future__ import annotations
import html
import logging
from datetime import datetime
from typing import Any
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.constants import ChatMemberStatus
from telegram.ext import ContextTypes

from bot_core.security.auth import log_security_audit_event
from bot_core.services.config_manager import load_config, save_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.groups_guard.events")


async def notify_owner_bot_added_to_group(
    chat: Any,
    from_user: Any,
    context: ContextTypes.DEFAULT_TYPE
) -> None:
    """Notifica de inmediato al Owner cuando el bot es añadido a cualquier grupo."""
    chat_id = chat.id
    chat_title = chat.title or "Grupo Sin Título"
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed_groups = set(cfg.get("allowed_group_ids", []))

    cfg.setdefault("groups_cache", {})[str(chat_id)] = chat_title
    save_config(cfg)

    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    adder_name = from_user.full_name if from_user else "Desconocido"
    adder_username = f"@{from_user.username}" if (from_user and from_user.username) else "sin username"
    adder_id = from_user.id if from_user else 0

    is_authorized_group = (chat_id in allowed_groups)
    status_log = "GRUPO_AUTORIZADO" if is_authorized_group else "GRUPO_NO_AUTORIZADO"
    log_security_audit_event(
        event="bot_added_to_chat",
        user_id=adder_id,
        username=adder_username,
        full_name=adder_name,
        chat_id=chat_id,
        chat_title=chat_title,
        command=f"ADD_TO_{status_log}",
        description=f"Bot añadido a {status_log} por {adder_name} ({adder_username}, ID: {adder_id})"
    )

    if not owner_id:
        return

    if is_authorized_group:
        msg_owner = (
            "ℹ️ <b>Notificación: Bot Añadido a Grupo Autorizado</b>\n\n"
            f"👥 <b>Grupo:</b> {html.escape(chat_title)}\n"
            f"🆔 <b>ID de Chat:</b> <code>{chat_id}</code>\n"
            f"👤 <b>Añadido por:</b> {html.escape(adder_name)} ({html.escape(adder_username)})\n"
            f"🆔 <b>ID de Usuario:</b> <code>{adder_id}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>Este grupo ya se encuentra registrado en la lista de grupos autorizados.</i>"
        )
        try:
            await context.bot.send_message(chat_id=owner_id, text=msg_owner, parse_mode="HTML")
        except Exception as e:
            logger.error(f"Error notificando al owner sobre adición a grupo autorizado: {e}")
    else:
        alert_text = (
            "🚨 <b>ALERTA DE SEGURIDAD: BOT AÑADIDO A NUEVO GRUPO</b>\n\n"
            "El bot ha sido agregado a un grupo que <b>NO está en la lista de autorizados</b>:\n\n"
            f"👥 <b>Grupo:</b> {html.escape(chat_title)}\n"
            f"🆔 <b>ID del Grupo:</b> <code>{chat_id}</code>\n"
            f"👤 <b>Añadido por:</b> {html.escape(adder_name)} ({html.escape(adder_username)})\n"
            f"🆔 <b>ID de Usuario:</b> <code>{adder_id}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>¿Deseas autorizar las funciones del bot en este grupo o prefieres que el bot abandone el grupo inmediatamente?</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🟢 Autorizar Grupo", callback_data=f"auth_allow_group:{chat_id}"),
                InlineKeyboardButton("🚪 Salir del Grupo", callback_data=f"auth_leave_group:{chat_id}")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        try:
            await context.bot.send_message(
                chat_id=owner_id,
                text=alert_text,
                parse_mode="HTML",
                reply_markup=reply_markup
            )
        except Exception as e:
            logger.error(f"Error enviando alerta al owner sobre nuevo grupo: {e}")


async def on_my_chat_member(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Manejador para eventos de membresía del propio bot (ChatMemberUpdated)."""
    result = update.my_chat_member
    if not result:
        return

    chat = result.chat
    if chat.type not in ("group", "supergroup"):
        return

    from_user = result.from_user
    old_status = result.old_chat_member.status if result.old_chat_member else None
    new_status = result.new_chat_member.status if result.new_chat_member else None

    was_member = old_status in (ChatMemberStatus.MEMBER, ChatMemberStatus.ADMINISTRATOR)
    is_member = new_status in (ChatMemberStatus.MEMBER, ChatMemberStatus.ADMINISTRATOR)

    if not was_member and is_member:
        await notify_owner_bot_added_to_group(chat, from_user, context)


async def on_new_chat_members(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Manejador para mensajes de servicio con nuevos miembros del chat."""
    msg = update.message
    if not msg or not msg.new_chat_members or not msg.chat:
        return

    bot_id = context.bot.id
    if any(m.id == bot_id for m in msg.new_chat_members):
        await notify_owner_bot_added_to_group(msg.chat, msg.from_user, context)
