"""
Manejador de telemetría térmica y guardián de CPU.
Principio SOLID: Módulo desacoplado de telemetría de hardware.
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

from bot_core.services.config_manager import load_config
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.thermal")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id == owner_id


def format_thermal_dashboard_html() -> Tuple[str, InlineKeyboardMarkup]:
    """Genera el mensaje interactivo en formato HTML para Telegram con telemetría de CPU y guardián térmico."""
    from monitor.checkers.thermal import (
        DEFAULT_TEMP_CRITICAL,
        DEFAULT_TEMP_RECOVERY,
        DEFAULT_TEMP_WARNING,
        get_cpu_temperatures,
        is_ai_service_active,
    )
    status = get_cpu_temperatures()
    hostname = platform.node()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    cfg = load_config()
    ai_enabled_in_config = cfg.get("ai_engine_enabled", cfg.get("ollama_enabled", True))
    if not ai_enabled_in_config:
        ai_state_str = "⚪ <b>DESHABILITADO (Equipo de Desarrollo)</b>"
    else:
        ai_active = is_ai_service_active()
        if ai_active:
            ai_state_str = "🟢 <b>ACTIVO (Límite: 8 GB RAM / 70% CPU Máx)</b>"
        else:
            ai_state_str = "🔴 <b>DETENIDO / EN PAUSA</b>"

    cores_lines = []
    for c_name, c_temp in status["core_temps"].items():
        c_badge = "🔴" if c_temp >= DEFAULT_TEMP_CRITICAL else ("🟡" if c_temp >= DEFAULT_TEMP_WARNING else "🟢")
        cores_lines.append(f"  • {html.escape(c_name)}: {c_badge} <code>{c_temp:.1f}°C</code>")
    cores_str = "\n".join(cores_lines) if cores_lines else "  • Sensores integrados operando normalmente"

    text = (
        "🌡️ <b>DIAGNÓSTICO TÉRMICO Y PROTECCIÓN FÍSICA DE CPU</b>\n"
        "═══════════════════════════════\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"🔥 <b>Estado Térmico:</b> {status['badge']} <b>{status['level']}</b>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
        "═══════════════════════════════\n"
        f"🌡️ <b>Temperatura Pico:</b> {status['badge']} <b><code>{status['max_temp']:.1f}°C</code></b>\n"
        f"📦 <b>Package CPU:</b> <code>{status['package_temp']:.1f}°C</code>\n"
        f"📊 <b>Promedio Sensores:</b> <code>{status['avg_temp']:.1f}°C</code>\n\n"
        "<b>Detalle por Núcleo Físico:</b>\n"
        f"{cores_str}\n\n"
        "<b>Servicios y Guardián Autónomo:</b>\n"
        f"  • Asistente Corporativo: {ai_state_str}\n"
        "  • Guardián Térmico: 🟢 <b>ACTIVO (Auto-corte preventivo)</b>\n\n"
        "⚙️ <b>Umbrales de Protección Física:</b>\n"
        f"  • Advertencia (Warning): <code>{DEFAULT_TEMP_WARNING:.1f}°C</code>\n"
        f"  • Parada Emergencia (Stop IA): <code>{DEFAULT_TEMP_CRITICAL:.1f}°C</code>\n"
        f"  • Auto-Recuperación: <code>&lt; {DEFAULT_TEMP_RECOVERY:.1f}°C</code> (3 min)\n"
        "═══════════════════════════════\n"
        "<i>El guardián térmico opera de forma autónoma cada 5 segundos protegiendo el hardware contra sobrecalentamiento.</i>"
    )

    keyboard = InlineKeyboardMarkup([
        [
            InlineKeyboardButton("🔄 Actualizar Temperatura", callback_data="thermal:refresh")
        ],
        [
            InlineKeyboardButton("💻 Ver Recursos (RAM/CPU)", callback_data="sys_opt:refresh")
        ]
    ])

    return text, keyboard


async def cmd_temperatura(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Diagnóstico térmico y estado del guardián físico de CPU (EXCLUSIVO OWNER en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Consultando sensores térmicos y estado del guardián físico de CPU...</i>",
        parse_mode="HTML"
    )

    try:
        dashboard_text, keyboard = await asyncio.to_thread(format_thermal_dashboard_html)
        try:
            await wait_msg.delete()
        except Exception:
            pass
        await update.message.reply_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
    except Exception as e:
        logger.error(f"Error generando panel térmico: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error consultando sensores térmicos:</b> <code>{html.escape(str(e))}</code>", parse_mode="HTML")
        except Exception:
            await update.message.reply_text(f"❌ Error: {e}")


async def handle_thermal_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de telemetría térmica."""
    query = update.callback_query
    if not query or not query.data:
        return

    if not _is_owner(query.from_user.id):
        await query.answer("⛔ Solo el creador del bot puede consultar la telemetría térmica.", show_alert=True)
        return

    try:
        action = query.data.split(":", 1)[1]
    except IndexError:
        await query.answer("⚠️ Solicitud inválida.")
        return

    if action == "refresh":
        await query.answer("🔄 Actualizando lectura de sensores...")
        dashboard_text, keyboard = await asyncio.to_thread(format_thermal_dashboard_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
