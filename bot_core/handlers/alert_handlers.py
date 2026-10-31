"""
# ==============================================================================
# 🚨 FACHADA DE HANDLERS DE ALERTAS: alert_handlers.py
# Fachada ultraligera compatible que delega al paquete bot_core.handlers.alerts.
# Ubicación: /opt/sonar/bot_core/handlers/alert_handlers.py
# ==============================================================================
"""
from bot_core.handlers.alerts import (
    cmd_ack,
    cmd_alertas,
    cmd_mantenimiento,
    cmd_reglas,
    cmd_resolver,
    cmd_silenciar,
    handle_alert_action_callback,
    register_alert_routes,
    _get_config,
    _get_portal_alerts_url,
    _reply,
)

__all__ = [
    "cmd_alertas",
    "cmd_ack",
    "cmd_resolver",
    "cmd_silenciar",
    "cmd_mantenimiento",
    "cmd_reglas",
    "handle_alert_action_callback",
    "register_alert_routes",
    "_get_portal_alerts_url",
    "_reply",
    "_get_config",
]
