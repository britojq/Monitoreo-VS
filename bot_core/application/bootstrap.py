"""
# ==============================================================================
# 🚀 ORQUESTACIÓN Y ARRANQUE DE APLICACIÓN: bootstrap.py (@IA_ValleSeco_bot)
# Desacoplamiento arquitectónico del ciclo de vida, workers y bootstrap (ARC-001)
# Ubicación: /opt/sonar/bot_core/application/bootstrap.py
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
from typing import Any, Dict, List, Optional, Tuple

import httpx
import telegram
from telegram import BotCommand, BotCommandScopeChat, BotCommandScopeDefault, Update
from telegram.ext import Application, ContextTypes
from telegram.request import HTTPXRequest

from bot_core.contracts.security_provider import (
    SecurityProviderProtocol,
    get_default_security_provider,
)
from bot_core.router import register_all_handlers
from bot_core.services.ai_chat_service import load_commands_data
from bot_core.services.config_manager import (
    clear_auto_rollback_notice,
    ensure_golden_backup,
    get_auto_rollback_notice,
    load_config,
)
from bot_core.services.connection_service import (
    runtime_connection_watchdog,
    select_working_connection,
    trigger_connection_failover,
)

logger = logging.getLogger(__name__)

_CONSECUTIVE_NETWORK_ERRORS: int = 0


async def handle_bot_error(
    update: object,
    context: ContextTypes.DEFAULT_TYPE,
    provider: Optional[SecurityProviderProtocol] = None,
) -> None:
    """Registra y gestiona errores inesperados, colisiones de sesión o fallos de red durante el polling."""
    global _CONSECUTIVE_NETWORK_ERRORS
    err = context.error
    active_provider = provider or get_default_security_provider()

    if isinstance(err, telegram.error.Conflict):
        logger.error(f"🚨 COLISIÓN DE INSTANCIAS DETECTADA (Conflict 409): {err}")
        try:
            active_provider.send_conflict_alert()
        except Exception as e_alert:
            logger.error(f"Error enviando alerta de conflicto: {e_alert}")
        await asyncio.sleep(25)

    elif isinstance(err, (telegram.error.NetworkError, telegram.error.TimedOut, httpx.NetworkError, httpx.TimeoutException)):
        _CONSECUTIVE_NETWORK_ERRORS += 1
        logger.warning(f"Aviso de red en polling Telegram (fallo #{_CONSECUTIVE_NETWORK_ERRORS}): {err}")
        if _CONSECUTIVE_NETWORK_ERRORS >= 8:
            async def _delayed_failover() -> None:
                await asyncio.sleep(15)
                if _CONSECUTIVE_NETWORK_ERRORS >= 8:
                    await trigger_connection_failover(reason=f"8 fallos consecutivos en polling ({err})")
            asyncio.create_task(_delayed_failover())
    else:
        logger.error(f"Excepción en bot handler: {err}", exc_info=err)


class BotApplicationBootstrapper:
    """
    Bootstrapper modular de la aplicación Telegram.
    Encapsula configuración de red, workers en segundo plano y registro de handlers (ARC-001).
    """

    def __init__(
        self,
        provider: Optional[SecurityProviderProtocol] = None,
        config: Optional[Dict[str, Any]] = None,
        commands: Optional[Dict[str, Any]] = None,
    ) -> None:
        self.provider: SecurityProviderProtocol = provider or get_default_security_provider()
        self.config: Dict[str, Any] = config if config is not None else load_config()
        if commands is None:
            _, cmds = load_commands_data()
            self.commands: Dict[str, Any] = cmds
        else:
            self.commands = commands
        self.application: Optional[Application] = None

    def build_http_requests(self, active_proxy: Optional[str] = None) -> Tuple[HTTPXRequest, HTTPXRequest]:
        """Construye las instancias HTTPXRequest optimizadas con pool de conexiones y timeouts corporativos."""
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
        return request, get_updates_request

    async def _sync_telegram_commands(self, app: Application, owner_id: int) -> None:
        """Sincroniza el menú de comandos en los clientes de Telegram."""
        try:
            default_commands = [
                BotCommand("start", "Iniciar interacción y validar acceso"),
                BotCommand("menu", "Desplegar teclado interactivo"),
                BotCommand("ayuda", "Guía interactiva de operaciones"),
                BotCommand("estado", "Resumen operacional general"),
                BotCommand("sedes", "Matriz de conectividad de sedes"),
                BotCommand("servicios", "Estado de servicios corporativos"),
                BotCommand("radar", "Tráfico y saturación WAN"),
                BotCommand("silenciar", "Silenciar notificaciones"),
                BotCommand("alertas", "Gestión de incidentes"),
                BotCommand("cancelar", "Cancelar operación activa"),
                BotCommand("version", "Detalle de release y hash"),
                BotCommand("ai", "Consultar Asistente Corporativo"),
                BotCommand("info", "Términos y seguridad corporativa"),
            ]
            await app.bot.set_my_commands(default_commands, scope=BotCommandScopeDefault())

            if owner_id:
                owner_commands = default_commands + [
                    BotCommand("sedes_up", "Sedes operativas online"),
                    BotCommand("sedes_down", "Sedes caídas o con alarma"),
                    BotCommand("equipos_up", "Equipos y nodos online"),
                    BotCommand("equipos_down", "Equipos fuera de línea"),
                    BotCommand("topologia", "Matriz y topología WAN/LAN"),
                    BotCommand("analisis_red", "Escaneo y análisis de red"),
                    BotCommand("netflow", "Telemetría y flujos de red"),
                    BotCommand("syslog", "Visor de eventos Syslog"),
                    BotCommand("snmp", "Diagnóstico métricas SNMP"),
                    BotCommand("traps", "Monitor trampas SNMP"),
                    BotCommand("wol", "Encendido remoto Wake-on-LAN"),
                    BotCommand("captura_web", "Evidencia visual del portal"),
                    BotCommand("reporte_completo", "Auditoría integral ejecutiva"),
                    BotCommand("reporte_sedes", "Consolidado técnico sedes"),
                    BotCommand("reporte_servicios", "Consolidado servicios"),
                    BotCommand("reporte_caidas", "Histórico de incidentes"),
                    BotCommand("ssl", "Auditoría certificados SSL"),
                    BotCommand("inventario", "Hardware y activos TI"),
                    BotCommand("configs", "Auditoría switches Cisco/Huawei"),
                    BotCommand("ups", "Telemetría y autonomía eléctrica"),
                    BotCommand("predicciones", "Capacidad y proyección WAN"),
                    BotCommand("ack", "Reconocimiento de incidentes"),
                    BotCommand("resolver", "Cierre manual de alarmas"),
                    BotCommand("mantenimiento", "Ventana de mantenimiento"),
                    BotCommand("reglas", "Políticas de supresión"),
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

    async def _check_auto_rollback_notice(self, app: Application, owner_id: int) -> None:
        """Verificador de Autorrecuperación (Auto-Rollback vía MariaDB SSOT - SEC-010)."""
        try:
            info = get_auto_rollback_notice()
            if info:
                cp_id = info.get("checkpoint_id", "LATEST")
                desc = info.get("description", "Recuperación automática de estado seguro")
                dt = info.get("datetime", "Reciente")
                if owner_id:
                    alert_msg = (
                        "⚠️ <b>Alerta de Autorrecuperación (Auto-Rollback)</b>\n"
                        "━━━━━━━━━━━━\n"
                        "El sistema detectó corrupción de archivos o apagón imprevisto durante el arranque.\n\n"
                        "🔄 <b>Acción:</b> <code>Rollback Automático Ejecutado</code>\n"
                        "✅ <b>Estado Actual:</b> <code>Sistema Restaurado y Operativo</code>\n"
                        f"📁 <b>Punto Restaurado:</b> <code>{html.escape(str(cp_id))}</code>\n"
                        f"📝 <b>Detalle:</b> <i>{html.escape(str(desc))}</i>\n"
                        f"⏰ <b>Fecha:</b> <code>{html.escape(str(dt))}</code>\n"
                        "━━━━━━━━━━━━\n"
                        "<i>El bot se encuentra en línea y completamente funcional.</i>"
                    )
                    await app.bot.send_message(chat_id=owner_id, text=alert_msg, parse_mode="HTML")
                clear_auto_rollback_notice()
        except Exception as err:
            logger.error(f"Error procesando notificación de auto-rollback: {err}")

    async def _check_post_update_restart_notice(self, app: Application, owner_id: int) -> None:
        """Verificador de Confirmación Post-Reinicio tras Actualización Git."""
        try:
            from monitor.alerts.boot_alert import get_bot_setting, set_bot_setting
            pending_json = await asyncio.to_thread(get_bot_setting, "pending_restart_notification", None)
            if pending_json:
                await asyncio.to_thread(set_bot_setting, "pending_restart_notification", "")
                data = json.loads(pending_json)
                commit = data.get("new_commit", "N/A")
                msg = html.escape(data.get("commit_message", "Actualización del sistema"))
                duration = data.get("duration_seconds", "N/A")

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
                        "<i>Asistente Corporativo listo para recibir instrucciones.</i>"
                    )
                    await app.bot.send_message(
                        chat_id=int(owner_id),
                        text=notice_msg,
                        parse_mode="HTML",
                        disable_web_page_preview=True,
                    )
        except Exception as err:
            logger.error(f"Error procesando notificación de confirmación post-actualización: {err}")

    def create_application(self) -> Optional[Application]:
        """Construye y configura la instancia Application de Telegram lista para ejecución."""
        bot_token = self.provider.get_bot_token()
        if not bot_token:
            logger.error("Error: Token de Telegram no configurado en el proveedor de seguridad.")
            return None

        # Selección y conmutación automática de conexión (Directa vs Proxies corporativos)
        active_proxy = None
        if self.config.get("auto_proxy_failover", True):
            active_proxy = select_working_connection(bot_token)

        request, get_updates_request = self.build_http_requests(active_proxy)

        async def on_post_init(app: Application) -> None:
            """Inicia tareas en segundo plano del bot (Watchdog de red, actualizaciones y auto-rollback)."""
            from monitor.gitops import auto_update_worker

            # 1. Centinela de Conmutación Dinámica en Caliente (Watchdog)
            if self.config.get("auto_proxy_failover", True):
                asyncio.create_task(runtime_connection_watchdog(app))

            # 2. Actualizador Autónomo Git
            asyncio.create_task(
                auto_update_worker(
                    bot_instance=app.bot,
                    get_owner_id_func=lambda: self.config.get("owner_id", 0),
                    get_config_func=lambda: self.config,
                )
            )

            owner_id = self.provider.get_owner_id() or self.config.get("owner_id", 0)

            # 3. Verificador de Autorrecuperación
            asyncio.create_task(self._check_auto_rollback_notice(app, owner_id))

            # 4. Verificador de Confirmación Post-Reinicio
            asyncio.create_task(self._check_post_update_restart_notice(app, owner_id))

            # 5. Sincronización de Comandos
            asyncio.create_task(self._sync_telegram_commands(app, owner_id))

        application = (
            Application.builder()
            .token(bot_token)
            .request(request)
            .get_updates_request(get_updates_request)
            .post_init(on_post_init)
            .build()
        )

        async def _error_callback(update: object, context: ContextTypes.DEFAULT_TYPE) -> None:
            await handle_bot_error(update, context, self.provider)

        application.add_error_handler(_error_callback)
        ensure_golden_backup()

        commands_enabled = bool(self.config.get("commands_enabled", True))
        register_all_handlers(application, commands_dict=self.commands, commands_enabled=commands_enabled)

        self.application = application
        return application

    def run_polling(self, app: Optional[Application] = None) -> None:
        """Inicia el bucle de polling persistente de Telegram."""
        target_app = app or self.application or self.create_application()
        if not target_app:
            logger.error("No se puede iniciar el polling: la aplicación no pudo ser creada.")
            return

        target_app.run_polling(
            allowed_updates=Update.ALL_TYPES,
            bootstrap_retries=-1,
            poll_interval=1.0,
            timeout=10,
            drop_pending_updates=False,
        )

    def run(self) -> None:
        """Orquesta el ciclo completo de inicialización y ejecución del bot."""
        app = self.create_application()
        if app:
            self.run_polling(app)
