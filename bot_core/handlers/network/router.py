"""
# ==============================================================================
# 🚏 ENRUTADOR DE RUTAS DE RED TELEGRAM: router.py
# Registra los comandos de telemetría y diagnóstico de red en Telegram Application.
# Ubicación: /opt/sonar/bot_core/handlers/network/router.py
# ==============================================================================
"""
import logging
from telegram.ext import Application, CommandHandler

from bot_core.handlers.network.actions.analysis_action import handle_analisis_red
from bot_core.handlers.network.actions.cluster_discovery_action import handle_cluster, handle_discovery
from bot_core.handlers.network.actions.ip_action import handle_ip
from bot_core.handlers.network.actions.snmp_action import handle_snmp
from bot_core.handlers.network.actions.traps_action import handle_traps
from bot_core.handlers.network.actions.telemetry_action import handle_netflow, handle_syslog
from bot_core.handlers.network.actions.topology_wol_action import handle_topologia, handle_wol

logger = logging.getLogger("bot.handlers.network.router")


def register_network_routes(application: Application) -> None:
    """Registra los manejadores de telemetría y diagnóstico de red en la aplicación de Telegram."""
    application.add_handler(CommandHandler(["analisis_red", "red", "escaner_red", "network_scan", "radar"], handle_analisis_red))
    application.add_handler(CommandHandler(["ip", "mi_ip", "ip_servidor", "server_ip", "my_ip", "red_ip", "ifconfig"], handle_ip))
    application.add_handler(CommandHandler(["cluster", "nodo", "rol_nodo", "token_cluster"], handle_cluster))
    application.add_handler(CommandHandler(["discovery", "antirogue", "antirrogue", "descubrimiento"], handle_discovery))
    application.add_handler(CommandHandler(["snmp", "telemetria", "interfaces"], handle_snmp))
    application.add_handler(CommandHandler(["traps", "trap", "snmptraps", "snmp_traps"], handle_traps))
    application.add_handler(CommandHandler(["syslog", "syslogs", "logs_red"], handle_syslog))
    application.add_handler(CommandHandler(["netflow", "flows", "flujos", "toptalkers"], handle_netflow))
    application.add_handler(CommandHandler(["topologia", "topology", "grafo", "mapared"], handle_topologia))
    application.add_handler(CommandHandler(["wol", "wake", "wakeonlan", "encender"], handle_wol))
    logger.info("Rutas de Red y Telemetría registradas exitosamente.")
