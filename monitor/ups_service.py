"""
# ==============================================================================
# 🔋 RECOLECTOR Y GESTOR DE ALERTAS DE UPS: ups_service.py (@IA_ValleSeco_bot)
# Fachada delegadora SOLID ultra-delgada con preservación de paridad de API y CLI
# Ubicación: /opt/sonar/monitor/ups_service.py
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
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# 1. Constantes y políticas de dominio
from monitor.monitor_db import get_db_connection
from monitor.telegram_dispatcher import TelegramDispatcher
from monitor.ups.domain.battery_policy import (
    CELLS_PER_12V_BATTERY,
    COMMAND_INFO,
    COMMAND_RATING,
    COMMAND_STATUS,
    TOTAL_BATTERIES,
    TOTAL_CELLS,
    V_BANK_MAX,
    V_BANK_MIN,
    V_CELL_MAX,
    V_CELL_MIN,
    calculate_battery_percentage,
)
from monitor.ups.domain.duration_formatter import (
    format_duration,
    get_notification_target,
    get_notification_targets,
)

# 2. Drivers de comunicación industrial
from monitor.ups.drivers.megatec_driver import (
    query_ups_hardware,
    query_ups_info,
    query_ups_rating,
    query_ups_serial,
)
from monitor.ups.drivers.serial_scanner import list_system_serial_ports
from monitor.ups.drivers.usb_device_info import get_usb_device_info

# 3. ITAM y Detección
from monitor.ups.itam.detect_hardware_action import scan_and_detect_ups
from monitor.ups.itam.sync_inventory_action import sync_ups_to_inventory

# 4. Constructores de alertas y Workers
from monitor.ups.daemon.alert_builder import (
    build_ups_battery_low_alert,
    build_ups_fluctuation_alert,
    build_ups_outage_alert,
    build_ups_recovery_alert,
)
from monitor.ups.daemon.worker_daemon import (
    poll_and_process_ups,
    run_ups_daemon,
)

LOG_DIR = Path("/tmp/monitor")
LOG_FILE = LOG_DIR / "ups_service.log"
logger = logging.getLogger("ups.service")


def main() -> None:
    """Punto de entrada principal para ejecución por línea de comandos."""
    parser = argparse.ArgumentParser(description="Gestor y Recolector de Telemetría UPS ZTG LV6KL")
    parser.add_argument("--daemon", action="store_true", help="Ejecutar en modo demonio continuo")
    parser.add_argument("--poll", action="store_true", help="Ejecutar una única consulta y actualizar BD")
    parser.add_argument("--scan", action="store_true", help="Escanear sistemáticamente todos los puertos seriales para autodetectar el UPS")
    parser.add_argument("--test-port", type=str, default=None, help="Probar un puerto serial específico (ej: /dev/ttyUSB0)")
    parser.add_argument("--baud", type=int, default=None, help="Velocidad en baudios para la prueba (ej: 2400, 9600)")
    parser.add_argument("--interval", type=int, default=10, help="Intervalo en segundos para modo demonio")
    parser.add_argument("--info", action="store_true", help="Interrogar al UPS para extraer modelo, marca, firmware y rating de placa")
    args = parser.parse_args()

    if args.info:
        target_port = args.test_port or "/dev/ttyS0"
        target_baud = args.baud or 2400
        hw_data = query_ups_hardware(port=target_port, baud=target_baud)
        print(json.dumps(hw_data, ensure_ascii=False, indent=2))
        sys.exit(0 if hw_data.get("success") else 1)
    elif args.scan:
        print("🔍 Iniciando escaneo de puertos seriales del sistema...")
        ports = list_system_serial_ports()
        print(f"Puertos físicos/USB disponibles encontrados: {ports}")
        res = scan_and_detect_ups(preferred_port=args.test_port, preferred_baud=args.baud)
        if res:
            p, b, data = res
            print(f"✅ ¡UPS DETECTADO EXITOSAMENTE en {p} @ {b} bps!")
            print(f"   Voltaje Entrada: {data.get('input_voltage')}V | Salida: {data.get('output_voltage')}V | Batería: {data.get('battery_percent')}%")
        else:
            print("❌ No se detectó ningún UPS respondiendo en los puertos seriales escaneados.")
            sys.exit(1)
    elif args.test_port:
        target_port = args.test_port
        target_baud = args.baud or 2400
        print(f"🔍 Probando comunicación con UPS en {target_port} @ {target_baud} bps...")
        data = query_ups_serial(port=target_port, baud=target_baud)
        if data:
            print("=====================================================")
            print("       ESTADO EN TIEMPO REAL: UPS ZTG LV6KL 6kVA     ")
            print("=====================================================")
            print(f" Puerto Probado:      {target_port} @ {target_baud} bps")
            print(f" Estado de Línea:     {'MODO BATERÍA (CORTE ELÉCTRICO)' if data.get('is_on_battery') else 'RED COMERCIAL NORMAL'}")
            print(f" Entrada Red (220V):  {data.get('input_voltage')} VAC")
            raw_out_cli = float(data.get('output_voltage') or 208.0)
            out_eq_cli = round(raw_out_cli * (110.0 / 208.0), 1) if raw_out_cli > 150 else raw_out_cli
            print(f" Salida Equipos (110V): {out_eq_cli} VAC (Inversor L-L: {raw_out_cli} VAC)")
            print(f" Frecuencia:          {data.get('frequency')} Hz")
            print(f" Consumo de Carga:    {data.get('load_percent')} %")
            print(f" Nivel de Batería:    {data.get('battery_percent')} % ({data.get('battery_voltage')} V/celda)")
            print(f" Temperatura Interna: {data.get('temperature_c')} °C")
            print("=====================================================")
        else:
            print(f"❌ Sin respuesta del UPS en {target_port} @ {target_baud} bps.")
            sys.exit(1)
    elif args.daemon:
        asyncio.run(run_ups_daemon(interval_seconds=args.interval))
    else:
        result = asyncio.run(poll_and_process_ups())
        if result:
            print("=====================================================")
            print("       ESTADO EN TIEMPO REAL: UPS ZTG LV6KL 6kVA     ")
            print("=====================================================")
            print(f" Estado de Línea:     {'MODO BATERÍA (CORTE ELÉCTRICO)' if result.get('is_on_battery') else 'RED COMERCIAL NORMAL'}")
            print(f" Entrada Red (220V):  {result.get('input_voltage')} VAC")
            raw_out_cli = float(result.get('output_voltage') or 208.0)
            out_eq_cli = round(raw_out_cli * (110.0 / 208.0), 1) if raw_out_cli > 150 else raw_out_cli
            print(f" Salida Equipos (110V): {out_eq_cli} VAC (Inversor L-L: {raw_out_cli} VAC)")
            print(f" Frecuencia:          {result.get('frequency')} Hz")
            print(f" Consumo de Carga:    {result.get('load_percent')} %")
            print(f" Nivel de Batería:    {result.get('battery_percent')} % ({result.get('battery_voltage')} V/celda)")
            print(f" Temperatura Interna: {result.get('temperature_c')} °C")
            print(f" Alertas Telegram:    {'ACTIVADAS' if result.get('telegram_alert_enabled') else 'DESACTIVADAS'} (Destino: {result.get('telegram_alert_target')})")
            print("=====================================================")
        else:
            print("No se pudo obtener respuesta del UPS en el puerto serial configurado.")


if __name__ == "__main__":
    main()
