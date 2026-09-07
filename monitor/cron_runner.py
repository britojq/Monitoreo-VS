"""
# ==============================================================================
# ⏰ DESPACHADOR DINÁMICO DE REPORTES PROGRAMADOS: cron_runner.py (@IA_ValleSeco_bot)
# Orquestación de ejecuciones periódicas basadas en horarios dinámicos configurables
# Ubicación: /scripts/telegram-admin-bot/monitor/cron_runner.py
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
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_engine import execute_monitoring, get_default_telegram_chats
from monitor.telegram_dispatcher import TelegramDispatcher

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)
CONFIG_PATH = BASE_DIR / "config" / "config.json"
DEBOUNCE_FILE = LOG_DIR / "last_cron_dispatch.txt"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [cron.runner] %(message)s",
    handlers=[
        logging.FileHandler(LOG_DIR / "cron_runner.log", encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("cron.runner")


def load_cron_config() -> Dict[str, any]:
    """Carga la configuración de reportes programados desde config.json."""
    default_config = {
        "cron_reports_enabled": True,
        "cron_schedules": ["07:30", "16:00"],
        "cron_reports_updated_at": "",
        "cron_reports_updated_by": "Sistema",
    }
    if CONFIG_PATH.exists():
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                data = json.load(f)
                default_config["cron_reports_enabled"] = bool(data.get("cron_reports_enabled", True))
                schedules = data.get("cron_schedules", ["07:30", "16:00"])
                if isinstance(schedules, list) and schedules:
                    default_config["cron_schedules"] = sorted(list(set(schedules)))
                default_config["cron_reports_updated_at"] = data.get("cron_reports_updated_at", "")
                default_config["cron_reports_updated_by"] = data.get("cron_reports_updated_by", "")
        except Exception as e:
            logger.error(f"Error cargando {CONFIG_PATH}: {e}")
    return default_config


def get_next_schedule(schedules: List[str]) -> Tuple[Optional[str], Optional[str]]:
    """Determina cuál es el próximo horario programado y cuándo ocurrirá."""
    if not schedules:
        return None, None

    now = datetime.now()
    now_hm = now.strftime("%H:%M")

    # Buscar horario posterior hoy
    for sch in schedules:
        if sch > now_hm:
            return sch, "hoy"

    # Si ya pasaron todos hoy, el primero de mañana
    return schedules[0], "mañana"


async def run_scheduled_dispatch(force: bool = False) -> bool:
    """Verifica el horario actual y ejecuta el monitoreo si coincide."""
    cfg = load_cron_config()
    is_enabled = cfg["cron_reports_enabled"]
    schedules = cfg["cron_schedules"]

    now = datetime.now()
    current_time_str = now.strftime("%H:%M")
    today_date_str = now.strftime("%Y-%m-%d")

    if not is_enabled and not force:
        logger.info("⏸️ [PAUSA] Los reportes programados están desactivados por el administrador.")
        return False

    if not force:
        if current_time_str not in schedules:
            return False

        # Verificación Anti-Duplicados (Debounce para el mismo minuto)
        current_stamp = f"{today_date_str} {current_time_str}"
        if DEBOUNCE_FILE.exists():
            try:
                last_stamp = DEBOUNCE_FILE.read_text(encoding="utf-8").strip()
                if last_stamp == current_stamp:
                    logger.info(f"⏳ [DEBOUNCE] El reporte para {current_stamp} ya fue despachado previamente.")
                    return False
            except Exception:
                pass

        DEBOUNCE_FILE.write_text(current_stamp, encoding="utf-8")

    logger.info(f"⏰ [DISPARO PROGRAMADO] Iniciando escaneo y despacho para las {current_time_str}...")

    # Ejecutar monitoreo de servicios corporativos
    result = await execute_monitoring(target="servicios", debug_mode=False)

    dispatcher = TelegramDispatcher()
    target_chats = get_default_telegram_chats()

    logger.info(f"📤 Despachando a destinatarios oficiales: {target_chats}")

    for chat_id in target_chats:
        try:
            if result.get("report_servicios"):
                await dispatcher.send_text(chat_id, result["report_servicios"])

            if result.get("screenshot_file") and Path(result["screenshot_file"]).exists():
                caption = (
                    "📸 <b>Captura en Tiempo Real</b>\n"
                    "🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n"
                    "📌 <i>Vista Global de Infraestructura</i>"
                )
                await dispatcher.send_photo(chat_id, result["screenshot_file"], caption=caption)

            logger.info(f"✅ Despachado exitosamente a chat_id: {chat_id}")
        except Exception as e:
            logger.error(f"❌ Error despachando a {chat_id}: {e}")

    logger.info("🏁 Ciclo de reporte programado finalizado con éxito.")
    return True


def print_status():
    """Muestra el estado actual de los reportes programados en consola."""
    cfg = load_cron_config()
    is_enabled = cfg["cron_reports_enabled"]
    schedules = cfg["cron_schedules"]
    updated_at = cfg["cron_reports_updated_at"] or "N/A"
    updated_by = cfg["cron_reports_updated_by"] or "N/A"

    next_sch, next_day = get_next_schedule(schedules)

    print("\n" + "=" * 55)
    print("⏰ ESTADO DE REPORTES PROGRAMADOS (CRON VALLE SECO)")
    print("=" * 55)
    estado_str = "🟢 ACTIVADO" if is_enabled else "🔴 PAUSADO"
    print(f"• Estado General:        {estado_str}")
    print(f"• Horarios Configurados: {', '.join(schedules) if schedules else 'Ninguno'}")
    if next_sch:
        print(f"• Próximo Despacho:      {next_sch} ({next_day})")
    else:
        print("• Próximo Despacho:      Sin horarios")
    print(f"• Última Modificación:   {updated_at}")
    print(f"• Modificado Por:        {updated_by}")
    print("=" * 55 + "\n")


def main():
    parser = argparse.ArgumentParser(description="Despachador Dinámico de Reportes Programados")
    parser.add_argument("--status", action="store_true", help="Consultar estado y horarios configurados")
    parser.add_argument("--force", action="store_true", help="Forzar ejecución y despacho inmediato")
    parser.add_argument("--check", action="store_true", help="Verificar coincidencia de hora sin despachar")
    args = parser.parse_args()

    if args.status:
        print_status()
        return

    if args.check:
        cfg = load_cron_config()
        now_hm = datetime.now().strftime("%H:%M")
        matches = (now_hm in cfg["cron_schedules"]) and cfg["cron_reports_enabled"]
        print(f"Check at {now_hm}: matches={matches}")
        sys.exit(0 if matches else 1)

    asyncio.run(run_scheduled_dispatch(force=args.force))


if __name__ == "__main__":
    main()
