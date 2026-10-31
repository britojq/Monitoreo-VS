"""
# ==============================================================================
# 📊 FACHADA DE HANDLERS DE SERVICIOS: service_handlers.py
# Fachada ultraligera compatible que delega al paquete bot_core.handlers.services.
# Ubicación: /opt/sonar/bot_core/handlers/service_handlers.py
# ==============================================================================
"""
from bot_core.handlers.services import (
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
    _get_active_config,
    _reply,
    _resolve_group_info,
    _run_and_send_monitoring_report,
)

__all__ = [
    "cmd_captura_web",
    "cmd_debug_completo",
    "cmd_debug_sedes",
    "cmd_debug_servicios",
    "cmd_internet",
    "cmd_reporte_caidas",
    "cmd_reporte_completo",
    "cmd_reporte_sedes",
    "cmd_reporte_servicios",
    "cmd_ssl",
    "register_service_routes",
    "_get_active_config",
    "_reply",
    "_resolve_group_info",
    "_run_and_send_monitoring_report",
]
