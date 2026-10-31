"""
Fachada ultradelgada de retrocompatibilidad para el análisis de red.
Arquitectura modular delegada en monitor.checkers.analyzer.
"""
from monitor.checkers.analyzer import (  # noqa: F401
    NetworkHost,
    build_txt_and_html_reports,
    capture_packets,
    execute_network_analysis,
    generate_html_network_report,
    generate_markdown_report,
    generate_telegram_summary,
    get_interface_ip_and_mask,
    is_ip_in_local_subnet,
    load_mac_whitelist,
    load_oui_database,
    parse_pcap_traffic,
    ping_measure,
    purge_old_audit_files,
    resolve_hostname_sync,
    run_arp_and_nmap_scan,
    run_full_network_analysis,
    save_network_analysis_report_to_db,
)
