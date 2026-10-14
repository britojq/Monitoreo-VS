"""
# ==============================================================================
# 🌐 MONITOREO DE SERVICIOS CORPORATIVOS: monitor_servicios.py (@IA_ValleSeco_bot)
# Chequeo concurrente de DNS, LDAP, SMTP, Web, SAO, SURGE y Servicios Regionales
# Ubicación: /scripts/telegram-admin-bot/monitor/monitor_servicios.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import logging
import os
import sys
import time
import urllib.parse
from pathlib import Path
from typing import Dict, List, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.config_parser import (
    MonitorConfigLoader,
    ServiceConfig,
    get_formatted_datetime,
    render_template,
)
from monitor.checkers import CheckerRegistry

logger = logging.getLogger("monitor.servicios")


async def verify_single_service(svc: ServiceConfig) -> Tuple[str, bool, str, str]:
    """
    Ejecuta la verificación adecuada según el tipo de servicio mediante el patrón Strategy.
    Retorna (letter, is_ok, log_detail, status_label).
    """
    checker = CheckerRegistry.get(svc.service_type)
    result = await checker.check(svc)

    is_ok = result.is_ok
    detail = result.detail
    state_label = "ACTIVO" if is_ok else "APAGADO"
    log_text = (
        f"----------------------------------------\n"
        f"SERVICIO: [{svc.letter}] {svc.name} ({svc.service_type})\n"
        f"ESTADO: {state_label} | DETALLE: {detail}\n"
        f"----------------------------------------"
    )
    return svc.letter, is_ok, log_text, state_label


async def run_services_check(debug_mode: bool = False) -> Tuple[str, Dict[str, str], List[str], float]:
    """
    Ejecuta el chequeo concurrente de todos los servicios corporativos.
    Retorna: (reporte_formateado, variables_dict, lista_logs, tiempo_segundos).
    """
    t0 = time.perf_counter()
    loader = MonitorConfigLoader()
    services = loader.get_services()

    tasks = [verify_single_service(svc) for svc in services]
    results = await asyncio.gather(*tasks)

    # Construir variables para plantillas
    fecha_str, hora_str = get_formatted_datetime()
    variables: Dict[str, str] = {
        "fecha": fecha_str,
        "hora": hora_str
    }
    logs: List[str] = []

    # Inicializar nombres y estados para compatibilidad con plantillas legacy (A..Z)
    for code in range(ord('A'), ord('Z') + 1):
        letter = chr(code)
        variables[f"NAMESERVICE{letter}"] = loader.raw_monitoreo.get(f"NAMESERVICE{letter}", "")
        variables[f"STHOST{letter}"] = ""
        variables[f"STATESERVICE{letter}"] = "NO_CONFIGURADO"

    corp_lines: List[str] = []
    reg_lines: List[str] = []

    for svc, (letter, is_ok, log_text, state_label) in zip(services, results):
        variables[f"NAMESERVICE{letter}"] = svc.name
        msg = svc.msg_normal if is_ok else svc.msg_error
        line_item = msg if msg else (f"✅ - {svc.name}" if is_ok else f"❌ - {svc.name}")
        variables[f"STHOST{letter}"] = line_item
        variables[f"STATESERVICE{letter}"] = state_label
        logs.append(log_text)

        if svc.scope == "regional":
            reg_lines.append(line_item)
        else:
            corp_lines.append(line_item)

    variables["SERVICIOS_CORPORATIVOS"] = "\n".join(corp_lines)
    variables["SERVICIOS_REGIONALES"] = "\n".join(reg_lines)

    # Seleccionar plantilla según debug_mode
    template_key = "MENSAJEDEBUGA" if debug_mode else "MENSAJEA"
    template_str = loader.templates.get(template_key, "")

    if not template_str:
        # Fallback si no está la plantilla
        lines = [f"📊 *REPORTE DE SERVICIOS CORPORATIVOS*", f"Fecha: {fecha_str} {hora_str}", ""]
        if corp_lines:
            lines.append("━━━━━━━━━━━━")
            lines.append("**Servicios Corporativos Verificados:**")
            lines.append("━━━━━━━━━━━━")
            lines.extend(corp_lines)
            lines.append("")
        if reg_lines:
            lines.append("━━━━━━━━━━━━")
            lines.append("**Servicios Regionales – Carabobo Verificados:**")
            lines.append("━━━━━━━━━━━━")
            lines.extend(reg_lines)
        report = "\n".join(lines)
    else:
        report = render_template(template_str, variables)

    elapsed_time = round(time.perf_counter() - t0, 2)
    return report.strip(), variables, logs, elapsed_time


def main():
    parser = argparse.ArgumentParser(description="Chequeo de Servicios Corporativos")
    parser.add_argument("--debug", action="store_true", help="Ejecutar en modo depuración")
    parser.add_argument("--send", action="store_true", help="Enviar reporte por Telegram tras el chequeo")
    args = parser.parse_args()

    print(f"🚀 Iniciando chequeo de servicios corporativos (Debug: {args.debug})...")
    report, variables, logs, elapsed = asyncio.run(run_services_check(debug_mode=args.debug))
    print("\n" + "=" * 50)
    print(report)
    print("=" * 50)
    print(f"⏱️ Tiempo total de ejecución: {elapsed} segundos")


if __name__ == "__main__":
    main()
