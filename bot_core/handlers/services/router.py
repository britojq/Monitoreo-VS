"""
# ==============================================================================
# 🚏 ENRUTADOR DE RUTAS DE SERVICIOS Y MONITOREO: router.py
# Registra los comandos de servicios, sedes, caídas, web, internet y SSL en Telegram.
# Ubicación: /opt/sonar/bot_core/handlers/services/router.py
# ==============================================================================
"""
from __future__ import annotations

import logging
from telegram.ext import Application, CommandHandler

from bot_core.handlers.services.actions.debug_action import (
    cmd_debug_completo,
    cmd_debug_sedes,
    cmd_debug_servicios,
)
from bot_core.handlers.services.actions.internet_action import handle_internet as cmd_internet
from bot_core.handlers.services.actions.report_action import (
    cmd_captura_web,
    cmd_reporte_caidas,
    cmd_reporte_completo,
    cmd_reporte_sedes,
    cmd_reporte_servicios,
)
from bot_core.handlers.services.actions.ssl_action import handle_ssl as cmd_ssl

logger = logging.getLogger("bot.handlers.services.router")


def register_service_routes(application: Application) -> None:
    """Registra las rutas de comandos de monitoreo y servicios en la aplicación Telegram."""
    application.add_handler(CommandHandler(["reporte_servicios", "servicios", "servicio", "servicios_grupo", "servicios_ondemand"], cmd_reporte_servicios))
    application.add_handler(CommandHandler(["reporte_sedes", "sedes", "sede", "sitios", "sedes_grupo", "sedes_ondemand", "enlaces"], cmd_reporte_sedes))
    application.add_handler(CommandHandler(["reporte_caidas", "caidas", "incidentes", "fallas", "caidas_grupo", "caidas_ondemand"], cmd_reporte_caidas))
    application.add_handler(CommandHandler(["web", "pantalla", "captura", "dashboard", "screenshot"], cmd_captura_web))
    application.add_handler(CommandHandler(["reporte_completo", "reporte", "completo", "todo", "monitoreo", "monitoreo_grupo", "monitoreo_ondemand"], cmd_reporte_completo))
    application.add_handler(CommandHandler(["debug_servicios", "debugservicios", "servicios_debug", "debug_servicio"], cmd_debug_servicios))
    application.add_handler(CommandHandler(["debug_sedes", "debugsedes", "sedes_debug", "debug_sede"], cmd_debug_sedes))
    application.add_handler(CommandHandler(["debug_completo", "debugcompleto", "debug_monitoreo", "monitoreo_debug", "debug_todo"], cmd_debug_completo))
    application.add_handler(CommandHandler(["internet", "proxy", "proxies", "conectividad", "salida_internet"], cmd_internet))
    application.add_handler(CommandHandler(["certificados", "certificado", "ssl", "certs", "tls"], cmd_ssl))
    logger.info("Rutas de Servicios y Monitoreo registradas exitosamente.")
