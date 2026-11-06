"""
CLI y punto de entrada para Auto-Discovery de Red.
"""
import argparse
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.checkers.discovery.subnet_orchestrator import (
    scan_all_active_subnets,
    scan_subnet,
)


def main():
    """Punto de entrada principal para ejecución por línea de comandos."""
    parser = argparse.ArgumentParser(description="Motor de Auto-Discovery y Detección Anti-Rogue")
    parser.add_argument("--subnet", type=str, help="Subred específica a escanear")
    parser.add_argument("--all", action="store_true", help="Escanear todas las subredes activas")
    parser.add_argument("--force", action="store_true", help="Forzar escaneo de subredes")
    parser.add_argument("--manual", action="store_true", help="Marca la ejecución como manual")

    args = parser.parse_args()
    executed_by = "manual" if args.manual else "cron"

    if args.subnet:
        scan_subnet(args.subnet, executed_by=executed_by)
    elif args.all:
        scan_all_active_subnets(executed_by=executed_by, force=args.force)
    else:
        scan_subnet("192.168.1.0/24", executed_by=executed_by)


if __name__ == "__main__":
    main()
