"""
# ==============================================================================
# 🛡️ SEGURIDAD, AUTENTICACIÓN Y AUDITORÍA: auth.py
# Control estricto de autorización RBAC, filtrado de chats y eventos de auditoría.
# Principio de Responsabilidad Única (S) y Principios SOLID.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import os
import platform
import re
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.services.config_manager import BASE_DIR, AUDIT_DIR, load_config
from bot_core.utils.telegram_helpers import safe_reply_html
from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    is_core_operational,
    activate_hardware_first_boot,
)

logger = logging.getLogger("bot.security.auth")

MSG_UNAUTHORIZED_GROUP_COMMAND = (
    "🛑 <b>ACCESO DENEGADO • POLÍTICA DE SEGURIDAD</b>\n"
    "━━━━━━━━━━━━\n"
    "⚠️ <b>ADVERTENCIA DE SEGURIDAD:</b>\n"
    "La ejecución de comandos operativos <b>NO está permitida fuera del grupo de trabajo oficial asignado</b>.\n\n"
    "🔒 <b>Estado:</b> <code>Solicitud Bloqueada</code>\n"
    "🚨 <b>Auditoría:</b> <i>Este incidente de ejecución fuera de grupo ha sido registrado en el sistema y reportado a la Administración Técnica (ATIT).</i>\n"
    "━━━━━━━━━━━━\n"
    "<i>ℹ️ Por favor, realice sus consultas y solicitudes exclusivamente dentro del grupo oficial autorizado.</i>"
)

MSG_UNAUTHORIZED_ADMIN_COMMAND = (
    "🛑 <b>ACCESO DENEGADO • COMANDO NO AUTORIZADO</b>\n"
    "━━━━━━━━━━━━\n"
    "⚠️ <b>ADVERTENCIA DE SEGURIDAD:</b>\n"
    "Usted no posee los privilegios requeridos para ejecutar esta instrucción en el sistema.\n\n"
    "🔒 <b>Estado:</b> <code>Instrucción Bloqueada</code>\n"
    "🚨 <b>Auditoría:</b> <i>Este intento de ejecución no autorizada ha sido registrado en el sistema y reportado a la Administración Técnica (ATIT).</i>\n"
    "━━━━━━━━━━━━\n"
    "<i>ℹ️ Si considera que esto es un error, contacte a la Coordinación de Infraestructura Tecnológica.</i>"
)


def _get_active_config() -> Dict[str, Any]:
    """Obtiene la configuración activa en memoria desde bot o cargándola."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


def get_audit_log_path() -> Path:
    """Obtiene la ruta absoluta del log de auditoría asegurando su directorio."""
    AUDIT_DIR.mkdir(parents=True, exist_ok=True)
    cfg = _get_active_config()
    raw_name = cfg.get("audit_log_file", "intentos_acceso.log")
    if Path(raw_name).is_absolute():
        return Path(raw_name)
    if raw_name.startswith("audit/"):
        return BASE_DIR / raw_name
    return AUDIT_DIR / Path(raw_name).name


def get_security_warning_html() -> str:
    """Genera la advertencia legal y de auditoría estandarizada en formato HTML."""
    return (
        "⚠️ <b>ADVERTENCIA DE SEGURIDAD Y AUDITORÍA</b>\n"
        "━━━━━━━━━━━━\n"
        "🏛️ <i>Sistema de Monitoreo - Valle Seco</i>\n"
        "🔒 <b>Uso estrictamente confidencial y restringido.</b>\n\n"
        "Toda interacción, comando ejecutado e intento de acceso es registrado "
        "con fines de seguridad, trazabilidad y control de infraestructura."
    )


def log_security_audit_event(
    event: str,
    user_id: int,
    username: str = "",
    full_name: str = "",
    chat_id: int = 0,
    chat_title: str = "",
    command: str = "",
    description: str = "",
    extra_data: dict | None = None
) -> None:
    """Registra un evento de seguridad tanto en la base de datos MariaDB (audit_logs) como en el archivo local."""
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    clean_username = (username or "").lstrip("@")
    user_label = f"{full_name} (@{clean_username})" if clean_username else (full_name or str(user_id))

    # 1. Registro en Base de Datos MariaDB (audit_logs)
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                sql = """
                    INSERT INTO audit_logs
                    (user_id, user_name, user_email, user_role, event, module, entity_name, entity_label, description, new_values, ip_address, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                """
                payload = {
                    "telegram_user_id": user_id,
                    "username": clean_username,
                    "full_name": full_name,
                    "chat_id": chat_id,
                    "chat_title": chat_title,
                    "command": command,
                }
                if extra_data:
                    payload.update(extra_data)

                cur.execute(sql, (
                    None,
                    user_label[:255],
                    f"{user_id}@telegram.local",
                    "unauthorized",
                    (event or "unauthorized_access")[:255],
                    "bot",
                    "Telegram Bot",
                    command[:255] if command else "Telegram Command",
                    description or f"Intento no autorizado de {user_label} en {chat_title} ({chat_id}): {command}",
                    json.dumps(payload, ensure_ascii=False),
                    "127.0.0.1"
                ))
                conn.commit()
        finally:
            conn.close()
    except Exception as e_db:
        logger.warning(f"No se pudo registrar evento de seguridad en MariaDB audit_logs: {e_db}")

    # 2. Registro de respaldo en archivo plano
    cfg = _get_active_config()
    if cfg.get("log_unauthorized_to_file", True):
        audit_path = get_audit_log_path()
        try:
            log_line = (
                f"[{now_str}] EVENTO: {event.upper()} | ID: {user_id} | "
                f"Username: @{clean_username} | Nombre: {full_name} | "
                f"Chat: {chat_title} (ID: {chat_id}) | Comando: {command} | "
                f"Detalle: {description}\n"
            )
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e_file:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e_file}")


def is_authorized(update: Update) -> bool:
    """Verifica si el usuario y el chat están autorizados."""
    if not update.effective_user or not update.effective_chat:
        return False

    user_id = update.effective_user.id
    chat_id = update.effective_chat.id
    chat_type = update.effective_chat.type

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    allowed_users = cfg.get("allowed_user_ids", [])
    allowed_groups = cfg.get("allowed_group_ids", [])

    user_authorized = (user_id == owner_id) or (user_id in allowed_users)

    if not user_authorized:
        return False

    if chat_type in ['group', 'supergroup']:
        if not allowed_groups:
            return False
        if chat_id not in allowed_groups:
            return False

    return True


async def check_authorization(update: Update, context: ContextTypes.DEFAULT_TYPE) -> bool:
    """
    Verifica la autorización del usuario, chat y estado de activación del sistema.
    Si el sistema está en estado FIRST_BOOT_PENDING o PENDING_VALIDATION:
    - Permite al Owner enviar el Serial de Activación para desbloquear el sistema.
    - Bloquea todos los demás comandos y usuarios con aviso de espera de activación.
    """
    bot_mod = sys.modules.get("bot")
    if bot_mod:
        if hasattr(bot_mod, "_CONSECUTIVE_NETWORK_ERRORS"):
            bot_mod._CONSECUTIVE_NETWORK_ERRORS = 0
        if hasattr(bot_mod, "_LAST_SUCCESSFUL_POLL_TIME"):
            bot_mod._LAST_SUCCESSFUL_POLL_TIME = time.time()

    # 0. Verificación de Estado de Activación e Integridad del Sistema
    if not is_core_operational():
        user = update.effective_user
        raw_text = (update.message.text or update.message.caption or "") if update.message else ""

        serial_candidate = ""
        match = re.search(r'(AUTH-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})', raw_text, re.IGNORECASE)
        if match:
            serial_candidate = match.group(1).upper()
        elif raw_text.strip().upper().startswith("AUTH-"):
            serial_candidate = raw_text.strip().upper()
        elif context and getattr(context, "args", None):
            for arg in context.args:
                m_arg = re.search(r'(AUTH-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})', arg, re.IGNORECASE)
                if m_arg:
                    serial_candidate = m_arg.group(1).upper()
                    break

        # Si el Owner envía el Serial Challenge
        if user and user.id == IMMUTABLE_OWNER_ID and serial_candidate:
            ok, msg = activate_hardware_first_boot(serial_candidate)
            if ok:
                await safe_reply_html(
                    update.message,
                    f"{msg}\n\n"
                    "🎉 <b>¡Bienvenido!</b> El sistema ha completado la validación de integridad y se encuentra ahora 100% <b>OPERACIONAL</b>."
                )
                return True
            else:
                await safe_reply_html(
                    update.message,
                    f"❌ <b>Error de Activación:</b>\n\n<code>{html.escape(msg)}</code>\n\n"
                    "<i>Verifique el Serial recibido en la alerta de emergencia e intente nuevamente dentro del tiempo límite de 10 minutos.</i>"
                )
                return False

        if update.message:
            hostname = platform.node()
            await safe_reply_html(
                update.message,
                "⏳ <b>Bot en espera de validación de instalación.</b>\n\n"
                f"<i>El sistema en el servidor <code>{html.escape(hostname)}</code> requiere validación de hardware.\n\n"
                "💻 <b>Instrucciones de Validación:</b>\n"
                "Inicie sesión por consola en el servidor (SSH o terminal física) y ejecute:\n"
                "<code>activar AUTH-XXXX-XXXX-XXXX-XXXX</code>\n\n"
                "(Utilice el Serial de Activación recibido en la notificación privada).</i>"
            )
        return False

    cfg = _get_active_config()

    # 2. Modo Mantenimiento Activado por el Owner
    if cfg.get("maintenance_mode", False):
        user = update.effective_user
        if user and user.id == IMMUTABLE_OWNER_ID:
            pass
        else:
            if update.message:
                await safe_reply_html(
                    update.message,
                    "🔧 <b>Modo Mantenimiento Activo</b>\n\n"
                    "El bot se encuentra temporalmente en modo de mantenimiento por el Administrador. "
                    "Las funciones se reanudarán en breve."
                )
            return False

    if is_authorized(update):
        return True

    user = update.effective_user
    chat = update.effective_chat

    user_id = user.id if user else 0
    username = f"@{user.username}" if (user and user.username) else ""
    first_name = (user.first_name or "").strip() if user else ""
    last_name = (user.last_name or "").strip() if user else ""
    if last_name.lower() == "none":
        last_name = ""
    name_parts = [p for p in [first_name, last_name] if p]
    full_name = " ".join(name_parts) or "(sin nombre)"
    lang = (user.language_code or "desconocido") if (user and user.language_code) else "desconocido"

    # Guardar en caché de usuarios para el comando /permisos
    if user_id:
        cfg.setdefault("users_cache", {})[str(user_id)] = {
            "full_name": full_name,
            "username": username
        }

    chat_id = chat.id if chat else 0
    chat_type = chat.type if chat else "desconocido"
    chat_title = chat.title if (chat and chat.type in ['group', 'supergroup']) else "Chat Privado"

    if chat_id and chat.title:
        cfg.setdefault("groups_cache", {})[str(chat_id)] = chat.title

    msg_text = update.message.text if (update.message and update.message.text) else "(sin texto)"
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    user_label = f"{full_name} ({username})" if username else full_name
    logger.warning(
        f"Acceso DENEGADO | User: {user_label} (ID: {user_id}) | "
        f"Chat: {chat_title} (ID: {chat_id}) | Msg: {msg_text}"
    )

    # 1. Registrar en BD y archivo de auditoría
    log_security_audit_event(
        event="unauthorized_access",
        user_id=user_id,
        username=username,
        full_name=full_name,
        chat_id=chat_id,
        chat_title=chat_title,
        command=msg_text,
        description=f"Intento no autorizado en chat {chat_title} (ID: {chat_id}, Tipo: {chat_type}): {msg_text}",
        extra_data={"lang": lang, "chat_type": chat_type}
    )

    # 2. Responder al usuario no autorizado
    if update.message and cfg.get("reply_unauthorized_user", True):
        aviso_legal = get_security_warning_html()
        user_reply = (
            f"{aviso_legal}\n\n"
            "⛔ <b>Acceso Restringido</b>\n\n"
            "No tienes autorización para interactuar con este bot.\n\n"
            f"Para solicitar acceso al administrador, proporciona tu ID:\n"
            f"🆔 <code>{user_id}</code>"
        )
        await safe_reply_html(update.message, user_reply)

    # 3. Notificar en tiempo real al owner con botones interactivos de autorización
    owner_id = cfg.get("owner_id", 0)
    if owner_id and user_id != owner_id and cfg.get("notify_unauthorized_to_owner", True):
        owner_alert = (
            "🚨 <b>Alerta: Intento de Acceso No Autorizado</b>\n\n"
            f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username)})\n"
            f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
            f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
            f"🌐 <b>Idioma:</b> <code>{html.escape(str(lang))}</code>\n"
            f"📝 <b>Mensaje enviado:</b>\n<pre>{html.escape(msg_text)}</pre>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>"
        )
        keyboard = [
            [
                InlineKeyboardButton("✅ Permitir / Autorizar", callback_data=f"auth_allow:{user_id}"),
                InlineKeyboardButton("❌ Denegar", callback_data=f"auth_deny:{user_id}")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)

        try:
            await context.bot.send_message(
                chat_id=owner_id,
                text=owner_alert,
                reply_markup=reply_markup,
                parse_mode='HTML'
            )
        except Exception as e:
            logger.warning(f"No se pudo enviar notificación de alerta al owner ({owner_id}): {e}")

    return False


async def require_private_chat(update: Update, context: ContextTypes.DEFAULT_TYPE) -> bool:
    """
    Garantiza que un comando administrativo exclusivo del Administrador se ejecute únicamente en chat privado.
    Si se intenta ejecutar en un grupo o supergrupo:
    1. Borra de inmediato el mensaje con el comando en el grupo.
    2. Envía un aviso informativo temporal en el grupo indicando que el comando solo es válido en privado.
    3. Si quien lo escribió fue el Administrador, le envía una notificación en chat privado para que pueda ejecutarlo allí.
    """
    chat = update.effective_chat
    user = update.effective_user
    msg = update.message

    if not chat or chat.type == "private":
        return True

    # Es un grupo o supergrupo: borrar el mensaje inmediatamente
    if msg:
        cmd_name = msg.text.split()[0] if (msg.text and msg.text.strip()) else "administrativo"
        try:
            await msg.delete()
        except Exception as e:
            logger.debug(f"No se pudo borrar mensaje de comando restringido en grupo: {e}")

        # Mensaje temporal en el grupo
        warning_text = (
            "⚠️ <b>Comando Restringido:</b> Esta instrucción no está permitida en este grupo "
            "y ha sido eliminada por políticas de seguridad."
        )
        try:
            temp_msg = await context.bot.send_message(
                chat_id=chat.id,
                text=warning_text,
                parse_mode='HTML'
            )
            async def _auto_delete_notice(sent_msg: Any) -> None:
                await asyncio.sleep(8.0)
                try:
                    await sent_msg.delete()
                except Exception:
                    pass
            asyncio.create_task(_auto_delete_notice(temp_msg))
        except Exception as e:
            logger.debug(f"No se pudo enviar aviso de comando restringido en grupo: {e}")

        # Si el Owner fue quien lo escribió por error, notificarle en privado
        if user and user.id == IMMUTABLE_OWNER_ID:
            chat_title = chat.title or "Grupo"
            owner_notice = (
                "🔒 <b>Aviso de Seguridad en Grupo</b>\n\n"
                f"Has intentado ejecutar el comando <code>{html.escape(cmd_name)}</code> en el grupo <b>{html.escape(chat_title)}</b>.\n\n"
                "Por políticas de seguridad y privacidad, el mensaje fue eliminado automáticamente del grupo.\n"
                f"Puedes ejecutar <code>{html.escape(cmd_name)}</code> de forma segura directamente aquí en este chat privado."
            )
            try:
                await context.bot.send_message(
                    chat_id=IMMUTABLE_OWNER_ID,
                    text=owner_notice,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.debug(f"No se pudo enviar aviso privado al owner: {e}")

    return False
