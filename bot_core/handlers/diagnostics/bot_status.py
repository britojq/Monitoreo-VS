"""
Diagnóstico de conectividad, proxies, usuarios permitidos y negados (/botstatus).
"""

from __future__ import annotations
import asyncio
import html
import logging
from datetime import datetime
from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.groups_guard.group_enforcer import _resolve_group_info
from bot_core.handlers.rbac.rbac_handler import _resolve_user_info, get_denied_users_summary
from bot_core.services.config_manager import load_config
from bot_core.services.connection_service import (
    ACTIVE_CONNECTION_LABEL,
    _check_endpoint_health,
    load_proxies_list,
)
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.diagnostics.status")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id in (owner_id, IMMUTABLE_OWNER_ID)


async def bot_status(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Diagnóstico completo de conectividad, proxies, usuarios permitidos y negados (Exclusivo Owner en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    waiting_msg = None
    try:
        waiting_msg = await update.message.reply_text(
            "⏳ <i>Ejecutando diagnóstico en tiempo real de red, proxies y accesos...</i>",
            parse_mode="HTML"
        )
    except Exception:
        pass

    cfg = load_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    bot_token = cfg.get("bot_token", "")
    tg_url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Probar Conexión Directa
    direct_res = await _check_endpoint_health("Conexión Directa a Internet", tg_url, None, timeout=4.0)
    network_results = [direct_res]

    # 2. Probar proxies secuencialmente
    proxies_list = load_proxies_list()
    for idx, p in enumerate(proxies_list):
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if p_url:
            if idx > 0:
                await asyncio.sleep(1.0)
            p_res = await _check_endpoint_health(p_name, tg_url, p_url, timeout=5.5)
            network_results.append(p_res)

    allowed_users = cfg.get("allowed_user_ids", [])
    allowed_groups = cfg.get("allowed_group_ids", [])

    owner_name, owner_username = await _resolve_user_info(context.bot, owner_id)
    owner_tag = f" ({owner_username})" if owner_username else ""

    other_users = [u for u in allowed_users if u != owner_id]
    user_info_tasks = [_resolve_user_info(context.bot, uid) for uid in other_users]
    group_info_tasks = [_resolve_group_info(context.bot, gid) for gid in allowed_groups]

    user_info_results = await asyncio.gather(*user_info_tasks) if user_info_tasks else []
    group_info_results = await asyncio.gather(*group_info_tasks) if group_info_tasks else []

    denied_users = get_denied_users_summary()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    active_channel = ACTIVE_CONNECTION_LABEL or "Conexión Directa"

    lines = [
        "📊 <b>Informe de Estado y Diagnóstico del Bot</b>",
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>",
        "",
        "🌐 <b>CONECTIVIDAD A INTERNET DEL BOT (DIRECTA Y PROXIES)</b>"
    ]

    for res in network_results:
        icon = "🟢" if res["ok"] else "🔴"
        lines.append(f"• <b>{html.escape(res['name'])}:</b>\n   {icon} <code>{html.escape(res['detail'])}</code>")

    lines.append("")
    lines.append(f"🔄 <b>Canal Activo del Bot:</b> <code>{html.escape(active_channel)}</code>")
    lines.append("")
    lines.append("👥 <b>CONTROL DE ACCESO DE USUARIOS</b>")
    lines.append(f"👑 <b>Creador / Owner:</b> {html.escape(owner_name)}{html.escape(owner_tag)} [<code>{owner_id}</code>]")
    lines.append("")

    lines.append("✅ <b>Usuarios Permitidos:</b>")
    if not other_users:
        lines.append("• <i>(No hay usuarios adicionales en la lista)</i>")
    else:
        for idx, (uid, (uname, uuser)) in enumerate(zip(other_users, user_info_results), 1):
            utag = f" ({uuser})" if uuser else ""
            lines.append(f"{idx}. 👤 <b>Usuario:</b> {html.escape(uname)}{html.escape(utag)}\n   🆔 <b>ID de Telegram:</b> <code>{uid}</code>")

    lines.append("")
    lines.append("🏢 <b>Grupos Permitidos:</b>")
    if not allowed_groups:
        lines.append("• <i>(No hay grupos en la lista)</i>")
    else:
        for idx, (gid, gtitle) in enumerate(zip(allowed_groups, group_info_results), 1):
            lines.append(f"{idx}. 👥 <b>Grupo:</b> {html.escape(gtitle)}\n   🆔 <b>ID de Chat:</b> <code>{gid}</code>")

    lines.append("")
    lines.append("⛔ <b>Usuarios Negados / No Autorizados:</b>")
    if not denied_users:
        lines.append("• <i>(No hay usuarios bloqueados o negados recientemente)</i>")
    else:
        for idx, duser in enumerate(denied_users, 1):
            dtag = f" ({duser['username']})" if duser['username'] else ""
            lines.append(
                f"{idx}. 🚫 <b>Usuario:</b> {html.escape(duser['name'])}{html.escape(dtag)}\n"
                f"   🆔 <b>ID de Telegram:</b> <code>{duser['user_id']}</code>\n"
                f"   ⏰ <b>Último Intento:</b> <code>{duser['last_seen']}</code>"
            )

    report_text = "\n".join(lines)
    if waiting_msg:
        try:
            await waiting_msg.edit_text(report_text, parse_mode="HTML")
            return
        except Exception:
            try:
                await waiting_msg.delete()
            except Exception:
                pass

    await update.message.reply_text(report_text, parse_mode="HTML")
