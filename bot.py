"""
# ==============================================================================
# 🤖 BOT PRINCIPAL: MONITOR VALLE SECO (@IA_ValleSeco_bot)
# Punto de entrada principal y fachada desacoplada para el servicio Telegram.
# Arquitectura SOLID - Principio de Responsabilidad Única y Delegación Modular.
# Ubicación: /scripts/telegram-admin-bot/bot.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import os
import platform
import builtins
builtins.platform = platform
import re
import shutil
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import httpx
import telegram
from telegram import Update
from telegram.request import HTTPXRequest
from telegram.ext import (
    Application,
    ContextTypes,
)

# ------------------------------------------------------------------------------
# 1. LOGGING CORPORATIVO
# ------------------------------------------------------------------------------
logging.basicConfig(
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    level=logging.INFO,
)
logger = logging.getLogger(__name__)

# ------------------------------------------------------------------------------
# 2. IMPORTACIONES Y RE-EXPORTACIONES DESDE BOT_CORE (SOLID & RETROCOMPATIBILIDAD)
# ------------------------------------------------------------------------------
from bot_core.utils.telegram_helpers import (
    AI_RATE_LIMITS,
    check_ai_rate_limit,
    markdown_to_telegram_html,
    safe_deliver_update_result,
    safe_reply_html,
    sanitizar_pii,
    split_message,
)
from bot_core.utils.system_helpers import (
    _exec_system_command,
    run_command_async,
)
from bot_core.services.config_manager import (
    AI_DIR,
    BASE_DIR,
    DOCS_DIR,
    SYSTEM_PROMPT_PATH,
    ensure_golden_backup,
    env_int,
    load_config,
    restore_golden_backup,
    save_config,
)
from bot_core.security.auth import (
    MSG_UNAUTHORIZED_ADMIN_COMMAND,
    MSG_UNAUTHORIZED_GROUP_COMMAND,
    check_authorization,
    get_security_warning_html,
    is_authorized,
    log_security_audit_event,
    require_private_chat,
)
from bot_core.services.connection_service import (
    ACTIVE_CONNECTION_LABEL,
    ACTIVE_PROXY_URL,
    _CONSECUTIVE_NETWORK_ERRORS,
    _FAILOVER_LOCK,
    _LAST_SUCCESSFUL_POLL_TIME,
    _check_endpoint_health,
    async_evaluate_best_connection,
    load_proxies_list,
    restart_bot_process,
    runtime_connection_watchdog,
    select_working_connection,
    trigger_connection_failover,
)
from bot_core.services.ai_chat_service import (
    _animate_waiting_message,
    _ask_ollama_sync,
    _build_ollama_messages,
    append_to_history,
    ask_ollama_async,
    handle_chat_message,
    handle_dynamic_command,
    load_commands_data,
    load_system_prompt,
    register_ai_routes,
    reset_chat,
)
from bot_core.handlers.base_handlers import (
    start,
    unknown_cmd,
)
from bot_core.handlers.service_handlers import (
    _run_and_send_monitoring_report,
    cmd_captura_web,
    cmd_debug_completo,
    cmd_debug_sedes,
    cmd_debug_servicios,
    cmd_internet,
    cmd_reporte_caidas,
    cmd_reporte_completo,
    cmd_reporte_sedes,
    cmd_reporte_servicios,
    cmd_ssl,
    register_service_routes,
)
from bot_core.handlers.network_handlers import (
    cmd_analisis_red,
    cmd_cluster,
    cmd_discovery,
    cmd_ip,
    cmd_netflow,
    cmd_snmp,
    cmd_syslog,
    cmd_topologia,
    cmd_traps,
    cmd_wol,
    register_network_routes,
)
from bot_core.handlers.itam_handlers import (
    cmd_configs,
    cmd_inventario,
    cmd_predicciones,
    cmd_ups,
    register_itam_routes,
)
from bot_core.handlers.alert_handlers import (
    cmd_ack,
    cmd_alertas,
    cmd_mantenimiento,
    cmd_reglas,
    cmd_resolver,
    cmd_silenciar,
    handle_alert_action_callback,
    register_alert_routes,
)
from bot_core.router import register_all_handlers
from bot_core.contracts.security_provider import (
    SecurityProviderProtocol,
    get_default_security_provider,
)
from monitor.proxy_cache import (
    ProxyInflightGuard,
    get_cached_proxy_result,
    save_cached_proxy_result,
)

# ------------------------------------------------------------------------------
# Inversión de Dependencias (DIP): Delegación al proveedor de seguridad formal
# ------------------------------------------------------------------------------
_SECURITY_PROVIDER: SecurityProviderProtocol = get_default_security_provider()

# Funciones de acceso dinámico al proveedor de seguridad formal (sin secretos en variables globales)
def get_bot_token() -> str:
    """Obtiene el token del bot de forma efímera desde el proveedor de seguridad."""
    return _SECURITY_PROVIDER.get_bot_token()


def get_owner_id() -> int:
    """Obtiene el identificador del propietario desde el proveedor de seguridad."""
    return _SECURITY_PROVIDER.get_owner_id()


def __getattr__(name: str) -> Any:
    """Resuelve atributos de compatibilidad histórica bajo demanda sin exponer secretos en el ámbito global."""
    if name == "IMMUTABLE_BOT_TOKEN":
        return _SECURITY_PROVIDER.get_bot_token()
    if name == "IMMUTABLE_OWNER_ID":
        return _SECURITY_PROVIDER.get_owner_id()
    if name == "IMMUTABLE_AUTO_UPDATE_ENABLED":
        return _SECURITY_PROVIDER.is_auto_update_enabled()
    if name == "IMMUTABLE_GIT_REPO_URL":
        return _SECURITY_PROVIDER.get_git_repo_url()
    if name == "IMMUTABLE_GIT_BRANCH":
        return _SECURITY_PROVIDER.get_git_branch()
    raise AttributeError(f"module '{__name__}' has no attribute '{name}'")


def is_core_operational() -> bool:
    """Verifica si el núcleo de seguridad física se encuentra operativo."""
    return _SECURITY_PROVIDER.is_core_operational()


def get_core_status() -> Dict[str, Any]:
    """Obtiene el diagnóstico operativo del núcleo y hardware vault."""
    return _SECURITY_PROVIDER.get_core_status()


def activate_hardware_first_boot() -> bool:
    """Inicializa la bóveda física en el primer arranque del servidor."""
    return _SECURITY_PROVIDER.activate_first_boot()


def migrate_core_token(new_token: str) -> bool:
    """Cifra y persiste un nuevo token en la bóveda de hardware."""
    return _SECURITY_PROVIDER.migrate_token(new_token)


# ------------------------------------------------------------------------------
# 3. VARIABLES GLOBALES DE ESTADO Y CONFIGURACIÓN
# ------------------------------------------------------------------------------
CONFIG: Dict[str, Any] = load_config()
MESSAGES, COMMANDS = load_commands_data()
SYSTEM_PROMPT: str = load_system_prompt(CONFIG)
_BOT_APP_INSTANCE: Optional[Application] = None


# ------------------------------------------------------------------------------
# 4. MANEJADOR GLOBAL DE ERRORES (TELEGRAM / HTTPX / CONFLICT)
# ------------------------------------------------------------------------------
async def bot_error_handler(update: object, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Registra y gestiona errores inesperados, colisiones de sesión o fallos de red durante el polling."""
    global _CONSECUTIVE_NETWORK_ERRORS
    err = context.error

    if isinstance(err, telegram.error.Conflict):
        logger.error(f"🚨 COLISIÓN DE INSTANCIAS DETECTADA (Conflict 409): {err}")
        try:
            _SECURITY_PROVIDER.send_conflict_alert()
        except Exception as e_alert:
            logger.error(f"Error enviando alerta de conflicto: {e_alert}")
        await asyncio.sleep(25)

    elif isinstance(err, (telegram.error.NetworkError, telegram.error.TimedOut, httpx.NetworkError, httpx.TimeoutException)):
        _CONSECUTIVE_NETWORK_ERRORS += 1
        logger.warning(f"Aviso de red en polling Telegram (fallo #{_CONSECUTIVE_NETWORK_ERRORS}): {err}")
        if _CONSECUTIVE_NETWORK_ERRORS >= 8:
            async def _delayed_failover():
                await asyncio.sleep(15)
                if _CONSECUTIVE_NETWORK_ERRORS >= 8:
                    await trigger_connection_failover(reason=f"8 fallos consecutivos en polling ({err})")
            asyncio.create_task(_delayed_failover())
    else:
        logger.error(f"Excepción en bot handler: {err}", exc_info=err)


# ------------------------------------------------------------------------------
# 5. PUNTO DE ENTRADA PRINCIPAL (MAIN - CON INYECCIÓN DE DEPENDENCIAS)
# ------------------------------------------------------------------------------
def main(security_provider: Optional[SecurityProviderProtocol] = None) -> None:
    """Punto de entrada principal: configuración de red, background workers y polling."""
    global _BOT_APP_INSTANCE
    provider = security_provider or _SECURITY_PROVIDER
    bot_token = provider.get_bot_token()

    if not bot_token:
        logger.error("Error: Token de Telegram no configurado en el proveedor de seguridad.")
        return

    # Selección y conmutación automática de conexión (Directa vs Proxies corporativos)
    active_proxy = None
    if CONFIG.get("auto_proxy_failover", True):
        active_proxy = select_working_connection(bot_token)

    request = HTTPXRequest(
        connection_pool_size=256,
        connect_timeout=15.0,
        read_timeout=35.0,
        write_timeout=35.0,
        pool_timeout=10.0,
        proxy=active_proxy,
    )
    get_updates_request = HTTPXRequest(
        connection_pool_size=1,
        connect_timeout=15.0,
        read_timeout=35.0,
        write_timeout=35.0,
        pool_timeout=10.0,
        proxy=active_proxy,
    )

    async def on_post_init(app: Application) -> None:
        """Inicia tareas en segundo plano del bot (Watchdog de red, actualizaciones y auto-rollback)."""
        from monitor.system_updater import auto_update_worker

        # 1. Centinela de Conmutación Dinámica en Caliente (Watchdog)
        if CONFIG.get("auto_proxy_failover", True):
            asyncio.create_task(runtime_connection_watchdog(app))

        # 2. Actualizador Autónomo Git
        asyncio.create_task(
            auto_update_worker(
                bot_instance=app.bot,
                get_owner_id_func=lambda: CONFIG.get("owner_id", 0),
                get_config_func=lambda: CONFIG,
            )
        )

        # 3. Verificador de Autorrecuperación (Auto-Rollback)
        async def _check_auto_rollback_notice():
            flag_file = Path("/tmp/.auto_rollback_occurred")
            if flag_file.exists():
                try:
                    with open(flag_file, "r", encoding="utf-8") as f:
                        info = json.load(f)
                    cp_id = info.get("checkpoint_id", "LATEST")
                    desc = info.get("description", "Recuperación automática de estado seguro")
                    dt = info.get("datetime", "Reciente")
                    owner_id = CONFIG.get("owner_id", 0)
                    if owner_id:
                        alert_msg = (
                            "⚠️ <b>Alerta de Autorrecuperación (Auto-Rollback)</b>\n"
                            "━━━━━━━━━━━━\n"
                            "El sistema detectó corrupción de archivos o apagón imprevisto durante el arranque.\n\n"
                            "🔄 <b>Acción:</b> <code>Rollback Automático Ejecutado</code>\n"
                            "✅ <b>Estado Actual:</b> <code>Sistema Restaurado y Operativo</code>\n"
                            f"📁 <b>Punto Restaurado:</b> <code>{html.escape(cp_id)}</code>\n"
                            f"📝 <b>Detalle:</b> <i>{html.escape(desc)}</i>\n"
                            f"⏰ <b>Fecha:</b> <code>{html.escape(dt)}</code>\n"
                            "━━━━━━━━━━━━\n"
                            "<i>El bot se encuentra en línea y completamente funcional.</i>"
                        )
                        await app.bot.send_message(chat_id=owner_id, text=alert_msg, parse_mode="HTML")
                    flag_file.unlink(missing_ok=True)
                except Exception as err:
                    logger.error(f"Error procesando notificación de auto-rollback: {err}")

        asyncio.create_task(_check_auto_rollback_notice())

        # 4. Verificador de Confirmación Post-Reinicio tras Actualización Git
        async def _check_post_update_restart_notice():
            try:
                from monitor.boot_alert import get_bot_setting, set_bot_setting
                pending_json = await asyncio.to_thread(get_bot_setting, "pending_restart_notification", None)
                if pending_json:
                    await asyncio.to_thread(set_bot_setting, "pending_restart_notification", "")
                    data = json.loads(pending_json)
                    commit = data.get("new_commit", "N/A")
                    msg = html.escape(data.get("commit_message", "Actualización del sistema"))
                    duration = data.get("duration_seconds", "N/A")
                    owner_id = provider.get_owner_id() or CONFIG.get("owner_id", 0)

                    # Regla de Oro #1: Solo enviar al chat privado del Owner (chat_id positivo)
                    if owner_id and int(owner_id) > 0:
                        notice_msg = (
                            "🟢 <b>¡SISTEMA REINICIADO Y EN LÍNEA!</b>\n"
                            "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                            "El servicio <code>tg-admin-bot</code> ha completado su reinicio tras la actualización y se encuentra 100% operativo.\n\n"
                            f"🏷️ <b>Commit Activo:</b> <code>{html.escape(commit)}</code>\n"
                            f"📝 <b>Detalle:</b> <i>{msg}</i>\n"
                            f"⏱️ <b>Tiempo de Despliegue:</b> <code>{duration}s</code>\n"
                            "🛡️ <b>Diagnóstico:</b> <code>Portal Web, BD y Cluster API validados exitosamente</code>\n"
                            "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                            "<i>El bot está listo para recibir comandos.</i>"
                        )
                        await app.bot.send_message(chat_id=owner_id, text=notice_msg, parse_mode="HTML")
                        logger.info(f"Notificación post-reinicio enviada al Owner ({commit}).")
            except Exception as e_pnr:
                logger.warning(f"Error procesando pending_restart_notification: {e_pnr}")

        asyncio.create_task(_check_post_update_restart_notice())

        # 5. Sincronizar dinámicamente el menú nativo de comandos en Telegram
        async def _sync_telegram_commands():
            try:
                from telegram import BotCommand, BotCommandScopeChat, BotCommandScopeDefault

                default_commands = [
                    BotCommand("start", "Menú principal y bienvenida"),
                    BotCommand("servicios", "Consultar estado de servicios"),
                    BotCommand("sedes", "Consultar estado de sedes y enlaces"),
                    BotCommand("monitoreo", "Reporte de infraestructura unificado"),
                    BotCommand("ip", "Dirección IP y parámetros de red"),
                    BotCommand("internet", "Diagnóstico de conectividad y proxies"),
                    BotCommand("analisis_red", "Diagnóstico de red local"),
                    BotCommand("reset_ia", "Reiniciar conversación con la IA"),
                    BotCommand("info", "Información legal y aviso de seguridad"),
                ]
                await app.bot.set_my_commands(default_commands, scope=BotCommandScopeDefault())

                owner_id = int(CONFIG.get("owner_id", 0))
                if owner_id:
                    owner_commands = [
                        BotCommand("start", "Panel de control principal"),
                        BotCommand("ip", "Dirección IP y red del servidor"),
                        BotCommand("servicios", "Servicios corporativos (/servicios web, grupo)"),
                        BotCommand("sedes", "Sedes y enlaces (/sedes web)"),
                        BotCommand("caidas", "Servicios caídos e incidentes (/caidas web)"),
                        BotCommand("web", "Captura HD del dashboard web en vivo"),
                        BotCommand("monitoreo", "Reporte unificado (Servicios + Sedes)"),
                        BotCommand("recursos", "Memoria RAM, CPU y optimización"),
                        BotCommand("cron", "Panel de horarios y reportes automáticos"),
                        BotCommand("permisos", "Gestión de usuarios y autorizaciones"),
                        BotCommand("grupos", "Auditar grupos activos y forzar salida"),
                        BotCommand("salir_grupo", "Forzar salida de un grupo por ID"),
                        BotCommand("bloqueo_comandos", "Bloquear/activar comandos para usuarios"),
                        BotCommand("botstatus", "Diagnóstico de conectividad y proxies"),
                        BotCommand("limpiador", "Mantenimiento y limpieza de espacio"),
                        BotCommand("emergencia", "Panel de contingencia y mantenimiento"),
                        BotCommand("actualizar", "Comprobar y aplicar cambios Git"),
                        BotCommand("rollback", "Revertir versión y restaurar BD"),
                        BotCommand("estado_deploy", "Estado del despliegue y respaldos"),
                        BotCommand("desbloquear_update", "Liberar Circuit Breaker"),
                        BotCommand("internet", "Diagnóstico de internet y proxies"),
                        BotCommand("analisis_red", "Captura y análisis profundo de red"),
                        BotCommand("debug_servicios", "Diagnóstico exhaustivo servicios"),
                        BotCommand("debug_sedes", "Diagnóstico exhaustivo sedes"),
                        BotCommand("debug_completo", "Diagnóstico técnico integral"),
                        BotCommand("debug_monitor", "Conmutar modo depuración global"),
                        BotCommand("mensaje", "Difusión masiva a usuarios autorizados"),
                        BotCommand("reinicia", "Reiniciar el servidor host del sistema"),
                        BotCommand("reset_ia", "Reiniciar memoria del asistente IA"),
                        BotCommand("info", "Términos y seguridad corporativa"),
                    ]
                    await app.bot.set_my_commands(owner_commands, scope=BotCommandScopeChat(chat_id=owner_id))
                    logger.info("Comandos nativos de Telegram sincronizados para Default y Owner.")
            except Exception as err:
                logger.warning(f"Error sincronizando comandos nativos con Telegram: {err}")

        asyncio.create_task(_sync_telegram_commands())

    application = (
        Application.builder()
        .token(bot_token)
        .request(request)
        .get_updates_request(get_updates_request)
        .post_init(on_post_init)
        .build()
    )

    _BOT_APP_INSTANCE = application
    application.add_error_handler(bot_error_handler)
    ensure_golden_backup()

    # Registro centralizado de todas las rutas desacopladas
    commands_enabled = bool(CONFIG.get("commands_enabled", True))
    register_all_handlers(application, commands_dict=COMMANDS, commands_enabled=commands_enabled)

    application.run_polling(
        allowed_updates=Update.ALL_TYPES,
        bootstrap_retries=-1,
        poll_interval=1.0,
        timeout=10,
        drop_pending_updates=False,
    )


if __name__ == "__main__":
    main()
