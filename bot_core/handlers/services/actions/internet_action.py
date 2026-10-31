"""
# ==============================================================================
# 🌐 ACTIONS: DIAGNÓSTICO DE INTERNET: internet_action.py
# Verificación de salida a internet y proxies corporativos.
# Ubicación: /opt/sonar/bot_core/handlers/services/actions/internet_action.py
# ==============================================================================
"""
from __future__ import annotations

import asyncio
from datetime import datetime
import html
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.services.helpers import get_active_config, reply
from bot_core.security.auth import (
    MSG_UNAUTHORIZED_GROUP_COMMAND,
    log_security_audit_event,
)
from bot_core.services.connection_service import _check_endpoint_health, load_proxies_list
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot.handlers.services.internet")


async def handle_internet(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para verificar la funcionalidad y latencia de las salidas a internet y proxies corporativos."""
    if not update.effective_user or not update.message:
        return

    cfg = get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id if update.effective_chat else 0
    is_owner = (user_id == owner_id)

    allowed_groups = cfg.get("allowed_group_ids", [])
    is_allowed_group = (chat_id in allowed_groups)

    if not is_owner and not is_allowed_group:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/internet"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        log_security_audit_event(
            event="unauthorized_internet_command",
            user_id=user_id,
            username=user.username or "",
            full_name=full_name,
            chat_id=chat_id,
            chat_title=chat_title,
            command=msg_text,
            description=f"Comando /internet denegado fuera de grupo por {full_name}"
        )

        await reply(update.message, MSG_UNAUTHORIZED_GROUP_COMMAND)

        if owner_id and user_id != owner_id and cfg.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Solicitud de Diagnóstico de Internet Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📋 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente porque el usuario no es el administrador ni la petición se originó en el grupo autorizado.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre solicitud /internet denegada: {e}")
        return

    if cfg.get("commands_locked_for_users", False) and not is_owner:
        await reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Verificando estado y latencia de salida a internet y proxies corporativos... Por favor espere.</i>",
        parse_mode='HTML'
    )

    try:
        bot_token = cfg.get("bot_token", "")
        tg_url = f"https://api.telegram.org/bot{bot_token}/getMe"

        network_tasks = []
        if is_owner:
            network_tasks.append(_check_endpoint_health("Conexión Directa a Internet", tg_url, None, timeout=3.5))

        proxies_list = load_proxies_list()
        for p in proxies_list:
            p_name = p.get("name", "Proxy")
            p_url = p.get("url")
            if p_url:
                network_tasks.append(_check_endpoint_health(p_name, tg_url, p_url, timeout=3.5))

        network_results = await asyncio.gather(*network_tasks)

        try:
            await wait_msg.delete()
        except Exception:
            pass

        lines = [
            "🌐 <b>DIAGNÓSTICO DE RED E INTERNET</b>",
            "━━━━━━━━━━━━"
        ]

        for res in network_results:
            icon = "🟢" if res["ok"] else "🔴"
            lines.append(f"• <b>{html.escape(res['name'])}:</b>\n   {icon} <code>{html.escape(res['detail'])}</code>")

        response_text = "\n".join(lines)
        await update.message.reply_text(response_text, parse_mode='HTML')

    except Exception as e:
        logger.error(f"Error ejecutando diagnóstico de internet (/internet): {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el diagnóstico:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await reply(update.message, f"❌ Error ejecutando diagnóstico de internet: {e}")
