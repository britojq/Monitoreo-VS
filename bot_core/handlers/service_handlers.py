"""
# ==============================================================================
# 📊 HANDLERS DE MONITOREO Y SERVICIOS: service_handlers.py
# Despacho de reportes de Sedes, Servicios, Caídas, Captura Web, Internet y SSL.
# Principio de Responsabilidad Única (S) y Principios SOLID.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import sys
from datetime import datetime
from typing import Any, Dict, List

from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes

from bot_core.security.auth import (
    MSG_UNAUTHORIZED_ADMIN_COMMAND,
    MSG_UNAUTHORIZED_GROUP_COMMAND,
    check_authorization,
    get_audit_log_path,
    require_private_chat,
)
from bot_core.services.config_manager import load_config
from bot_core.services.connection_service import _check_endpoint_health, load_proxies_list
from bot_core.utils.telegram_helpers import markdown_to_telegram_html, safe_reply_html
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot.handlers.services")


def _get_active_config() -> Dict[str, Any]:
    """Obtiene la configuración activa del bot."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


async def _reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Invoca safe_reply_html dinámicamente respetando cualquier patch en bot.safe_reply_html."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


async def _resolve_group_info(bot: Any, group_id: int) -> str:
    """Resuelve dinámicamente el título de un grupo de Telegram."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "_resolve_group_info"):
        return await bot_mod._resolve_group_info(bot, group_id)
    try:
        chat = await bot.get_chat(chat_id=group_id)
        return chat.title or str(group_id)
    except Exception:
        return str(group_id)


async def _run_and_send_monitoring_report(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    target: str = "completo",
    target_name: str = "Infraestructura",
    force_debug: bool = False
) -> None:
    """Función unificada para ejecutar chequeos concurrentes y enviar reportes a Telegram."""
    if not update.effective_user or not update.message:
        return

    # Verificar autorización general del usuario
    if not await check_authorization(update, context):
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    allowed_groups = cfg.get("allowed_group_ids", [])
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id if update.effective_chat else 0

    is_owner = (user_id == owner_id)
    is_allowed_group = (chat_id in allowed_groups)

    # Si es comando de debug directo, es estrictamente reservado para el Owner
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

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] DEBUG_DIRECTO DENEGADO ({target.upper()}) | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)

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

        return

    # Regla de seguridad 2: Solo el owner o usuarios dentro de los grupos autorizados
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

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] REPORTE DENEGADO ({target.upper()}) | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        await _reply(update.message, MSG_UNAUTHORIZED_GROUP_COMMAND)

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

        return

    # Regla de bloqueo global de comandos para usuarios/grupos activada por el Owner
    if cfg.get("commands_locked_for_users", False) and not is_owner:
        await _reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return

    # Determinar si se ejecuta en modo depuración
    if force_debug and is_owner:
        is_debug = True
    else:
        is_debug = bool(cfg.get("monitor_debug_mode", False)) if is_owner else False

    # Detectar argumentos y opciones especiales
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
        await _reply(
            update.message,
            "🔒 <b>Acceso Restringido:</b> El modo de chequeo en vivo (On-Demand) está reservado exclusivamente para el Administrador o el Grupo Corporativo autorizado.\n\n"
            "<i>Utilice <code>/servicios</code> o <code>/sedes</code> para consultar el reporte oficial consolidado (Snapshot).</i>"
        )
        return

    if send_to_group and not is_owner:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    target_groups = []
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
            await _reply(
                update.message,
                "❌ <b>No se encontraron grupos autorizados:</b>\n"
                "No hay ningún grupo registrado en <code>allowed_group_ids</code> ni se indicó un ID de grupo válido.\n"
                "<i>Use <code>/grupos</code> o autorice el grupo en el sistema.</i>"
            )
            return

    # Mensaje temporal de espera según el modo seleccionado
    if send_to_group:
        wait_msg = await update.message.reply_text(
            f"⏳ <i>Ejecutando chequeo de {target_name} ({'On-Demand' if is_ondemand else 'Snapshot'}) y preparando despacho hacia el grupo corporativo...</i>",
            parse_mode='HTML'
        )
    elif is_ondemand:
        wait_msg = await update.message.reply_text(
            f"⏳ <i>Ejecutando sondeo físico de red en caliente (On-Demand) de {target_name}... Por favor espere.</i>",
            parse_mode='HTML'
        )
    else:
        wait_msg = await update.message.reply_text(
            f"⏳ <i>Consultando reporte oficial (Snapshot) de {target_name}... Por favor espere.</i>",
            parse_mode='HTML'
        )

    try:
        from monitor.monitor_engine import execute_monitoring
        from monitor.web_screenshot import capture_web_dashboard

        if is_ondemand:
            mode_label = "Chequeo Físico en Caliente (On-Demand)"
            screen_file = None
            result = await execute_monitoring(
                target=target,
                debug_mode=is_debug,
                capture_screenshot=False,
                mode="ondemand"
            )
        else:
            arg_first = (context.args[0].lower() if (context.args and len(context.args) > 0) else "")
            if send_to_group or arg_first in ("web", "full", "pantalla", "todo", "global"):
                capture_mode = "full"
                mode_label = "Vista Global"
            elif target == "servicios":
                capture_mode = "servicios"
                mode_label = "Servicios Activos"
            elif target == "sedes":
                capture_mode = "sedes"
                mode_label = "Sedes Regionales y Equipos en Sitio"
            elif target in ("caidas", "incidentes", "fallas"):
                capture_mode = "caidas"
                mode_label = "Incidentes y Servicios Caídos"
            else:
                capture_mode = "full"
                mode_label = "Vista Global"

            for a in args_lower:
                if a in ("full", "global", "todo", "pantalla"):
                    capture_mode = "full"
                    mode_label = "Vista Global"
                    break
                elif a == "servicios":
                    capture_mode = "servicios"
                    mode_label = "Servicios Activos"
                    break
                elif a == "sedes":
                    capture_mode = "sedes"
                    mode_label = "Sedes Regionales y Equipos en Sitio"
                    break
                elif a in ("caidas", "fallas", "incidentes"):
                    capture_mode = "caidas"
                    mode_label = "Incidentes y Servicios Caídos"
                    break

            monitoring_task = asyncio.create_task(
                execute_monitoring(target=target, debug_mode=is_debug, capture_screenshot=False, mode="snapshot")
            )
            screenshot_task = asyncio.create_task(
                capture_web_dashboard(mode=capture_mode)
            )

            monitoring_res, screen_res = await asyncio.gather(monitoring_task, screenshot_task, return_exceptions=True)

            if isinstance(monitoring_res, Exception):
                raise monitoring_res
            result = monitoring_res

            screen_file = None
            if isinstance(screen_res, Exception):
                logger.warning(f"No se pudo generar captura web ({screen_res})")
            else:
                screen_file = screen_res

        # CASO 1: Despacho al Grupo Destinado
        if send_to_group:
            dispatched_groups = []
            html_svc = markdown_to_telegram_html(result["report_servicios"]) if result.get("report_servicios") else None
            html_sedes = markdown_to_telegram_html(result["report_sedes"]) if result.get("report_sedes") else None
            photo_bytes = screen_file.read_bytes() if (screen_file and screen_file.exists()) else None

            for gid in target_groups:
                try:
                    if html_svc:
                        try:
                            await context.bot.send_message(chat_id=gid, text=html_svc, parse_mode='HTML')
                        except Exception as e_html:
                            logger.warning(f"Fallo envío HTML a grupo {gid} ({e_html}). Enviando texto plano...")
                            await context.bot.send_message(chat_id=gid, text=result["report_servicios"])

                    if html_sedes:
                        try:
                            await context.bot.send_message(chat_id=gid, text=html_sedes, parse_mode='HTML')
                        except Exception as e_html:
                            logger.warning(f"Fallo envío HTML sedes a grupo {gid} ({e_html}). Enviando texto plano...")
                            await context.bot.send_message(chat_id=gid, text=result["report_sedes"])

                    if photo_bytes:
                        caption = (
                            f"📸 <b>Captura en Tiempo Real</b>\n"
                            f"🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n"
                            f"📌 <i>{mode_label}</i>"
                        )
                        await context.bot.send_photo(
                            chat_id=gid,
                            photo=photo_bytes,
                            caption=caption,
                            parse_mode='HTML'
                        )

                    gtitle = await _resolve_group_info(context.bot, gid)
                    dispatched_groups.append(f"• 👥 <b>{html.escape(gtitle)}</b> (<code>{gid}</code>)")
                    logger.info(f"Reporte ({target}) despachado exitosamente al grupo {gid}")
                except Exception as e_grp:
                    logger.error(f"Error despachando reporte a grupo {gid}: {e_grp}")
                    dispatched_groups.append(f"• ⚠️ <code>{gid}</code>: Error ({html.escape(str(e_grp))})")

            try:
                await wait_msg.delete()
            except Exception:
                pass

            groups_summary = "\n".join(dispatched_groups) if dispatched_groups else "Sin destinatarios"
            elapsed_sec = result.get("elapsed_seconds", 0)
            confirm_text = (
                "✅ <b>Reporte Despachado al Grupo Corporativo</b>\n\n"
                f"📋 <b>Tipo de Reporte:</b> <code>{html.escape(target_name)}</code> ({mode_label})\n"
                f"⏱️ <b>Tiempo de Chequeo:</b> <code>{elapsed_sec}s</code>\n\n"
                f"<b>Destinatario(s):</b>\n{groups_summary}\n\n"
                "<i>ℹ️ El reporte textual y la captura de pantalla fueron enviados directamente al grupo corporativo autorizado.</i>"
            )
            await _reply(update.message, confirm_text)

            if is_debug and is_owner and result.get("log_file") and result["log_file"].exists():
                with open(result["log_file"], "rb") as doc:
                    await context.bot.send_document(
                        chat_id=chat_id,
                        document=doc,
                        filename="servicelog.txt",
                        caption=f"📄 Registro técnico detallado de ejecución ({result['elapsed_seconds']}s)"
                    )
            return

        # CASO 2: Despacho normal al chat de la solicitud
        try:
            await wait_msg.delete()
        except Exception:
            pass

        if result.get("skipped"):
            await _reply(
                update.message,
                "⚠️ <b>Chequeo en curso:</b> Ya existe una ronda de monitoreo ejecutándose en el servidor. Por favor espere unos segundos e intente nuevamente."
            )
            return

        if result.get("report_servicios"):
            html_svc = markdown_to_telegram_html(result["report_servicios"])
            await _reply(update.message, html_svc)

        if result.get("report_sedes"):
            html_sedes = markdown_to_telegram_html(result["report_sedes"])
            await _reply(update.message, html_sedes)

        if screen_file and screen_file.exists():
            caption = (
                f"📸 <b>Captura en Tiempo Real</b>\n"
                f"🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n"
                f"📌 <i>{mode_label}</i>"
            )
            with open(screen_file, "rb") as photo_doc:
                await update.message.reply_photo(
                    photo=photo_doc,
                    caption=caption,
                    parse_mode='HTML'
                )

        if is_debug and is_owner and result.get("log_file") and result["log_file"].exists():
            with open(result["log_file"], "rb") as doc:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=doc,
                    filename="servicelog.txt",
                    caption=f"📄 Registro técnico detallado de ejecución ({result['elapsed_seconds']}s)"
                )

    except Exception as e:
        logger.error(f"Error ejecutando monitoreo ({target}): {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el chequeo:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await _reply(update.message, f"❌ Error ejecutando chequeo: {e}")


async def cmd_reporte_servicios(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Servicios Corporativos."""
    await _run_and_send_monitoring_report(update, context, target="servicios", target_name="Servicios Corporativos")


async def cmd_reporte_sedes(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Sedes y Enlaces."""
    await _run_and_send_monitoring_report(update, context, target="sedes", target_name="Sedes y Equipos de Comunicación")


async def cmd_reporte_caidas(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Incidentes y Caídas."""
    await _run_and_send_monitoring_report(update, context, target="caidas", target_name="Incidentes y Servicios Caídos")


async def cmd_captura_web(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando directo para obtener la captura web en tiempo real."""
    await _run_and_send_monitoring_report(update, context, target="web", target_name="Vista Web en Tiempo Real")


async def cmd_reporte_completo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte completo (Servicios + Sedes)."""
    await _run_and_send_monitoring_report(update, context, target="completo", target_name="Servicios Corporativos y Sedes")


async def cmd_debug_servicios(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte exhaustivo de servicios."""
    if not await require_private_chat(update, context):
        return

    await _run_and_send_monitoring_report(
        update, context,
        target="servicios",
        target_name="Servicios Corporativos (Debug Directo)",
        force_debug=True
    )


async def cmd_debug_sedes(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte exhaustivo de sedes."""
    if not await require_private_chat(update, context):
        return

    await _run_and_send_monitoring_report(
        update, context,
        target="sedes",
        target_name="Sedes y Equipos (Debug Directo)",
        force_debug=True
    )


async def cmd_debug_completo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte técnico integral exhaustivo."""
    if not await require_private_chat(update, context):
        return

    await _run_and_send_monitoring_report(
        update, context,
        target="completo",
        target_name="Servicios y Sedes (Debug Directo)",
        force_debug=True
    )


async def cmd_internet(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para verificar la funcionalidad y latencia de las salidas a internet y proxies corporativos."""
    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
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

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] INTERNET DENEGADO | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        await _reply(update.message, MSG_UNAUTHORIZED_GROUP_COMMAND)

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
        await _reply(
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
            await _reply(update.message, f"❌ Error ejecutando diagnóstico de internet: {e}")


async def cmd_ssl(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando de Monitoreo de Certificados SSL/TLS (Fase 3)."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    args = context.args or []
    subcmd = args[0].lower() if args else "list"

    user_id = update.effective_user.id if update.effective_user else 0
    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            if subcmd in ("list", "resumen", "status", "todos"):
                with conn.cursor() as cur:
                    cur.execute("""
                        SELECT domain, port, days_remaining, valid_to, issuer_cn, last_check_status, is_wildcard, is_self_signed
                        FROM ssl_certificates
                        WHERE is_active = 1
                        ORDER BY days_remaining ASC
                    """)
                    certs = cur.fetchall()

                if not certs:
                    await _reply(
                        update.message,
                        "🔒 <b>MONITOREO DE CERTIFICADOS SSL/TLS</b>\n\n"
                        "ℹ️ No hay certificados registrados en el inventario."
                    )
                    return

                total = len(certs)
                valid = sum(1 for c in certs if c["days_remaining"] > 30 and c["last_check_status"] != "error")
                expiring = sum(1 for c in certs if 0 <= c["days_remaining"] <= 30 and c["last_check_status"] != "error")
                critical = sum(1 for c in certs if 0 <= c["days_remaining"] <= 7 and c["last_check_status"] != "error")
                expired = sum(1 for c in certs if c["days_remaining"] < 0 or c["last_check_status"] == "expired")
                errors = sum(1 for c in certs if c["last_check_status"] == "error")

                lines = [
                    "🔒 <b>MONITOREO DE CERTIFICADOS SSL/TLS</b>",
                    "🏢 <i>Centro de Telecomunicaciones Valle Seco</i>\n",
                    f"📊 <b>Resumen General:</b>",
                    f"• Total registrados: <b>{total}</b>",
                    f"• 🟢 Válidos (&gt;30d): <b>{valid}</b>",
                    f"• 🟡 Por Vencer (&le;30d): <b>{expiring}</b>",
                    f"• 🔴 Críticos (&le;7d): <b>{critical}</b>",
                    f"• ❌ Expirados: <b>{expired}</b>",
                    f"• ⚠️ Fallos / Inalcanzable: <b>{errors}</b>\n",
                    "📋 <b>Inventario de Dominios:</b>",
                ]

                for c in certs[:15]:
                    dom = html.escape(c["domain"])
                    port = c["port"]
                    days = c["days_remaining"]
                    status = c["last_check_status"]

                    if status == "error":
                        icon = "⚠️"
                        day_str = "Error de conexión"
                    elif days < 0 or status == "expired":
                        icon = "❌"
                        day_str = f"Expiró hace {abs(days)}d"
                    elif days <= 7:
                        icon = "🔴"
                        day_str = f"Crítico: {days}d restantes"
                    elif days <= 30:
                        icon = "🟡"
                        day_str = f"Por vencer: {days}d restantes"
                    else:
                        icon = "🟢"
                        day_str = f"{days}d restantes"

                    wild_badge = " [WILDCARD]" if c.get("is_wildcard") else ""
                    self_badge = " [SELF-SIGNED]" if c.get("is_self_signed") else ""
                    lines.append(f"{icon} <code>{dom}:{port}</code>{wild_badge}{self_badge}\n   └ {day_str}")

                if len(certs) > 15:
                    lines.append(f"\n<i>...y {len(certs) - 15} certificados más. Use /certificados detail &lt;dominio&gt;</i>")

                lines.append("\n💡 <i>Comandos: /certificado &lt;dominio&gt; | /certificados expirando [días]</i>")
                await _reply(update.message, "\n".join(lines))

            elif subcmd in ("expiring", "por_vencer", "vencimiento", "expirando"):
                limit_days = 30
                if len(args) > 1 and args[1].isdigit():
                    limit_days = int(args[1])

                with conn.cursor() as cur:
                    cur.execute("""
                        SELECT domain, port, days_remaining, valid_to, issuer_cn, last_check_status
                        FROM ssl_certificates
                        WHERE is_active = 1 AND days_remaining <= %s
                        ORDER BY days_remaining ASC
                    """, (limit_days,))
                    expiring_list = cur.fetchall()

                if not expiring_list:
                    await _reply(
                        update.message,
                        f"🟢 <b>Todos los certificados vigentes</b>\n\n"
                        f"No hay certificados que expiren en los próximos <b>{limit_days} días</b>."
                    )
                    return

                lines = [
                    f"⏰ <b>CERTIFICADOS POR VENCER (&le; {limit_days} días)</b>\n",
                ]
                for c in expiring_list:
                    dom = html.escape(c["domain"])
                    days = c["days_remaining"]
                    icon = "❌" if days < 0 else ("🔴" if days <= 7 else "🟡")
                    v_to = c["valid_to"].strftime("%d/%m/%Y") if c["valid_to"] else "N/A"
                    lines.append(f"{icon} <code>{dom}:{c['port']}</code>\n   └ {days} días restantes (Vence: {v_to})")

                await _reply(update.message, "\n".join(lines))

            elif subcmd in ("detail", "detalle", "info") or (args and subcmd not in ("check", "scan", "verificar")):
                target_domain = args[1] if subcmd in ("detail", "detalle", "info") and len(args) > 1 else args[0]
                with conn.cursor() as cur:
                    cur.execute("""
                        SELECT * FROM ssl_certificates
                        WHERE domain LIKE %s OR domain = %s
                        LIMIT 1
                    """, (f"%{target_domain}%", target_domain))
                    c = cur.fetchone()

                if not c:
                    await _reply(
                        update.message,
                        f"🔍 No se encontró ningún certificado registrado para '<code>{html.escape(target_domain)}</code>'."
                    )
                    return

                days = c["days_remaining"]
                status = c["last_check_status"]
                icon = "❌" if days < 0 else ("🔴" if days <= 7 else ("🟡" if days <= 30 else "🟢"))
                if status == "error":
                    icon = "⚠️"

                v_from = c["valid_from"].strftime("%d/%m/%Y %H:%M") if c["valid_from"] else "N/A"
                v_to = c["valid_to"].strftime("%d/%m/%Y %H:%M") if c["valid_to"] else "N/A"
                l_chk = c["last_checked_at"].strftime("%d/%m/%Y %H:%M") if c["last_checked_at"] else "N/A"

                sans = ""
                if c.get("san_entries"):
                    try:
                        s_list = json.loads(c["san_entries"]) if isinstance(c["san_entries"], str) else c["san_entries"]
                        sans = ", ".join(s_list[:4])
                        if len(s_list) > 4:
                            sans += f" (+{len(s_list)-4} más)"
                    except Exception:
                        pass

                msg = (
                    f"{icon} <b>DETALLE CERTIFICADO SSL/TLS</b>\n"
                    f"━━━━━━━━━━━━━━━━━━━━━━\n"
                    f"🌐 <b>Dominio:</b> <code>{html.escape(c['domain'])}:{c['port']}</code>\n"
                    f"📊 <b>Estado:</b> <code>{status.upper() if status else 'N/A'}</code> ({days} días)\n"
                    f"🏢 <b>Subject CN:</b> <code>{html.escape(c.get('subject_cn') or 'N/A')}</code>\n"
                    f"🏛️ <b>Issuer CA:</b> <code>{html.escape(c.get('issuer_cn') or c.get('issuer_org') or 'N/A')}</code>\n"
                    f"📅 <b>Válido Desde:</b> {v_from}\n"
                    f"⏳ <b>Válido Hasta:</b> {v_to}\n"
                    f"🔑 <b>Clave Pública:</b> {c.get('public_key_algorithm') or 'RSA'} {c.get('public_key_bits') or ''} bits\n"
                    f"✍️ <b>Firma:</b> <code>{c.get('signature_algorithm') or 'N/A'}</code>\n"
                    f"🔢 <b>N° Serie:</b> <code>{c.get('serial_number') or 'N/A'}</code>\n"
                    f"🔄 <b>Renovaciones:</b> {c.get('renewal_count', 0)}\n"
                    + (f"🏷️ <b>SANs:</b> <code>{html.escape(sans)}</code>\n" if sans else "")
                    + f"🕒 <b>Última Inspección:</b> {l_chk}\n"
                    f"━━━━━━━━━━━━━━━━━━━━━━"
                )
                await _reply(update.message, msg)

            elif subcmd in ("check", "scan", "verificar"):
                if not is_admin:
                    await _reply(update.message, "⛔ La re-inspección forzada está reservada para el Administrador.")
                    return

                target = args[1] if len(args) > 1 else ""
                if not target:
                    await _reply(update.message, "⚠️ Especifique el dominio a inspeccionar. Ej: <code>/ssl check core.telegram.org</code>")
                    return

                await _reply(update.message, f"⏳ <i>Inspeccionando certificado TLS para <code>{html.escape(target)}</code>...</i>")

                from monitor.ssl_checker import check_and_update_certificate, extract_cert_info
                with conn.cursor() as cur:
                    cur.execute("SELECT * FROM ssl_certificates WHERE domain = %s", (target,))
                    rec = cur.fetchone()

                if rec:
                    res = check_and_update_certificate(conn, rec, timeout=5.0)
                else:
                    res = extract_cert_info(target, 443, timeout=5.0)

                if res["success"]:
                    await _reply(
                        update.message,
                        f"✅ <b>Inspección Exitosa</b>\n"
                        f"• Dominio: <code>{html.escape(res['domain'])}</code>\n"
                        f"• Días Restantes: <b>{res['days_remaining']} días</b>\n"
                        f"• Vence: {res['valid_to']}\n"
                        f"• Emisor: <code>{html.escape(res['issuer_cn'] or 'N/A')}</code>\n"
                        f"• Estado: <b>{res['status'].upper()}</b>"
                    )
                else:
                    await _reply(
                        update.message,
                        f"❌ <b>Fallo en Inspección</b>\n"
                        f"• Dominio: <code>{html.escape(res['domain'])}</code>\n"
                        f"• Error: {html.escape(res.get('error_message') or 'Timeout / Inalcanzable')}"
                    )

            else:
                await _reply(
                    update.message,
                    "⚠️ Subcomando no reconocido. Use:\n"
                    "• <code>/certificados</code> — Lista general\n"
                    "• <code>/certificado &lt;dominio&gt;</code> — Detalle completo\n"
                    "• <code>/certificados expirando [días]</code> — Próximos a vencer\n"
                    + ("• <code>/ssl check &lt;dominio&gt;</code> — Inspeccionar en vivo\n" if is_admin else "")
                )

        finally:
            conn.close()

    except Exception as e:
        logger.error(f"Error en cmd_ssl: {e}")
        await _reply(update.message, f"❌ Error procesando comando SSL: {html.escape(str(e))}")


def register_service_routes(application: Application) -> None:
    """Registra las rutas de comandos de monitoreo y servicios en la aplicación Telegram."""
    application.add_handler(CommandHandler(["reporte_servicios", "servicios", "servicio", "servicios_grupo", "servicios_ondemand"], cmd_reporte_servicios))
    application.add_handler(CommandHandler(["reporte_sedes", "sedes", "sede", "sitios", "sedes_grupo", "sedes_ondemand", "enlaces"], cmd_reporte_sedes))
    application.add_handler(CommandHandler(["reporte_caidas", "caidas", "incidentes", "fallas", "caidas_grupo", "caidas_ondemand"], cmd_reporte_caidas))
    application.add_handler(CommandHandler(["web", "pantalla", "captura", "dashboard", "screenshot"], cmd_captura_web))
    application.add_handler(CommandHandler(["reporte_completo", "reporte", "completo", "todo", "monitoreo", "monitoreo_grupo", "monitoreo_ondemand"], cmd_reporte_completo))
    application.add_handler(CommandHandler(["debug_servicios", "debugservicios", "servicios_debug", "debug_servicio"], cmd_debug_servicios))
    application.add_handler(CommandHandler(["debug_sedes", "debugsedes", "sedes_debug", "debug_sede"], cmd_debug_sedes))
    application.add_handler(CommandHandler(["debug_completo", "debugcompleto", "debug_monitoreo", "monitoreo_debug", "debug_todo"], cmd_debug_completo))
    application.add_handler(CommandHandler(["internet", "proxy", "proxies", "conectividad", "salida_internet"], cmd_internet))
    application.add_handler(CommandHandler(["certificados", "certificado", "ssl", "certs", "tls"], cmd_ssl))
    logger.info("Rutas de Servicios y Monitoreo registradas exitosamente.")
