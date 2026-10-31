"""
Fachada ultradelgada de retrocompatibilidad para NET Radar Engine.
Arquitectura modular delegada en monitor.checkers.radar.
"""
from monitor.checkers.radar import (  # noqa: F401
    LINUX_REPO_DOMAINS,
    WINDOWS_UPDATE_DOMAINS,
    capture_and_analyze_flows,
    detect_update_activity,
    format_bytes_human,
    generate_markdown_report_from_db,
    get_interface_ip_and_mask,
    get_kernel_arp_table,
    infer_os_and_hostname,
    is_ip_in_local_subnet,
    lookup_oui_vendor,
    main_daemon,
    run_radar_cycle,
)
from monitor.checkers.radar.radar_cli import main


if __name__ == "__main__":
    main()
