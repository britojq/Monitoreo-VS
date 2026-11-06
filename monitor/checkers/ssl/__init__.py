"""
Paquete modular de verificación y auditoría de certificados SSL/TLS.
"""
from monitor.infrastructure.monitor_db import get_db_connection
from monitor.checkers.ssl.socket_handshake import (
    create_permissive_ssl_context,
    perform_ssl_handshake,
)
from monitor.checkers.ssl.ssl_orchestrator import (
    auto_discover_https_services,
    check_and_update_certificate,
    print_cli_table,
    run_all_ssl_checks,
)
from monitor.checkers.ssl.x509_parser import (
    extract_cert_info,
    match_hostname,
)

__all__ = [
    "get_db_connection",
    "create_permissive_ssl_context",
    "perform_ssl_handshake",
    "match_hostname",
    "extract_cert_info",
    "auto_discover_https_services",
    "check_and_update_certificate",
    "run_all_ssl_checks",
    "print_cli_table",
]
