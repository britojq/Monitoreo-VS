"""
Manejador de administración del Motor de Inteligencia / Asistente Corporativo.
Principio SOLID: Módulo aislado con cumplimiento estricto de la Regla de Oro #2.
"""

from __future__ import annotations
import asyncio
import html
import logging
import platform
from datetime import datetime
from typing import Tuple

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.ai_control.dtos import AiControlActionPayload
from bot_core.services.config_manager import load_config, save_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.ai_control")


def format_ai_control_panel_html() -> Tuple[str, InlineKeyboardMarkup]:
    """Genera el mensaje interactivo en formato HTML para Telegram para el control del motor de inteligencia."""
    from monitor.thermal_guard import is_ai_service_active
    hostname = platform.node()
    is_dev = (hostname.upper() == "CENCARATIT")

    cfg = load_config()
    ai_enabled_in_config = bool(cfg.get("ai_engine_enabled", cfg.get("ollama_enabled", True)))
    ai_service_running = is_ai_service_active()

    config_badge = "🟢 <b>ACTIVADO</b>" if ai_enabled_in_config else "🔴 <b>DESACTIVADO</b>"
    service_badge = "🟢 <b>ACTIVO (Running)</b>" if ai_service_running else "🔴 <b>DETENIDO (Inactive)</b>"
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    if is_dev:
        env_note = (
            "⚠️ <b>POLÍTICA DE SEGURIDAD EN DESARROLLO:</b>\n"
            "El asistente corporativo se encuentra permanentemente <b>deshabilitado en este equipo</b> "
            "para resguardar el hardware físico. No se permite su activación en <code>CENCARATIT</code>.\n"
        )
    else:
        env_note = (
            "🛡️ <b>LIMITADORES ACTIVOS DE HARDWARE:</b>\n"
            "• Tope de Memoria RAM: <code>8 GB</code> (Cgroups systemd)\n"
            "• Cuota de CPU: <code>Máx. 70% (3 hilos)</code>\n"
            "• Guardián Térmico: <code>Auto-corte preventivo a 75°C</code>\n"
        )

    text = (
        "🧠 <b>CONTROL Y ADMINISTRACIÓN DEL ASISTENTE CORPORATIVO</b>\n"
        "═══════════════════════════════\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
        "═══════════════════════════════\n"
        f"⚙️ <b>Estado en Configuración:</b> {config_badge}\n"
        f"🚀 <b>Estado del Servicio Systemd:</b> {service_badge}\n\n"
        f"{env_note}"
        "═══════════════════════════════\n"
        "<i>Utilice los botones interactivos inferiores para gestionar el estado del servicio:</i>"
    )

    buttons = []
    if is_dev:
        buttons.append([
            InlineKeyboardButton("⚪ Asistente Restringido en Dev", callback_data="ia_act:dev_blocked")
        ])
    else:
        if ai_enabled_in_config and ai_service_running:
            buttons.append([
                InlineKeyboardButton("🛑 Desactivar Asistente", callback_data="ia_act:disable")
            ])
        else:
            buttons.append([
                InlineKeyboardButton("▶️ Activar Asistente", callback_data="ia_act:enable")
            ])

    buttons.append([
        InlineKeyboardButton("🔄 Actualizar", callback_data="ia_act:refresh"),
        InlineKeyboardButton("🌡️ Temperatura", callback_data="thermal:refresh"),
        InlineKeyboardButton("❌ Cerrar", callback_data="ia_act:close")
    ])

    return text, InlineKeyboardMarkup(buttons)


async def cmd_ia_control(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando interactivo para consultar, encender o apagar el motor local de IA (EXCLUSIVO OWNER en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    cfg = load_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    if user.id != owner_id and user.id != IMMUTABLE_OWNER_ID:
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    hostname = platform.node()
    is_dev = (hostname.upper() == "CENCARATIT")

    if context.args:
        subcmd = context.args[0].lower().strip()
        if subcmd in ("on", "activar", "start", "enable", "1", "si"):
            if is_dev:
                await update.message.reply_text(
                    "⚠️ <b>Acción Restringida:</b>\n"
                    "Por directriz estricta de seguridad del sistema, el asistente corporativo permanece "
                    f"deshabilitado permanentemente en el servidor de desarrollo (<code>{html.escape(hostname)}</code>).",
                    parse_mode="HTML"
                )
                return

            wait_m = await update.message.reply_text("⏳ <i>Iniciando servicio y aplicando límites de hardware...</i>", parse_mode="HTML")
            cfg["ai_engine_enabled"] = True
            cfg["ollama_enabled"] = True
            save_config(cfg)
            try:
                from monitor.thermal_guard import start_ai_service
                await asyncio.to_thread(start_ai_service)
            except Exception as e:
                logger.error(f"Error iniciando servicio IA: {e}")
            try:
                await wait_m.delete()
            except Exception:
                pass
            dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
            await update.message.reply_text(
                f"✅ <b>Asistente Corporativo ACTIVADO exitosamente en {html.escape(hostname)}.</b>\n\n{dashboard_text}",
                parse_mode="HTML",
                reply_markup=keyboard
            )
            return

        elif subcmd in ("off", "desactivar", "stop", "disable", "0", "no"):
            wait_m = await update.message.reply_text("⏳ <i>Deteniendo servicio y liberando memoria RAM...</i>", parse_mode="HTML")
            cfg["ai_engine_enabled"] = False
            cfg["ollama_enabled"] = False
            save_config(cfg)
            try:
                from monitor.thermal_guard import stop_ai_service
                await asyncio.to_thread(stop_ai_service)
            except Exception as e:
                logger.error(f"Error deteniendo servicio IA: {e}")
            try:
                await wait_m.delete()
            except Exception:
                pass
            dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
            await update.message.reply_text(
                f"🛑 <b>Asistente Corporativo DESACTIVADO exitosamente en {html.escape(hostname)}.</b>\n\n{dashboard_text}",
                parse_mode="HTML",
                reply_markup=keyboard
            )
            return

        elif subcmd not in ("panel", "status", "estado"):
            full_query = " ".join(context.args).strip()
            from bot_core.services.ai_chat_service import handle_chat_query_direct
            await handle_chat_query_direct(update, context, full_query)
            return

    dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
    await update.message.reply_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)


async def handle_ia_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de control del asistente corporativo."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = load_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    clicker_id = query.from_user.id

    if clicker_id != owner_id and clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("⛔ Solo el creador del bot puede controlar el asistente corporativo.", show_alert=True)
        return

    payload = AiControlActionPayload.from_callback_data(query.data)
    action = payload.action

    hostname = platform.node()
    is_dev = (hostname.upper() == "CENCARATIT")

    if action == "dev_blocked":
        await query.answer("⚠️ Bloqueado: No permitido en desarrollo por protección física.", show_alert=True)
        return

    if action == "close":
        await query.answer("Panel cerrado.")
        try:
            await query.message.delete()
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass
        return

    if action == "refresh":
        await query.answer("🔄 Actualizando panel...")
        dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return

    if action == "enable":
        if is_dev:
            await query.answer("⚠️ Bloqueado: No permitido en servidor de desarrollo.", show_alert=True)
            return
        await query.answer("🚀 Iniciando asistente corporativo...")
        cfg["ai_engine_enabled"] = True
        cfg["ollama_enabled"] = True
        save_config(cfg)
        try:
            from monitor.thermal_guard import start_ai_service
            await asyncio.to_thread(start_ai_service)
        except Exception as e:
            logger.error(f"Error iniciando servicio IA en callback: {e}")
        dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return

    if action == "disable":
        await query.answer("🛑 Deteniendo asistente corporativo...")
        cfg["ai_engine_enabled"] = False
        cfg["ollama_enabled"] = False
        save_config(cfg)
        try:
            from monitor.thermal_guard import stop_ai_service
            await asyncio.to_thread(stop_ai_service)
        except Exception as e:
            logger.error(f"Error deteniendo servicio IA en callback: {e}")
        dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return
