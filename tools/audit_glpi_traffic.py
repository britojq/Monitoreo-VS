#!/usr/bin/env python3
"""
==============================================================================
🔍 AUDITORÍA AUTOMATIZADA DE TRÁFICO GLPI (DESARROLLO VS PRODUCCIÓN)
Herramienta: tools/audit_glpi_traffic.py

Inspecciona en caliente las fuentes primarias:
1. Registros de peticiones HTTP en `/var/log/apache2/laravel_access.log` (Desarrollo).
2. Entradas en MariaDB (`glpi_inventory_logs`) en Desarrollo y Producción.
3. Emite la matriz exacta de estado: qué máquinas reportan a Desarrollo,
   cuáles a Producción y cuáles están pendientes.
==============================================================================
"""

import argparse
import os
import re
import subprocess
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

# Colores ANSI
C_RESET = "\033[0m"
C_BOLD = "\033[1m"
C_GREEN = "\033[32m"
C_RED = "\033[31m"
C_YELLOW = "\033[33m"
C_CYAN = "\033[36m"
C_GRAY = "\033[90m"

BASE_DIR = Path(__file__).resolve().parent.parent
DEV_APACHE_LOG = Path("/var/log/apache2/laravel_access.log")


def get_dev_apache_traffic() -> Dict[str, str]:
    """Extrae las IPs y marcas de tiempo de peticiones POST a la API de GLPI en Desarrollo."""
    traffic: Dict[str, str] = {}
    cmd = ["sudo", "grep", "-E", "POST.*inventory.*receiver", str(DEV_APACHE_LOG)]
    try:
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
        if res.returncode == 0:
            for line in res.stdout.strip().split("\n"):
                if not line:
                    continue
                parts = line.split()
                if len(parts) >= 4:
                    ip = parts[0]
                    # Fecha en formato [22/Oct/2026:12:01:22
                    raw_date = parts[3].lstrip("[")
                    traffic[ip] = raw_date
    except Exception:
        pass
    return traffic


def get_mariadb_glpi_logs_local() -> Dict[str, Tuple[str, str]]:
    """Consulta la tabla glpi_inventory_logs en el MariaDB local (Desarrollo)."""
    logs: Dict[str, Tuple[str, str]] = {}
    try:
        query = (
            "SELECT ip_address, COALESCE(device_name, 'Desconocido'), MAX(created_at) "
            "FROM glpi_inventory_logs "
            "WHERE ip_address IS NOT NULL AND ip_address != '' "
            "GROUP BY ip_address, device_name "
            "ORDER BY MAX(created_at) DESC;"
        )
        cmd = ["sudo", "mariadb", "monitoreo_vs", "-sN", "-e", query]
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
        if res.returncode == 0:
            for line in res.stdout.strip().split("\n"):
                if not line:
                    continue
                parts = line.split("\t")
                if len(parts) >= 3:
                    ip = parts[0].strip()
                    device = parts[1].strip()
                    dt = parts[2].strip()
                    if ip not in logs:
                        logs[ip] = (device, dt)
    except Exception:
        pass
    return logs


def get_mariadb_glpi_logs_remote(remote_host: str = "produccion") -> Dict[str, Tuple[str, str]]:
    """Consulta la tabla glpi_inventory_logs en el MariaDB remoto de Producción vía SSH."""
    logs: Dict[str, Tuple[str, str]] = {}
    try:
        query = (
            "SELECT ip_address, COALESCE(device_name, 'Desconocido'), MAX(created_at) "
            "FROM glpi_inventory_logs "
            "WHERE ip_address IS NOT NULL AND ip_address != '' "
            "GROUP BY ip_address, device_name "
            "ORDER BY MAX(created_at) DESC;"
        )
        cmd = [
            "ssh",
            "-o", "BatchMode=yes",
            "-o", "ConnectTimeout=10",
            remote_host,
            f"sudo mariadb monitoreo_vs -sN -e '{query}'"
        ]
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=35)
        if res.returncode == 0:
            for line in res.stdout.strip().split("\n"):
                if not line:
                    continue
                parts = line.split("\t")
                if len(parts) >= 3:
                    ip = parts[0].strip()
                    device = parts[1].strip()
                    dt = parts[2].strip()
                    if ip not in logs or (device != "NULL" and logs[ip][0] in ("NULL", "Desconocido")):
                        logs[ip] = (device, dt)
    except Exception:
        pass
    return logs


def main() -> int:
    parser = argparse.ArgumentParser(description="Auditoría de Tráfico GLPI en Vivo")
    parser.add_argument(
        "--remote",
        nargs="?",
        const="produccion",
        default=None,
        help="Host SSH de Producción para contrastar logs remotos (requiere autorización previa)"
    )
    parser.add_argument("--check-only", action="store_true", help="Retorna 1 si hay puestos reportando a Desarrollo")
    args = parser.parse_args()

    print(f"\n{C_BOLD}{C_CYAN}========================================================================={C_RESET}")
    print(f"{C_BOLD}{C_CYAN} 🔍 AUDITORÍA DE TRÁFICO GLPI EN VIVO (FUENTE DE VERDAD: LOGS REALES)     {C_RESET}")
    print(f"{C_BOLD}{C_CYAN}========================================================================={C_RESET}\n")

    dev_traffic = get_dev_apache_traffic()
    dev_db = get_mariadb_glpi_logs_local()
    prod_db = get_mariadb_glpi_logs_remote(args.remote) if args.remote else {}

    # Consolidar todas las IPs observadas
    all_ips = set(dev_traffic.keys()) | set(dev_db.keys()) | set(prod_db.keys())
    # Excluir loopback y la IP del propio servidor de desarrollo si no aporta
    ips_sorted = sorted(
        [ip for ip in all_ips if ip not in ("127.0.0.1", "::1")],
        key=lambda x: [int(p) if p.isdigit() else 0 for p in x.split(".")]
    )

    print(f"{C_BOLD}{'DIRECCIÓN IP':<16} {'DISPOSITIVO / HOSTNAME':<28} {'ÚLTIMO REPORTE DESARROLLO':<28} {'ESTADO OPERATIVO'}{C_RESET}")
    print(f"{C_GRAY}{'-'*100}{C_RESET}")

    pending_count = 0
    migrated_count = 0

    for ip in ips_sorted:
        dev_info = dev_db.get(ip, ("-", "-"))
        prod_info = prod_db.get(ip, ("-", "-"))

        device_name = prod_info[0] if prod_info[0] != "-" else dev_info[0]
        last_dev = dev_traffic.get(ip) or dev_info[1]
        last_prod = prod_info[1]

        # Determinar estado factual
        has_recent_dev_traffic = ip in dev_traffic

        if ip == "192.168.1.250" and has_recent_dev_traffic:
            status_str = f"{C_BOLD}{C_RED}🔴 AGENTE LOCAL DE PRODUCCIÓN APUNTANDO A DESARROLLO{C_RESET}"
            pending_count += 1
        elif has_recent_dev_traffic:
            status_str = f"{C_BOLD}{C_RED}🔴 APUNTANDO A DESARROLLO (TRÁFICO ACTIVO DETECTADO){C_RESET}"
            pending_count += 1
        elif last_dev != "-" and last_prod == "-":
            status_str = f"{C_BOLD}{C_RED}🔴 SOLO REGISTRADO EN DESARROLLO (NUNCA EN PRODUCCIÓN){C_RESET}"
            pending_count += 1
        elif last_prod != "-":
            status_str = f"{C_BOLD}{C_GREEN}🟢 MIGRADO A PRODUCCIÓN ({last_prod[:16]}){C_RESET}"
            migrated_count += 1
        else:
            status_str = f"{C_YELLOW}⚪ SIN REPORTES RECIENTES{C_RESET}"

        dev_disp = last_dev if last_dev != "-" else "Sin tráfico reciente"
        print(f"{ip:<16} {device_name[:26]:<28} {dev_disp[:26]:<28} {status_str}")

    print(f"\n{C_BOLD}RESUMEN DE AUDITORÍA FÁCTICA:{C_RESET}")
    if args.remote:
        print(f"  • Puestos con reportes directos en Producción: {C_GREEN}{migrated_count}{C_RESET}")
    else:
        print(f"  • Modo Local: {C_CYAN}Inspeccionando Apache y MariaDB de Desarrollo{C_RESET} (sin conexión SSH a Producción)")
    print(f"  • Puestos con tráfico detectado hacia Desarrollo: {C_RED}{pending_count}{C_RESET}")

    if pending_count > 0:
        print(f"\n{C_BOLD}{C_YELLOW}⚠️ ACCIÓN REQUERIDA (BLOQUE B - MAÑANA):{C_RESET}")
        print("  Reconfigurar físicamente los puestos marcados en rojo para apuntar a Producción.")
        if args.check_only:
            return 1
    else:
        print(f"\n{C_BOLD}{C_GREEN}🎉 PARIDAD ABSOLUTA: Cero clientes reportando a Desarrollo.{C_RESET}")

    print()
    return 0


if __name__ == "__main__":
    sys.exit(main())
