"""
Orquestador unificado de monitoreo.
Permite ejecutar chequeos individuales (servicios o sedes) o el chequeo completo,
generar los archivos de logs consolidados y despachar alertas a Telegram.
"""

from __future__ import annotations

import argparse
import asyncio
import logging
import time
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from monitor.config_parser import get_formatted_datetime
from monitor.monitor_servicios import run_services_check
from monitor.monitor_sedes import run_sedes_check
from monitor.telegram_dispatcher import TelegramDispatcher

logger = logging.getLogger("monitor.engine")

LOG_DIR = Path("/tmp/monitor")


def write_consolidated_log(logs: List[str]) -> Path:
    """Escribe el log consolidado en /tmp/monitor/servicelog.txt."""
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    log_file = LOG_DIR / "servicelog.txt"
    fecha_str, hora_str = get_formatted_datetime()

    header = (
        f"################################################\n"
        f"##  REGISTROS DE CHEQUEO DE SISTEMA ATIT\n"
        f"##  FECHA DE EJECUCION: {fecha_str}\n"
        f"##  HORA DE EJECUCION: {hora_str}\n"
        f"################################################\n\n"
    )

    content = header + "\n\n".join(logs) + "\n"
    log_file.write_text(content, encoding="utf-8", errors="ignore")
    return log_file


async def execute_monitoring(
    target: str = "completo",
    debug_mode: bool = False
) -> Dict[str, any]:
    """
    Ejecuta el chequeo según el objetivo indicado:
    - 'servicios': Solo Componente 1 (Servicios Corporativos)
    - 'sedes': Solo Componente 2 (Sedes y Equipos de Comunicación)
    - 'completo': Ambos componentes ejecutados en paralelo
    """
    t0 = time.perf_counter()
    target_clean = target.strip().lower()

    report_servicios: Optional[str] = None
    report_sedes: Optional[str] = None
    all_logs: List[str] = []
    combined_variables: Dict[str, str] = {}

    if target_clean == "servicios":
        report_servicios, vars_s, logs_s, _ = await run_services_check(debug_mode=debug_mode)
        all_logs.extend(logs_s)
        combined_variables.update(vars_s)
    elif target_clean == "sedes":
        report_sedes, vars_sd, logs_sd, _ = await run_sedes_check(debug_mode=debug_mode)
        all_logs.extend(logs_sd)
        combined_variables.update(vars_sd)
    else:  # completo
        (report_servicios, vars_s, logs_s, _), (report_sedes, vars_sd, logs_sd, _) = await asyncio.gather(
            run_services_check(debug_mode=debug_mode),
            run_sedes_check(debug_mode=debug_mode)
        )
        all_logs.extend(logs_s)
        all_logs.extend(logs_sd)
        combined_variables.update(vars_s)
        combined_variables.update(vars_sd)

    elapsed_time = round(time.perf_counter() - t0, 2)
    log_file_path = write_consolidated_log(all_logs)

    return {
        "target": target_clean,
        "debug_mode": debug_mode,
        "report_servicios": report_servicios,
        "report_sedes": report_sedes,
        "log_file": log_file_path,
        "elapsed_seconds": elapsed_time,
        "total_items_checked": len(all_logs)
    }


def main():
    parser = argparse.ArgumentParser(description="Orquestador de Monitoreo ATIT")
    parser.add_argument("target", nargs="?", default="completo", choices=["servicios", "sedes", "completo"], help="Tipo de chequeo a ejecutar")
    parser.add_argument("--debug", action="store_true", help="Ejecutar en modo depuración")
    parser.add_argument("--send", action="store_true", help="Enviar reporte por Telegram")
    parser.add_argument("--chat-id", type=str, help="ID de chat de Telegram para el envío")
    args = parser.parse_args()

    print(f"🚀 Ejecutando monitoreo: [{args.target.upper()}] (Debug: {args.debug})...")
    result = asyncio.run(execute_monitoring(target=args.target, debug_mode=args.debug))

    print("\n" + "=" * 60)
    if result["report_servicios"]:
        print("📄 REPORTE DE SERVICIOS:")
        print(result["report_servicios"])
        print("-" * 60)

    if result["report_sedes"]:
        print("🏢 REPORTE DE SEDES:")
        print(result["report_sedes"])
        print("-" * 60)

    print(f"📁 Log consolidado guardado en: {result['log_file']}")
    print(f"⏱️ Tiempo total de ejecución: {result['elapsed_seconds']} segundos ({result['total_items_checked']} elementos verificados)")
    print("=" * 60)

    if args.send:
        dispatcher = TelegramDispatcher()
        chat = args.chat_id or dispatcher.loader.raw_bot.get("IDC") or dispatcher.loader.raw_bot.get("IDA")
        if chat:
            print(f"📤 Despachando alertas a Telegram (Chat ID: {chat})...")
            if result["report_servicios"]:
                asyncio.run(dispatcher.send_text(chat, result["report_servicios"]))
            if result["report_sedes"]:
                asyncio.run(dispatcher.send_text(chat, result["report_sedes"]))
            if args.debug and result["log_file"].exists():
                asyncio.run(dispatcher.send_document(chat, result["log_file"], caption="Log técnico de ejecución"))
            print("✅ Reportes despachados.")


if __name__ == "__main__":
    main()
