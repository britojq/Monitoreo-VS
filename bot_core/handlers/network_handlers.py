"""Fachada ultradelgada de retrocompatibilidad para Handlers de Red."""
from bot_core.handlers.network import (  # noqa: F401
    cmd_analisis_red, cmd_cluster, cmd_discovery, cmd_ip,
    cmd_netflow, cmd_snmp, cmd_syslog, cmd_topologia,
    cmd_traps, cmd_wol, register_network_routes,
)

__all__ = [
    "cmd_analisis_red", "cmd_ip", "cmd_cluster", "cmd_discovery",
    "cmd_snmp", "cmd_traps", "cmd_syslog", "cmd_netflow",
    "cmd_topologia", "cmd_wol", "register_network_routes",
]
