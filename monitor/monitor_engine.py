"""
# ==============================================================================
# 📊 ORQUESTADOR DE MONITOREO Y CLI: monitor_engine.py (@IA_ValleSeco_bot)
# Coordinación de chequeos concurrentes, reportes unificados y despacho a Telegram
# Ubicación: /scripts/telegram-admin-bot/monitor/monitor_engine.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
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

from monitor.config_parser import get_formatted_datetime, MonitorConfigLoader, render_template
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


def get_latest_snapshot() -> Tuple[Optional[dict], Optional[str]]:
    """Recupera el último payload_json y la fecha de creación del snapshot en MariaDB."""
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT payload_json, created_at FROM monitoring_snapshots ORDER BY id DESC LIMIT 1")
                row = cursor.fetchone()
                if row:
                    payload = row["payload_json"]
                    if isinstance(payload, str):
                        payload = json.loads(payload)
                    created_at = row["created_at"]
                    dt_str = created_at.strftime("%Y-%m-%d %H:%M:%S") if hasattr(created_at, "strftime") else str(created_at)
                    return payload, dt_str
        finally:
            conn.close()
    except Exception as e:
        logger.error(f"Error cargando último snapshot de MariaDB: {e}")
    return None, None


def compile_services_from_snapshot(snapshot: dict, snapshot_dt: str, debug_mode: bool = False) -> Tuple[str, Dict[str, str], List[str]]:
    """Compila el reporte oficial de Servicios a partir del snapshot consolidado en MariaDB."""
    loader = MonitorConfigLoader()
    services_data = snapshot.get("services", [])

    fecha_str, hora_str = get_formatted_datetime()
    variables: Dict[str, str] = {
        "fecha": fecha_str,
        "hora": hora_str
    }
    logs: List[str] = []

    # Inicializar A..Z
    for code in range(ord('A'), ord('Z') + 1):
        letter = chr(code)
        variables[f"NAMESERVICE{letter}"] = loader.raw_monitoreo.get(f"NAMESERVICE{letter}", "")
        variables[f"STHOST{letter}"] = ""
        variables[f"STATESERVICE{letter}"] = "NO_CONFIGURADO"

    corp_lines: List[str] = []
    reg_lines: List[str] = []

    for s in services_data:
        letter = s.get("letter", "")
        name = s.get("name", "Servicio")
        is_up = bool(s.get("is_up", False))

        variables[f"NAMESERVICE{letter}"] = name
        line_item = f"✅ - {name}" if is_up else f"❌ - {name}"
        variables[f"STHOST{letter}"] = line_item
        variables[f"STATESERVICE{letter}"] = "ACTIVO" if is_up else "APAGADO"
        logs.append(f"SERVICIO: [{letter}] {name} | ESTADO: {'ACTIVO' if is_up else 'APAGADO'}")

        scope = s.get("scope", "corporativo")
        if scope == "regional":
            reg_lines.append(line_item)
        else:
            corp_lines.append(line_item)

    variables["SERVICIOS_CORPORATIVOS"] = "\n".join(corp_lines)
    variables["SERVICIOS_REGIONALES"] = "\n".join(reg_lines)

    template_key = "MENSAJEDEBUGA" if debug_mode else "MENSAJEA"
    template_str = loader.templates.get(template_key, "")

    if not template_str:
        lines = [f"📊 *REPORTE DE SERVICIOS CORPORATIVOS*", f"Fecha: {fecha_str} {hora_str}", f"🕒 Snapshot: {snapshot_dt}", ""]
        if corp_lines:
            lines.append("━━━━━━━━━━━━\n**Servicios Corporativos Verificados:**\n━━━━━━━━━━━━")
            lines.extend(corp_lines)
            lines.append("")
        if reg_lines:
            lines.append("━━━━━━━━━━━━\n**Servicios Regionales – Carabobo Verificados:**\n━━━━━━━━━━━━")
            lines.extend(reg_lines)
        report = "\n".join(lines)
    else:
        report = render_template(template_str, variables)

    return report.strip(), variables, logs


def compile_sedes_from_snapshot(snapshot: dict, snapshot_dt: str, debug_mode: bool = False) -> Tuple[str, Dict[str, str], List[str]]:
    """Compila el reporte oficial de Sedes y Equipos a partir del snapshot consolidado en MariaDB."""
    loader = MonitorConfigLoader()
    sites_data = snapshot.get("sites", [])

    fecha_str, hora_str = get_formatted_datetime()
    variables: Dict[str, str] = {
        "fecha": fecha_str,
        "hora": hora_str
    }
    logs: List[str] = []

    # Inicializar sedes A..H y equipos 1..8
    for code in range(ord('A'), ord('H') + 1):
        letter = chr(code)
        variables[f"NAMESITE{letter}"] = loader.raw_monitoreo.get(f"NAMESITE{letter}", "")
        variables[f"STSITE{letter}"] = ""
        variables[f"ESTATSITE{letter}"] = "NO_CONFIGURADO"
        for num in range(1, 9):
            variables[f"NAMESITE{letter}EQUIPO{num}"] = loader.raw_monitoreo.get(f"NAMESITE{letter}EQUIPO{num}", "")
            variables[f"STSITE{letter}EQUIPO{num}"] = ""
            variables[f"ESTATSITE{letter}EQUIPO{num}"] = "NO_CONFIGURADO"

    site_blocks: List[str] = []

    for st in sites_data:
        letter = st.get("letter", "")
        name = st.get("name", "Sede")
        is_up = bool(st.get("is_up", False))
        site_line = f"✅ - {name}" if is_up else f"❌ - {name}"

        variables[f"NAMESITE{letter}"] = name
        variables[f"STSITE{letter}"] = site_line
        variables[f"ESTATSITE{letter}"] = "ACTIVO" if is_up else "APAGADO"

        block_lines = [
            "━━━━━━━━━━━━",
            f"**{name}**",
            "━━━━━━━━━━━━",
            site_line,
        ]

        devices = st.get("devices", [])
        for num, dev in enumerate(devices, start=1):
            dev_name = dev.get("name", f"Equipo {num}")
            dev_up = bool(dev.get("is_up", False))
            dev_line = f"✅ - {dev_name}" if dev_up else f"❌ - {dev_name}"

            variables[f"NAMESITE{letter}EQUIPO{num}"] = dev_name
            variables[f"STSITE{letter}EQUIPO{num}"] = dev_line
            variables[f"ESTATSITE{letter}EQUIPO{num}"] = "ACTIVO" if dev_up else "APAGADO"

            block_lines.append(dev_line)

        site_blocks.append("\n".join(block_lines))

    variables["SEDES_Y_ENLACES"] = "\n\n".join(site_blocks)

    template_key = "MENSAJEDEBUGB" if debug_mode else "MENSAJEC"
    template_str = loader.templates.get(template_key, "")

    if not template_str:
        lines = [f"🏢 *REPORTE DE SEDES Y ENLACES*", f"Fecha: {fecha_str} {hora_str}", f"🕒 Snapshot: {snapshot_dt}", ""]
        lines.append(variables["SEDES_Y_ENLACES"])
        report = "\n".join(lines)
    else:
        report = render_template(template_str, variables)

    return report.strip(), variables, logs


async def execute_monitoring(
    target: str = "completo",
    debug_mode: bool = False,
    capture_screenshot: bool = False,
    mode: str = "snapshot"
) -> Dict[str, any]:
    """
    Ejecuta el chequeo según el objetivo indicado:
    - 'servicios': Servicios Corporativos
    - 'sedes': Sedes y Equipos de Comunicación
    - 'completo' o 'monitoreo': Ambos componentes
    - 'analisis_red': Análisis profundo de tráfico LAN (.pcap, tshark, arp-scan)
    - 'limpiar': Limpieza de temporales y logs antiguos
    Modo:
    - 'snapshot': Lectura instantánea desde MariaDB (SSOT), cero discrepancias visuales.
    - 'ondemand': Sondeo físico de red en caliente (exclusivo para Owner / Grupo).
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

    # MODO 1: LECTURA DE SNAPSHOT CONSOLIDADO DESDE MARIADB (Por defecto)
    if mode == "snapshot" and target_clean in ("servicios", "sedes", "completo", "monitoreo", "caidas", "incidentes", "fallas"):
        snapshot, snapshot_dt = get_latest_snapshot()
        if snapshot:
            if target_clean == "servicios":
                report_servicios, vars_s, logs_s = compile_services_from_snapshot(snapshot, snapshot_dt, debug_mode)
                all_logs.extend(logs_s)
            elif target_clean == "sedes":
                report_sedes, vars_sd, logs_sd = compile_sedes_from_snapshot(snapshot, snapshot_dt, debug_mode)
                all_logs.extend(logs_sd)
            elif target_clean in ("caidas", "incidentes", "fallas"):
                rep_s, vars_s, logs_s = compile_services_from_snapshot(snapshot, snapshot_dt, debug_mode)
                rep_sd, vars_sd, logs_sd = compile_sedes_from_snapshot(snapshot, snapshot_dt, debug_mode)
                all_logs.extend(logs_s)
                all_logs.extend(logs_sd)
                caidas_lines = []
                for l in (rep_s or "").splitlines():
                    if "❌" in l:
                        caidas_lines.append(l)
                for l in (rep_sd or "").splitlines():
                    if "❌" in l:
                        caidas_lines.append(l)

                fecha_str, hora_str = get_formatted_datetime()
                if caidas_lines:
                    report_servicios = (
                        f"🚨 **REPORTE DE INCIDENTES Y SERVICIOS CAÍDOS**\n"
                        f"📅 *{fecha_str} • {hora_str}*\n"
                        f"🕒 Snapshot: {snapshot_dt}\n\n"
                        f"━━━━━━━━━━━━━━━━━━━━\n"
                        + "\n".join(caidas_lines) + "\n"
                        f"━━━━━━━━━━━━━━━━━━━━"
                    )
                else:
                    report_servicios = (
                        f"✅ **SIN INCIDENTES ACTIVOS**\n"
                        f"📅 *{fecha_str} • {hora_str}*\n"
                        f"🕒 Snapshot: {snapshot_dt}\n\n"
                        f"Todos los servicios y enlaces se encuentran operando con normalidad."
                    )
                report_sedes = None
            else:  # completo / monitoreo
                report_servicios, vars_s, logs_s = compile_services_from_snapshot(snapshot, snapshot_dt, debug_mode)
                report_sedes, vars_sd, logs_sd = compile_sedes_from_snapshot(snapshot, snapshot_dt, debug_mode)
                all_logs.extend(logs_s)
                all_logs.extend(logs_sd)

    # MODO 2: SONDEO FÍSICO DE RED EN CALIENTE (ONDEMAND O FALLBACK)
    if report_servicios is None and report_sedes is None and target_clean not in ("analisis_red", "red", "trafico", "limpiar", "clean", "limpieza"):
        if target_clean == "servicios":
            report_servicios, vars_s, logs_s, _ = await run_services_check(debug_mode=debug_mode)
            all_logs.extend(logs_s)

        elif target_clean == "sedes":
            report_sedes, vars_sd, logs_sd, _ = await run_sedes_check(debug_mode=debug_mode)
            all_logs.extend(logs_sd)

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
                    f"🚨 **REPORTE DE INCIDENTES Y SERVICIOS CAÍDOS (EN VIVO)**\n"
                    f"📅 *{fecha_str} • {hora_str}*\n\n"
                    f"━━━━━━━━━━━━━━━━━━━━\n"
                    + "\n".join(caidas_lines) + "\n"
                    f"━━━━━━━━━━━━━━━━━━━━"
                )
            else:
                report_servicios = (
                    f"✅ **SIN INCIDENTES ACTIVOS (EN VIVO)**\n"
                    f"📅 *{fecha_str} • {hora_str}*\n\n"
                    f"Todos los servicios y enlaces se encuentran operando con normalidad."
                )
            report_sedes = None

        else:  # completo / monitoreo
            (report_servicios, vars_s, logs_s, _), (report_sedes, vars_sd, logs_sd, _) = await asyncio.gather(
                run_services_check(debug_mode=debug_mode),
                run_sedes_check(debug_mode=debug_mode)
            )
            all_logs.extend(logs_s)
            all_logs.extend(logs_sd)

    if target_clean in ("analisis_red", "red", "trafico"):
        from monitor.network_analyzer import run_full_network_analysis
        rep_txt, pcap, _, _ = await run_full_network_analysis(capture_duration=60)
        report_network = rep_txt
        pcap_path = pcap
        all_logs.append(rep_txt)

    elif target_clean in ("limpiar", "clean", "limpieza"):
        from monitor.system_cleaner import run_system_cleanup
        rep_clean = await run_system_cleanup()
        report_servicios = rep_clean
        all_logs.append(rep_clean)

    elapsed_time = round(time.perf_counter() - t0, 2)
    log_file_path = write_consolidated_log(all_logs)

    # Generar captura web panorámica solo si fue solicitada explícitamente (ej: ejecuciones CLI o cron desatendido)
    screenshot_file: Optional[Path] = None
    if capture_screenshot and target_clean in ("servicios", "sedes", "completo", "monitoreo", "caidas", "incidentes", "fallas"):
        try:
            from monitor.web_screenshot import capture_web_dashboard
            screenshot_file = await capture_web_dashboard(mode="full")
        except Exception as e_sc:
            logger.warning(f"No se pudo generar captura web panorámica ({e_sc})")

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
        "screenshot_file": screenshot_file,
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
    result = asyncio.run(execute_monitoring(target=args.target, debug_mode=args.debug, capture_screenshot=True))

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

    if result.get("screenshot_file"):
        print(f"📸 Captura Web Panorámica generada en: {result['screenshot_file']}")
        print("-" * 65)

    print(f"📁 Log consolidado guardado en: {result['log_file']}")
    print(f"⏱️ Tiempo de ejecución: {result['elapsed_seconds']}s")
    print("=" * 65)

    # Envío a Telegram (activo por defecto para ejecuciones de cron a menos que se use --no-send)
    if not args.no_send:
        # Si es un despacho programado desatendido (sin chat_id específico), validar si están activos
        if not args.chat_id and CONFIG_PATH.exists():
            try:
                with open(CONFIG_PATH, "r", encoding="utf-8") as f_cfg:
                    cfg_check = json.load(f_cfg)
                    if not cfg_check.get("cron_reports_enabled", True):
                        print("⏸️ [DESPACHO AUTOMÁTICO] Los envíos programados a Telegram están temporalmente desactivados.")
                        return
            except Exception:
                pass

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
            if result.get("screenshot_file") and Path(result["screenshot_file"]).exists():
                caption = (
                    "📸 <b>Captura en Tiempo Real</b>\n"
                    "🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n"
                    "📌 <i>Vista Global</i>"
                )
                asyncio.run(dispatcher.send_photo(chat, result["screenshot_file"], caption=caption))
            if result.get("pcap_file") and Path(result["pcap_file"]).exists():
                asyncio.run(dispatcher.send_document(chat, Path(result["pcap_file"]), caption="Captura de paquetes de red"))
            if args.debug and result["log_file"].exists():
                asyncio.run(dispatcher.send_document(chat, result["log_file"], caption="Log técnico consolidado"))

        print("✅ Reportes despachados exitosamente a Telegram.")


if __name__ == "__main__":
    main()

