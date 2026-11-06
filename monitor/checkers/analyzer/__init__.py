"""
Paquete modular de análisis de red (SONAR Network Analyzer).
"""
from monitor.checkers.analyzer.dtos import NetworkHost
from monitor.checkers.analyzer.analyzer_orchestrator import (
    build_txt_and_html_reports,
    execute_network_analysis,
    purge_old_audit_files,
    run_full_network_analysis,
    save_network_analysis_report_to_db,
)
from monitor.checkers.analyzer.html_report_builder import generate_html_network_report
from monitor.checkers.analyzer.oui_resolver import (
    get_interface_ip_and_mask,
    is_ip_in_local_subnet,
    load_mac_whitelist,
    load_oui_database,
    resolve_hostname_sync,
)
from monitor.checkers.analyzer.pcap_parser import (
    parse_pcap_traffic,
    ping_measure,
    run_arp_and_nmap_scan,
)
from monitor.checkers.analyzer.tcpdump_runner import capture_packets
from monitor.checkers.analyzer.text_report_builder import (
    generate_markdown_report,
    generate_telegram_summary,
)

__all__ = [
    "NetworkHost",
    "is_ip_in_local_subnet",
    "get_interface_ip_and_mask",
    "load_mac_whitelist",
    "load_oui_database",
    "resolve_hostname_sync",
    "capture_packets",
    "parse_pcap_traffic",
    "run_arp_and_nmap_scan",
    "ping_measure",
    "generate_html_network_report",
    "generate_telegram_summary",
    "generate_markdown_report",
    "build_txt_and_html_reports",
    "save_network_analysis_report_to_db",
    "purge_old_audit_files",
    "execute_network_analysis",
    "run_full_network_analysis",
]
