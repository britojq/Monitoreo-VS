"""
Componente 1: Monitoreo de Servicios Corporativos.
Verifica de forma asíncrona todos los servicios empresariales configurados (A a Z).
"""

from __future__ import annotations

import argparse
import asyncio
import logging
import time
from typing import Dict, List, Tuple

from monitor.config_parser import (
    MonitorConfigLoader,
    ServiceConfig,
    get_formatted_datetime,
    render_template,
)
from monitor.checker_base import (
    check_cups,
    check_dhcp,
    check_dns,
    check_ldap,
    check_ping,
    check_proxy,
    check_smtp,
    check_web,
)

logger = logging.getLogger("monitor.servicios")


async def verify_single_service(svc: ServiceConfig) -> Tuple[str, bool, str, str]:
    """
    Ejecuta la verificación adecuada según el tipo de servicio.
    Retorna (letter, is_ok, log_detail, status_label).
    """
    stype = svc.service_type
    is_ok = False
    detail = ""

    if stype == "WEB":
        is_ok, _, detail = await check_web(svc.web_url)
    elif stype == "DNS":
        is_ok, _, detail = await check_dns(svc.ip_host, svc.dns_test_host)
    elif stype == "PROXY":
        # Formatear proxy_url si tiene credenciales
        proxy_url = f"http://{svc.proxy_user_pass}@{svc.proxy_ip_port}" if svc.proxy_user_pass else f"http://{svc.proxy_ip_port}"
        is_ok, _, detail = await check_proxy(proxy_url, svc.url_test_site)
    elif stype == "SMTP":
        is_ok, _, detail = await check_smtp(svc.ip_host, svc.smtp_port)
    elif stype == "DHCP":
        is_ok, _, detail = await check_dhcp(svc.net_interface)
    elif stype == "CUPS":
        is_ok, _, detail = await check_cups(svc.cups_port_ip)
    elif stype == "LDAP":
        is_ok, _, detail = await check_ldap(svc.ip_host, svc.ldap_port_ip)
    else:  # PING u otros
        is_ok, _, detail = await check_ping(svc.ip_host)

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

    # Map de servicios por letra
    svc_map = {svc.letter: svc for svc in services}

    # Inicializar nombres y estados para todas las letras
    for code in range(ord('A'), ord('Z') + 1):
        letter = chr(code)
        variables[f"NAMESERVICE{letter}"] = loader.raw_monitoreo.get(f"NAMESERVICE{letter}", "")
        variables[f"STHOST{letter}"] = ""
        variables[f"STATESERVICE{letter}"] = "NO_CONFIGURADO"

    for letter, is_ok, log_text, state_label in results:
        svc = svc_map[letter]
        variables[f"NAMESERVICE{letter}"] = svc.name
        msg = svc.msg_normal if is_ok else svc.msg_error
        variables[f"STHOST{letter}"] = msg
        variables[f"STATESERVICE{letter}"] = state_label
        logs.append(log_text)

    # Seleccionar plantilla de mensajes.conf según debug_mode
    template_key = "MENSAJEDEBUGA" if debug_mode else "MENSAJEA"
    template_str = loader.templates.get(template_key, "")

    if not template_str:
        # Fallback si no está la plantilla
        lines = [f"📊 *REPORTE DE SERVICIOS CORPORATIVOS*", f"Fecha: {fecha_str} {hora_str}", ""]
        for letter in sorted(svc_map.keys()):
            lines.append(variables[f"STHOST{letter}"])
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
