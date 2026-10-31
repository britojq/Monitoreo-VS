"""
# ==============================================================================
# 🔍 ACTION: ANÁLISIS DE RED: analysis_action.py
# Ejecuta el análisis y diagnóstico avanzado de red local (ARP / ICMP / NET Radar).
# Ubicación: /opt/sonar/bot_core/handlers/network/actions/analysis_action.py
# ==============================================================================
"""
import html
import logging
from datetime import datetime
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.network.helpers import get_active_config, reply
from bot_core.security.auth import (
    MSG_UNAUTHORIZED_GROUP_COMMAND,
    check_authorization,
    log_security_audit_event,
)
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot.handlers.network.analysis")


async def handle_analisis_red(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar el análisis y diagnóstico avanzado de red local (ARP / ICMP)."""
    if not update.effective_user or not update.message:
        return

    if not await check_authorization(update, context):
        return

    cfg = get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    allowed_groups = cfg.get("allowed_group_ids", [])
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id if update.effective_chat else 0

    is_owner = (user_id == owner_id)
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
        msg_text = update.message.text or "/analisis_red"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        log_security_audit_event(
            event="unauthorized_network_analysis",
            user_id=user_id,
            username=user.username or "",
            full_name=full_name,
            chat_id=chat_id,
            chat_title=chat_title,
            command=msg_text,
            description=f"Intento de análisis de red no autorizado en {chat_title} por {full_name}"
        )

        await reply(update.message, MSG_UNAUTHORIZED_GROUP_COMMAND)

        if owner_id and user_id != owner_id and cfg.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Análisis de Red Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre intento de analisis_red: {e}")

        return

    if cfg.get("commands_locked_for_users", False) and not is_owner:
        await reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return

    duration = 60
    is_live = False

    if context.args:
        for arg in context.args:
            arg_clean = arg.strip().lower()
            if arg_clean in ("vivo", "live", "captura", "raw", "scan"):
                is_live = True
            elif arg_clean.isdigit():
                val = int(arg_clean)
                if 5 <= val <= 600:
                    duration = val
                    is_live = True

    if not is_live:
        try:
            from monitor.net_radar_engine import generate_markdown_report_from_db
            summary_text, md_file, _ = generate_markdown_report_from_db()

            await update.message.reply_text(summary_text, parse_mode='HTML')

            if md_file and md_file.exists():
                with open(md_file, "rb") as f:
                    await context.bot.send_document(
                        chat_id=chat_id,
                        document=f,
                        filename=md_file.name,
                        caption="📄 Reporte estructurado NET Radar (Tráfico y Actualizaciones)"
                    )

            if is_allowed_group and not is_owner and owner_id:
                try:
                    group_title = update.effective_chat.title or "Grupo Autorizado"
                    await context.bot.send_message(
                        chat_id=owner_id,
                        text=f"📋 <b>Copia de Auditoría: NET Radar ejecutado en {html.escape(group_title)}</b>\n\n" + summary_text,
                        parse_mode='HTML'
                    )
                    if md_file and md_file.exists():
                        with open(md_file, "rb") as f:
                            await context.bot.send_document(
                                chat_id=owner_id,
                                document=f,
                                filename=md_file.name,
                                caption=f"📄 Reporte NET Radar ({group_title})"
                            )
                except Exception as e:
                    logger.error(f"Error enviando copia de auditoría al Owner: {e}")
            return
        except Exception as e_fast:
            logger.error(f"Error generando reporte rápido NET Radar desde BD: {e_fast}", exc_info=True)

    wait_msg = await update.message.reply_text(
        f"⏳ <b>Iniciando captura de tráfico en tiempo real ({duration}s) con tcpdump y análisis profundo con tshark...</b>\n\n"
        "<i>Analizando protocolos, calculando volumen TX/RX, detectando tráfico sospechoso, tormentas de broadcast y midiendo latencias. Por favor espere.</i>",
        parse_mode='HTML'
    )

    try:
        from monitor.network_analyzer import execute_network_analysis
        result = await execute_network_analysis(duration_seconds=duration)

        try:
            await wait_msg.delete()
        except Exception:
            pass

        await update.message.reply_text(result["summary_text"], parse_mode='HTML')

        md_file = result.get("md_report") or result.get("txt_report")
        html_file = result.get("html_report")

        if md_file and md_file.exists():
            with open(md_file, "rb") as f:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=f,
                    filename=md_file.name,
                    caption="📄 Reporte estructurado de análisis de red"
                )
        if html_file and html_file.exists():
            with open(html_file, "rb") as f:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=f,
                    filename=html_file.name,
                    caption="🌐 Reporte interactivo de análisis de red (HTML)"
                )

        if is_allowed_group and not is_owner and owner_id:
            try:
                group_title = update.effective_chat.title or "Grupo Autorizado"
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=f"📋 <b>Copia de Auditoría: Reporte de Red ejecutado en {html.escape(group_title)}</b>\n\n" + result["summary_text"],
                    parse_mode='HTML'
                )
                if md_file and md_file.exists():
                    with open(md_file, "rb") as f:
                        await context.bot.send_document(
                            chat_id=owner_id,
                            document=f,
                            filename=md_file.name,
                            caption=f"📄 Reporte de análisis de red ({group_title})"
                        )
                if html_file and html_file.exists():
                    with open(html_file, "rb") as f:
                        await context.bot.send_document(
                            chat_id=owner_id,
                            document=f,
                            filename=html_file.name,
                            caption=f"🌐 Reporte HTML ({group_title})"
                        )
            except Exception as e:
                logger.error(f"Error enviando copia de auditoría al Owner: {e}")

    except Exception as e:
        logger.error(f"Error ejecutando análisis de red: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el escaneo de red:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await reply(update.message, f"❌ Error ejecutando análisis de red: {e}")
