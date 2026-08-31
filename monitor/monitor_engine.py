"""
# ==============================================================================
# 📊 ORQUESTADOR DE MONITOREO Y CLI: monitor_engine.py (@IA_ValleSeco_bot)
# Coordinación de chequeos concurrentes, reportes unificados y despacho a Telegram
# Ubicación: /scripts/telegram-admin-bot/monitor/monitor_engine.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import json
import logging
import os
import sys
import time
from pathlib import Path
from typing import Dict, List, Optional, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.config_parser import get_formatted_datetime
from monitor.core_shield import IMMUTABLE_OWNER_ID, IMMUTABLE_BOT_TOKEN
from monitor.monitor_servicios import run_services_check
from monitor.monitor_sedes import run_sedes_check
from monitor.telegram_dispatcher import TelegramDispatcher

logger = logging.getLogger("monitor.engine")

LOG_DIR = Path("/tmp/monitor")
CONFIG_PATH = BASE_DIR / "config" / "config.json"


def get_default_telegram_chats() -> List[str | int]:
    """Obtiene la lista de destinatarios por defecto para reportes programados."""
    recipients = []
    if CONFIG_PATH.exists():
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                cfg = json.load(f)
                groups = cfg.get("allowed_group_ids", [])
                if groups:
                    recipients.extend(groups)
                owner = cfg.get("owner_id")
                if owner and owner not in recipients:
                    recipients.append(owner)
        except Exception:
            pass

    if not recipients:
        recipients = [-1001383163558, IMMUTABLE_OWNER_ID]
    return recipients


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


LOCK_FILE = Path("/tmp/monitor_engine.lock")


async def execute_monitoring(
    target: str = "completo",
    debug_mode: bool = False
) -> Dict[str, any]:
    """
    Ejecuta el chequeo según el objetivo indicado:
    - 'servicios': Servicios Corporativos
    - 'sedes': Sedes y Equipos de Comunicación
    - 'completo' o 'monitoreo': Ambos componentes
    - 'analisis_red': Análisis profundo de tráfico LAN (.pcap, tshark, arp-scan)
    - 'limpiar': Limpieza de temporales y logs antiguos
    """
    # Protección Anti-Concurrencia: Evitar ejecuciones simultáneas
    if LOCK_FILE.exists():
        try:
            mtime = LOCK_FILE.stat().st_mtime
            if (time.time() - mtime) < 180:  # 3 minutos de expiración
                logger.warning("Ya existe una ejecución de monitoreo en curso. Omitiendo ejecución duplicada.")
                return {
                    "target": target,
                    "debug_mode": debug_mode,
                    "report_servicios": None,
                    "report_sedes": None,
                    "report_network": None,
                    "pcap_file": None,
                    "log_file": LOG_DIR / "servicelog.txt",
                    "elapsed_seconds": 0.0,
                    "total_items_checked": 0,
                    "skipped": True
                }
            else:
                LOCK_FILE.unlink(missing_ok=True)
        except Exception:
            pass

    try:
        LOCK_FILE.write_text(f"{os.getpid()}\n{time.time()}\n", encoding="utf-8")
    except Exception:
        pass

    t0 = time.perf_counter()
    target_clean = target.strip().lower()

    report_servicios: Optional[str] = None
    report_sedes: Optional[str] = None
    report_network: Optional[str] = None
    pcap_path: Optional[Path] = None
    all_logs: List[str] = []

    if target_clean == "servicios":
        report_servicios, vars_s, logs_s, _ = await run_services_check(debug_mode=debug_mode)
        all_logs.extend(logs_s)

    elif target_clean == "sedes":
        report_sedes, vars_sd, logs_sd, _ = await run_sedes_check(debug_mode=debug_mode)
        all_logs.extend(logs_sd)

    elif target_clean in ("analisis_red", "red", "trafico"):
        from monitor.network_analyzer import run_full_network_analysis
        rep_txt, pcap, _, _ = await run_full_network_analysis(capture_duration=60)
        report_network = rep_txt
        pcap_path = pcap
        all_logs.append(rep_txt)

    elif target_clean in ("caidas", "incidentes", "fallas"):
        (rep_s, vars_s, logs_s, _), (rep_sd, vars_sd, logs_sd, _) = await asyncio.gather(
            run_services_check(debug_mode=debug_mode),
            run_sedes_check(debug_mode=debug_mode)
        )
        all_logs.extend(logs_s)
        all_logs.extend(logs_sd)
        caidas_lines = []
        if rep_s:
            for l in rep_s.splitlines():
                if "❌" in l:
                    caidas_lines.append(l)
        if rep_sd:
            for l in rep_sd.splitlines():
                if "❌" in l:
                    caidas_lines.append(l)

        fecha_str, hora_str = get_formatted_datetime()
        if caidas_lines:
            report_servicios = (
                f"🚨 **REPORTE DE INCIDENTES Y SERVICIOS CAÍDOS**\n"
                f"📅 *{fecha_str} • {hora_str}*\n\n"
                f"━━━━━━━━━━━━━━━━━━━━\n"
                + "\n".join(caidas_lines) + "\n"
                f"━━━━━━━━━━━━━━━━━━━━\n"
                f"⚠️ *Revise la captura web adjunta o http://monitoreo-vs.local/*"
            )
        else:
            report_servicios = (
                f"✅ **SIN INCIDENTES ACTIVOS**\n"
                f"📅 *{fecha_str} • {hora_str}*\n\n"
                f"Todos los servicios y enlaces se encuentran operando con normalidad."
            )
        report_sedes = None

    elif target_clean in ("web", "pantalla", "dashboard"):
        report_servicios = None
        report_sedes = None

    elif target_clean in ("limpiar", "clean", "limpieza"):
        from monitor.system_cleaner import run_system_cleanup
        rep_clean = await run_system_cleanup()
        report_servicios = rep_clean
        all_logs.append(rep_clean)

    else:  # completo / monitoreo
        (report_servicios, vars_s, logs_s, _), (report_sedes, vars_sd, logs_sd, _) = await asyncio.gather(
            run_services_check(debug_mode=debug_mode),
            run_sedes_check(debug_mode=debug_mode)
        )
        all_logs.extend(logs_s)
        all_logs.extend(logs_sd)

    elapsed_time = round(time.perf_counter() - t0, 2)
    log_file_path = write_consolidated_log(all_logs)

    try:
        LOCK_FILE.unlink(missing_ok=True)
    except Exception:
        pass

    return {
        "target": target_clean,
        "debug_mode": debug_mode,
        "report_servicios": report_servicios,
        "report_sedes": report_sedes,
        "report_network": report_network,
        "pcap_file": pcap_path,
        "log_file": log_file_path,
        "elapsed_seconds": elapsed_time,
        "total_items_checked": len(all_logs)
    }


def main():
    parser = argparse.ArgumentParser(description="Orquestador CLI de Monitoreo Valle Seco")
    parser.add_argument(
        "target",
        nargs="?",
        default="servicios",
        choices=["servicios", "sedes", "completo", "monitoreo", "analisis_red", "limpiar"],
        help="Tipo de chequeo a ejecutar (por defecto: servicios)"
    )
    parser.add_argument("--debug", action="store_true", help="Ejecutar en modo depuración")
    parser.add_argument("--no-send", action="store_true", help="No enviar por Telegram (solo imprimir en terminal)")
    parser.add_argument("--chat-id", type=str, help="ID específico de chat de Telegram para el envío")
    args = parser.parse_args()

    print(f"🚀 [MONITOR VALLE SECO] Ejecutando: [{args.target.upper()}]...")
    result = asyncio.run(execute_monitoring(target=args.target, debug_mode=args.debug))

    print("\n" + "=" * 65)
    if result.get("report_servicios"):
        print("📄 REPORTE DE SERVICIOS:\n")
        print(result["report_servicios"])
        print("-" * 65)

    if result.get("report_sedes"):
        print("🏢 REPORTE DE SEDES:\n")
        print(result["report_sedes"])
        print("-" * 65)

    if result.get("report_network"):
        print("🌐 REPORTE DE ANÁLISIS DE RED:\n")
        print(result["report_network"])
        print("-" * 65)

    print(f"📁 Log consolidado guardado en: {result['log_file']}")
    print(f"⏱️ Tiempo de ejecución: {result['elapsed_seconds']}s")
    print("=" * 65)

    # Envío a Telegram (activo por defecto para ejecuciones de cron a menos que se use --no-send)
    if not args.no_send:
        dispatcher = TelegramDispatcher()
        target_chats = [args.chat_id] if args.chat_id else get_default_telegram_chats()

        for chat in target_chats:
            print(f"📤 Despachando reporte a Telegram (Chat ID: {chat})...")
            if result.get("report_servicios"):
                asyncio.run(dispatcher.send_text(chat, result["report_servicios"]))
            if result.get("report_sedes"):
                asyncio.run(dispatcher.send_text(chat, result["report_sedes"]))
            if result.get("report_network"):
                asyncio.run(dispatcher.send_text(chat, result["report_network"]))
            if result.get("pcap_file") and Path(result["pcap_file"]).exists():
                asyncio.run(dispatcher.send_document(chat, Path(result["pcap_file"]), caption="Captura de paquetes de red"))
            if args.debug and result["log_file"].exists():
                asyncio.run(dispatcher.send_document(chat, result["log_file"], caption="Log técnico consolidado"))

        print("✅ Reportes despachados exitosamente a Telegram.")


if __name__ == "__main__":
    main()
