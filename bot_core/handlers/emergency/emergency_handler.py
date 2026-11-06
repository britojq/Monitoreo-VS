"""
Manejador de panel de emergencia y paradas del servicio.
"""

from __future__ import annotations
import asyncio
import html
import logging
from typing import Optional, Tuple

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.emergency.dtos import EmergencyActionPayload
from bot_core.services.config_manager import load_config, save_config, restore_golden_backup
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.emergency")


def _build_emergency_panel() -> Tuple[str, InlineKeyboardMarkup]:
    """Construye el texto y botones interactivos para el panel de control de emergencia."""
    cfg = load_config()
    is_maint = bool(cfg.get("maintenance_mode", False))
    maint_label = "🔴 ACTIVADO (Usuarios y grupos bloqueados)" if is_maint else "🟢 DESACTIVADO (Operación normal)"

    text = (
        "🚨 <b>PANEL DE CONTROL DE EMERGENCIA (EXCLUSIVO OWNER)</b>\n\n"
        "Este panel permite ejecutar acciones de contingencia inmediata sobre el servicio del bot y su configuración:\n\n"
        "• <b>Estado del Servicio:</b> <code>ACTIVO (Running)</code>\n"
        f"• <b>Modo Mantenimiento:</b> <code>{maint_label}</code>\n"
        "• <b>Respaldo de Configuración:</b> <code>Disponible</code>\n\n"
        "<i>Seleccione una acción de emergencia a continuación:</i>"
    )

    keyboard = [
        [
            InlineKeyboardButton("🛑 Detener Servicio del Bot", callback_data="emergencia:stop_prompt"),
        ],
        [
            InlineKeyboardButton("▶️ Reanudar Normalidad" if is_maint else "⏸️ Activar Mantenimiento",
                                 callback_data="emergencia:toggle_maint"),
            InlineKeyboardButton("🔄 Restaurar Configuración", callback_data="emergencia:restore_prompt"),
        ],
        [
            InlineKeyboardButton("❌ Cerrar Panel", callback_data="emergencia:close")
        ]
    ]
    return text, InlineKeyboardMarkup(keyboard)


async def _delayed_emergency_stop(actor_id: Optional[int] = None) -> None:
    """Espera 1.5 segundos para permitir el envío del mensaje de confirmación y detiene el servicio con auditoría inmutable."""
    from monitor.gitops.services.systemd_control import SystemdController

    await SystemdController.stop_service_delayed(
        delay_seconds=1.5,
        service="tg-admin-bot.service",
        actor_id=actor_id,
        reason="emergency_panel_stop_confirmed",
    )


async def cmd_emergencia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el panel interactivo de emergencia exclusivo para el Owner en chat privado."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if user.id != IMMUTABLE_OWNER_ID:
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    text, reply_markup = _build_emergency_panel()
    await update.message.reply_text(text, parse_mode="HTML", reply_markup=reply_markup)


async def handle_emergency_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de emergencia del Owner."""
    query = update.callback_query
    if not query or not query.data:
        return

    clicker_id = query.from_user.id
    if clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("🛑 Acción bloqueada: No posee privilegios administrativos.", show_alert=True)
        return

    payload = EmergencyActionPayload.from_callback_data(query.data)
    action = payload.action

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

    elif action == "menu":
        await query.answer()
        text, reply_markup = _build_emergency_panel()
        try:
            await query.edit_message_text(text, parse_mode="HTML", reply_markup=reply_markup)
        except Exception:
            pass
        return

    elif action == "stop_prompt":
        await query.answer()
        prompt_text = (
            "⚠️ <b>CONFIRMACIÓN: DETENER SERVICIO</b>\n\n"
            "¿Está seguro de que desea detener el servicio <code>tg-admin-bot.service</code>?\n\n"
            "El bot dejará de responder inmediatamente a todos los usuarios y no ejecutará tareas programadas hasta que se inicie manualmente en el servidor mediante:\n"
            "<code>sudo systemctl start tg-admin-bot.service</code>"
        )
        keyboard = [
            [InlineKeyboardButton("🛑 SÍ, DETENER SERVICIO AHORA", callback_data="emergencia:stop_confirm")],
            [InlineKeyboardButton("⬅️ Volver al Panel", callback_data="emergencia:menu")]
        ]
        try:
            await query.edit_message_text(prompt_text, parse_mode="HTML", reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return

    elif action == "stop_confirm":
        await query.answer("🛑 Deteniendo servicio del bot...")
        try:
            await query.edit_message_text(
                "🛑 <b>Servicio Detenido por Emergencia</b>\n\n"
                "El bot ha recibido la orden de apagado del Administrador y se ha detenido correctamente.\n\n"
                "Para reactivarlo en sitio o vía SSH, ejecute en la consola:\n"
                "<code>sudo systemctl start tg-admin-bot.service</code>",
                parse_mode="HTML",
                reply_markup=None
            )
        except Exception:
            pass
        asyncio.create_task(_delayed_emergency_stop(actor_id=clicker_id))
        return

    elif action == "toggle_maint":
        cfg = load_config()
        current_maint = bool(cfg.get("maintenance_mode", False))
        new_maint = not current_maint
        cfg["maintenance_mode"] = new_maint
        save_config(cfg)

        status_msg = "Mantenimiento ACTIVADO (usuarios y grupos bloqueados)" if new_maint else "Operación NORMAL restablecida"
        await query.answer(f"✅ {status_msg}")

        text, reply_markup = _build_emergency_panel()
        try:
            await query.edit_message_text(text, parse_mode="HTML", reply_markup=reply_markup)
        except Exception:
            pass
        return

    elif action == "restore_prompt":
        await query.answer()
        prompt_text = (
            "⚠️ <b>CONFIRMACIÓN: RESTAURAR CONFIGURACIÓN</b>\n\n"
            "¿Desea restaurar los archivos de configuración desde la copia de seguridad segura?\n\n"
            "Se restaurará:\n"
            "• <code>MariaDB bot_settings (SSOT)</code>\n\n"
            "<i>Las credenciales protegidas y la identidad del servidor se mantendrán intactas.</i>"
        )
        keyboard = [
            [InlineKeyboardButton("🔄 SÍ, RESTAURAR AHORA", callback_data="emergencia:restore_confirm")],
            [InlineKeyboardButton("⬅️ Volver al Panel", callback_data="emergencia:menu")]
        ]
        try:
            await query.edit_message_text(prompt_text, parse_mode="HTML", reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return

    elif action == "restore_confirm":
        await query.answer("🔄 Restaurando configuración...")
        ok, msg = restore_golden_backup()
        if ok:
            res_text = (
                "✅ <b>Configuración Restaurada con Éxito</b>\n\n"
                f"{html.escape(msg)}\n\n"
                "Los archivos de configuración han sido restablecidos y recargados en memoria."
            )
        else:
            res_text = f"❌ <b>Error al Restaurar Configuración:</b>\n\n<code>{html.escape(msg)}</code>"
        keyboard = [[InlineKeyboardButton("⬅️ Volver al Panel", callback_data="emergencia:menu")]]
        try:
            await query.edit_message_text(res_text, parse_mode="HTML", reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return
