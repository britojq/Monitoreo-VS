"""
Manejador de panel de permisos y resumen de usuarios no autorizados (RBAC).
"""

from __future__ import annotations
import html
import json
import logging
from datetime import datetime
from typing import Any, Dict, List, Tuple
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.groups_guard.group_enforcer import _resolve_group_info
from bot_core.services.config_manager import load_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.rbac")


async def _resolve_user_info(bot: Any, user_id: int) -> Tuple[str, str]:
    """Obtiene el nombre completo y @username de un usuario vía Telegram API o caché local."""
    cfg = load_config()
    users_cache = cfg.get("users_cache", {})
    cached = users_cache.get(str(user_id), {})
    cached_name = cached.get("full_name", "")
    cached_user = cached.get("username", "")

    try:
        chat = await bot.get_chat(user_id)
        first_name = (chat.first_name or "").strip()
        last_name = (chat.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        name_parts = [p for p in [first_name, last_name] if p]
        full_name = " ".join(name_parts) or chat.title or cached_name or "(sin nombre)"
        username = f"@{chat.username}" if chat.username else cached_user

        cfg.setdefault("users_cache", {})[str(user_id)] = {
            "full_name": full_name,
            "username": username
        }
        return full_name, username
    except Exception:
        return cached_name or "(Nombre no disponible)", cached_user


def _format_audit_date(raw_date: str) -> str:
    """Convierte fecha ISO (YYYY-MM-DD HH:MM:SS) a formato legible en español."""
    if not raw_date or raw_date == "Desconocida":
        return raw_date
    try:
        dt = datetime.strptime(raw_date.strip(), "%Y-%m-%d %H:%M:%S")
        return dt.strftime("%d/%m/%Y a las %I:%M:%S %p")
    except Exception:
        return raw_date


def get_denied_users_summary() -> List[Dict[str, Any]]:
    """Extrae la lista de usuarios no autorizados registrados en MariaDB audit_logs."""
    denied_map: Dict[int, Dict[str, Any]] = {}
    cfg = load_config()
    allowed_users = set(cfg.get("allowed_user_ids", []))
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    if owner_id:
        allowed_users.add(owner_id)

    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT user_name, new_values, created_at
                    FROM audit_logs
                    WHERE module IN ('telegram_bot', 'bot')
                      AND (user_role = 'unauthorized' OR event LIKE '%unauthorized%' OR description LIKE '%no autorizado%')
                    ORDER BY id DESC LIMIT 100
                """)
                for user_name, new_val_raw, created_at in cur.fetchall():
                    if not new_val_raw:
                        continue
                    try:
                        payload = json.loads(new_val_raw)
                        uid = payload.get("telegram_user_id")
                        if uid and uid not in allowed_users and uid not in denied_map:
                            username = payload.get("username", "")
                            nombre = payload.get("full_name") or user_name or "(sin nombre)"
                            if nombre.lower() == "none" or nombre == "None":
                                nombre = "(sin nombre)"
                            fecha_str = created_at.strftime("%Y-%m-%d %H:%M:%S") if hasattr(created_at, "strftime") else str(created_at)
                            denied_map[uid] = {
                                "user_id": uid,
                                "username": username if username != "(sin username)" else "",
                                "name": nombre,
                                "last_seen": _format_audit_date(fecha_str)
                            }
                    except Exception:
                        continue
        finally:
            conn.close()
    except Exception as e:
        logger.warning(f"Error consultando MariaDB audit_logs en get_denied_users_summary: {e}")

    return list(denied_map.values())


async def _build_permissions_panel(bot: Any) -> Tuple[str, InlineKeyboardMarkup | None]:
    """Construye el texto y el teclado interactivo con datos completos de usuarios y grupos."""
    cfg = load_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    allowed_users = cfg.get("allowed_user_ids", [])
    allowed_groups = cfg.get("allowed_group_ids", [])

    owner_name, owner_username = await _resolve_user_info(bot, owner_id)
    owner_tag = f" ({owner_username})" if owner_username else ""

    text_lines = [
        "🔐 <b>Panel de Control de Acceso y Permisos</b>",
        "",
        f"👑 <b>Creador / Owner:</b> {html.escape(owner_name)}{html.escape(owner_tag)}",
        f"🆔 <b>ID de Telegram:</b> <code>{owner_id}</code> <i>(Acceso Permanente)</i>",
        ""
    ]

    keyboard = []
    other_users = [u for u in allowed_users if u != owner_id]
    text_lines.append("👥 <b>Usuarios Permitidos:</b>")
    if not other_users:
        text_lines.append("<i>(No hay usuarios adicionales en la lista)</i>")
    else:
        for idx, uid in enumerate(other_users, 1):
            name, username = await _resolve_user_info(bot, uid)
            user_tag = f" ({username})" if username else ""
            text_lines.append(f"{idx}. 👤 <b>Usuario:</b> {html.escape(name)}{html.escape(user_tag)}")
            text_lines.append(f"   🆔 <b>ID de Telegram:</b> <code>{uid}</code>")
            btn_label = f"❌ Quitar: {name[:15]} ({uid})"
            keyboard.append([InlineKeyboardButton(btn_label, callback_data=f"auth_revoke_user:{uid}")])

    text_lines.append("")
    text_lines.append("🏢 <b>Grupos Permitidos:</b>")
    if not allowed_groups:
        text_lines.append("<i>(No hay grupos en la lista)</i>")
    else:
        for idx, gid in enumerate(allowed_groups, 1):
            gtitle = await _resolve_group_info(bot, gid)
            text_lines.append(f"{idx}. 👥 <b>Grupo:</b> {html.escape(gtitle)}")
            text_lines.append(f"   🆔 <b>ID de Chat:</b> <code>{gid}</code>")
            btn_label = f"❌ Quitar Grupo: {gtitle[:15]} ({gid})"
            keyboard.append([InlineKeyboardButton(btn_label, callback_data=f"auth_revoke_group:{gid}")])

    if keyboard:
        text_lines.append("")
        text_lines.append("ℹ️ <i>Presiona un botón para revocar el acceso a un usuario o grupo.</i>")

    keyboard.append([InlineKeyboardButton("👥 Auditar Grupos Activos / Salir", callback_data="auth_open_groups")])
    reply_markup = InlineKeyboardMarkup(keyboard) if keyboard else None
    return "\n".join(text_lines), reply_markup


async def manage_permissions(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el panel de gestión de permisos con datos detallados (exclusivo Owner en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    cfg = load_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    if user.id != owner_id and user.id != IMMUTABLE_OWNER_ID:
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    panel_text, reply_markup = await _build_permissions_panel(context.bot)
    await update.message.reply_text(panel_text, parse_mode="HTML", reply_markup=reply_markup)
