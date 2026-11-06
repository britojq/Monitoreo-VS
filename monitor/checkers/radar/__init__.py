"""
Paquete modular NET Radar (análisis de tráfico y detección de actualizaciones).
"""
from monitor.checkers.radar.constants import (
    LINUX_REPO_DOMAINS,
    WINDOWS_UPDATE_DOMAINS,
    format_bytes_human,
)
from monitor.checkers.radar.flow_sniffer import (
    capture_and_analyze_flows,
    get_interface_ip_and_mask,
    get_kernel_arp_table,
    is_ip_in_local_subnet,
    lookup_oui_vendor,
)
from monitor.checkers.radar.markdown_reporter import generate_markdown_report_from_db
from monitor.checkers.radar.radar_orchestrator import main_daemon, run_radar_cycle
from monitor.checkers.radar.update_detector import (
    detect_update_activity,
    infer_os_and_hostname,
)

__all__ = [
    "WINDOWS_UPDATE_DOMAINS",
    "LINUX_REPO_DOMAINS",
    "format_bytes_human",
    "get_kernel_arp_table",
    "lookup_oui_vendor",
    "infer_os_and_hostname",
    "is_ip_in_local_subnet",
    "get_interface_ip_and_mask",
    "capture_and_analyze_flows",
    "detect_update_activity",
    "run_radar_cycle",
    "generate_markdown_report_from_db",
    "main_daemon",
]
