"""Fachada ultradelgada de retrocompatibilidad para el verificador SSL/TLS."""
from monitor.checkers.ssl import (  # noqa: F401
    auto_discover_https_services, check_and_update_certificate,
    create_permissive_ssl_context, extract_cert_info, get_db_connection,
    match_hostname, print_cli_table, run_all_ssl_checks,
)
from monitor.checkers.ssl.ssl_cli import main

if __name__ == "__main__":
    main()

