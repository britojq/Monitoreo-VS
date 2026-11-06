"""
# ==============================================================================
# 🔀 ENRUTADOR Y REGISTRADOR DE COMANDOS: router.py
# Principio Abierto/Cerrado (O) y Responsabilidad Única (S).
# Centraliza el registro desacoplado de handlers de Telegram en la aplicación.
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Any, Dict, Optional

from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    MessageHandler,
    filters,
)

logger = logging.getLogger("bot.router")


def register_itam_routes(application: Application) -> None:
    """Registra los comandos de activos, inventario, respaldos y energía (UPS)."""
    from bot_core.handlers.itam_handlers import (
        cmd_configs,
        cmd_inventario,
        cmd_predicciones,
        cmd_ups,
    )

    application.add_handler(CommandHandler(["ups", "energia", "bateria", "power"], cmd_ups))
    application.add_handler(CommandHandler(["inventario", "ciclovida", "garantias", "lifecycle"], cmd_inventario))
    application.add_handler(CommandHandler(["predicciones", "predictivo", "capacidad", "tendencias"], cmd_predicciones))
    application.add_handler(CommandHandler(["configs", "config", "respaldos", "backup"], cmd_configs))
    logger.info("Rutas de ITAM y Hardware registradas exitosamente.")


def register_alert_routes(application: Application) -> None:
    """Registra los comandos y callbacks del sistema de alertas e incidentes."""
    from bot_core.handlers.alerts.router import register_alert_routes as _register_alerts
    _register_alerts(application)


def register_all_handlers(
    application: Application,
    commands_dict: Optional[Dict[str, Any]] = None,
    commands_enabled: bool = True,
) -> None:
    """
    Registra ordenadamente todas las familias de rutas y handlers en la aplicación Telegram.
    Sigue estrictamente el Principio Abierto/Cerrado (OCP) delegando en cada submódulo.
    """
    # 1. Rutas de bienvenida y comandos base
    from bot_core.handlers.base_handlers import start, unknown_cmd
    application.add_handler(CommandHandler(["start", "help", "ayuda"], start))

    # 2. Rutas de administración y seguridad del sistema
    from bot_core.handlers.admin_router import register_admin_routes
    register_admin_routes(application)

    # 3. Rutas de servicios y monitoreo
    from bot_core.handlers.services.router import register_service_routes
    register_service_routes(application)

    # 4. Rutas de red, topología y diagnóstico
    from bot_core.handlers.network.router import register_network_routes
    register_network_routes(application)

    # 5. Rutas de ITAM, inventario y energía
    register_itam_routes(application)

    # 6. Rutas de alertas e incidentes
    from bot_core.handlers.alerts.router import register_alert_routes as _reg_alerts
    _reg_alerts(application)

    # 7. Rutas de IA
    from bot_core.services.ai_chat_service import (
        handle_chat_message,
        handle_dynamic_command,
        register_ai_routes,
    )
    register_ai_routes(application)

    # 8. Comandos dinámicos desde MariaDB (SSOT)
    if commands_enabled and commands_dict:
        reserved_commands = {
            "start", "help", "ayuda", "activar", "autorizar_hardware", "auth_hw",
            "reset_ia", "reset_chat", "borrar_chat", "ia_grupo", "ia_group", "permisos", "autorizados", "whitelist", "usuarios",
            "grupos", "mis_grupos", "listar_grupos", "chat_grupos", "salir_grupo", "leave_group",
            "expulsar_grupo", "abandonar_grupo", "botstatus", "statusbot", "estado_bot", "estatus",
            "status", "estado", "debug_monitor", "monitordebug", "bloqueo_comandos", "bloquear_comandos",
            "lock_commands", "pausar_comandos", "control_comandos", "limpiador", "limpieza", "cleaner",
            "temperatura", "temp", "termal", "cpu_temp", "mensaje", "broadcast", "difusion", "anuncio",
            "comunicado", "reporte_servicios", "servicios", "servicios_grupo", "reporte_sedes", "sedes",
            "sedes_grupo", "sitios", "reporte_caidas", "caidas", "caidas_grupo", "reporte_completo",
            "monitoreo", "monitoreo_grupo", "analisis_red", "red", "escaner_red", "network_scan",
            "internet", "proxy", "proxies", "conectividad", "info", "aviso", "legal", "terminos",
            "migrar_token", "migrartoken", "cambiar_token", "actualizar", "update", "upgrade",
            "git_update", "check_update", "debug_servicios", "debugservicios", "servicios_debug",
            "debug_sedes", "debugsedes", "sedes_debug", "debug_completo", "debugcompleto",
            "debug_monitoreo", "monitoreo_debug", "emergencia", "panico", "contingencia", "web",
            "pantalla", "captura", "dashboard", "screenshot", "reinicia", "reboot", "ia", "motor_ia",
            "toggle_ia", "servicio_ia", "lockdown", "unlock", "autorizar_consola", "consola", "shield",
            "cluster", "nodo", "rol_nodo", "token_cluster", "discovery", "antirogue", "antirrogue", "descubrimiento",
            "snmp", "telemetria", "interfaces", "certificados", "certificado", "ssl", "certs", "tls",
            "alertas", "alerta", "alerts", "ack", "reconocer", "resolver", "resolve", "silenciar",
            "silence", "mute", "mantenimiento", "maint", "reglas", "rules", "configs", "config",
            "respaldos", "backup", "traps", "trap", "snmptraps", "snmp_traps", "syslog", "syslogs", "logs_red",
            "netflow", "flows", "flujos", "toptalkers", "topologia", "topology", "grafo", "mapared", "wol", "wake", "wakeonlan", "encender",
            "predicciones", "predictivo", "capacidad", "tendencias", "inventario", "ciclovida",
            "garantias", "lifecycle", "ups", "energia", "bateria", "power", "recursos", "memoria",
            "optimizar", "plasma", "cron", "envios", "reportes_programados", "programacion",
            "reiniciar_bot", "restart_bot", "bot_restart", "reiniciar", "rollback", "revertir",
            "git_rollback", "estado_deploy", "deploy_status", "git_status", "desbloquear_update",
            "unlock_update", "log_actualizacion", "log_deploy", "deploy_log", "auditoria_deploy",
            "desbloqueo_total", "bloqueo_total", "auth_terminal", "auth_console"
        }
        for cmd_name in commands_dict.keys():
            if cmd_name.lower() not in reserved_commands:
                application.add_handler(CommandHandler(cmd_name, handle_dynamic_command))
        logger.info("Bot iniciado con comandos dinámicos + asistente local de IA.")
    else:
        logger.info("Bot iniciado en MODO INTERACTIVO (comandos dinámicos deshabilitados).")

    # 9. Interceptor prioritario de autorización por texto para el Owner ("soy yo", "autorizar", etc.)
    from bot_core.handlers.shield import handle_soy_yo_text_message
    application.add_handler(
        MessageHandler(
            filters.TEXT & filters.Regex(r"(?i)\b(soy\s+yo|autorizar|autorizo|permitir|override)\b") & ~filters.COMMAND,
            handle_soy_yo_text_message
        )
    )

    # 10. Mensajes de texto normales -> Asistente local de IA
    application.add_handler(
        MessageHandler(filters.TEXT & ~filters.COMMAND, handle_chat_message)
    )

    # 11. Fallback para comandos no reconocidos
    application.add_handler(MessageHandler(filters.COMMAND, unknown_cmd))
    logger.info("Todos los manejadores del bot registrados satisfactoriamente.")
