"""
# ==============================================================================
# 📊 PAQUETE MODULAR DE HANDLERS DE SERVICIOS: __init__.py
# Re-exporta acciones y el enrutador para paridad total con la arquitectura preexistente.
# Ubicación: /opt/sonar/bot_core/handlers/services/__init__.py
# ==============================================================================
"""
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
    run_and_send_monitoring_report as _run_and_send_monitoring_report,
)
from bot_core.handlers.services.actions.ssl_action import handle_ssl as cmd_ssl
from bot_core.handlers.services.helpers import (
    get_active_config as _get_active_config,
    reply as _reply,
    resolve_group_info as _resolve_group_info,
)
from bot_core.handlers.services.router import register_service_routes

__all__ = [
    "cmd_reporte_servicios",
    "cmd_reporte_sedes",
    "cmd_reporte_caidas",
    "cmd_captura_web",
    "cmd_reporte_completo",
    "cmd_debug_servicios",
    "cmd_debug_sedes",
    "cmd_debug_completo",
    "cmd_internet",
    "cmd_ssl",
    "register_service_routes",
    "_run_and_send_monitoring_report",
    "_get_active_config",
    "_reply",
    "_resolve_group_info",
]
