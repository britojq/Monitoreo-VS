"""
# ==============================================================================
# 🌐 PAQUETE MODULAR DE HANDLERS DE RED: __init__.py
# Exporta las acciones de red y el enrutador para retrocompatibilidad total.
# Ubicación: /opt/sonar/bot_core/handlers/network/__init__.py
# ==============================================================================
"""
from bot_core.handlers.network.actions.analysis_action import handle_analisis_red as cmd_analisis_red
from bot_core.handlers.network.actions.cluster_discovery_action import (
    handle_cluster as cmd_cluster,
    handle_discovery as cmd_discovery,
)
from bot_core.handlers.network.actions.ip_action import handle_ip as cmd_ip
from bot_core.handlers.network.actions.snmp_action import handle_snmp as cmd_snmp
from bot_core.handlers.network.actions.traps_action import handle_traps as cmd_traps
from bot_core.handlers.network.actions.telemetry_action import (
    handle_netflow as cmd_netflow,
    handle_syslog as cmd_syslog,
)
from bot_core.handlers.network.actions.topology_wol_action import (
    handle_topologia as cmd_topologia,
    handle_wol as cmd_wol,
)
from bot_core.handlers.network.router import register_network_routes

__all__ = [
    "cmd_analisis_red",
    "cmd_ip",
    "cmd_cluster",
    "cmd_discovery",
    "cmd_snmp",
    "cmd_traps",
    "cmd_syslog",
    "cmd_netflow",
    "cmd_topologia",
    "cmd_wol",
    "register_network_routes",
]
