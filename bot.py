"""
# ==============================================================================
# 🤖 BOT PRINCIPAL: MONITOR VALLE SECO (@IA_ValleSeco_bot)
# Punto de entrada principal y fachada desacoplada para el servicio Telegram.
# Arquitectura SOLID - Principio de Responsabilidad Única y Delegación Modular.
# Orquestación y ciclo de vida extraídos a bot_core/application/bootstrap.py (ARC-001).
# Ubicación: /opt/sonar/bot.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import logging
import platform
import builtins
builtins.platform = platform
from typing import Any, Dict, Optional

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
    clear_auto_rollback_notice,
    ensure_golden_backup,
    env_int,
    get_auto_rollback_notice,
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
from bot_core.handlers.services import (
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
from bot_core.handlers.network import (
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
from bot_core.handlers.alerts import (
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
from bot_core.services.proxy_cache import (
    ProxyInflightGuard,
    get_cached_proxy_result,
    save_cached_proxy_result,
)
from bot_core.application.bootstrap import (
    BotApplicationBootstrapper,
    handle_bot_error,
)

# ------------------------------------------------------------------------------
# 2.1 DELIMITACIÓN FORMAL DE API PÚBLICA / FACHADA (ARCH-001)
# ------------------------------------------------------------------------------
__all__ = (
    # Ciclo de vida y orquestación
    "main",
    "bot_error_handler",
    "register_all_handlers",
    # Estado global y configuración
    "CONFIG",
    "COMMANDS",
    "MESSAGES",
    "SYSTEM_PROMPT",
    "BASE_DIR",
    "AI_DIR",
    "DOCS_DIR",
    "SYSTEM_PROMPT_PATH",
    "load_config",
    "save_config",
    "ensure_golden_backup",
    "restore_golden_backup",
    "env_int",
    "clear_auto_rollback_notice",
    "get_auto_rollback_notice",
    # Bóveda de hardware y seguridad criptográfica
    "get_bot_token",
    "get_owner_id",
    "is_core_operational",
    "get_core_status",
    "activate_hardware_first_boot",
    "migrate_core_token",
    "check_authorization",
    "is_authorized",
    "log_security_audit_event",
    "require_private_chat",
    "get_security_warning_html",
    # Red y proxies
    "ProxyInflightGuard",
    "get_cached_proxy_result",
    "save_cached_proxy_result",
    "select_working_connection",
    "runtime_connection_watchdog",
    "trigger_connection_failover",
    "load_proxies_list",
    "restart_bot_process",
    "async_evaluate_best_connection",
    # Utilidades y sanitización
    "sanitizar_pii",
    "check_ai_rate_limit",
    "markdown_to_telegram_html",
    "safe_reply_html",
    "safe_deliver_update_result",
    "split_message",
    "AI_RATE_LIMITS",
    "run_command_async",
    "_exec_system_command",
)

# ------------------------------------------------------------------------------
# Inversión de Dependencias (DIP): Delegación al proveedor de seguridad formal
# ------------------------------------------------------------------------------
_SECURITY_PROVIDER: SecurityProviderProtocol = get_default_security_provider()


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
# 4. MANEJADOR GLOBAL DE ERRORES (DELEGACIÓN A APLICACIÓN)
# ------------------------------------------------------------------------------
async def bot_error_handler(update: object, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Registra y gestiona errores inesperados o fallos de red durante el polling."""
    await handle_bot_error(update, context, _SECURITY_PROVIDER)


# ------------------------------------------------------------------------------
# 5. PUNTO DE ENTRADA PRINCIPAL (MAIN - DELEGADO A BOOTSTRAPPER CON DIP)
# ------------------------------------------------------------------------------
def main(security_provider: Optional[SecurityProviderProtocol] = None) -> None:
    """
    Punto de entrada principal: orquestación de red, background workers y polling
    delegados a BotApplicationBootstrapper conforme a SRP y DIP (ARC-001).
    """
    global _BOT_APP_INSTANCE
    provider = security_provider or _SECURITY_PROVIDER
    bootstrapper = BotApplicationBootstrapper(
        provider=provider,
        config=CONFIG,
        commands=COMMANDS,
    )
    application = bootstrapper.create_application()
    if not application:
        return

    _BOT_APP_INSTANCE = application
    bootstrapper.run_polling(application)


if __name__ == "__main__":
    main()
