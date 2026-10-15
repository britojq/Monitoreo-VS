"""
# ==============================================================================
# 👑 HANDLERS DE ADMINISTRACIÓN Y CONTROL: admin_handlers.py
# Mantenimiento, Recursos, Térmica, Motor de IA, GitOps, Grupos, Permisos y Shield.
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
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
import socket
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Set, Tuple

from telegram import (
    ChatMember,
    ChatMemberUpdated,
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    Update,
)
from telegram.constants import ChatMemberStatus
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    ChatMemberHandler,
    CommandHandler,
    ContextTypes,
    ConversationHandler,
    MessageHandler,
    filters,
)

from bot_core.security.auth import (
    MSG_UNAUTHORIZED_ADMIN_COMMAND,
    MSG_UNAUTHORIZED_GROUP_COMMAND,
    check_authorization,
    get_audit_log_path,
    get_security_warning_html,
    is_authorized,
    require_private_chat,
)
from bot_core.services.config_manager import (
    BASE_DIR,
    CONFIG_PATH,
    ensure_golden_backup,
    load_config,
    restore_golden_backup,
    save_config,
)
from bot_core.services.connection_service import (
    ACTIVE_CONNECTION_LABEL,
    _check_endpoint_health,
    load_proxies_list,
)
from bot_core.utils.telegram_helpers import (
    safe_deliver_update_result,
    safe_reply_html,
)
from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    activate_hardware_first_boot,
    migrate_core_token,
)

logger = logging.getLogger("bot.handlers.admin")

MIGRAR_TOKEN_WAITING = 1


def _get_active_config() -> Dict[str, Any]:
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


async def _reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


# ==============================================================================
# 1. RESOLUCIÓN DE INFORMACIÓN DE USUARIOS Y GRUPOS
# ==============================================================================

async def _resolve_user_info(bot: Any, user_id: int) -> Tuple[str, str]:
    """Obtiene el nombre completo y @username de un usuario vía Telegram API o caché local."""
    cfg = _get_active_config()
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


async def _resolve_group_info(bot: Any, group_id: int) -> str:
    """Obtiene el título de un grupo vía Telegram API o caché local."""
    cfg = _get_active_config()
    groups_cache = cfg.get("groups_cache", {})
    cached_title = groups_cache.get(str(group_id), "")

    try:
        chat = await bot.get_chat(group_id)
        title = chat.title or cached_title or "Grupo"
        cfg.setdefault("groups_cache", {})[str(group_id)] = title
        return title
    except Exception:
        return cached_title or "Grupo"


def _get_known_group_candidates() -> Set[int]:
    """Recopila todos los IDs de grupos conocidos en config y logs de auditoría."""
    cfg = _get_active_config()
    candidates: Set[int] = set()
    for gid in cfg.get("allowed_group_ids", []):
        try:
            candidates.add(int(gid))
        except (ValueError, TypeError):
            pass

    for gid_str in cfg.get("groups_cache", {}).keys():
        try:
            gid = int(gid_str)
            if gid < 0:
                candidates.add(gid)
        except (ValueError, TypeError):
            pass

    audit_path = get_audit_log_path()
    if audit_path.exists():
        try:
            content = audit_path.read_text(encoding="utf-8", errors="ignore")
            found_ids = re.findall(r"ID:\s*(-100\d+|-\d{7,})", content)
            for fid in found_ids:
                try:
                    candidates.add(int(fid))
                except ValueError:
                    pass
        except Exception:
            pass

    return candidates


# ==============================================================================
# 2. LIMPIEZA Y RECURSOS DEL SISTEMA
# ==============================================================================

async def cmd_limpiador(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Diagnóstico de almacenamiento y limpieza interactiva del sistema (EXCLUSIVO OWNER en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id

    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Analizando almacenamiento, inodos y cachés del sistema... Por favor espere unos segundos.</i>",
        parse_mode='HTML'
    )

    try:
        from monitor.system_cleaner import build_cleaner_dashboard
        dashboard_text, keyboard = await build_cleaner_dashboard()

        try:
            await wait_msg.delete()
        except Exception:
            pass

        await update.message.reply_text(
            dashboard_text,
            parse_mode='HTML',
            reply_markup=keyboard
        )
    except Exception as e:
        logger.error(f"Error generando panel de limpieza: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error generando panel de limpieza:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await _reply(update.message, f"❌ Error: {e}")


async def handle_cleaner_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de limpieza."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    clicker_id = query.from_user.id

    if clicker_id != owner_id:
        await query.answer("⛔ Solo el creador del bot puede ejecutar acciones de limpieza del sistema.", show_alert=True)
        return

    try:
        action = query.data.split(":", 1)[1]
    except IndexError:
        await query.answer("⚠️ Solicitud inválida.")
        return

    from monitor.system_cleaner import build_cleaner_dashboard, execute_clean_task

    if action == "refresh":
        await query.answer("🔄 Actualizando diagnóstico del sistema...")
        dashboard_text, keyboard = await build_cleaner_dashboard()
        try:
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    await query.answer("⚙️ Ejecutando limpieza en el sistema...")
    result_banner = await execute_clean_task(action)
    dashboard_text, keyboard = await build_cleaner_dashboard()
    full_message = f"{result_banner}\n\n═══════════════════════════════\n\n{dashboard_text}"

    try:
        await query.edit_message_text(full_message, parse_mode='HTML', reply_markup=keyboard)
    except Exception:
        try:
            await query.message.reply_text(result_banner, parse_mode='HTML')
        except Exception:
            pass


async def cmd_recursos_sistema(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Diagnóstico integral de recursos (RAM, Swap, CPU) y optimización (EXCLUSIVO OWNER en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id

    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Analizando telemetría del servidor, memoria RAM, Swap y estado del sistema...</i>",
        parse_mode='HTML'
    )

    try:
        from monitor.system_optimizer import format_optimizer_dashboard_html
        dashboard_text, keyboard = await asyncio.to_thread(format_optimizer_dashboard_html)

        try:
            await wait_msg.delete()
        except Exception:
            pass

        await update.message.reply_text(
            dashboard_text,
            parse_mode='HTML',
            reply_markup=keyboard
        )
    except Exception as e:
        logger.error(f"Error generando panel de recursos: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error generando diagnóstico de recursos:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await _reply(update.message, f"❌ Error: {e}")


async def handle_optimizer_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de optimización de recursos."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    clicker_id = query.from_user.id

    if clicker_id != owner_id:
        await query.answer("⛔ Solo el creador del bot puede ejecutar acciones de optimización del sistema.", show_alert=True)
        return

    try:
        action = query.data.split(":", 1)[1]
    except IndexError:
        await query.answer("⚠️ Solicitud inválida.")
        return

    from monitor.system_optimizer import execute_optimize_plasma, format_optimizer_dashboard_html

    if action == "refresh":
        await query.answer("🔄 Actualizando diagnóstico de recursos...")
        dashboard_text, keyboard = await asyncio.to_thread(format_optimizer_dashboard_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    if action == "restart_plasma":
        await query.answer("🧹 Optimizando y liberando memoria...")
        try:
            await query.edit_message_text(
                "⏳ <b>Ejecutando optimización del entorno gráfico y memoria...</b>\n"
                "<i>Por favor espera unos segundos mientras se liberan recursos y se reanuda Plasma Shell...</i>",
                parse_mode='HTML'
            )
        except Exception:
            pass

        banner, _ = await asyncio.to_thread(execute_optimize_plasma)
        dashboard_text, keyboard = await asyncio.to_thread(format_optimizer_dashboard_html)
        full_message = f"{banner}\n\n═══════════════════════════════\n\n{dashboard_text}"

        try:
            await query.edit_message_text(full_message, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            try:
                await query.message.reply_text(full_message, parse_mode='HTML', reply_markup=keyboard)
            except Exception:
                pass


# ==============================================================================
# 3. DIAGNÓSTICO TÉRMICO Y GUARDIÁN CPU
# ==============================================================================

def format_thermal_dashboard_html() -> Tuple[str, InlineKeyboardMarkup]:
    """Genera el mensaje interactivo en formato HTML para Telegram con telemetría de CPU y guardián térmico."""
    from monitor.thermal_guard import (
        DEFAULT_TEMP_CRITICAL,
        DEFAULT_TEMP_RECOVERY,
        DEFAULT_TEMP_WARNING,
        get_cpu_temperatures,
        is_ai_service_active,
    )
    status = get_cpu_temperatures()
    hostname = platform.node()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    cfg = _get_active_config()
    ai_enabled_in_config = cfg.get("ollama_enabled", True)
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
        f"  • Motor Local de IA: {ai_state_str}\n"
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
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id

    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Consultando sensores térmicos y estado del guardián físico de CPU...</i>",
        parse_mode='HTML'
    )

    try:
        dashboard_text, keyboard = await asyncio.to_thread(format_thermal_dashboard_html)
        try:
            await wait_msg.delete()
        except Exception:
            pass
        await update.message.reply_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
    except Exception as e:
        logger.error(f"Error generando panel térmico: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error consultando sensores térmicos:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await _reply(update.message, f"❌ Error: {e}")


async def handle_thermal_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de telemetría térmica."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    clicker_id = query.from_user.id

    if clicker_id != owner_id:
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
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass


# ==============================================================================
# 4. GESTIÓN DEL MOTOR LOCAL DE IA
# ==============================================================================

def format_ai_control_panel_html() -> Tuple[str, InlineKeyboardMarkup]:
    """Genera el mensaje interactivo en formato HTML para Telegram para el control del motor local de IA."""
    from monitor.thermal_guard import is_ai_service_active
    hostname = platform.node()
    is_dev = (hostname.upper() == "CENCARATIT")

    cfg = _get_active_config()
    ai_enabled_in_config = bool(cfg.get("ollama_enabled", True))
    ai_service_running = is_ai_service_active()

    config_badge = "🟢 <b>ACTIVADO</b>" if ai_enabled_in_config else "🔴 <b>DESACTIVADO</b>"
    service_badge = "🟢 <b>ACTIVO (Running)</b>" if ai_service_running else "🔴 <b>DETENIDO (Inactive)</b>"
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    if is_dev:
        env_note = (
            "⚠️ <b>POLÍTICA DE SEGURIDAD EN DESARROLLO:</b>\n"
            "El motor local de IA se encuentra permanentemente <b>deshabilitado en este equipo</b> "
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
        "🧠 <b>CONTROL Y ADMINISTRACIÓN DEL MOTOR LOCAL DE IA</b>\n"
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
            InlineKeyboardButton("⚪ IA Restringida en Dev", callback_data="ia_act:dev_blocked")
        ])
    else:
        if ai_enabled_in_config and ai_service_running:
            buttons.append([
                InlineKeyboardButton("🛑 Desactivar Motor de IA", callback_data="ia_act:disable")
            ])
        else:
            buttons.append([
                InlineKeyboardButton("▶️ Activar Motor de IA", callback_data="ia_act:enable")
            ])

    buttons.append([
        InlineKeyboardButton("🔄 Actualizar", callback_data="ia_act:refresh"),
        InlineKeyboardButton("🌡️ Temperatura", callback_data="thermal:refresh"),
        InlineKeyboardButton("❌ Cerrar", callback_data="ia_act:close")
    ])

    return text, InlineKeyboardMarkup(buttons)


async def cmd_ia_control(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando interactivo para consultar, encender o apagar el motor local de IA (EXCLUSIVO OWNER en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id

    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    hostname = platform.node()
    is_dev = (hostname.upper() == "CENCARATIT")

    if context.args:
        subcmd = context.args[0].lower().strip()
        if subcmd in ("on", "activar", "start", "enable", "1", "si"):
            if is_dev:
                await _reply(
                    update.message,
                    "⚠️ <b>Acción Restringida:</b>\n"
                    "Por directriz estricta de seguridad del sistema, el motor local de IA permanece "
                    f"deshabilitado permanentemente en el servidor de desarrollo (<code>{html.escape(hostname)}</code>)."
                )
                return

            wait_m = await update.message.reply_text("⏳ <i>Iniciando servicio del motor local de IA y aplicando límites de hardware...</i>", parse_mode='HTML')
            cfg["ollama_enabled"] = True
            save_config()
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
                f"✅ <b>Motor Local de IA ACTIVADO exitosamente en {html.escape(hostname)}.</b>\n\n{dashboard_text}",
                parse_mode='HTML',
                reply_markup=keyboard
            )
            return

        elif subcmd in ("off", "desactivar", "stop", "disable", "0", "no"):
            wait_m = await update.message.reply_text("⏳ <i>Deteniendo servicio del motor local de IA y liberando memoria RAM...</i>", parse_mode='HTML')
            cfg["ollama_enabled"] = False
            save_config()
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
                f"🛑 <b>Motor Local de IA DESACTIVADO exitosamente en {html.escape(hostname)}.</b>\n\n{dashboard_text}",
                parse_mode='HTML',
                reply_markup=keyboard
            )
            return

    dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
    await _reply(update.message, dashboard_text, reply_markup=keyboard)


async def handle_ia_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de control del motor local de IA."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    clicker_id = query.from_user.id

    if clicker_id != owner_id:
        await query.answer("⛔ Solo el creador del bot puede controlar el motor local de IA.", show_alert=True)
        return

    try:
        action = query.data.split(":", 1)[1]
    except IndexError:
        await query.answer("⚠️ Solicitud inválida.")
        return

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
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    if action == "enable":
        if is_dev:
            await query.answer("⚠️ Bloqueado: No permitido en servidor de desarrollo.", show_alert=True)
            return
        await query.answer("🚀 Iniciando motor local de IA...")
        cfg["ollama_enabled"] = True
        save_config()
        try:
            from monitor.thermal_guard import start_ai_service
            await asyncio.to_thread(start_ai_service)
        except Exception as e:
            logger.error(f"Error iniciando servicio IA en callback: {e}")
        dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    if action == "disable":
        await query.answer("🛑 Deteniendo motor local de IA...")
        cfg["ollama_enabled"] = False
        save_config()
        try:
            from monitor.thermal_guard import stop_ai_service
            await asyncio.to_thread(stop_ai_service)
        except Exception as e:
            logger.error(f"Error deteniendo servicio IA en callback: {e}")
        dashboard_text, keyboard = await asyncio.to_thread(format_ai_control_panel_html)
        try:
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return


# ==============================================================================
# 5. COMUNICADOS MASIVOS Y DIFUSIÓN (BROADCAST)
# ==============================================================================

async def cmd_broadcast_mensaje(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Envío de mensajes tipo Broadcast (difusión en privado exclusivo Owner)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id

    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    raw_text = update.message.text or ""
    parts = raw_text.split(maxsplit=1)
    if len(parts) < 2 or not parts[1].strip():
        guide_text = (
            "📢 <b>Panel de Difusión Masiva (Broadcast)</b>\n\n"
            "<b>Uso del comando:</b>\n"
            "<code>/mensaje &lt;texto del comunicado&gt;</code>\n\n"
            "<b>Ejemplo:</b>\n"
            "<code>/mensaje Estimado equipo, el día de hoy a las 15:00 se realizará mantenimiento preventivo.</code>\n\n"
            "<i>El mensaje será enviado por el bot a todos los grupos autorizados y usuarios con acceso al sistema.</i>"
        )
        await _reply(update.message, guide_text)
        return

    broadcast_content = parts[1].strip()
    if (broadcast_content.startswith('"') and broadcast_content.endswith('"')) or \
       (broadcast_content.startswith("'") and broadcast_content.endswith("'")):
        broadcast_content = broadcast_content[1:-1].strip()

    now_str = datetime.now().strftime("%d/%m/%Y %H:%M")
    formatted_announcement = (
        "📢 <b>COMUNICADO OFICIAL</b>\n\n"
        f"{html.escape(broadcast_content)}\n\n"
        f"🏛️ <i>Mensaje emitido por la Administración • {now_str}</i>"
    )

    allowed_users = [uid for uid in cfg.get("allowed_user_ids", []) if uid != owner_id]
    allowed_groups = cfg.get("allowed_group_ids", [])
    total_destinatarios = len(allowed_users) + len(allowed_groups)

    if total_destinatarios == 0:
        await _reply(update.message, "⚠️ No hay usuarios ni grupos adicionales en la lista de autorizados para enviar la difusión.")
        return

    wait_msg = await update.message.reply_text(
        f"⏳ <i>Iniciando difusión masiva hacia {total_destinatarios} destino(s)... Por favor espere.</i>",
        parse_mode='HTML'
    )

    success_count = 0
    fail_count = 0
    delivery_details = []

    for gid in allowed_groups:
        g_title = await _resolve_group_info(context.bot, gid)
        try:
            await context.bot.send_message(chat_id=gid, text=formatted_announcement, parse_mode='HTML')
            success_count += 1
            delivery_details.append(f"• 🟢 <b>Grupo:</b> {html.escape(g_title)} (<code>{gid}</code>) → <i>Entregado</i>")
        except Exception as e:
            fail_count += 1
            err_msg = "Bloqueado o expulsado" if "forbidden" in str(e).lower() else "Error de envío"
            delivery_details.append(f"• 🔴 <b>Grupo:</b> {html.escape(g_title)} (<code>{gid}</code>) → <i>{err_msg}</i>")
            logger.warning(f"Fallo al enviar broadcast a grupo {gid}: {e}")

    for uid in allowed_users:
        u_name, u_uname = await _resolve_user_info(context.bot, uid)
        u_label = f"{u_name} ({u_uname})" if u_uname else u_name
        try:
            await context.bot.send_message(chat_id=uid, text=formatted_announcement, parse_mode='HTML')
            success_count += 1
            delivery_details.append(f"• 🟢 <b>Usuario:</b> {html.escape(u_label)} (<code>{uid}</code>) → <i>Entregado</i>")
        except Exception as e:
            fail_count += 1
            err_msg = "Bot bloqueado por el usuario" if "forbidden" in str(e).lower() else "Chat no iniciado / Inaccesible"
            delivery_details.append(f"• 🔴 <b>Usuario:</b> {html.escape(u_label)} (<code>{uid}</code>) → <i>{err_msg}</i>")
            logger.warning(f"Fallo al enviar broadcast a usuario {uid}: {e}")

    try:
        await wait_msg.delete()
    except Exception:
        pass

    report_lines = [
        "📊 <b>Reporte de Difusión Masiva (Broadcast)</b>",
        "",
        f"✅ <b>Envíos exitosos:</b> {success_count}",
        f"❌ <b>Envíos fallidos:</b> {fail_count}",
        f"👥 <b>Total destinos:</b> {total_destinatarios}",
        "",
        "📋 <b>Detalle de Entrega:</b>",
        *delivery_details,
        "",
        "<i>El comunicado fue emitido y firmado por el Bot con éxito.</i>"
    ]
    await update.message.reply_text("\n".join(report_lines), parse_mode='HTML')


async def cmd_info(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el aviso legal, términos de confidencialidad y advertencia de seguridad."""
    if not update.effective_user or not update.message:
        return
    aviso_legal = get_security_warning_html()
    await _reply(update.message, aviso_legal)


# ==============================================================================
# 6. GITOPS, ACTUALIZACIÓN Y ROLLBACK
# ==============================================================================

async def cmd_actualizar(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Verifica y aplica actualizaciones desde Git (Exclusivo Owner en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    from monitor.system_updater import build_update_dashboard, execute_git_update

    if context.args:
        arg = context.args[0].lower()
        if arg in ("now", "apply", "aplicar", "force", "instalar", "si"):
            wait_msg = await update.message.reply_text(
                "⏳ <b>[1/5] Iniciando despliegue de actualización...</b>",
                parse_mode='HTML'
            )

            async def progress_hook(status_text: str) -> None:
                try:
                    await wait_msg.edit_text(status_text, parse_mode='HTML')
                except Exception as e_hook:
                    logger.debug(f"Aviso actualizando wait_msg en cmd_actualizar: {e_hook}")

            try:
                res = await execute_git_update(
                    bot_instance=context.bot,
                    auto_restart=False,
                    progress_callback=progress_hook
                )
            except Exception as e_exec:
                logger.error(f"Error imprevisto ejecutando git update: {e_exec}", exc_info=True)
                res = f"❌ <b>Error inesperado durante la ejecución de la actualización:</b>\n<pre>{html.escape(str(e_exec))}</pre>"

            await safe_deliver_update_result(update.message, res, edit_msg=wait_msg)
            from monitor.system_updater import restart_service_delayed
            asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
            return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Comprobando repositorio remoto oficial en GitHub (origin/master)...</i>",
        parse_mode='HTML'
    )
    dashboard_text, keyboard = await build_update_dashboard(bot_instance=context.bot)
    try:
        await wait_msg.delete()
    except Exception:
        pass

    await _reply(update.message, dashboard_text, reply_markup=keyboard)


async def handle_update_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones de actualización del sistema presionadas por el Owner."""
    query = update.callback_query
    if not query or not query.data:
        return

    clicker_id = query.from_user.id
    if clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("⛔ Solo el creador y administrador del bot puede gestionar actualizaciones.", show_alert=True)
        return

    from monitor.system_updater import (
        build_deployment_dashboard,
        build_update_dashboard,
        clear_update_lock,
        execute_git_update,
        execute_rollback,
        restart_service_delayed,
    )

    action = query.data.replace("update_act:", "")

    if action in ("check", "refresh"):
        await query.answer("🔄 Verificando repositorio...")
        dashboard_text, keyboard = await build_update_dashboard(bot_instance=context.bot)
        try:
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    elif action in ("apply", "force"):
        await query.answer("🚀 Aplicando actualización...")
        try:
            await query.edit_message_text(
                "⏳ <b>[1/5] Iniciando despliegue de actualización...</b>\n\n"
                "• Evaluando conectividad y proxy...\n"
                "• Respaldando archivos en <code>config/</code>...\n"
                "• Generando respaldo comprimido de base de datos MariaDB...",
                parse_mode='HTML'
            )
        except Exception:
            pass

        async def progress_hook(status_text: str) -> None:
            try:
                await query.edit_message_text(status_text, parse_mode='HTML')
            except Exception as e_hook:
                logger.debug(f"Aviso actualizando query en handle_update_callback: {e_hook}")

        try:
            res = await execute_git_update(
                bot_instance=context.bot,
                auto_restart=False,
                progress_callback=progress_hook
            )
        except Exception as e_exec:
            logger.error(f"Error imprevisto ejecutando git update: {e_exec}", exc_info=True)
            res = f"❌ <b>Error inesperado durante la ejecución de la actualización:</b>\n<pre>{html.escape(str(e_exec))}</pre>"

        await safe_deliver_update_result(query, res)
        asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
        return

    elif action == "status":
        await query.answer("📊 Consultando estado de despliegue...")
        text, keyboard = await build_deployment_dashboard()
        try:
            await query.edit_message_text(text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    elif action == "unlock":
        await query.answer("🔓 Desbloqueando Circuit Breaker...")
        clear_update_lock()
        text, keyboard = await build_deployment_dashboard()
        try:
            await query.edit_message_text("✅ <b>Circuit Breaker liberado.</b>\n\n" + text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    elif action == "rollback_prompt":
        await query.answer()
        keyboard = [
            [
                InlineKeyboardButton("⚠️ Confirmar Reversión Inmediata", callback_data="update_act:rollback_confirm"),
                InlineKeyboardButton("❌ Cancelar", callback_data="update_act:status")
            ]
        ]
        prompt_text = (
            "⚠️ <b>¿Confirma que desea revertir el sistema a la versión anterior?</b>\n\n"
            "• Repositorio Git volverá a <code>HEAD@{1}</code>.\n"
            "• MariaDB restaurará el último snapshot comprimido.\n"
            "• El portal web se sincronizará y se purgará la caché.\n"
            "• El bot se reiniciará automáticamente."
        )
        try:
            await query.edit_message_text(prompt_text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return

    elif action == "rollback_confirm":
        await query.answer("🔄 Ejecutando Rollback...")
        try:
            await query.edit_message_text(
                "⏳ <b>[1/3] Ejecutando Rollback seguro del sistema...</b>\n\n"
                "• Revirtiendo repositorio Git a commit anterior...\n"
                "• Restaurando snapshot de base de datos MariaDB...",
                parse_mode='HTML'
            )
        except Exception:
            pass

        async def progress_hook_rb(status_text: str) -> None:
            try:
                await query.edit_message_text(status_text, parse_mode='HTML')
            except Exception as e_hook:
                logger.debug(f"Aviso actualizando query rollback: {e_hook}")

        try:
            res = await execute_rollback(
                bot_instance=context.bot,
                auto_restart=False,
                progress_callback=progress_hook_rb
            )
        except Exception as e_rb:
            logger.error(f"Error imprevisto ejecutando rollback: {e_rb}", exc_info=True)
            res = f"❌ <b>Error inesperado durante el rollback:</b>\n<pre>{html.escape(str(e_rb))}</pre>"

        await safe_deliver_update_result(query, res)
        asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
        return

    elif action == "get_audit_log":
        await query.answer("📋 Obteniendo log de auditoría...")
        audit_file = BASE_DIR / "logs" / "last_deploy_audit.log"
        if audit_file.exists():
            with open(audit_file, "rb") as f_doc:
                await context.bot.send_document(
                    chat_id=clicker_id,
                    document=f_doc,
                    filename="last_deploy_audit.log",
                    caption="📋 <b>Registro de Auditoría del Último Despliegue GitOps</b>",
                    parse_mode='HTML'
                )
        else:
            await query.message.reply_text("ℹ️ No se ha encontrado un registro de despliegue reciente.")
        return


async def cmd_rollback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Revierte actualizaciones del sistema de forma segura (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id and user_id != IMMUTABLE_OWNER_ID:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    from monitor.system_updater import execute_rollback, restart_service_delayed

    target_commit = context.args[0] if context.args else None

    if target_commit and target_commit.lower() in ("si", "now", "confirm", "forzar"):
        wait_msg = await update.message.reply_text(
            "⏳ <b>[1/3] Ejecutando Rollback seguro del sistema...</b>",
            parse_mode='HTML'
        )

        async def progress_hook_rb(status_text: str) -> None:
            try:
                await wait_msg.edit_text(status_text, parse_mode='HTML')
            except Exception as e_hook:
                logger.debug(f"Aviso actualizando wait_msg en rollback: {e_hook}")

        try:
            res = await execute_rollback(
                bot_instance=context.bot,
                auto_restart=False,
                progress_callback=progress_hook_rb
            )
        except Exception as e_rb:
            logger.error(f"Error imprevisto ejecutando rollback: {e_rb}", exc_info=True)
            res = f"❌ <b>Error inesperado durante el rollback:</b>\n<pre>{html.escape(str(e_rb))}</pre>"

        await safe_deliver_update_result(update.message, res, edit_msg=wait_msg)
        asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
        return

    keyboard = [
        [
            InlineKeyboardButton("⚠️ Sí, Revertir Sistema Inmediatamente", callback_data="update_act:rollback_confirm"),
            InlineKeyboardButton("❌ Cancelar", callback_data="update_act:status")
        ]
    ]
    prompt_text = (
        "⚠️ <b>Confirmación de Reversión del Sistema (Rollback)</b>\n\n"
        "Esta acción restaurará el sistema a la versión anterior:\n"
        "• 📦 Reversión del repositorio Git a <code>HEAD@{1}</code>.\n"
        "• 💾 Restauración del último snapshot atómico de MariaDB.\n"
        "• 🌐 Sincronización del portal web y purga de cachés de Laravel.\n"
        "• 🔓 Desbloqueo automático del Circuit Breaker.\n\n"
        "<b>¿Desea proceder con la reversión?</b>"
    )
    await _reply(update.message, prompt_text, reply_markup=InlineKeyboardMarkup(keyboard))


async def cmd_estado_deploy(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el estado técnico de GitOps, conectividad, Circuit Breaker y respaldos."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id and user_id != IMMUTABLE_OWNER_ID:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    from monitor.system_updater import build_deployment_dashboard

    wait_msg = await update.message.reply_text(
        "⏳ <i>Consultando estado técnico del pipeline y enlaces de red...</i>",
        parse_mode='HTML'
    )
    text, keyboard = await build_deployment_dashboard()
    try:
        await wait_msg.delete()
    except Exception:
        pass

    await _reply(update.message, text, reply_markup=keyboard)


async def cmd_desbloquear_update(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Libera el Circuit Breaker de actualización (.update_lock)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id and user_id != IMMUTABLE_OWNER_ID:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    from monitor.system_updater import clear_update_lock, is_update_locked
    locked, _ = is_update_locked()
    clear_update_lock()

    if locked:
        msg = (
            "🔓 <b>Circuit Breaker Liberado Exitosamente</b>\n\n"
            "Se ha retirado la protección de bloqueo de despliegue.\n"
            "El sistema está listo nuevamente para ejecutar <code>/actualizar</code>."
        )
    else:
        msg = (
            "ℹ️ <b>Circuit Breaker Despejado</b>\n\n"
            "El sistema no se encontraba bloqueado. Puede ejecutar <code>/actualizar</code> con normalidad."
        )
    await _reply(update.message, msg)


async def cmd_reiniciar_bot(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reinicia el servicio systemd del bot de forma segura (Exclusivo Owner)."""
    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    await update.message.reply_text(
        "⚡ <b>Reiniciando servicio del bot en segundo plano...</b>\n<i>Estará de vuelta en breves segundos.</i>",
        parse_mode='HTML'
    )
    from monitor.system_updater import restart_service_delayed
    asyncio.create_task(restart_service_delayed(delay_seconds=1.5))


async def cmd_log_actualizacion(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Envía el archivo de auditoría del último despliegue al Owner (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id and user_id != IMMUTABLE_OWNER_ID:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    audit_file = BASE_DIR / "logs" / "last_deploy_audit.log"
    if audit_file.exists():
        with open(audit_file, "rb") as f_doc:
            await context.bot.send_document(
                chat_id=user_id,
                document=f_doc,
                filename="last_deploy_audit.log",
                caption="📋 <b>Registro de Auditoría del Último Despliegue GitOps</b>",
                parse_mode='HTML'
            )
    else:
        await _reply(update.message, "ℹ️ No se ha encontrado un registro de despliegue reciente.")


# ==============================================================================
# 7. PANEL DE CONTROL DE EMERGENCIA Y REINICIO
# ==============================================================================

def _build_emergency_panel() -> Tuple[str, InlineKeyboardMarkup]:
    """Construye el texto y botones interactivos para el panel de control de emergencia."""
    cfg = _get_active_config()
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


async def _delayed_emergency_stop() -> None:
    """Espera 1.5 segundos para permitir el envío del mensaje de confirmación y detiene el servicio."""
    await asyncio.sleep(1.5)
    try:
        proc = await asyncio.create_subprocess_exec("sudo", "systemctl", "stop", "tg-admin-bot.service")
        await proc.communicate()
    except Exception as e:
        logger.error(f"Error al detener servicio por emergencia: {e}")


async def cmd_emergencia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el panel interactivo de emergencia exclusivo para el Owner en chat privado."""
    if not await require_private_chat(update, context):
        return

    if not await check_authorization(update, context):
        return

    user = update.effective_user
    if not user or user.id != IMMUTABLE_OWNER_ID:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    text, reply_markup = _build_emergency_panel()
    await _reply(update.message, text, reply_markup=reply_markup)


async def cmd_reinicia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Ejecuta el reinicio completo del sistema operativo (sudo reboot, Exclusivo Owner en privado)."""
    user = update.effective_user
    if not user or user.id != IMMUTABLE_OWNER_ID:
        logger.warning(f"Intento NO AUTORIZADO de ejecutar /reinicia por usuario: {user.id if user else 'desconocido'}")
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    logger.critical(f"🛑 INSTRUCCIÓN DE REINICIO DEL SERVIDOR (/reinicia) RECIBIDA POR EL OWNER (ID: {user.id})")

    if update.message and update.effective_chat and update.effective_chat.type in ("group", "supergroup"):
        try:
            await update.message.delete()
        except Exception:
            pass

    msg_owner = (
        "🔄 <b>REINICIO DEL SERVIDOR EN PROGRESO</b>\n"
        "━━━━━━━━━━━━\n"
        "⚠️ <b>Instrucción recibida:</b> <code>/reinicia</code>\n\n"
        "💾 <i>Sincronizando buffers de disco (sync)...</i>\n"
        "⏳ <i>Ejecutando reinicio del sistema (<code>sudo reboot</code>) en 2 segundos...</i>\n"
        "━━━━━━━━━━━━\n"
        "👋 <i>El bot y todos los servicios se reactivarán automáticamente tras el arranque.</i>"
    )

    try:
        await context.bot.send_message(chat_id=IMMUTABLE_OWNER_ID, text=msg_owner, parse_mode='HTML')
    except Exception as e_send:
        logger.error(f"Error enviando notificación de reinicio al Owner: {e_send}")
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            try:
                await _reply(update.message, msg_owner)
            except Exception:
                pass

    async def _execute_system_reboot() -> None:
        await asyncio.sleep(2.0)
        try:
            subprocess.run(["sync"], check=False)
            subprocess.run(["sudo", "reboot"], check=False)
        except Exception as err:
            logger.error(f"Error al ejecutar sudo reboot: {err}")

    asyncio.create_task(_execute_system_reboot())


async def handle_emergency_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de emergencia del Owner."""
    query = update.callback_query
    if not query or not query.data:
        return

    clicker_id = query.from_user.id
    if clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("🛑 Acción bloqueada: No posee privilegios administrativos.", show_alert=True)
        return

    action = query.data.replace("emergencia:", "")

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
            await query.edit_message_text(text, parse_mode='HTML', reply_markup=reply_markup)
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
            await query.edit_message_text(prompt_text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(keyboard))
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
                parse_mode='HTML',
                reply_markup=None
            )
        except Exception:
            pass
        asyncio.create_task(_delayed_emergency_stop())
        return

    elif action == "toggle_maint":
        cfg = _get_active_config()
        current_maint = bool(cfg.get("maintenance_mode", False))
        new_maint = not current_maint
        cfg["maintenance_mode"] = new_maint
        save_config()

        status_msg = "Mantenimiento ACTIVADO (usuarios y grupos bloqueados)" if new_maint else "Operación NORMAL restablecida"
        await query.answer(f"✅ {status_msg}")

        text, reply_markup = _build_emergency_panel()
        try:
            await query.edit_message_text(text, parse_mode='HTML', reply_markup=reply_markup)
        except Exception:
            pass
        return

    elif action == "restore_prompt":
        await query.answer()
        prompt_text = (
            "⚠️ <b>CONFIRMACIÓN: RESTAURAR CONFIGURACIÓN</b>\n\n"
            "¿Desea restaurar los archivos de configuración desde la copia de seguridad segura?\n\n"
            "Se restaurará:\n"
            "• <code>config/config.json</code>\n\n"
            "<i>Las credenciales protegidas y la identidad del servidor se mantendrán intactas.</i>"
        )
        keyboard = [
            [InlineKeyboardButton("🔄 SÍ, RESTAURAR AHORA", callback_data="emergencia:restore_confirm")],
            [InlineKeyboardButton("⬅️ Volver al Panel", callback_data="emergencia:menu")]
        ]
        try:
            await query.edit_message_text(prompt_text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(keyboard))
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
            await query.edit_message_text(res_text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return


# ==============================================================================
# 8. SENTINEL SHIELD Y MITIGACIÓN DE TERMINALES
# ==============================================================================

async def cmd_lockdown(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Activa el modo LOCKDOWN de contención perimetral inmediata (Exclusivo Administrador)."""
    cfg = _get_active_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed_admins = set(cfg.get("allowed_user_ids", [])) | {IMMUTABLE_OWNER_ID, owner_id}

    if user_id not in allowed_admins:
        if update.message:
            await update.message.reply_text("⛔ Acceso denegado: Comando exclusivo del Administrador.")
        return

    from monitor.terminal_shield import execute_lockdown
    execute_lockdown(target_user=update.effective_user.username or "admin")
    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    msg = (
        "🚨🚨 <b>MODO LOCKDOWN ACTIVADO</b> 🚨🚨\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"⏰ <b>Hora:</b> <code>{now_str}</code>\n\n"
        "🛡️ <b>Medidas de contención aplicadas de inmediato:</b>\n"
        " • Todas las consolas y emuladores (Konsole, xterm) han sido terminados.\n"
        " • Pantalla y sesiones gráficas bloqueadas.\n"
        " • Servidor VNC detenido temporalmente.\n"
        " • Ejecución de comandos interactivos y sudo restringidos.\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "<i>Para restaurar la operación normal use el botón inferior o /unlock</i>"
    )
    keyboard = [[InlineKeyboardButton("🔓 DESACTIVAR LOCKDOWN", callback_data=f"shield_unlock:{hostname}")]]
    if update.message:
        await _reply(update.message, msg, reply_markup=InlineKeyboardMarkup(keyboard))


async def cmd_unlock(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Desactiva el modo LOCKDOWN y restaura los servicios interactivos."""
    cfg = _get_active_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed_admins = set(cfg.get("allowed_user_ids", [])) | {IMMUTABLE_OWNER_ID, owner_id}

    if user_id not in allowed_admins:
        if update.message:
            await update.message.reply_text("⛔ Acceso denegado: Comando exclusivo del Administrador.")
        return

    from monitor.terminal_shield import execute_unlock
    execute_unlock()
    hostname = socket.gethostname()

    msg = (
        "✅ <b>MODO LOCKDOWN DESACTIVADO</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n\n"
        "Operación normal restablecida:\n"
        " • Servicio VNC reactivado.\n"
        " • Acceso administrativo y terminales desbloqueados.\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )
    if update.message:
        await _reply(update.message, msg)


async def cmd_autorizar_consola(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Habilita una ventana de tiempo autorizada para que el Administrador use terminales."""
    cfg = _get_active_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed_admins = set(cfg.get("allowed_user_ids", [])) | {IMMUTABLE_OWNER_ID, owner_id}

    if user_id not in allowed_admins:
        if update.message:
            await update.message.reply_text("⛔ Acceso denegado: Comando exclusivo del Administrador.")
        return

    minutes = 30
    if context.args:
        try:
            m = int(context.args[0])
            if 1 <= m <= 480:
                minutes = m
        except ValueError:
            pass

    from monitor.terminal_shield import execute_auth
    res = execute_auth(minutes)
    hostname = socket.gethostname()

    msg = (
        "🛡️ <b>VENTANA DE MANTENIMIENTO AUTORIZADA</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"⏱️ <b>Duración:</b> <code>{minutes} minutos</code>\n"
        f"⏰ <b>Válido hasta:</b> <code>{res.get('expiry', 'N/A')}</code>\n\n"
        "El Administrador puede abrir Konsole, terminales interactivas y ejecutar comandos sudo "
        "sin que se disparen alertas ni bloqueos preventivos.\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )
    if update.message:
        await _reply(update.message, msg)


async def handle_shield_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas de mitigación del Sentinel Shield."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = _get_active_config()
    clicker_id = query.from_user.id
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed_admins = set(cfg.get("allowed_user_ids", [])) | {IMMUTABLE_OWNER_ID, owner_id}

    if clicker_id not in allowed_admins:
        await query.answer("🛑 Acción bloqueada: No posee privilegios administrativos.", show_alert=True)
        return

    try:
        from monitor.terminal_shield import execute_auth, execute_kill, execute_lockdown, execute_unlock

        data_parts = query.data.split(":")
        action_prefix = data_parts[0]
        target_server = data_parts[1] if len(data_parts) > 1 else ""
        target_pid = int(data_parts[2]) if len(data_parts) > 2 and data_parts[2].isdigit() else 0
        target_ppid = int(data_parts[3]) if len(data_parts) > 3 and data_parts[3].isdigit() else 0
        hostname = socket.gethostname()

        if action_prefix == "shield_kill":
            await query.answer("🚫 Neutralizando terminal...")
            res = execute_kill(target_pid, target_ppid)
            killed_str = ", ".join(res.get("killed", [])) or f"PID {target_pid}"
            infractions = res.get("infractions", 1)
            reboot_triggered = res.get("reboot_triggered", False)

            if reboot_triggered:
                text = (
                    "🛑 <b>REINCIDENCIA MÁXIMA ALCANZADA (3/3)</b>\n"
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server or hostname)}</code>\n"
                    f"⚡ <b>Procesos Eliminados:</b> <code>{html.escape(killed_str)}</code>\n"
                    f"⚠️ <b>Infracciones Acumuladas:</b> <code>3 de 3</code>\n"
                    f"👤 <b>Acción ejecutada por:</b> Administrador (@{html.escape(query.from_user.username or 'admin')})\n"
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    "🚨 <i>Se ha activado el protocolo de reinicio por reincidencia.</i>"
                )
            else:
                text = (
                    "🚫 <b>TERMINAL NEUTRALIZADA EXITOSAMENTE</b>\n"
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server or hostname)}</code>\n"
                    f"⚡ <b>Procesos Eliminados:</b> <code>{html.escape(killed_str)}</code>\n"
                    f"⚠️ <b>Infracción Registrada:</b> <code>{infractions} de 3</code>\n"
                    "📺 <b>Acción en Pantalla:</b> Advertencia de 10s desplegada al operador.\n"
                    f"👤 <b>Acción ejecutada por:</b> Administrador (@{html.escape(query.from_user.username or 'admin')})\n"
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    "<i>Al registrarse el 3er intento no autorizado, el equipo se reiniciará automáticamente.</i>"
                )
            try:
                await query.edit_message_text(text, parse_mode='HTML', reply_markup=None)
            except Exception:
                pass

        elif action_prefix == "shield_lockdown":
            await query.answer("🔒 ACTIVANDO REINICIO DE SEGURIDAD...", show_alert=True)
            execute_lockdown(target_pid, target_ppid)
            text = (
                "🚨🚨 <b>REINICIO DE CONTENCIÓN ACTIVADO</b> 🚨🚨\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server or hostname)}</code>\n"
                f"⏰ <b>Hora:</b> <code>{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</code>\n\n"
                "🛡️ <b>Medidas de contención activadas:</b>\n"
                f" • Terminal interactiva terminada (PID: {target_pid}).\n"
                " • Todas las instancias de emuladores cerradas.\n"
                " • Reinicio automático del equipo en ejecución para neutralizar la intrusión.\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━"
            )
            try:
                await query.edit_message_text(text, parse_mode='HTML', reply_markup=None)
            except Exception:
                pass

        elif action_prefix == "shield_unlock":
            await query.answer("🔓 Desactivando estado de emergencia...")
            execute_unlock()
            text = (
                "✅ <b>SISTEMA RESTAURADO A LA NORMALIDAD</b>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server or hostname)}</code>\n\n"
                "El estado de emergencia ha sido levantado:\n"
                " • Infracciones acumuladas reseteadas a 0.\n"
                " • Permisos interactivos reactivados.\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━"
            )
            try:
                await query.edit_message_text(text, parse_mode='HTML', reply_markup=None)
            except Exception:
                pass

        elif action_prefix == "shield_auth":
            await query.answer("✅ Sesión autorizada por 30 minutos.")
            res = execute_auth(30)
            text = (
                "✅ <b>SESIÓN AUTORIZADA POR EL ADMINISTRADOR</b>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server or hostname)}</code>\n"
                f"🆔 <b>Terminal:</b> PID <code>{target_pid}</code>\n"
                f"⏱️ <b>Ventana de Trabajo:</b> <code>30 minutos</code> (hasta {res.get('expiry', 'N/A')})\n"
                "🛡️ <b>Infracciones:</b> Contador reseteado a 0.\n\n"
                "<i>Puede continuar utilizando la terminal sin interrupciones.</i>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━"
            )
            try:
                await query.edit_message_text(text, parse_mode='HTML', reply_markup=None)
            except Exception:
                pass

    except Exception as e_cb:
        logger.error(f"Error procesando shield callback: {e_cb}", exc_info=True)
        try:
            await query.answer(f"⚠️ Error ejecutando acción: {e_cb}", show_alert=True)
        except Exception:
            pass


# ==============================================================================
# 9. CONTROL DE ENVÍOS PROGRAMADOS POR CRON
# ==============================================================================

def _format_cron_panel_text() -> str:
    """Genera el texto informativo del panel de control de cron."""
    cfg = _get_active_config()
    is_enabled = bool(cfg.get("cron_reports_enabled", True))
    schedules = cfg.get("cron_schedules", ["07:30", "16:00"])
    if not isinstance(schedules, list):
        schedules = ["07:30", "16:00"]
    schedules = sorted(list(set(schedules)))

    updated_at = cfg.get("cron_reports_updated_at", "N/A")
    updated_by = cfg.get("cron_reports_updated_by", "N/A")

    now = datetime.now()
    now_hm = now.strftime("%H:%M")
    next_sch = None
    next_day = "hoy"
    for s in schedules:
        if s > now_hm:
            next_sch = s
            break
    if not next_sch and schedules:
        next_sch = schedules[0]
        next_day = "mañana"

    estado_label = "🟢 <b>ACTIVADOS (Operando)</b>" if is_enabled else "🔴 <b>PAUSADOS (Silenciados)</b>"
    horarios_str = ", ".join([f"<code>{h}</code>" for h in schedules]) if schedules else "<i>Ninguno</i>"
    proximo_str = f"<code>{next_sch}</code> ({next_day})" if (next_sch and is_enabled) else ("<i>En pausa</i>" if not is_enabled else "<i>Sin programar</i>")

    return (
        "⏰ <b>Control de Envíos Programados por Cron</b>\n\n"
        f"• <b>Estado:</b> {estado_label}\n"
        f"• <b>Horarios Diarios:</b> {horarios_str}\n"
        f"• <b>Próximo Envío:</b> {proximo_str}\n\n"
        f"👤 <b>Último Cambio:</b> {html.escape(str(updated_by))}\n"
        f"📅 <b>Fecha:</b> <code>{updated_at}</code>\n\n"
        "<b>Opciones de gestión por comando:</b>\n"
        "• <code>/cron on</code> o <code>/cron off</code> (Activar / Pausar)\n"
        "• <code>/cron horario 07:30, 16:00</code> (Asignar horarios)\n"
        "• <code>/cron agregar 12:00</code> (Añadir un horario)\n"
        "• <code>/cron quitar 16:00</code> (Eliminar un horario)"
    )


def _get_cron_keyboard() -> InlineKeyboardMarkup:
    """Genera el teclado interactivo con botones inline para el control de cron."""
    cfg = _get_active_config()
    is_enabled = bool(cfg.get("cron_reports_enabled", True))
    btn_toggle = (
        InlineKeyboardButton("🔴 Pausar Envíos Automáticos", callback_data="cron_act:pause")
        if is_enabled else
        InlineKeyboardButton("🟢 Reanudar Envíos Automáticos", callback_data="cron_act:resume")
    )
    return InlineKeyboardMarkup([
        [btn_toggle],
        [InlineKeyboardButton("🔄 Actualizar Estado", callback_data="cron_act:refresh")]
    ])


async def cmd_cron_control(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Gestiona la activación, pausa y horarios de los reportes programados por cron."""
    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    user_id = update.effective_user.id
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_owner = (user_id == owner_id)
    is_private = (update.effective_chat and update.effective_chat.type == "private")

    if not is_owner or not is_private:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = f"{update.effective_user.full_name} [Telegram]"

    if context.args:
        subcmd = context.args[0].lower()
        if subcmd in ("on", "activar", "activado", "enable", "start", "1", "si"):
            cfg["cron_reports_enabled"] = True
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config()
            await _reply(
                update.message,
                "🟢 <b>Envíos Programados: ACTIVADOS</b>\n\n"
                "Los reportes de monitoreo se despacharán puntualmente en los horarios configurados."
            )
            return

        elif subcmd in ("off", "desactivar", "desactivado", "pausar", "pause", "stop", "0", "no"):
            cfg["cron_reports_enabled"] = False
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config()
            await _reply(
                update.message,
                "🔴 <b>Envíos Programados: PAUSADOS</b>\n\n"
                "Se han silenciado los reportes automáticos por cron hacia el grupo corporativo."
            )
            return

        elif subcmd in ("horario", "horarios", "set"):
            new_times = []
            for arg in context.args[1:]:
                clean_arg = arg.replace(",", " ").strip()
                for token in clean_arg.split():
                    token = token.strip()
                    if re.match(r"^([01]\d|2[0-3]):[0-5]\d$", token):
                        new_times.append(token)

            if not new_times:
                await _reply(
                    update.message,
                    "⚠️ Formato inválido. Especifique horarios en formato HH:MM (24h).\n"
                    "Ejemplo: <code>/cron horario 07:30 16:00</code>"
                )
                return

            cfg["cron_schedules"] = sorted(list(set(new_times)))
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config()
            h_str = ", ".join([f"<code>{h}</code>" for h in cfg["cron_schedules"]])
            await _reply(update.message, f"✅ <b>Nuevos Horarios de Envío Asignados:</b> {h_str}")
            return

        elif subcmd in ("agregar", "add", "+"):
            if len(context.args) < 2:
                await _reply(update.message, "⚠️ Especifique el horario a agregar. Ej: <code>/cron agregar 12:00</code>")
                return
            new_h = context.args[1].strip()
            if not re.match(r"^([01]\d|2[0-3]):[0-5]\d$", new_h):
                await _reply(update.message, "⚠️ Formato inválido. Use HH:MM en formato 24 horas (ej. 14:30).")
                return

            cur_sch = set(cfg.get("cron_schedules", ["07:30", "16:00"]))
            cur_sch.add(new_h)
            cfg["cron_schedules"] = sorted(list(cur_sch))
            cfg["cron_reports_updated_at"] = now_str
            cfg["cron_reports_updated_by"] = admin_name
            save_config()
            await _reply(update.message, f"✅ Horario <code>{new_h}</code> agregado con éxito.")
            return

        elif subcmd in ("quitar", "eliminar", "del", "remove", "-"):
            if len(context.args) < 2:
                await _reply(update.message, "⚠️ Especifique el horario a quitar. Ej: <code>/cron quitar 16:00</code>")
                return
            del_h = context.args[1].strip()
            cur_sch = list(cfg.get("cron_schedules", ["07:30", "16:00"]))
            if del_h in cur_sch:
                cur_sch.remove(del_h)
                cfg["cron_schedules"] = sorted(cur_sch)
                cfg["cron_reports_updated_at"] = now_str
                cfg["cron_reports_updated_by"] = admin_name
                save_config()
                await _reply(update.message, f"🗑️ Horario <code>{del_h}</code> eliminado de la lista.")
            else:
                await _reply(update.message, f"ℹ️ El horario <code>{del_h}</code> no estaba configurado.")
            return

    panel_text = _format_cron_panel_text()
    keyboard = _get_cron_keyboard()
    await _reply(update.message, panel_text, reply_markup=keyboard)


async def handle_cron_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja los botones interactivos del panel de cron."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if query.from_user.id != owner_id:
        await query.answer("⛔ Acción restringida al Administrador.", show_alert=True)
        return

    action = query.data.replace("cron_act:", "")
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = f"{query.from_user.full_name} [Bot]"

    if action == "pause":
        cfg["cron_reports_enabled"] = False
        cfg["cron_reports_updated_at"] = now_str
        cfg["cron_reports_updated_by"] = admin_name
        save_config()
        await query.answer("🔴 Envíos programados pausados.")

    elif action == "resume":
        cfg["cron_reports_enabled"] = True
        cfg["cron_reports_updated_at"] = now_str
        cfg["cron_reports_updated_by"] = admin_name
        save_config()
        await query.answer("🟢 Envíos programados reanudados.")

    elif action == "refresh":
        await query.answer("🔄 Panel actualizado.")

    panel_text = _format_cron_panel_text()
    markup = _get_cron_keyboard()
    try:
        await query.edit_message_text(panel_text, parse_mode='HTML', reply_markup=markup)
    except Exception:
        pass


# ==============================================================================
# 10. AUDITORÍA Y CONTROL DE GRUPOS
# ==============================================================================

async def _build_groups_audit_panel(bot: Any) -> Tuple[str, InlineKeyboardMarkup]:
    """Verifica en tiempo real con Telegram en cuáles grupos está realmente el bot."""
    candidates = _get_known_group_candidates()
    bot_user = await bot.get_me()
    bot_id = bot_user.id
    cfg = _get_active_config()
    allowed_groups = set(cfg.get("allowed_group_ids", []))

    active_groups = []
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
                active_groups.append({
                    "id": gid,
                    "title": title,
                    "role_label": role_label,
                    "is_allowed": is_allowed,
                })
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
            auth_badge = "🟢 <b>Autorizado Oficial</b>" if g["is_allowed"] else "⚠️ <b>NO Autorizado</b>"
            lines.append(f"<b>{idx}. {html.escape(g['title'])}</b>")
            lines.append(f"   🆔 <code>{g['id']}</code> | {auth_badge}")
            lines.append(f"   🤖 <b>Rol del Bot:</b> {g['role_label']}\n")

            btn_label = f"🚪 Salir de: {g['title'][:14]}… ({g['id']})" if len(g['title']) > 15 else f"🚪 Salir de: {g['title']} ({g['id']})"
            keyboard.append([InlineKeyboardButton(btn_label, callback_data=f"auth_force_leave:{g['id']}")])

    lines.append("━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    lines.append("<i>💡 Pulsa el botón de cualquier grupo para obligar al bot a abandonarlo al instante.</i>")

    keyboard.append([
        InlineKeyboardButton("🔄 Actualizar Lista", callback_data="auth_refresh_groups"),
        InlineKeyboardButton("🔙 Volver a Permisos", callback_data="auth_back_to_permissions")
    ])

    return "\n".join(lines), InlineKeyboardMarkup(keyboard)


async def cmd_grupos(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Audita los grupos donde está el bot y fuerza salidas (Exclusivo Owner en privado)."""
    if not await require_private_chat(update, context):
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    user_id = update.effective_user.id if update.effective_user else 0

    if user_id != owner_id:
        if update.message:
            await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    wait_msg = None
    if update.message:
        wait_msg = await update.message.reply_text(
            "⏳ <i>Consultando en tiempo real con Telegram los grupos activos del bot...</i>",
            parse_mode='HTML'
        )

    panel_text, reply_markup = await _build_groups_audit_panel(context.bot)

    if wait_msg:
        try:
            await wait_msg.edit_text(panel_text, parse_mode='HTML', reply_markup=reply_markup)
        except Exception:
            await update.message.reply_text(panel_text, parse_mode='HTML', reply_markup=reply_markup)
    elif update.message:
        await update.message.reply_text(panel_text, parse_mode='HTML', reply_markup=reply_markup)


async def cmd_salir_grupo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Fuerza la salida del bot de un grupo específico por ID (/salir_grupo <id>)."""
    if not await require_private_chat(update, context):
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    user_id = update.effective_user.id if update.effective_user else 0

    if user_id != owner_id:
        if update.message:
            await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    if not context.args:
        await _reply(
            update.message,
            "ℹ️ <b>Uso del comando /salir_grupo:</b>\n\n"
            "Debes especificar el ID numérico del grupo del cual deseas que el bot salga.\n"
            "Ejemplo: <code>/salir_grupo -1001383163558</code>\n\n"
            "<i>También puedes usar <code>/grupos</code> para ver los grupos activos y tocar el botón de salida directamente.</i>"
        )
        return

    target_str = context.args[0].strip()
    try:
        target_group_id = int(target_str)
    except ValueError:
        await _reply(update.message, f"❌ El identificador <code>{html.escape(target_str)}</code> no es un ID numérico válido.")
        return

    groups_cache = cfg.get("groups_cache", {})
    group_name = groups_cache.get(str(target_group_id), f"Grupo {target_group_id}")

    try:
        await context.bot.leave_chat(chat_id=target_group_id)
        allowed_groups = cfg.setdefault("allowed_group_ids", [])
        if target_group_id in allowed_groups:
            allowed_groups.remove(target_group_id)
            save_config()

        await _reply(
            update.message,
            f"✅ <b>Instrucción Ejecutada:</b>\n\n"
            f"El bot ha abandonado forzosamente el grupo <b>{html.escape(group_name)}</b> (ID: <code>{target_group_id}</code>)."
        )
    except Exception as e:
        await _reply(
            update.message,
            f"⚠️ <b>Aviso al intentar salir del grupo:</b>\n\n"
            f"<code>{html.escape(str(e))}</code>\n\n"
            f"<i>Es posible que el bot ya haya sido expulsado previamente o no sea miembro de ese grupo.</i>"
        )


async def notify_owner_bot_added_to_group(
    chat: Any,
    from_user: Any,
    context: ContextTypes.DEFAULT_TYPE
) -> None:
    """Notifica de inmediato al Owner cuando el bot es añadido a cualquier grupo."""
    chat_id = chat.id
    chat_title = chat.title or "Grupo Sin Título"
    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    allowed_groups = set(cfg.get("allowed_group_ids", []))

    cfg.setdefault("groups_cache", {})[str(chat_id)] = chat_title
    save_config()

    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    adder_name = from_user.full_name if from_user else "Desconocido"
    adder_username = f"@{from_user.username}" if (from_user and from_user.username) else "sin username"
    adder_id = from_user.id if from_user else 0

    is_authorized_group = (chat_id in allowed_groups)
    audit_path = get_audit_log_path()
    status_log = "GRUPO_AUTORIZADO" if is_authorized_group else "GRUPO_NO_AUTORIZADO"
    log_line = (
        f"[{now_str}] BOT AÑADIDO A {status_log} | Chat: {chat_title} (ID: {chat_id}) | "
        f"Añadido por: {adder_name} ({adder_username}, ID: {adder_id})\n"
    )
    try:
        with open(audit_path, "a", encoding="utf-8") as f:
            f.write(log_line)
    except Exception as e:
        logger.error(f"Error escribiendo en log de auditoría: {e}")

    if not owner_id:
        return

    if is_authorized_group:
        msg_owner = (
            "ℹ️ <b>Notificación: Bot Añadido a Grupo Autorizado</b>\n\n"
            f"👥 <b>Grupo:</b> {html.escape(chat_title)}\n"
            f"🆔 <b>ID de Chat:</b> <code>{chat_id}</code>\n"
            f"👤 <b>Añadido por:</b> {html.escape(adder_name)} ({html.escape(adder_username)})\n"
            f"🆔 <b>ID de Usuario:</b> <code>{adder_id}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>Este grupo ya se encuentra registrado en la lista de grupos autorizados.</i>"
        )
        try:
            await context.bot.send_message(chat_id=owner_id, text=msg_owner, parse_mode='HTML')
        except Exception as e:
            logger.error(f"Error notificando al owner sobre adición a grupo autorizado: {e}")
    else:
        alert_text = (
            "🚨 <b>ALERTA DE SEGURIDAD: BOT AÑADIDO A NUEVO GRUPO</b>\n\n"
            "El bot ha sido agregado a un grupo que <b>NO está en la lista de autorizados</b>:\n\n"
            f"👥 <b>Grupo:</b> {html.escape(chat_title)}\n"
            f"🆔 <b>ID del Grupo:</b> <code>{chat_id}</code>\n"
            f"👤 <b>Añadido por:</b> {html.escape(adder_name)} ({html.escape(adder_username)})\n"
            f"🆔 <b>ID de Usuario:</b> <code>{adder_id}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>¿Deseas autorizar las funciones del bot en este grupo o prefieres que el bot abandone el grupo inmediatamente?</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🟢 Autorizar Grupo", callback_data=f"auth_allow_group:{chat_id}"),
                InlineKeyboardButton("🚪 Salir del Grupo", callback_data=f"auth_leave_group:{chat_id}")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        try:
            await context.bot.send_message(
                chat_id=owner_id,
                text=alert_text,
                parse_mode='HTML',
                reply_markup=reply_markup
            )
        except Exception as e:
            logger.error(f"Error enviando alerta al owner sobre nuevo grupo: {e}")


async def on_my_chat_member(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Manejador para eventos de membresía del propio bot (ChatMemberUpdated)."""
    result = update.my_chat_member
    if not result:
        return

    chat = result.chat
    if chat.type not in ("group", "supergroup"):
        return

    from_user = result.from_user
    old_status = result.old_chat_member.status if result.old_chat_member else None
    new_status = result.new_chat_member.status if result.new_chat_member else None

    was_member = old_status in (ChatMemberStatus.MEMBER, ChatMemberStatus.ADMINISTRATOR)
    is_member = new_status in (ChatMemberStatus.MEMBER, ChatMemberStatus.ADMINISTRATOR)

    if not was_member and is_member:
        await notify_owner_bot_added_to_group(chat, from_user, context)


async def on_new_chat_members(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Manejador para mensajes de servicio con nuevos miembros del chat."""
    msg = update.message
    if not msg or not msg.new_chat_members or not msg.chat:
        return

    bot_id = context.bot.id
    if any(m.id == bot_id for m in msg.new_chat_members):
        await notify_owner_bot_added_to_group(msg.chat, msg.from_user, context)


# ==============================================================================
# 11. MIGRACIÓN DINÁMICA DE TOKEN Y ACTIVACIÓN DE HARDWARE
# ==============================================================================

async def cmd_migrar_token_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Inicia el flujo conversacional interactivo para la migración de token (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return ConversationHandler.END

    if not is_authorized(update) or not update.effective_user or update.effective_user.id != IMMUTABLE_OWNER_ID:
        if update.message:
            await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return ConversationHandler.END

    context.user_data["migrar_token_ts"] = time.time()

    await _reply(
        update.message,
        "🔄 <b>Migración de Identidad del Bot (Token de Telegram)</b>\n\n"
        "Este proceso re-cifrará el nuevo token con los estándares de seguridad locales sin exponerlo en texto plano.\n\n"
        "1️⃣ Ve a @BotFather y copia tu nuevo Token de bot.\n"
        "2️⃣ <b>Pega y envía el nuevo Token</b> como respuesta a este mensaje.\n\n"
        "⏳ <i>Tienes 2 minutos para responder. Envía <code>/cancelar</code> para abortar.</i>"
    )
    return MIGRAR_TOKEN_WAITING


async def handle_new_token_input(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Procesa, valida con Telegram y re-cifra el nuevo token de forma segura."""
    if not update.effective_user or update.effective_user.id != IMMUTABLE_OWNER_ID:
        return ConversationHandler.END

    start_ts = context.user_data.get("migrar_token_ts", 0)
    if time.time() - start_ts > 120:
        await _reply(
            update.message,
            "⏱️ <b>Operación Expirada:</b> Ha transcurrido el tiempo límite de 2 minutos. La migración ha sido cancelada."
        )
        return ConversationHandler.END

    new_token = update.message.text.strip() if (update.message and update.message.text) else ""

    if new_token.lower() in ("/cancelar", "cancelar", "/cancel", "abortar"):
        await _reply(update.message, "🛑 <b>Migración cancelada:</b> Se mantiene el token actual sin modificaciones.")
        return ConversationHandler.END

    status_msg = await update.message.reply_text("🔄 Validando nuevo token contra la API oficial de Telegram...")
    ok, msg = migrate_core_token(new_token)

    if ok:
        try:
            await status_msg.edit_text(
                f"{msg}\n\n"
                "🚀 <b>Reiniciando servicio en 3 segundos para aplicar la nueva identidad...</b>\n"
                "<i>El bot reanudará la atención automáticamente bajo el nuevo token.</i>",
                parse_mode='HTML'
            )
        except Exception:
            await _reply(update.message, f"{msg}\n\n🚀 <b>Reiniciando servicio para aplicar la nueva identidad...</b>")

        async def _restart_service() -> None:
            await asyncio.sleep(2.5)
            os.system("sudo systemctl restart tg-admin-bot.service &")

        asyncio.create_task(_restart_service())
        return ConversationHandler.END
    else:
        try:
            await status_msg.edit_text(
                f"❌ <b>Fallo en la Validación:</b>\n\n{html.escape(msg)}\n\n"
                "<i>La operación fue abortada. El token original sigue activo y protegido.</i>",
                parse_mode='HTML'
            )
        except Exception:
            await _reply(
                update.message,
                f"❌ <b>Fallo en la Validación:</b>\n\n{html.escape(msg)}\n\n"
                "<i>La operación fue abortada. El token original sigue activo y protegido.</i>"
            )
        return ConversationHandler.END


async def cancel_migrar_token(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Cancela el flujo de migración de token."""
    if update.message:
        await _reply(update.message, "🛑 <b>Operación cancelada.</b>")
    return ConversationHandler.END


async def cmd_activar_manual(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner activar manualmente la verificación de hardware usando /activar <SERIAL>."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or update.effective_user.id != IMMUTABLE_OWNER_ID:
        return

    serial_arg = ""
    if context.args:
        serial_arg = context.args[0].strip().upper()
    elif update.message and update.message.text:
        match = re.search(r'(AUTH-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})', update.message.text, re.IGNORECASE)
        if match:
            serial_arg = match.group(1).upper()

    if not serial_arg:
        await _reply(update.message, "ℹ️ <b>Uso del comando:</b> <code>/activar AUTH-XXXX-XXXX-XXXX-XXXX</code>")
        return

    ok, msg = activate_hardware_first_boot(serial_arg)
    if ok:
        await _reply(
            update.message,
            f"<b>{msg}</b>\n\n🎉 <b>¡Bienvenido!</b> El sistema ha completado la validación de integridad y se encuentra ahora 100% <b>OPERACIONAL</b>."
        )
    else:
        await _reply(
            update.message,
            f"❌ <b>Error de Activación:</b>\n\n<code>{html.escape(msg)}</code>\n\n"
            "<i>Verifique el Serial recibido en la alerta de emergencia e intente nuevamente dentro de la ventana de 10 minutos.</i>"
        )


# ==============================================================================
# 12. PERMISOS, AUDITORÍA Y CONTROL DE ACCESO
# ==============================================================================

async def _build_permissions_panel(bot: Any) -> Tuple[str, InlineKeyboardMarkup | None]:
    """Construye el texto y el teclado interactivo con datos completos de usuarios y grupos."""
    cfg = _get_active_config()
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
    if not await require_private_chat(update, context):
        return

    if not update.effective_user:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    if update.effective_user.id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    panel_text, reply_markup = await _build_permissions_panel(context.bot)
    await _reply(update.message, panel_text, reply_markup=reply_markup)


def get_denied_users_summary() -> List[Dict[str, Any]]:
    """Extrae la lista de usuarios no autorizados registrados en el log de auditoría."""
    audit_path = get_audit_log_path()
    if not audit_path.exists():
        return []

    denied_map: Dict[int, Dict[str, Any]] = {}
    cfg = _get_active_config()
    allowed_users = set(cfg.get("allowed_user_ids", []))
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    if owner_id:
        allowed_users.add(owner_id)

    try:
        lines = audit_path.read_text(encoding="utf-8").splitlines()
        for line in reversed(lines):
            line = line.strip()
            if not line or "NO AUTORIZADO" not in line:
                continue

            try:
                date_match = re.search(r"\[(.*?)\]", line)
                fecha = date_match.group(1) if date_match else "Desconocida"

                id_match = re.search(r"ID:\s*(-?\d+)", line)
                user_id = int(id_match.group(1)) if id_match else None

                user_match = re.search(r"Username:\s*([^|]+)", line)
                username = user_match.group(1).strip() if user_match else ""

                name_match = re.search(r"Nombre:\s*([^|]+)", line)
                nombre = name_match.group(1).strip() if name_match else "(sin nombre)"
                if nombre.lower() == "none" or nombre == "None":
                    nombre = "(sin nombre)"
                nombre = nombre.replace(" None", "").strip()

                if user_id and user_id not in allowed_users:
                    if user_id not in denied_map:
                        denied_map[user_id] = {
                            "user_id": user_id,
                            "username": username if username != "(sin username)" else "",
                            "name": nombre,
                            "last_seen": fecha
                        }
            except Exception:
                continue
    except Exception as e:
        logger.warning(f"Error leyendo {audit_path}: {e}")

    return list(denied_map.values())


async def handle_auth_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja la acción de los botones inline de autorización y revocación presionados por el creador."""
    query = update.callback_query
    if not query or not query.data:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    clicker_id = query.from_user.id

    if clicker_id != owner_id:
        await query.answer("⛔ Solo el creador del bot tiene permiso para gestionar accesos.", show_alert=True)
        return

    if query.data in ("auth_open_groups", "auth_refresh_groups"):
        await query.answer("🔍 Verificando grupos activos...")
        panel_text, reply_markup = await _build_groups_audit_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode='HTML', reply_markup=reply_markup)
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
            await query.edit_message_text(text=panel_text, parse_mode='HTML', reply_markup=reply_markup)
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
        save_config()
        estado_label = "🟢 ACTIVADO" if new_state else "🔴 DESACTIVADO"
        await query.answer(f"Modo Depuración {estado_label}")
        if query.message:
            try:
                await query.edit_message_text(
                    f"🧪 <b>Panel de Modo Depuración (Monitor ATIT)</b>\n\n"
                    f"<b>Estado Actual:</b> <code>{estado_label}</code>\n\n"
                    f"Configuración guardada exitosamente. "
                    f"{'Los próximos reportes incluirán archivo servicelog.txt adjunto.' if new_state else 'El monitor operará en modo producción estándar.'}",
                    parse_mode='HTML',
                    reply_markup=None
                )
            except Exception:
                pass
        return

    if action == "auth_toggle_cmd_lock":
        new_state = (target_id_str == "on")
        cfg["commands_locked_for_users"] = new_state
        save_config()
        estado_label = "🔒 BLOQUEADOS PARA OTROS" if new_state else "🔓 HABILITADOS PARA TODOS"
        await query.answer(f"Comandos {estado_label}")
        if query.message:
            try:
                await query.edit_message_text(
                    "🛡️ <b>Control de Acceso a Comandos (Exclusivo Owner)</b>\n\n"
                    f"<b>Estado Actual:</b> <code>{estado_label}</code>\n\n"
                    f"Configuración guardada exitosamente.\n"
                    f"{'Los demás usuarios y grupos tienen el uso de comandos temporalmente bloqueado.' if new_state else 'Los usuarios y grupos autorizados pueden usar los comandos normalmente.'}",
                    parse_mode='HTML',
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
            save_config()
            logger.info(f"Usuario {target_user_id} autorizado y guardado en config.json por {clicker_id}")

        await query.answer(f"✅ Usuario {target_user_id} autorizado exitosamente.")
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"✅ <b>ESTADO: AUTORIZADO Y AGREGADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(text=original_text + status_badge, parse_mode='HTML', reply_markup=None)
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
                "📌 <b>RECUERDA QUE:</b>\n\n"
                f"{aviso_legal}"
            )
            await context.bot.send_message(chat_id=target_user_id, text=user_approval_msg, parse_mode='HTML')
        except Exception as e:
            logger.info(f"No se pudo notificar directamente al usuario {target_user_id}: {e}")

    elif action == "auth_deny":
        allowed_list = cfg.setdefault("allowed_user_ids", [])
        if target_user_id in allowed_list:
            allowed_list.remove(target_user_id)
            save_config()

        await query.answer(f"❌ Acceso denegado para {target_user_id}.")
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"❌ <b>ESTADO: ACCESO DENEGADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(text=original_text + status_badge, parse_mode='HTML', reply_markup=None)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass

        try:
            await context.bot.send_message(
                chat_id=target_user_id,
                text="⛔ <b>Solicitud Denegada</b>\n\nEl administrador ha rechazado tu solicitud de acceso a este bot.",
                parse_mode='HTML'
            )
        except Exception as e:
            logger.info(f"No se pudo notificar al usuario {target_user_id}: {e}")

    elif action == "auth_revoke_user":
        allowed_list = cfg.setdefault("allowed_user_ids", [])
        if target_user_id in allowed_list:
            allowed_list.remove(target_user_id)
            save_config()
            logger.info(f"Usuario {target_user_id} revocado y guardado en config.json por {clicker_id}")

        await query.answer(f"✅ Usuario {target_user_id} revocado de la lista.")
        panel_text, reply_markup = await _build_permissions_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode='HTML', reply_markup=reply_markup)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass

        try:
            await context.bot.send_message(
                chat_id=target_user_id,
                text="⛔ <b>Acceso Revocado</b>\n\nEl administrador ha revocado tu autorización para interactuar con este bot.",
                parse_mode='HTML'
            )
        except Exception as e:
            logger.info(f"No se pudo notificar al usuario revocado {target_user_id}: {e}")

    elif action == "auth_revoke_group":
        allowed_groups = cfg.setdefault("allowed_group_ids", [])
        if target_user_id in allowed_groups:
            allowed_groups.remove(target_user_id)
            save_config()
            logger.info(f"Grupo {target_user_id} revocado y guardado en config.json por {clicker_id}")

        await query.answer(f"✅ Grupo {target_user_id} revocado de la lista.")
        panel_text, reply_markup = await _build_permissions_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode='HTML', reply_markup=reply_markup)
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
            save_config()
            logger.info(f"Grupo {target_group_id} autorizado y guardado en config.json por {clicker_id}")

        await query.answer("✅ Grupo autorizado exitosamente.")
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"✅ <b>ESTADO: GRUPO AUTORIZADO Y ACTIVADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(text=original_text + status_badge, parse_mode='HTML', reply_markup=None)
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
            save_config()

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
            await query.edit_message_text(text=original_text + status_badge, parse_mode='HTML', reply_markup=None)
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
            save_config()

        if leave_ok:
            await query.answer(f"🚪 El bot ha salido de: {group_name[:25]}", show_alert=True)
        else:
            await query.answer("⚠️ El bot ya no estaba en ese grupo o fue expulsado previamente.", show_alert=True)

        panel_text, reply_markup = await _build_groups_audit_panel(context.bot)
        try:
            await query.edit_message_text(text=panel_text, parse_mode='HTML', reply_markup=reply_markup)
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass
        return


async def toggle_debug_monitor(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner activar, desactivar o consultar el Modo Depuración del Monitor (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    current_status = bool(cfg.get("monitor_debug_mode", False))

    if context.args:
        arg = context.args[0].lower()
        if arg in ("on", "activar", "activado", "true", "1", "si"):
            cfg["monitor_debug_mode"] = True
            save_config()
            await _reply(
                update.message,
                "🧪 <b>Modo Depuración del Monitor: ACTIVADO</b>\n\n"
                "Los próximos reportes incluirán información técnica detallada y el archivo consolidado <code>servicelog.txt</code> enviado exclusivamente a tu chat privado."
            )
            return
        elif arg in ("off", "desactivar", "desactivado", "false", "0", "no"):
            cfg["monitor_debug_mode"] = False
            save_config()
            await _reply(
                update.message,
                "📊 <b>Modo Depuración del Monitor: DESACTIVADO</b>\n\n"
                "El monitor operará en modo producción estándar."
            )
            return
        elif arg in ("status", "estado", "ver"):
            estado_txt = "🟢 <b>ACTIVADO</b>" if current_status else "🔴 <b>DESACTIVADO</b>"
            await _reply(update.message, f"🧪 <b>Estado actual del Modo Depuración:</b> {estado_txt}")
            return

    estado_str = "🟢 ACTIVO" if current_status else "🔴 INACTIVO"
    keyboard = [
        [
            InlineKeyboardButton("🟢 Activar Depuración", callback_data="auth_toggle_debug:on"),
            InlineKeyboardButton("🔴 Desactivar Depuración", callback_data="auth_toggle_debug:off")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await _reply(
        update.message,
        f"🧪 <b>Panel de Modo Depuración (Monitor ATIT)</b>\n\n"
        f"<b>Estado Actual:</b> <code>{estado_str}</code>\n\n"
        f"• <b>Activo:</b> Genera reportes exhaustivos y adjunta el archivo <code>servicelog.txt</code>.\n"
        f"• <b>Inactivo:</b> Envía el resumen consolidado estándar.\n\n"
        f"<i>Presiona una opción para cambiar el estado:</i>",
        reply_markup=reply_markup
    )


async def toggle_commands_lock(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner bloquear o desbloquear la ejecución de comandos para el resto de usuarios y grupos."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    current_status = bool(cfg.get("commands_locked_for_users", False))

    if context.args:
        arg = context.args[0].lower()
        if arg in ("on", "activar", "activado", "bloquear", "lock", "true", "1", "si"):
            cfg["commands_locked_for_users"] = True
            save_config()
            await _reply(
                update.message,
                "🔒 <b>Bloqueo de Comandos: ACTIVADO</b>\n\n"
                "A partir de ahora, los demás usuarios y los grupos <b>NO podrán ejecutar comandos</b>.\n"
                "Tú como Administrador conservas el acceso total e ilimitado."
            )
            return
        elif arg in ("off", "desactivar", "desactivado", "desbloquear", "unlock", "false", "0", "no"):
            cfg["commands_locked_for_users"] = False
            save_config()
            await _reply(
                update.message,
                "🔓 <b>Bloqueo de Comandos: DESACTIVADO</b>\n\n"
                "Los usuarios y grupos autorizados <b>pueden volver a ejecutar comandos</b> normalmente."
            )
            return
        elif arg in ("status", "estado", "ver"):
            estado_txt = "🔒 <b>ACTIVADO (Comandos bloqueados para usuarios/grupos)</b>" if current_status else "🔓 <b>DESACTIVADO (Comandos habilitados)</b>"
            await _reply(update.message, f"🛡️ <b>Estado del Control de Comandos:</b>\n{estado_txt}")
            return

    estado_str = "🔒 BLOQUEADOS PARA OTROS" if current_status else "🔓 HABILITADOS PARA TODOS"
    keyboard = [
        [
            InlineKeyboardButton("🔒 Bloquear a Otros", callback_data="auth_toggle_cmd_lock:on"),
            InlineKeyboardButton("🔓 Habilitar a Todos", callback_data="auth_toggle_cmd_lock:off")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    panel_msg = (
        "🛡️ <b>Control de Acceso a Comandos (Exclusivo Owner)</b>\n\n"
        f"<b>Estado Actual:</b> <code>{estado_str}</code>\n\n"
        "• <b>Bloquear:</b> Desactiva la ejecución de comandos para el grupo y usuarios autorizados.\n"
        "• <b>Habilitar:</b> Restablece el uso de comandos a la normalidad.\n\n"
        "<i>Tú como Administrador siempre mantendrás acceso completo e ilimitado a todas las herramientas.</i>"
    )
    await _reply(update.message, panel_msg, reply_markup=reply_markup)


async def bot_status(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Diagnóstico completo de conectividad, proxies, usuarios permitidos y negados (Exclusivo Owner en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    if update.effective_user.id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    waiting_msg = None
    try:
        waiting_msg = await update.message.reply_text(
            "⏳ <i>Ejecutando diagnóstico en tiempo real de red, proxies y accesos...</i>",
            parse_mode='HTML'
        )
    except Exception:
        pass

    bot_token = cfg.get("bot_token", "")
    tg_url = f"https://api.telegram.org/bot{bot_token}/getMe"

    network_tasks = [
        _check_endpoint_health("Conexión Directa a Internet", tg_url, None, timeout=4.0)
    ]
    proxies_list = load_proxies_list()
    for p in proxies_list:
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if p_url:
            network_tasks.append(_check_endpoint_health(p_name, tg_url, p_url, timeout=7.0))

    network_results = await asyncio.gather(*network_tasks)

    allowed_users = cfg.get("allowed_user_ids", [])
    allowed_groups = cfg.get("allowed_group_ids", [])

    owner_name, owner_username = await _resolve_user_info(context.bot, owner_id)
    owner_tag = f" ({owner_username})" if owner_username else ""

    other_users = [u for u in allowed_users if u != owner_id]
    user_info_tasks = [_resolve_user_info(context.bot, uid) for uid in other_users]
    group_info_tasks = [_resolve_group_info(context.bot, gid) for gid in allowed_groups]

    user_info_results = await asyncio.gather(*user_info_tasks) if user_info_tasks else []
    group_info_results = await asyncio.gather(*group_info_tasks) if group_info_tasks else []

    denied_users = get_denied_users_summary()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    active_channel = ACTIVE_CONNECTION_LABEL or "Conexión Directa"

    lines = [
        "📊 <b>Informe de Estado y Diagnóstico del Bot</b>",
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>",
        "",
        "🌐 <b>DIAGNÓSTICO DE RED E INTERNET</b>"
    ]

    for res in network_results:
        icon = "🟢" if res["ok"] else "🔴"
        lines.append(f"• <b>{html.escape(res['name'])}:</b>\n   {icon} <code>{html.escape(res['detail'])}</code>")

    lines.append("")
    lines.append(f"🔄 <b>Canal Activo del Bot:</b> <code>{html.escape(active_channel)}</code>")
    lines.append("")
    lines.append("👥 <b>CONTROL DE ACCESO DE USUARIOS</b>")
    lines.append(f"👑 <b>Creador / Owner:</b> {html.escape(owner_name)}{html.escape(owner_tag)} [<code>{owner_id}</code>]")
    lines.append("")

    lines.append("✅ <b>Usuarios Permitidos:</b>")
    if not other_users:
        lines.append("• <i>(No hay usuarios adicionales en la lista)</i>")
    else:
        for idx, (uid, (uname, uuser)) in enumerate(zip(other_users, user_info_results), 1):
            utag = f" ({uuser})" if uuser else ""
            lines.append(f"{idx}. 👤 <b>Usuario:</b> {html.escape(uname)}{html.escape(utag)}\n   🆔 <b>ID de Telegram:</b> <code>{uid}</code>")

    lines.append("")
    lines.append("🏢 <b>Grupos Permitidos:</b>")
    if not allowed_groups:
        lines.append("• <i>(No hay grupos en la lista)</i>")
    else:
        for idx, (gid, gtitle) in enumerate(zip(allowed_groups, group_info_results), 1):
            lines.append(f"{idx}. 👥 <b>Grupo:</b> {html.escape(gtitle)}\n   🆔 <b>ID de Chat:</b> <code>{gid}</code>")

    lines.append("")
    lines.append("⛔ <b>Usuarios Negados / No Autorizados:</b>")
    if not denied_users:
        lines.append("• <i>(No hay usuarios bloqueados o negados recientemente)</i>")
    else:
        for idx, duser in enumerate(denied_users, 1):
            dtag = f" ({duser['username']})" if duser['username'] else ""
            lines.append(
                f"{idx}. 🚫 <b>Usuario:</b> {html.escape(duser['name'])}{html.escape(dtag)}\n"
                f"   🆔 <b>ID de Telegram:</b> <code>{duser['user_id']}</code>\n"
                f"   ⏰ <b>Último Intento:</b> <code>{duser['last_seen']}</code>"
            )

    report_text = "\n".join(lines)
    if waiting_msg:
        try:
            await waiting_msg.edit_text(report_text, parse_mode='HTML')
            return
        except Exception:
            try:
                await waiting_msg.delete()
            except Exception:
                pass

    await _reply(update.message, report_text)


# ==============================================================================
# 13. REGISTRO DE RUTAS ADMINISTRATIVAS
# ==============================================================================

def register_admin_routes(application: Application) -> None:
    """Registra todos los comandos y callbacks de administración del sistema."""
    # Comandos
    application.add_handler(CommandHandler(["limpiador", "limpieza", "cleaner"], cmd_limpiador))
    application.add_handler(CommandHandler(["recursos", "memoria", "optimizar", "plasma", "recursos_sistema", "optimizer"], cmd_recursos_sistema))
    application.add_handler(CommandHandler(["temperatura", "temp", "termal", "cpu_temp", "thermal"], cmd_temperatura))
    application.add_handler(CommandHandler(["ia", "motor_ia", "toggle_ia", "servicio_ia", "ia_control"], cmd_ia_control))
    application.add_handler(CommandHandler(["mensaje", "broadcast", "difusion", "anuncio", "comunicado"], cmd_broadcast_mensaje))
    application.add_handler(CommandHandler(["info", "aviso", "legal", "terminos", "auditoria"], cmd_info))
    application.add_handler(CommandHandler(["actualizar", "update", "upgrade", "git_update", "check_update", "deploy"], cmd_actualizar))
    application.add_handler(CommandHandler(["rollback", "revertir", "git_rollback", "deshacer"], cmd_rollback))
    application.add_handler(CommandHandler(["estado_deploy", "deploy_status", "git_status", "pipeline"], cmd_estado_deploy))
    application.add_handler(CommandHandler(["desbloquear_update", "unlock_update", "clear_lock"], cmd_desbloquear_update))
    application.add_handler(CommandHandler(["reiniciar_bot", "restart_bot", "bot_restart", "reiniciar"], cmd_reiniciar_bot))
    application.add_handler(CommandHandler(["log_actualizacion", "log_deploy", "deploy_log", "auditoria_deploy"], cmd_log_actualizacion))
    application.add_handler(CommandHandler(["emergencia", "panico", "contingencia", "panic", "parada"], cmd_emergencia))
    application.add_handler(CommandHandler(["reinicia", "reboot"], cmd_reinicia))
    application.add_handler(CommandHandler(["lockdown", "bloqueo_total", "bloqueo_perimetral"], cmd_lockdown))
    application.add_handler(CommandHandler(["unlock", "desbloqueo_total", "desbloqueo_perimetral"], cmd_unlock))
    application.add_handler(CommandHandler(["autorizar_consola", "consola", "auth_terminal", "auth_console"], cmd_autorizar_consola))
    application.add_handler(CommandHandler(["cron", "envios", "reportes_programados", "programacion", "horarios_cron", "envios_cron"], cmd_cron_control))
    application.add_handler(CommandHandler(["grupos", "mis_grupos", "listar_grupos", "chat_grupos", "audit_grupos"], cmd_grupos))
    application.add_handler(CommandHandler(["salir_grupo", "leave_group", "expulsar_grupo", "abandonar_grupo"], cmd_salir_grupo))
    application.add_handler(CommandHandler(["activar", "autorizar_hardware", "auth_hw"], cmd_activar_manual))
    application.add_handler(CommandHandler(["permisos", "autorizados", "whitelist", "usuarios", "accesos"], manage_permissions))
    application.add_handler(CommandHandler(["debug_monitor", "monitordebug", "modo_debug"], toggle_debug_monitor))
    application.add_handler(CommandHandler(["bloqueo_comandos", "bloquear_comandos", "lock_commands", "pausar_comandos", "control_comandos"], toggle_commands_lock))
    application.add_handler(CommandHandler(["botstatus", "statusbot", "estado_bot", "estatus", "status", "estado", "bot_status", "diagnostico_bot"], bot_status))

    # Flujo conversacional de migración de token
    conv_migrar_token = ConversationHandler(
        entry_points=[CommandHandler("migrar_token", cmd_migrar_token_start)],
        states={
            MIGRAR_TOKEN_WAITING: [
                MessageHandler(filters.TEXT & ~filters.COMMAND, handle_new_token_input),
                CommandHandler("cancelar", cancel_migrar_token)
            ]
        },
        fallbacks=[CommandHandler("cancelar", cancel_migrar_token)],
        per_chat=True,
        per_user=True,
    )
    application.add_handler(conv_migrar_token)

    # Callbacks interactivos
    application.add_handler(CallbackQueryHandler(handle_cleaner_callback, pattern=r"^(clean|cleaner_act):"))
    application.add_handler(CallbackQueryHandler(handle_optimizer_callback, pattern=r"^sys_opt:"))
    application.add_handler(CallbackQueryHandler(handle_thermal_callback, pattern=r"^thermal:"))
    application.add_handler(CallbackQueryHandler(handle_ia_callback, pattern=r"^ia_act:"))
    application.add_handler(CallbackQueryHandler(handle_update_callback, pattern=r"^update_act:"))
    application.add_handler(CallbackQueryHandler(handle_emergency_callback, pattern=r"^emergencia:"))
    application.add_handler(CallbackQueryHandler(handle_shield_callback, pattern=r"^shield_"))
    application.add_handler(CallbackQueryHandler(handle_cron_callback, pattern=r"^cron_act:"))
    application.add_handler(CallbackQueryHandler(handle_auth_callback, pattern=r"^auth_"))

    # Eventos de chat y miembros
    application.add_handler(ChatMemberHandler(on_my_chat_member, ChatMemberHandler.MY_CHAT_MEMBER))
    application.add_handler(MessageHandler(filters.StatusUpdate.NEW_CHAT_MEMBERS, on_new_chat_members))
