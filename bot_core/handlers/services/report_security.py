"""
# ==============================================================================
# 🛡️ SEGURIDAD Y VALIDACIÓN DE REPORTES: report_security.py
# Valida permisos, políticas RBAC corporativas y emite alertas de seguridad.
# Ubicación: /opt/sonar/bot_core/handlers/services/report_security.py
# ==============================================================================
"""
from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
import html
import logging
from typing import Any, Dict, List, Optional

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.services.helpers import get_active_config, reply
from bot_core.security.auth import (
    MSG_UNAUTHORIZED_ADMIN_COMMAND,
    MSG_UNAUTHORIZED_GROUP_COMMAND,
    check_authorization,
    log_security_audit_event,
)
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot.handlers.services.security")


@dataclass(frozen=True)
class ReportValidationResult:
    """Resultado inmutable de validación de solicitud de reporte."""
    is_owner: bool
    is_debug: bool
    is_ondemand: bool
    send_to_group: bool
    target_groups: List[int]
    chat_id: int


async def validate_report_request(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    target: str,
    target_name: str,
    force_debug: bool,
) -> Optional[ReportValidationResult]:
    """Valida los permisos del solicitante, registra eventos de auditoría y extrae opciones."""
    if not update.effective_user or not update.message:
        return None

    if not await check_authorization(update, context):
        return None

    cfg: Dict[str, Any] = get_active_config()
    owner_id: int = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed_groups: List[int] = cfg.get("allowed_group_ids", [])
    user_id: int = update.effective_user.id
    chat_id: int = update.effective_chat.id if update.effective_chat else 0

    is_owner = (user_id == owner_id)
    is_allowed_group = (chat_id in allowed_groups)

    # 1. Comandos debug restringidos estrictamente al Owner
    if force_debug and not is_owner:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or f"/debug_{target}"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        log_security_audit_event(
            event=f"unauthorized_debug_{target}",
            user_id=user_id,
            username=user.username or "",
            full_name=full_name,
            chat_id=chat_id,
            chat_title=chat_title,
            command=msg_text,
            description=f"Intento de reporte debug restringido ({target_name}) por {full_name}"
        )

        await reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)

        if owner_id and cfg.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Reporte Debug Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📋 <b>Reporte Intentado:</b> <code>{html.escape(target_name)}</code>\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre solicitud debug denegada: {e}")
        return None

    # 2. Solo el owner o usuarios dentro de los grupos autorizados
    if not is_owner and not is_allowed_group:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or f"/{target}"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        log_security_audit_event(
            event=f"unauthorized_report_{target}",
            user_id=user_id,
            username=user.username or "",
            full_name=full_name,
            chat_id=chat_id,
            chat_title=chat_title,
            command=msg_text,
            description=f"Intento de reporte denegado fuera de grupo ({target_name}) por {full_name}"
        )

        await reply(update.message, MSG_UNAUTHORIZED_GROUP_COMMAND)

        if owner_id and user_id != owner_id and cfg.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Solicitud de Reporte Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📋 <b>Reporte Intentado:</b> <code>{html.escape(target_name)}</code>\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente porque el usuario no es el administrador ni la petición se originó en el grupo autorizado.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre solicitud de reporte restringido: {e}")
        return None

    # 3. Bloqueo global de comandos para usuarios/grupos
    if cfg.get("commands_locked_for_users", False) and not is_owner:
        await reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return None

    is_debug = True if (force_debug and is_owner) else (bool(cfg.get("monitor_debug_mode", False)) if is_owner else False)

    args_lower = [a.lower() for a in (context.args or [])]
    cmd_name = (update.message.text or "").split()[0].lower() if (update.message and update.message.text) else ""
    send_to_group = ("grupo" in args_lower or "group" in args_lower or "_grupo" in cmd_name)
    is_ondemand = (
        "ondemand" in args_lower or
        "on-demand" in args_lower or
        "_ondemand" in cmd_name or
        "live" in args_lower or
        "vivo" in args_lower or
        "caliente" in args_lower
    )

    if is_ondemand and not (is_owner or is_allowed_group):
        await reply(
            update.message,
            "🔒 <b>Acceso Restringido:</b> El modo de chequeo en vivo (On-Demand) está reservado exclusivamente para el Administrador o el Grupo Corporativo autorizado.\n\n"
            "<i>Utilice <code>/servicios</code> o <code>/sedes</code> para consultar el reporte oficial consolidado (Snapshot).</i>"
        )
        return None

    if send_to_group and not is_owner:
        await reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return None

    target_groups: List[int] = []
    if send_to_group:
        for a in (context.args or []):
            if (a.startswith("-100") or (a.startswith("-") and len(a) > 5)) and a.lstrip("-").isdigit():
                try:
                    target_groups.append(int(a))
                except ValueError:
                    pass

        if not target_groups:
            target_groups = list(allowed_groups)

        if not target_groups:
            await reply(
                update.message,
                "❌ <b>No se encontraron grupos autorizados:</b>\n"
                "No hay ningún grupo registrado en <code>allowed_group_ids</code> ni se indicó un ID de grupo válido.\n"
                "<i>Use <code>/grupos</code> o autorice el grupo en el sistema.</i>"
            )
            return None

    return ReportValidationResult(
        is_owner=is_owner,
        is_debug=is_debug,
        is_ondemand=is_ondemand,
        send_to_group=send_to_group,
        target_groups=target_groups,
        chat_id=chat_id,
    )
