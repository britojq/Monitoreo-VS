"""
# ==============================================================================
# 🏢 MONITOREO DE SEDES Y ENLACES: monitor_sedes.py (@IA_ValleSeco_bot)
# Chequeo concurrente de CIAUs, oficinas comerciales, routers y equipos de red
# Ubicación: /scripts/telegram-admin-bot/monitor/monitor_sedes.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import logging
import time
from typing import Dict, List, Tuple

from monitor.config_parser import (
    EquipmentConfig,
    MonitorConfigLoader,
    SiteConfig,
    get_formatted_datetime,
    render_template,
)
from monitor.checker_base import check_ping

logger = logging.getLogger("monitor.sedes")


async def verify_site_host(site: SiteConfig) -> Tuple[str, bool, str, str]:
    """Verifica la IP principal de una sede física mediante Ping ICMP."""
    is_ok, _, detail = await check_ping(site.ip_host)
    state_label = "ACTIVO" if is_ok else "APAGADO"
    log_text = (
        f"----------------------------------------\n"
        f"SEDE: [{site.letter}] {site.name} (IP: {site.ip_host})\n"
        f"ESTADO: {state_label} | DETALLE: {detail}\n"
        f"----------------------------------------"
    )
    return site.letter, is_ok, log_text, state_label


async def verify_equipment_host(eq: EquipmentConfig) -> Tuple[str, int, bool, str, str]:
    """Verifica un equipo de comunicaciones específico dentro de una sede."""
    is_ok, _, detail = await check_ping(eq.ip_host)
    state_label = "ACTIVO" if is_ok else "APAGADO"
    log_text = (
        f"----------------------------------------\n"
        f"EQUIPO: [{eq.site_letter}{eq.num}] {eq.name} (IP: {eq.ip_host})\n"
        f"ESTADO: {state_label} | DETALLE: {detail}\n"
        f"----------------------------------------"
    )
    return eq.site_letter, eq.num, is_ok, log_text, state_label


async def run_sedes_check(debug_mode: bool = False) -> Tuple[str, Dict[str, str], List[str], float]:
    """
    Ejecuta el chequeo concurrente de todas las sedes y equipos de comunicación.
    Retorna: (reporte_formateado, variables_dict, lista_logs, tiempo_segundos).
    """
    t0 = time.perf_counter()
    loader = MonitorConfigLoader()
    sites = loader.get_sites()

    # Tareas concurrentes para sedes y para cada uno de sus equipos
    site_tasks = [verify_site_host(site) for site in sites]
    eq_tasks = [verify_equipment_host(eq) for site in sites for eq in site.equipment]

    site_results, eq_results = await asyncio.gather(
        asyncio.gather(*site_tasks),
        asyncio.gather(*eq_tasks)
    )

    fecha_str, hora_str = get_formatted_datetime()
    variables: Dict[str, str] = {
        "fecha": fecha_str,
        "hora": hora_str
    }
    logs: List[str] = []

    # Map de sedes por letra
    site_map = {st.letter: st for st in sites}

    # Inicializar nombres y variables de sedes (A a H) y equipos (1 a 8)
    for code in range(ord('A'), ord('H') + 1):
        letter = chr(code)
        variables[f"NAMESITE{letter}"] = loader.raw_monitoreo.get(f"NAMESITE{letter}", "")
        variables[f"STSITE{letter}"] = ""
        variables[f"ESTATSITE{letter}"] = "NO_CONFIGURADO"
        for num in range(1, 9):
            variables[f"NAMESITE{letter}EQUIPO{num}"] = loader.raw_monitoreo.get(f"NAMESITE{letter}EQUIPO{num}", "")
            variables[f"STSITE{letter}EQUIPO{num}"] = ""
            variables[f"ESTATSITE{letter}EQUIPO{num}"] = "NO_CONFIGURADO"

    # Procesar resultados de sedes principales
    for letter, is_ok, log_text, state_label in site_results:
        st = site_map[letter]
        variables[f"NAMESITE{letter}"] = st.name
        msg = st.msg_normal if is_ok else st.msg_error
        variables[f"STSITE{letter}"] = msg
        variables[f"ESTATSITE{letter}"] = state_label
        logs.append(log_text)

    # Procesar resultados de equipos
    for s_letter, num, is_ok, log_text, state_label in eq_results:
        st = site_map[s_letter]
        eq = next((e for e in st.equipment if e.num == num), None)
        if eq:
            variables[f"NAMESITE{s_letter}EQUIPO{num}"] = eq.name
            msg = eq.msg_normal if is_ok else eq.msg_error
            variables[f"STSITE{s_letter}EQUIPO{num}"] = msg
            variables[f"ESTATSITE{s_letter}EQUIPO{num}"] = state_label
            logs.append(log_text)

    # Seleccionar plantilla de mensajes.conf según debug_mode
    template_key = "MENSAJEDEBUGB" if debug_mode else "MENSAJEC"
    template_str = loader.templates.get(template_key, "")

    if not template_str:
        lines = [f"🏢 *REPORTE DE SEDES Y ENLACES*", f"Fecha: {fecha_str} {hora_str}", ""]
        for letter in sorted(site_map.keys()):
            lines.append(variables[f"STSITE{letter}"])
        report = "\n".join(lines)
    else:
        report = render_template(template_str, variables)

    elapsed_time = round(time.perf_counter() - t0, 2)
    return report.strip(), variables, logs, elapsed_time


def main():
    parser = argparse.ArgumentParser(description="Chequeo de Sedes y Equipos de Comunicación")
    parser.add_argument("--debug", action="store_true", help="Ejecutar en modo depuración")
    parser.add_argument("--send", action="store_true", help="Enviar reporte por Telegram tras el chequeo")
    args = parser.parse_args()

    print(f"🚀 Iniciando chequeo de sedes y equipos de comunicación (Debug: {args.debug})...")
    report, variables, logs, elapsed = asyncio.run(run_sedes_check(debug_mode=args.debug))
    print("\n" + "=" * 50)
    print(report)
    print("=" * 50)
    print(f"⏱️ Tiempo total de ejecución: {elapsed} segundos")


if __name__ == "__main__":
    main()
