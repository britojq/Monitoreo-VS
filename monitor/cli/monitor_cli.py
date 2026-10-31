"""
# ==============================================================================
# 💻 INTERFAZ CLI DE MONITOREO: monitor_cli.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Punto de entrada CLI para ejecución por terminal y tareas cron del sistema.
# Ubicación: /opt/sonar/monitor/cli/monitor_cli.py
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import logging
from pathlib import Path
from typing import List, Union

from monitor.application.orchestrator import execute_monitoring
from monitor.core_shield import IMMUTABLE_OWNER_ID
from monitor.telegram_dispatcher import TelegramDispatcher

logger = logging.getLogger("monitor.cli")


def get_default_telegram_chats() -> List[Union[str, int]]:
    """Obtiene la lista de destinatarios por defecto para reportes programados desde MariaDB SSOT."""
    recipients: List[Union[str, int]] = []
    try:
        from bot_core.services.config_manager import load_config
        cfg = load_config()
        groups = cfg.get("allowed_group_ids", [])
        if groups:
            recipients.extend(groups)
        owner = cfg.get("owner_id")
        if owner and owner not in recipients:
            recipients.append(owner)
    except Exception:
        pass

    if not recipients:
        recipients = [IMMUTABLE_OWNER_ID]
    return recipients


def main() -> None:
    """Punto de entrada de línea de comandos para monitor_engine."""
    parser = argparse.ArgumentParser(description="Orquestador CLI de Monitoreo Valle Seco (S.O.N.A.R.)")
    parser.add_argument(
        "target",
        nargs="?",
        default="servicios",
        choices=["servicios", "sedes", "completo", "monitoreo", "analisis_red", "limpiar"],
        help="Tipo de chequeo a ejecutar (por defecto: servicios)",
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

    if not args.no_send:
        if not args.chat_id:
            try:
                from bot_core.services.config_manager import load_config
                cfg_check = load_config()
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
