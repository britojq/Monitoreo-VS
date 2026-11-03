"""
CLI y punto de entrada para la inspección y verificación SSL/TLS.
"""
import argparse
import json
import sys

from monitor.checkers.ssl.ssl_orchestrator import (
    auto_discover_https_services,
    check_and_update_certificate,
    print_cli_table,
    run_all_ssl_checks,
)
from monitor.checkers.ssl.x509_parser import extract_cert_info
from monitor.infrastructure.db_context import db_cursor


def main():
    """Punto de entrada principal para ejecución por línea de comandos."""
    parser = argparse.ArgumentParser(description="Verificador y Auditor Avanzado de Certificados SSL/TLS (@IA_ValleSeco_bot)")
    parser.add_argument("--domain", type=str, help="Dominio o IP a inspeccionar directamente")
    parser.add_argument("--port", type=int, default=443, help="Puerto SSL/TLS (default: 443)")
    parser.add_argument("--all", action="store_true", help="Ejecutar revisión completa de todos los certificados registrados en BD")
    parser.add_argument("--discover", action="store_true", help="Auto-descubrir servicios HTTPS desde monitored_services")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON puro")
    parser.add_argument("--timeout", type=float, default=5.0, help="Timeout de conexión en segundos (default: 5.0)")
    parser.add_argument("--table", action="store_true", help="Mostrar reporte en tabla formateada")

    args = parser.parse_args()

    if args.discover:
        count = auto_discover_https_services()
        print(f"✅ Auto-descubrimiento completado: {count} nuevos dominios SSL registrados.")
        sys.exit(0)

    if args.domain:
        with db_cursor() as cur:
            cur.execute("SELECT * FROM ssl_certificates WHERE domain = %s LIMIT 1", (args.domain,))
            record = cur.fetchone()

        if record:
            info = check_and_update_certificate(cert_record=record, timeout=args.timeout)
        else:
            info = extract_cert_info(args.domain, port=args.port, timeout=args.timeout)

        if args.json:
            print(json.dumps(info, indent=2, default=str))
        else:
            print_cli_table([info])
        sys.exit(0 if info.get("success") else 1)

    if args.all or len(sys.argv) == 1 or args.table:
        results = run_all_ssl_checks(auto_discover=True, timeout=args.timeout)
        if args.json:
            print(json.dumps(results, indent=2, default=str))
        else:
            print_cli_table(results)
        sys.exit(0)


if __name__ == "__main__":
    main()
