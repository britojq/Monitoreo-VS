"""
Fachada ultradelgada de retrocompatibilidad para Network Discovery.
Arquitectura modular delegada en monitor.checkers.discovery.
"""
from monitor.checkers.discovery import (  # noqa: F401
    deep_fingerprint_hosts,
    get_kernel_arp_table,
    get_local_interfaces_map,
    infer_device_type,
    lookup_oui_vendor,
    scan_all_active_subnets,
    scan_subnet,
    sweep_subnet_async,
)
from monitor.checkers.discovery.discovery_cli import main


if __name__ == "__main__":
    main()
