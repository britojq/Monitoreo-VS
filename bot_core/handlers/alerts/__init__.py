"""
# ==============================================================================
# 🚨 PAQUETE MODULAR DE HANDLERS DE ALERTAS: __init__.py
# Re-exporta acciones, callbacks y enrutador para paridad total con la arquitectura.
# Ubicación: /opt/sonar/bot_core/handlers/alerts/__init__.py
# ==============================================================================
"""
from bot_core.handlers.alerts.actions.alert_callback_action import handle_alert_action_callback
from bot_core.handlers.alerts.actions.alert_lifecycle_action import (
    handle_ack as cmd_ack,
    handle_reglas as cmd_reglas,
    handle_resolver as cmd_resolver,
    handle_silenciar as cmd_silenciar,
)
from bot_core.handlers.alerts.actions.alert_list_action import handle_alertas as cmd_alertas
from bot_core.handlers.alerts.actions.maintenance_action import handle_mantenimiento as cmd_mantenimiento
from bot_core.handlers.alerts.helpers import (
    get_config as _get_config,
    get_portal_alerts_url as _get_portal_alerts_url,
    reply as _reply,
)
from bot_core.handlers.alerts.router import register_alert_routes

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
