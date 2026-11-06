"""
Manejador de callbacks interactivos de autorización y revocación de permisos (RBAC).
"""

from __future__ import annotations
import html
import logging
from datetime import datetime
from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.groups_guard.group_enforcer import _build_groups_audit_panel
from bot_core.handlers.rbac.rbac_handler import _build_permissions_panel
from bot_core.services.config_manager import load_config, save_config
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.rbac.callbacks")


def get_security_warning_html() -> str:
    return (
        "⚠️ <i>Este bot administra sistemas de infraestructura crítica. "
        "Todas las acciones, comandos y consultas se registran de forma inmutable en MariaDB audit_logs.</i>"
    )


async def handle_auth_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja la acción de los botones inline de autorización y revocación presionados por el Owner."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = load_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    clicker_id = query.from_user.id

    if clicker_id != owner_id and clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("⛔ Solo el creador del bot tiene permiso para gestionar accesos.", show_alert=True)
        return

    if query.data in ("auth_open_groups", "auth_refresh_groups"):
        await query.answer("🔍 Verificando grupos activos...")
        panel_text, reply_markup = await _build_groups_audit_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode="HTML", reply_markup=reply_markup)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass
        return

    if query.data == "auth_back_to_permissions":
        await query.answer()
        panel_text, reply_markup = await _build_permissions_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode="HTML", reply_markup=reply_markup)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass
        return

    try:
        action, target_id_str = query.data.split(":", 1)
    except (ValueError, IndexError):
        await query.answer("⚠️ Datos de solicitud inválidos.")
        return

    if action == "auth_toggle_debug":
        new_state = (target_id_str == "on")
        cfg["monitor_debug_mode"] = new_state
        save_config(cfg)
        estado_label = "🟢 ACTIVADO" if new_state else "🔴 DESACTIVADO"
        await query.answer(f"Modo Depuración {estado_label}")
        if query.message:
            try:
                await query.edit_message_text(
                    f"🧪 <b>Panel de Modo Depuración</b>\n\n"
                    f"<b>Estado Actual:</b> <code>{estado_label}</code>\n\n"
                    f"Configuración guardada exitosamente.",
                    parse_mode="HTML",
                    reply_markup=None
                )
            except Exception:
                pass
        return

    if action == "auth_toggle_cmd_lock":
        new_state = (target_id_str == "on")
        cfg["commands_locked_for_users"] = new_state
        save_config(cfg)
        estado_label = "🔒 BLOQUEADOS PARA OTROS" if new_state else "🔓 HABILITADOS PARA TODOS"
        await query.answer(f"Comandos {estado_label}")
        if query.message:
            try:
                await query.edit_message_text(
                    "🛡️ <b>Control de Acceso a Comandos (Exclusivo Owner)</b>\n\n"
                    f"<b>Estado Actual:</b> <code>{estado_label}</code>\n\n"
                    "Configuración guardada exitosamente.",
                    parse_mode="HTML",
                    reply_markup=None
                )
            except Exception:
                pass
        return

    try:
        target_user_id = int(target_id_str)
    except ValueError:
        await query.answer("⚠️ ID de usuario inválido.")
        return

    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = query.from_user.full_name or f"@{query.from_user.username or 'admin'}"
    original_text = query.message.text_html if query.message else ""
    if not original_text and query.message and query.message.text:
        original_text = html.escape(query.message.text)

    if action == "auth_allow":
        allowed_list = cfg.setdefault("allowed_user_ids", [])
        if target_user_id not in allowed_list:
            allowed_list.append(target_user_id)
            save_config(cfg)
            logger.info(f"Usuario {target_user_id} autorizado por {clicker_id}")

        await query.answer(f"✅ Usuario {target_user_id} autorizado exitosamente.")
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"✅ <b>ESTADO: AUTORIZADO Y AGREGADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(text=original_text + status_badge, parse_mode="HTML", reply_markup=None)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass

        try:
            aviso_legal = get_security_warning_html()
            user_approval_msg = (
                "🎉 <b>¡Acceso Autorizado!</b>\n\n"
                "El administrador ha aprobado tu acceso. Ya puedes interactuar con el bot libremente.\n\n"
                f"{aviso_legal}"
            )
            await context.bot.send_message(chat_id=target_user_id, text=user_approval_msg, parse_mode="HTML")
        except Exception as e:
            logger.info(f"No se pudo notificar directamente al usuario {target_user_id}: {e}")

    elif action == "auth_deny":
        allowed_list = cfg.setdefault("allowed_user_ids", [])
        if target_user_id in allowed_list:
            allowed_list.remove(target_user_id)
            save_config(cfg)

        await query.answer(f"❌ Acceso denegado para {target_user_id}.")
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"❌ <b>ESTADO: ACCESO DENEGADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(text=original_text + status_badge, parse_mode="HTML", reply_markup=None)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass

        try:
            await context.bot.send_message(
                chat_id=target_user_id,
                text="⛔ <b>Solicitud Denegada</b>\n\nEl administrador ha rechazado tu solicitud de acceso a este bot.",
                parse_mode="HTML"
            )
        except Exception as e:
            logger.info(f"No se pudo notificar al usuario {target_user_id}: {e}")

    elif action == "auth_revoke_user":
        allowed_list = cfg.setdefault("allowed_user_ids", [])
        if target_user_id in allowed_list:
            allowed_list.remove(target_user_id)
            save_config(cfg)
            logger.info(f"Usuario {target_user_id} revocado por {clicker_id}")

        await query.answer(f"✅ Usuario {target_user_id} revocado de la lista.")
        panel_text, reply_markup = await _build_permissions_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode="HTML", reply_markup=reply_markup)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass

        try:
            await context.bot.send_message(
                chat_id=target_user_id,
                text="⛔ <b>Acceso Revocado</b>\n\nEl administrador ha revocado tu autorización para interactuar con este bot.",
                parse_mode="HTML"
            )
        except Exception as e:
            logger.info(f"No se pudo notificar al usuario revocado {target_user_id}: {e}")

    elif action == "auth_revoke_group":
        allowed_groups = cfg.setdefault("allowed_group_ids", [])
        if target_user_id in allowed_groups:
            allowed_groups.remove(target_user_id)
            save_config(cfg)
            logger.info(f"Grupo {target_user_id} revocado por {clicker_id}")

        await query.answer(f"✅ Grupo {target_user_id} revocado de la lista.")
        panel_text, reply_markup = await _build_permissions_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode="HTML", reply_markup=reply_markup)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass

    elif action == "auth_allow_group":
        target_group_id = int(target_id_str)
        allowed_groups = cfg.setdefault("allowed_group_ids", [])
        if target_group_id not in allowed_groups:
            allowed_groups.append(target_group_id)
            save_config(cfg)
            logger.info(f"Grupo {target_group_id} autorizado por {clicker_id}")

        await query.answer("✅ Grupo autorizado exitosamente.")
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"✅ <b>ESTADO: GRUPO AUTORIZADO Y ACTIVADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(text=original_text + status_badge, parse_mode="HTML", reply_markup=None)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass

    elif action == "auth_leave_group":
        target_group_id = int(target_id_str)
        allowed_groups = cfg.setdefault("allowed_group_ids", [])
        if target_group_id in allowed_groups:
            allowed_groups.remove(target_group_id)
            save_config(cfg)

        leave_ok = True
        try:
            await context.bot.leave_chat(chat_id=target_group_id)
            logger.info(f"El bot ha abandonado el grupo {target_group_id} por instrucción de {clicker_id}")
        except Exception as e:
            logger.warning(f"No se pudo ejecutar leave_chat en {target_group_id}: {e}")
            leave_ok = False

        await query.answer("🚪 El bot ha salido del grupo.")
        leave_label = "BOT HA ABANDONADO EL GRUPO" if leave_ok else "SOLICITUD PROCESADA (CHAT YA NO DISPONIBLE)"
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"🚪 <b>ESTADO: {leave_label}</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(text=original_text + status_badge, parse_mode="HTML", reply_markup=None)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass

    elif action == "auth_force_leave":
        target_group_id = int(target_id_str)
        groups_cache = cfg.get("groups_cache", {})
        group_name = groups_cache.get(str(target_group_id), f"Grupo {target_group_id}")
        leave_ok = True
        try:
            await context.bot.leave_chat(chat_id=target_group_id)
            logger.info(f"El bot ha forzado la salida del grupo {target_group_id} por {clicker_id}")
        except Exception as e:
            logger.warning(f"No se pudo ejecutar leave_chat en {target_group_id}: {e}")
            leave_ok = False

        allowed_groups = cfg.setdefault("allowed_group_ids", [])
        if target_group_id in allowed_groups:
            allowed_groups.remove(target_group_id)
            save_config(cfg)

        if leave_ok:
            await query.answer(f"🚪 El bot ha salido de: {group_name[:25]}", show_alert=True)
        else:
            await query.answer("⚠️ El bot ya no estaba en ese grupo o fue expulsado previamente.", show_alert=True)

        panel_text, reply_markup = await _build_groups_audit_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode="HTML", reply_markup=reply_markup)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass
