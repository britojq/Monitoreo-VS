#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
# ==============================================================================
# 🛡️ BOT CENTINELA DEDICADO DE SEGURIDAD Y LICENCIAMIENTO: SentinelCore
# Gestión centralizada de activaciones, auditoría de hardware y migración en caliente
# Ubicación: /scripts/telegram-admin-bot/sentinel_bot.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

import asyncio
import html
import json
import logging
import os
import platform
import subprocess
import sys
import time
from pathlib import Path
from typing import Optional, Tuple

import httpx
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    ContextTypes,
    ConversationHandler,
    MessageHandler,
    filters,
)
from telegram.request import HTTPXRequest

# Configurar logging
BASE_DIR = Path(__file__).resolve().parent
LOG_DIR = BASE_DIR / "logs"
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = LOG_DIR / "sentinel.log"

logging.basicConfig(
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    level=logging.INFO,
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger("sentinel_bot")

# Importar motor criptográfico de seguridad
from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    activate_hardware_first_boot,
    get_core_bot_token,
    get_core_status,
    get_node_telemetry,
    get_sentinel_token,
    is_core_operational,
    migrate_core_token,
    trip_deadman_switch,
)

STATE_WAITING_MIGRATION_TOKEN = 1
_ACTIVE_PROXY_URL: Optional[str] = None
_ACTIVE_CONNECTION_LABEL: str = "Conexión Directa"


def load_proxies_list() -> list:
    """Carga la lista de proxies configurados desde config.json y bot.conf."""
    proxies = []
    config_file = BASE_DIR / "config" / "config.json"
    if config_file.exists():
        try:
            with open(config_file, "r", encoding="utf-8") as f:
                data = json.load(f)
                proxies.extend(data.get("proxies", []))
        except Exception as e:
            logger.warning(f"Error cargando proxies desde config.json: {e}")

    # bot.conf secundario
    bot_conf = BASE_DIR / "config" / "bot.conf"
    if bot_conf.exists():
        try:
            for line in bot_conf.read_text(encoding="utf-8").splitlines():
                line = line.strip()
                if line.startswith("DEFAULTPROXY="):
                    p_url = line.split("=", 1)[1].strip().strip('"').strip("'")
                    if p_url and not any(p.get("url") == p_url for p in proxies):
                        proxies.append({"name": "Proxy bot.conf", "url": p_url, "enabled": True})
        except Exception:
            pass

    return proxies


def select_working_connection(bot_token: str) -> Optional[str]:
    """Evalúa rutas y selecciona la conexión directa o el proxy operativo para el Centinela."""
    global _ACTIVE_PROXY_URL, _ACTIVE_CONNECTION_LABEL
    url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Probar conexión directa
    try:
        r = httpx.get(url, timeout=3.5)
        if r.status_code == 200 and r.json().get("ok"):
            logger.info("🌐 Sentinel Bot: Conexión DIRECTA a Telegram verificada exitosamente.")
            _ACTIVE_CONNECTION_LABEL = "Conexión Directa"
            _ACTIVE_PROXY_URL = None
            return None
    except Exception as e:
        logger.warning(f"Sentinel Bot: Conexión directa no disponible ({e}). Evaluando proxies...")

    # 2. Probar proxies
    for p in load_proxies_list():
        if not p.get("enabled", True):
            continue
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if not p_url:
            continue
        try:
            r = httpx.get(url, proxy=p_url, timeout=4.0)
            if r.status_code == 200 and r.json().get("ok"):
                logger.info(f"🔄 Sentinel Bot: Conectividad vía [{p_name}]: {p_url}")
                _ACTIVE_CONNECTION_LABEL = f"Proxy: {p_name}"
                _ACTIVE_PROXY_URL = p_url
                return p_url
        except Exception:
            continue

    _ACTIVE_CONNECTION_LABEL = "Conexión Estándar (Sin verificar)"
    _ACTIVE_PROXY_URL = None
    return None


async def require_owner(update: Update) -> bool:
    """Valida estrictamente que el remitente sea el Administrador Propietario (Owner)."""
    user = update.effective_user
    if not user or user.id != IMMUTABLE_OWNER_ID:
        if update.message:
            try:
                await update.message.reply_text(
                    "🛑 <b>ACCESO DENEGADO</b>\n"
                    "Este bot es de uso estrictamente confidencial para el Administrador del Sistema.",
                    parse_mode="HTML"
                )
            except Exception:
                pass
        logger.warning(f"Intento no autorizado en Sentinel Bot por usuario: {user.id if user else 'Desconocido'}")
        return False
    return True


async def cmd_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Panel principal del Bot Centinela."""
    if not await require_owner(update):
        return

    telem = get_node_telemetry()
    status_emoji = "🟢" if telem["state"] == "OPERATIONAL" else "🟡"
    host_name = telem["hostname"]
    ips_str = ", ".join(telem["ips"])

    text = (
        "🛡️ <b>CENTRO DE CONTROL CENTINELA (SENTINEL CORE)</b>\n"
        "<b>═════════════════════════════════════</b>\n"
        "Sistema centralizado de seguridad, auditoría de hardware y migración en caliente.\n\n"
        f"🖥️ <b>Servidor Local:</b> <code>{host_name}</code>\n"
        f"🌐 <b>IPs Detectadas:</b> <code>{ips_str}</code>\n"
        f"🔒 <b>Estado de Seguridad:</b> {status_emoji} <code>{telem['state']}</code>\n"
        f"📡 <b>Canal de Conexión:</b> <code>{_ACTIVE_CONNECTION_LABEL}</code>\n"
        f"👤 <b>Owner Autorizado:</b> <code>{IMMUTABLE_OWNER_ID}</code>\n"
        "<b>═════════════════════════════════════</b>\n\n"
        "<b>Comandos de Gestión:</b>\n"
        "• <code>/estado</code> - Telemetría completa del nodo y bots.\n"
        "• <code>/activar &lt;SERIAL&gt;</code> - Validación manual de hardware.\n"
        "• <code>/migrar</code> - Asistente de migración en caliente.\n"
        "• <code>/reiniciar_bot</code> - Reiniciar servicio de monitoreo.\n"
        "• <code>/actualizar</code> - Sincronización forzada con GitHub (Git Update).\n"
    )

    keyboard = [
        [
            InlineKeyboardButton("📊 Telemetría Completa", callback_data="sentinel:menu:status"),
            InlineKeyboardButton("🔄 Migrar Servidor", callback_data="sentinel:menu:migrar")
        ],
        [
            InlineKeyboardButton("⚡ Forzar Git Update", callback_data="sentinel:git:force"),
            InlineKeyboardButton("🛡️ Reiniciar Bot", callback_data="sentinel:menu:restart_main")
        ]
    ]

    await update.message.reply_text(
        text,
        reply_markup=InlineKeyboardMarkup(keyboard),
        parse_mode="HTML"
    )


async def cmd_status(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el estado detallado de los nodos y del hardware."""
    if not await require_owner(update):
        return

    telem = get_node_telemetry()
    main_token = get_core_bot_token()
    token_preview = f"{main_token[:8]}...{main_token[-4:]}" if main_token else "No configurado"

    text = (
        "📊 <b>TELEMETRÍA DETALLADA DEL NODO</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Hostname:</b> <code>{telem['hostname']}</code>\n"
        f"🌐 <b>Direcciones IP:</b> <code>{', '.join(telem['ips'])}</code>\n"
        f"👤 <b>Usuario de Sistema:</b> <code>{telem['user']}</code>\n"
        f"📁 <b>Directorio Base:</b> <code>{telem['path']}</code>\n"
        f"🔒 <b>Estado DRM:</b> <code>{telem['state']}</code>\n"
        f"🤖 <b>Bot Principal:</b> <code>{token_preview}</code>\n"
        f"📡 <b>Ruta Red Centinela:</b> <code>{_ACTIVE_CONNECTION_LABEL}</code>\n"
    )
    if telem["active_serial"]:
        text += f"\n⚠️ <b>Serial Activo Pendiente:</b> <code>{telem['active_serial']}</code>\n"

    await update.message.reply_text(text, parse_mode="HTML")


async def cmd_activar(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando de activación manual de hardware."""
    if not await require_owner(update):
        return

    if not context.args or len(context.args) == 0:
        await update.message.reply_text(
            "ℹ️ <b>Uso del comando:</b> <code>/activar AUTH-XXXX-XXXX-XXXX-XXXX</code>",
            parse_mode="HTML"
        )
        return

    serial = context.args[0].strip()
    ok, msg = activate_hardware_first_boot(serial)
    if ok:
        await update.message.reply_text(
            f"🎉 <b>{msg}</b>\n\n"
            "🚀 Reiniciando servicio de monitoreo para inicializar el transporte...",
            parse_mode="HTML"
        )
        try:
            subprocess.run(["sudo", "systemctl", "restart", "tg-admin-bot"], check=False)
        except Exception as e:
            logger.error(f"Error reiniciando tg-admin-bot: {e}")
    else:
        await update.message.reply_text(f"❌ <b>Error de activación:</b> {msg}", parse_mode="HTML")


async def cmd_reiniciar_bot(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para reiniciar remotamente el servicio de monitoreo (tg-admin-bot)."""
    if not await require_owner(update):
        return

    msg = await update.message.reply_text("🔄 <b>Reiniciando servicio de monitoreo (tg-admin-bot)...</b>", parse_mode="HTML")
    try:
        subprocess.run(["sudo", "systemctl", "restart", "tg-admin-bot"], check=False)
        await msg.edit_text(
            "✅ <b>Servicio de Monitoreo Reiniciado</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "El bot principal (<code>tg-admin-bot</code>) ha sido reiniciado exitosamente y se encuentra en línea.",
            parse_mode="HTML"
        )
    except Exception as e:
        await msg.edit_text(f"❌ Error al reiniciar servicio: {e}")


async def cmd_actualizar(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para forzar o verificar actualizaciones Git en los servidores."""
    if not await require_owner(update):
        return

    from monitor.system_updater import check_updates, execute_git_update

    args = [a.lower() for a in context.args] if context.args else []
    if "force" in args or "forzar" in args or "ahora" in args:
        status_msg = await update.message.reply_text(
            "⏳ <b>Ejecutando sincronización forzada con GitHub (git reset --hard)...</b>\n"
            "<i>Por favor espera mientras se descargan y verifican los archivos.</i>",
            parse_mode="HTML"
        )
        report = await execute_git_update(bot_instance=context.bot)
        await status_msg.edit_text(report, parse_mode="HTML")
        return

    status_msg = await update.message.reply_text("🔍 <b>Comprobando actualizaciones en GitHub...</b>", parse_mode="HTML")
    res = await check_updates(bot_instance=context.bot)

    if not res.get("success"):
        err = res.get("error", "Error desconocido")
        text = (
            "❌ <b>Error al conectar con GitHub</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"<pre>{html.escape(err)}</pre>\n\n"
            "<i>Puedes forzar la sincronización directamente con el botón:</i>"
        )
        keyboard = [
            [InlineKeyboardButton("⚡ Forzar Actualización Git", callback_data="sentinel:git:force")]
        ]
        await status_msg.edit_text(text, reply_markup=InlineKeyboardMarkup(keyboard), parse_mode="HTML")
        return

    local_h = res.get("local_hash", "Desconocido")
    remote_h = res.get("remote_hash", "Desconocido")
    has_updates = res.get("has_updates", False)
    commits = res.get("commits", [])

    if not has_updates:
        text = (
            "✅ <b>EL SISTEMA ESTÁ ACTUALIZADO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🏷️ <b>Versión Activa:</b> <code>{local_h}</code>\n"
            f"🌿 <b>Rama:</b> <code>master</code>\n"
            f"🖥️ <b>Servidor:</b> <code>{platform.node()}</code>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "<i>El código local coincide exactamente con el repositorio oficial.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔄 Recomprobar", callback_data="sentinel:git:check"),
                InlineKeyboardButton("⚡ Forzar Sincronización", callback_data="sentinel:git:force")
            ]
        ]
    else:
        commits_preview = "\n".join(commits[:5])
        text = (
            "🚀 <b>NUEVA ACTUALIZACIÓN DISPONIBLE EN GITHUB</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🏷️ <b>Versión Local:</b> <code>{local_h}</code>\n"
            f"📦 <b>Versión Remota:</b> <code>{remote_h}</code>\n\n"
            f"<b>Últimos cambios ({len(commits)} commits):</b>\n"
            f"{commits_preview}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "<i>Presione el botón para sincronizar forzadamente.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🚀 Actualizar Ahora (Forzar)", callback_data="sentinel:git:force"),
                InlineKeyboardButton("🔄 Recomprobar", callback_data="sentinel:git:check")
            ]
        ]

    await status_msg.edit_text(text, reply_markup=InlineKeyboardMarkup(keyboard), parse_mode="HTML")


async def handle_callback_query(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Manejador interactivo de botones del Bot Centinela."""
    query = update.callback_query
    if not query or not query.data:
        return

    clicker_id = query.from_user.id
    if clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("🛑 Acción denegada: Privilegio exclusivo del Owner.", show_alert=True)
        return

    data = query.data
    await query.answer()

    if data.startswith("sentinel:activate:") or data.startswith("sentinel:migrar:"):
        serial = data.split(":")[-1].strip()
        ok, msg = activate_hardware_first_boot(serial)
        if ok:
            await query.edit_message_text(
                f"🎉 <b>{msg}</b>\n\n"
                "✅ <b>Hardware anclado exitosamente en este servidor.</b> Reiniciando servicio de monitoreo en segundo plano...",
                parse_mode="HTML"
            )
            try:
                subprocess.run(["sudo", "systemctl", "restart", "tg-admin-bot"], check=False)
            except Exception as e:
                logger.error(f"Error reiniciando tg-admin-bot: {e}")
        else:
            await query.edit_message_text(f"❌ <b>Fallo de activación:</b> {msg}", parse_mode="HTML")

    elif data.startswith("sentinel:block:"):
        serial = data.replace("sentinel:block:", "").strip()
        trip_deadman_switch(reason=f"Clon bloqueado manualmente por el Owner vía Centinela (Serial {serial})")
        await query.edit_message_text(
            "🔒 <b>CLON BLOQUEADO Y PURGADO EXITOSAMENTE</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "El anclaje criptográfico local ha sido invalidado permanentemente. "
            "El equipo infractor no podrá iniciar el bot ni interferir con la red corporativa.",
            parse_mode="HTML"
        )

    elif data == "sentinel:menu:status":
        telem = get_node_telemetry()
        await query.edit_message_text(
            f"📊 <b>TELEMETRÍA DEL NODO ({telem['hostname']})</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🖥️ <b>Host:</b> <code>{telem['hostname']}</code>\n"
            f"🌐 <b>IPs:</b> <code>{', '.join(telem['ips'])}</code>\n"
            f"🔒 <b>Estado DRM:</b> <code>{telem['state']}</code>\n"
            f"📡 <b>Canal Red:</b> <code>{_ACTIVE_CONNECTION_LABEL}</code>\n",
            parse_mode="HTML"
        )

    elif data == "sentinel:menu:restart_main":
        await query.edit_message_text("🔄 Reiniciando servicio <code>tg-admin-bot</code>...", parse_mode="HTML")
        try:
            subprocess.run(["sudo", "systemctl", "restart", "tg-admin-bot"], check=False)
            await query.message.reply_text("✅ Servicio <code>tg-admin-bot</code> reiniciado correctamente.", parse_mode="HTML")
        except Exception as e:
            await query.message.reply_text(f"❌ Error al reiniciar servicio: {e}")

    elif data == "sentinel:git:force":
        await query.edit_message_text(
            "⏳ <b>Ejecutando actualización forzada con GitHub (git reset --hard)...</b>\n"
            "<i>Descargando archivos, validando sintaxis y reiniciando servicios...</i>",
            parse_mode="HTML"
        )
        from monitor.system_updater import execute_git_update
        report = await execute_git_update(bot_instance=context.bot)
        await query.message.reply_text(report, parse_mode="HTML")

    elif data == "sentinel:git:check":
        await query.edit_message_text("🔍 <b>Comprobando actualizaciones en GitHub...</b>", parse_mode="HTML")
        from monitor.system_updater import check_updates
        res = await check_updates(bot_instance=context.bot)
        if not res.get("success"):
            err = res.get("error", "Error desconocido")
            await query.edit_message_text(
                f"❌ <b>Error al conectar con GitHub:</b>\n<pre>{html.escape(err)}</pre>",
                parse_mode="HTML"
            )
        elif not res.get("has_updates"):
            await query.edit_message_text(
                f"✅ <b>El sistema está al día.</b>\n"
                f"🏷️ Versión: <code>{res.get('local_hash')}</code>\n"
                f"🖥️ Servidor: <code>{platform.node()}</code>",
                parse_mode="HTML"
            )
        else:
            await query.edit_message_text(
                f"🚀 <b>Hay actualizaciones disponibles:</b> <code>{res.get('remote_hash')}</code>\n\n"
                f"Use el botón para aplicar la sincronización forzada.",
                reply_markup=InlineKeyboardMarkup([
                    [InlineKeyboardButton("⚡ Forzar Actualización Ahora", callback_data="sentinel:git:force")]
                ]),
                parse_mode="HTML"
            )


# Conversación interactiva de migración de token
async def start_migration_conversation(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Inicia el flujo conversacional interactivo de migración en caliente."""
    if not await require_owner(update):
        return ConversationHandler.END

    telem = get_node_telemetry()
    msg_text = (
        "🔄 <b>ASISTENTE DE MIGRACIÓN EN CALIENTE (ZERO-DOWNTIME)</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Servidor Local:</b> <code>{telem['hostname']}</code>\n"
        f"🌐 <b>IPs:</b> <code>{', '.join(telem['ips'])}</code>\n\n"
        "Este asistente reasignará la identidad del bot sin colisiones de polling.\n\n"
        "1️⃣ Ve a @BotFather y copia el <b>Nuevo Token de Telegram</b> que se le asignará al servidor de origen (Laboratorio).\n"
        "2️⃣ <b>Pega y envía el nuevo Token</b> como respuesta a este mensaje.\n\n"
        "⏳ <i>Tienes 2 minutos para responder. Envía <code>/cancelar</code> para abortar.</i>"
    )

    if update.callback_query:
        await update.callback_query.answer()
        await update.callback_query.edit_message_text(msg_text, parse_mode="HTML")
    else:
        await update.message.reply_text(msg_text, parse_mode="HTML")

    context.user_data["migr_start_ts"] = time.time()
    return STATE_WAITING_MIGRATION_TOKEN


async def handle_migration_token_input(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Valida el nuevo token y ejecuta la transición sin colisión."""
    if not await require_owner(update):
        return ConversationHandler.END

    start_ts = context.user_data.get("migr_start_ts", 0)
    if time.time() - start_ts > 120:
        await update.message.reply_text(
            "⏱️ <b>Operación Expirada:</b> Ha transcurrido el tiempo límite de 2 minutos. Migración cancelada.",
            parse_mode="HTML"
        )
        return ConversationHandler.END

    token_candidate = update.message.text.strip() if update.message and update.message.text else ""

    if token_candidate.lower() in ("/cancelar", "cancelar", "/cancel", "abortar"):
        await update.message.reply_text("🛑 <b>Migración cancelada:</b> No se realizaron cambios.", parse_mode="HTML")
        return ConversationHandler.END

    status_msg = await update.message.reply_text("🔄 Validando nuevo token contra la API de Telegram...")

    # Validar token
    try:
        async with httpx.AsyncClient(timeout=8.0) as client:
            r = await client.get(f"https://api.telegram.org/bot{token_candidate}/getMe")
            if r.status_code != 200 or not r.json().get("ok"):
                await status_msg.edit_text(
                    f"❌ <b>Token Rechazado por Telegram (HTTP {r.status_code}):</b> Verifique el código de BotFather e intente nuevamente con <code>/migrar</code>.",
                    parse_mode="HTML"
                )
                return ConversationHandler.END
            bot_info = r.json().get("result", {})
            bot_username = bot_info.get("username", "Desconocido")
    except Exception as e:
        await status_msg.edit_text(f"❌ Error conectando con Telegram: {e}", parse_mode="HTML")
        return ConversationHandler.END

    # Aplicar migración de token
    ok, msg = migrate_core_token(token_candidate)
    if ok:
        await status_msg.edit_text(
            f"🎉 <b>MIGRACIÓN EN CALIENTE COMPLETADA EXITOSAMENTE</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"✅ <b>Nuevo Token Reasignado:</b> <code>@{bot_username}</code>\n"
            "🔒 <b>Cifrado DRM:</b> Re-encriptado con la Clave de Hardware local.\n\n"
            "🚀 <b>Reiniciando servicio de monitoreo en 3 segundos para aplicar la nueva identidad...</b>",
            parse_mode="HTML"
        )
        # Reiniciar bot de monitoreo
        try:
            subprocess.run(["sudo", "systemctl", "restart", "tg-admin-bot"], check=False)
        except Exception as e:
            logger.error(f"Error reiniciando tg-admin-bot tras migración: {e}")
    else:
        await status_msg.edit_text(f"❌ <b>Error al aplicar migración:</b> {msg}", parse_mode="HTML")

    return ConversationHandler.END


async def cancel_migration(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Cancela el flujo de migración."""
    if update.message:
        await update.message.reply_text("🛑 Migración cancelada.", parse_mode="HTML")
    return ConversationHandler.END


def main() -> None:
    """Punto de entrada principal del servicio Bot Centinela."""
    logger.info("🛡️ Iniciando Sentinel Bot (The_Master_bot / nimda_control_bot)...")

    sentinel_token = get_sentinel_token()
    if not sentinel_token:
        logger.critical("❌ No se pudo desofuscar el Token del Bot Centinela. Abortando.")
        sys.exit(1)

    active_proxy = select_working_connection(sentinel_token)

    # Configurar clientes HTTP con soporte de proxies
    request = HTTPXRequest(
        connection_pool_size=128,
        connect_timeout=15.0,
        read_timeout=35.0,
        write_timeout=35.0,
        pool_timeout=10.0,
        proxy=active_proxy
    )
    get_updates_request = HTTPXRequest(
        connection_pool_size=1,
        connect_timeout=15.0,
        read_timeout=35.0,
        write_timeout=35.0,
        pool_timeout=10.0,
        proxy=active_proxy
    )

    application = (
        Application.builder()
        .token(sentinel_token)
        .request(request)
        .get_updates_request(get_updates_request)
        .build()
    )

    # Conversación de Migración
    migration_handler = ConversationHandler(
        entry_points=[
            CommandHandler(["migrar", "migracion", "cambiar_nodo"], start_migration_conversation),
            CallbackQueryHandler(start_migration_conversation, pattern=r"^sentinel:menu:migrar$")
        ],
        states={
            STATE_WAITING_MIGRATION_TOKEN: [
                MessageHandler(filters.TEXT & ~filters.COMMAND, handle_migration_token_input),
                CommandHandler("cancelar", cancel_migration)
            ]
        },
        fallbacks=[CommandHandler("cancelar", cancel_migration)],
        conversation_timeout=120
    )

    application.add_handler(migration_handler)
    application.add_handler(CommandHandler(["start", "help", "ayuda"], cmd_start))
    application.add_handler(CommandHandler(["status", "estado", "nodos", "telemetria"], cmd_status))
    application.add_handler(CommandHandler(["activar", "auth"], cmd_activar))
    application.add_handler(CommandHandler(["reiniciar_bot", "restart_bot", "reiniciar_principal"], cmd_reiniciar_bot))
    application.add_handler(CommandHandler(["actualizar", "update", "git_update", "forzar_actualizacion"], cmd_actualizar))
    application.add_handler(CallbackQueryHandler(handle_callback_query, pattern=r"^sentinel:"))

    logger.info(f"✅ Sentinel Bot iniciado y escuchando (Conexión: {_ACTIVE_CONNECTION_LABEL})")
    application.run_polling(
        bootstrap_retries=-1,
        poll_interval=1.0,
        timeout=10,
        drop_pending_updates=False
    )


if __name__ == "__main__":
    main()
