"""
# ==============================================================================
# 💻 INTERFAZ CLI DE TELEMETRÍA SNMP: snmp_cli.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Punto de entrada CLI para sondeo y descubrimiento SNMP por terminal.
# Ubicación: /opt/sonar/monitor/cli/snmp_cli.py
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import sys
from pathlib import Path
from typing import Optional

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.application.snmp_orchestrator import (
    print_status,
    run_snmp_poll,
)
from monitor.checkers.snmp.interface_discoverer import discover_interfaces
from monitor.infrastructure.db_context import db_cursor


def main() -> None:
    """Punto de entrada de línea de comandos para snmp_poller."""
    parser = argparse.ArgumentParser(description="Motor de Recolección y Telemetría SNMP Valle Seco (S.O.N.A.R.)")
    parser.add_argument("--poll", action="store_true", help="Ejecutar ciclo de sondeo SNMP")
    parser.add_argument("--device", type=int, help="ID de dispositivo específico a sondear")
    parser.add_argument("--interfaces", type=int, help="Descubrir interfaces para un ID de dispositivo")
    parser.add_argument("--status", action="store_true", help="Consultar estado de equipos SNMP")

    args = parser.parse_args()

    if args.status:
        print_status()
        return

    if args.interfaces:
        with db_cursor() as cur:
            cur.execute("SELECT * FROM snmp_devices WHERE id = %s", (args.interfaces,))
            dev = cur.fetchone()
        if dev:
            asyncio.run(discover_interfaces(dev))
        else:
            print(f"Dispositivo con ID {args.interfaces} no encontrado.")
        return

    dev_ids = [args.device] if args.device else None
    asyncio.run(run_snmp_poll(device_ids=dev_ids))


if __name__ == "__main__":
    main()
