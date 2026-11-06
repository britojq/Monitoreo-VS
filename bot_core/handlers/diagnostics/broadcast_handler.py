"""
Manejador de comunicados masivos y difusión oficial (Broadcast).
"""

from __future__ import annotations
import html
import logging
from datetime import datetime
from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.groups_guard.group_enforcer import _resolve_group_info
from bot_core.handlers.rbac.rbac_handler import _resolve_user_info
from bot_core.security.auth import get_security_warning_html
from bot_core.services.config_manager import load_config
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.diagnostics.broadcast")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id in (owner_id, IMMUTABLE_OWNER_ID)


async def cmd_broadcast_mensaje(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Envío de mensajes tipo Broadcast (difusión en privado exclusivo Owner)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    cfg = load_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)

    raw_text = update.message.text or ""
    parts = raw_text.split(maxsplit=1)
    if len(parts) < 2 or not parts[1].strip():
        guide_text = (
            "📢 <b>Panel de Difusión Masiva (Broadcast)</b>\n\n"
            "<b>Uso del comando:</b>\n"
            "<code>/mensaje &lt;texto del comunicado&gt;</code>\n\n"
            "<b>Ejemplo:</b>\n"
            "<code>/mensaje Estimado equipo, el día de hoy a las 15:00 se realizará mantenimiento preventivo.</code>\n\n"
            "<i>El mensaje será enviado por el bot a todos los grupos autorizados y usuarios con acceso al sistema.</i>"
        )
        await update.message.reply_text(guide_text, parse_mode="HTML")
        return

    broadcast_content = parts[1].strip()
    if (broadcast_content.startswith('"') and broadcast_content.endswith('"')) or \
       (broadcast_content.startswith("'") and broadcast_content.endswith("'")):
        broadcast_content = broadcast_content[1:-1].strip()

    now_str = datetime.now().strftime("%d/%m/%Y %H:%M")
    formatted_announcement = (
        "📢 <b>COMUNICADO OFICIAL</b>\n\n"
        f"{html.escape(broadcast_content)}\n\n"
        f"🏛️ <i>Mensaje emitido por la Administración • {now_str}</i>"
    )

    allowed_users = [uid for uid in cfg.get("allowed_user_ids", []) if uid != owner_id]
    allowed_groups = cfg.get("allowed_group_ids", [])
    total_destinatarios = len(allowed_users) + len(allowed_groups)

    if total_destinatarios == 0:
        await update.message.reply_text("⚠️ No hay usuarios ni grupos adicionales en la lista de autorizados para enviar la difusión.")
        return

    wait_msg = await update.message.reply_text(
        f"⏳ <i>Iniciando difusión masiva hacia {total_destinatarios} destino(s)... Por favor espere.</i>",
        parse_mode="HTML"
    )

    success_count = 0
    fail_count = 0
    delivery_details = []

    for gid in allowed_groups:
        g_title = await _resolve_group_info(context.bot, gid)
        try:
            await context.bot.send_message(chat_id=gid, text=formatted_announcement, parse_mode="HTML")
            success_count += 1
            delivery_details.append(f"• 🟢 <b>Grupo:</b> {html.escape(g_title)} (<code>{gid}</code>) → <i>Entregado</i>")
        except Exception as e:
            fail_count += 1
            err_msg = "Bloqueado o expulsado" if "forbidden" in str(e).lower() else "Error de envío"
            delivery_details.append(f"• 🔴 <b>Grupo:</b> {html.escape(g_title)} (<code>{gid}</code>) → <i>{err_msg}</i>")
            logger.warning(f"Fallo al enviar broadcast a grupo {gid}: {e}")

    for uid in allowed_users:
        u_name, u_uname = await _resolve_user_info(context.bot, uid)
        u_label = f"{u_name} ({u_uname})" if u_uname else u_name
        try:
            await context.bot.send_message(chat_id=uid, text=formatted_announcement, parse_mode="HTML")
            success_count += 1
            delivery_details.append(f"• 🟢 <b>Usuario:</b> {html.escape(u_label)} (<code>{uid}</code>) → <i>Entregado</i>")
        except Exception as e:
            fail_count += 1
            err_msg = "Bot bloqueado por el usuario" if "forbidden" in str(e).lower() else "Chat no iniciado / Inaccesible"
            delivery_details.append(f"• 🔴 <b>Usuario:</b> {html.escape(u_label)} (<code>{uid}</code>) → <i>{err_msg}</i>")
            logger.warning(f"Fallo al enviar broadcast a usuario {uid}: {e}")

    try:
        await wait_msg.delete()
    except Exception:
        pass

    report_lines = [
        "📊 <b>Reporte de Difusión Masiva (Broadcast)</b>",
        "",
        f"✅ <b>Envíos exitosos:</b> {success_count}",
        f"❌ <b>Envíos fallidos:</b> {fail_count}",
        f"👥 <b>Total destinos:</b> {total_destinatarios}",
        "",
        "📋 <b>Detalle de Entrega:</b>",
        *delivery_details,
        "",
        "<i>El comunicado fue emitido y firmado por el Bot con éxito.</i>"
    ]
    await update.message.reply_text("\n".join(report_lines), parse_mode="HTML")


async def cmd_info(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el aviso legal, términos de confidencialidad y advertencia de seguridad."""
    if not update.effective_user or not update.message:
        return
    aviso_legal = get_security_warning_html()
    await update.message.reply_text(aviso_legal, parse_mode="HTML")
