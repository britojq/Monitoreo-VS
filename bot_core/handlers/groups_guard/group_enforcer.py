"""
Manejador de auditoría de grupos y ejecución de órdenes de salida forzada.
"""

from __future__ import annotations
import html
import logging
from typing import Any, List, Set, Tuple
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.constants import ChatMemberStatus
from telegram.ext import ContextTypes

from bot_core.handlers.groups_guard.dtos import GroupAuditItemDto
from bot_core.services.config_manager import load_config, save_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.groups_guard.enforcer")


def _get_known_group_candidates() -> Set[int]:
    """Recopila todos los posibles IDs de grupos desde bot_settings y la caché en memoria."""
    cfg = load_config()
    candidates: Set[int] = set()

    for gid in cfg.get("allowed_group_ids", []):
        try:
            candidates.add(int(gid))
        except (ValueError, TypeError):
            pass

    for k in cfg.get("groups_cache", {}).keys():
        try:
            candidates.add(int(k))
        except (ValueError, TypeError):
            pass

    return candidates


async def _resolve_group_info(bot: Any, group_id: int) -> str:
    """Resuelve el título de un grupo vía caché o Telegram API."""
    cfg = load_config()
    cached = cfg.get("groups_cache", {}).get(str(group_id))
    if cached:
        return cached

    try:
        chat = await bot.get_chat(group_id)
        if chat and chat.title:
            cfg.setdefault("groups_cache", {})[str(group_id)] = chat.title
            save_config(cfg)
            return chat.title
    except Exception:
        pass

    return f"Grupo {group_id}"


async def _build_groups_audit_panel(bot: Any) -> Tuple[str, InlineKeyboardMarkup]:
    """Verifica en tiempo real con Telegram en cuáles grupos está realmente el bot."""
    candidates = _get_known_group_candidates()
    bot_user = await bot.get_me()
    bot_id = bot_user.id
    cfg = load_config()
    allowed_groups = set(cfg.get("allowed_group_ids", []))

    active_groups: List[GroupAuditItemDto] = []
    for gid in candidates:
        try:
            member = await bot.get_chat_member(chat_id=gid, user_id=bot_id)
            status = member.status if member else None
            if status in (ChatMemberStatus.MEMBER, ChatMemberStatus.ADMINISTRATOR, ChatMemberStatus.OWNER):
                chat = await bot.get_chat(gid)
                title = chat.title or cfg.get("groups_cache", {}).get(str(gid), "Grupo")
                cfg.setdefault("groups_cache", {})[str(gid)] = title
                role_label = "👑 Propietario" if status == ChatMemberStatus.OWNER else ("🛡️ Administrador" if status == ChatMemberStatus.ADMINISTRATOR else "👤 Miembro")
                is_allowed = gid in allowed_groups
                active_groups.append(GroupAuditItemDto(
                    group_id=gid,
                    title=title,
                    role_label=role_label,
                    is_allowed=is_allowed
                ))
        except Exception:
            pass

    lines = [
        "👥 <b>AUDITORÍA DE GRUPOS ACTIVOS • ADMINISTRACIÓN</b>",
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
        f"Grupos donde el bot se encuentra activo: <b>{len(active_groups)}</b>\n"
    ]

    keyboard = []
    if not active_groups:
        lines.append("<i>ℹ️ El bot no se encuentra agregado activamente en ningún grupo en este momento.</i>\n")
    else:
        for idx, g in enumerate(active_groups, 1):
            auth_badge = "🟢 <b>Autorizado Oficial</b>" if g.is_allowed else "⚠️ <b>NO Autorizado</b>"
            lines.append(f"<b>{idx}. {html.escape(g.title)}</b>")
            lines.append(f"   🆔 <code>{g.group_id}</code> | {auth_badge}")
            lines.append(f"   🤖 <b>Rol del Bot:</b> {g.role_label}\n")

            btn_label = f"🚪 Salir de: {g.title[:14]}… ({g.group_id})" if len(g.title) > 15 else f"🚪 Salir de: {g.title} ({g.group_id})"
            keyboard.append([InlineKeyboardButton(btn_label, callback_data=f"auth_force_leave:{g.group_id}")])

    lines.append("━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    lines.append("<i>💡 Pulsa el botón de cualquier grupo para obligar al bot a abandonarlo al instante.</i>")

    keyboard.append([
        InlineKeyboardButton("🔄 Actualizar Lista", callback_data="auth_refresh_groups"),
        InlineKeyboardButton("🔙 Volver a Permisos", callback_data="auth_back_to_permissions")
    ])

    return "\n".join(lines), InlineKeyboardMarkup(keyboard)


async def cmd_grupos(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Audita los grupos donde está el bot y fuerza salidas (Exclusivo Owner en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if user.id != owner_id and user.id != IMMUTABLE_OWNER_ID:
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Consultando en tiempo real con Telegram los grupos activos del bot...</i>",
        parse_mode="HTML"
    )

    panel_text, reply_markup = await _build_groups_audit_panel(context.bot)

    try:
        await wait_msg.edit_text(panel_text, parse_mode="HTML", reply_markup=reply_markup)
    except Exception:
        await update.message.reply_text(panel_text, parse_mode="HTML", reply_markup=reply_markup)


async def cmd_salir_grupo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Fuerza la salida del bot de un grupo específico por ID (/salir_grupo <id>)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if user.id != owner_id and user.id != IMMUTABLE_OWNER_ID:
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    if not context.args:
        await update.message.reply_text(
            "ℹ️ <b>Uso del comando /salir_grupo:</b>\n\n"
            "Debes especificar el ID numérico del grupo del cual deseas que el bot salga.\n"
            "Ejemplo: <code>/salir_grupo -1001383163558</code>\n\n"
            "<i>También puedes usar <code>/grupos</code> para ver los grupos activos y tocar el botón de salida directamente.</i>",
            parse_mode="HTML"
        )
        return

    target_str = context.args[0].strip()
    try:
        target_group_id = int(target_str)
    except ValueError:
        await update.message.reply_text(f"❌ El identificador <code>{html.escape(target_str)}</code> no es un ID numérico válido.", parse_mode="HTML")
        return

    groups_cache = cfg.get("groups_cache", {})
    group_name = groups_cache.get(str(target_group_id), f"Grupo {target_group_id}")

    try:
        await context.bot.leave_chat(chat_id=target_group_id)
        allowed_groups = cfg.setdefault("allowed_group_ids", [])
        if target_group_id in allowed_groups:
            allowed_groups.remove(target_group_id)
            save_config(cfg)

        await update.message.reply_text(
            f"✅ <b>Instrucción Ejecutada:</b>\n\n"
            f"El bot ha abandonado forzosamente el grupo <b>{html.escape(group_name)}</b> (ID: <code>{target_group_id}</code>).",
            parse_mode="HTML"
        )
    except Exception as e:
        await update.message.reply_text(
            f"⚠️ <b>Aviso al intentar salir del grupo:</b>\n\n"
            f"<code>{html.escape(str(e))}</code>\n\n"
            "<i>Es posible que el bot ya haya sido expulsado previamente o no sea miembro de ese grupo.</i>",
            parse_mode="HTML"
        )
