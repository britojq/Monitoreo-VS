"""
# ==============================================================================
# 🔋 RECOLECTOR Y GESTOR DE ALERTAS DE UPS: ups_service.py (@IA_ValleSeco_bot)
# Monitoreo serial en tiempo real (Megatec Q1) para UPS ZTG LV6KL 6kVA
# Ubicación: /scripts/telegram-admin-bot/monitor/ups_service.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import html
import json
import logging
import os
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, Optional, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_db import get_db_connection
from monitor.telegram_dispatcher import TelegramDispatcher

LOG_DIR = Path("/tmp/monitor")
try:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
except Exception:
    pass
LOG_FILE = LOG_DIR / "ups_service.log"

log_handlers = [logging.StreamHandler(sys.stdout)]
try:
    file_handler = logging.FileHandler(LOG_FILE, encoding="utf-8")
    log_handlers.append(file_handler)
except Exception as e:
    sys.stderr.write(f"Aviso: No se pudo abrir {LOG_FILE} ({e}). Registrando en stdout.\n")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [ups.service] %(message)s",
    handlers=log_handlers,
)
logger = logging.getLogger("ups.service")


def get_notification_target(target_type: str) -> str:
    """Resuelve el ID de Telegram respetando la REGLA DE ORO #1."""
    config_file = BASE_DIR / "config" / "config.json"
    owner_id = "38914901"
    group_id = "-1001383163558"
    node_role = "master"

    if config_file.exists():
        try:
            cfg = json.loads(config_file.read_text(encoding="utf-8"))
            owner_id = str(cfg.get("owner_id", "38914901"))
            groups = cfg.get("allowed_group_ids", [])
            if groups:
                group_id = str(groups[0])
            node_role = str(cfg.get("node_role", "master")).lower()
        except Exception:
            pass

    if target_type == "group":
        if node_role == "slave" or os.environ.get("TESTING") == "1" or os.environ.get("APP_ENV") == "local":
            logger.warning(
                f"🚨 REGLA DE ORO #1: Despacho a grupo redirigido a Owner ({owner_id}) "
                f"debido al entorno no productivo (rol: {node_role})."
            )
            return owner_id
        return group_id

    return owner_id


def format_duration(seconds: float | int) -> str:
    """Convierte segundos a formato legible en español."""
    total_sec = max(0, int(seconds))
    if total_sec < 60:
        return f"{total_sec} seg"
    minutes = total_sec // 60
    rem_sec = total_sec % 60
    if minutes < 60:
        return f"{minutes} min {rem_sec} seg" if rem_sec > 0 else f"{minutes} min"
    hours = minutes // 60
    rem_min = minutes % 60
    return f"{hours} h {rem_min} min"


def calculate_battery_percentage(v_cell: float) -> int:
    """Calcula el porcentaje de batería en base al voltaje por celda (plomo-ácido AGM/GEL)."""
    # Rango por celda: 1.75V (0%) a 2.25V (100% flotación)
    if v_cell >= 2.23:
        return 100
    elif v_cell <= 1.75:
        return 0
    pct = (v_cell - 1.75) / (2.25 - 1.75) * 100.0
    return min(100, max(0, int(round(pct))))


def query_ups_serial(port: str = "/dev/ttyS0", baud: int = 2400, timeout: float = 1.0) -> Optional[Dict[str, Any]]:
    """Consulta el UPS mediante el protocolo industrial Megatec (Q1) por puerto serial."""
    try:
        import serial
    except ImportError:
        logger.error("pyserial no está instalado en el entorno.")
        return None

    if not Path(port).exists():
        logger.warning(f"Puerto serial {port} no encontrado.")
        return None

    try:
        ser = serial.Serial(
            port,
            baudrate=baud,
            bytesize=serial.EIGHTBITS,
            parity=serial.PARITY_NONE,
            stopbits=serial.STOPBITS_ONE,
            timeout=timeout,
        )
        ser.dtr = True
        ser.rts = True
        ser.reset_input_buffer()
        ser.reset_output_buffer()

        ser.write(b"Q1\r")
        time.sleep(0.18)
        raw_ans = ser.read(100)
        ser.close()

        if not raw_ans:
            return None

        clean = raw_ans.decode("latin1", errors="ignore").strip().lstrip("(").rstrip("\r\xff\x00")
        parts = clean.split()
        if len(parts) < 8:
            logger.warning(f"Trama Q1 incompleta recibida: {repr(raw_ans)}")
            return None

        import re
        try:
            in_v = float(re.sub(r"[^0-9.]", "", parts[0].replace("%", "5")))
            in_fault_v = float(re.sub(r"[^0-9.]", "", parts[1].replace("%", "5")))
            out_v = float(re.sub(r"[^0-9.]", "", parts[2].replace("%", "5")))
            load_pct = int(float(re.sub(r"[^0-9.]", "", parts[3])))
            freq = float(re.sub(r"[^0-9.]", "", parts[4].replace("%", "5")))
            bat_v = float(re.sub(r"[^0-9.]", "", parts[5].replace("%", "5")))
            temp_c = float(re.sub(r"[^0-9.]", "", parts[6].replace("%", "5")))
            status_bits = re.sub(r"[^01]", "", parts[7])
            if len(status_bits) < 8:
                status_bits = status_bits.ljust(8, "0")
        except (ValueError, IndexError) as err:
            logger.warning(f"Error parseando campos de trama Q1: {err} (trama: {clean})")
            return None

        # Decodificación de bits de estado: b7 b6 b5 b4 b3 b2 b1 b0
        # b7: Utility Fail (1 = Corte de luz / En batería)
        # b6: Battery Low (1 = Batería baja)
        # b5: Bypass / AVR (1 = Activo)
        # b4: UPS Failed (1 = Falla)
        # b3: UPS Type (1 = On-line)
        # b2: Test in Progress (1 = Prueba)
        # b1: Shutdown Active (1 = Apagado)
        # b0: Beeper ON (1 = Activo)
        is_on_bat = (status_bits[0] == "1") if len(status_bits) > 0 else False
        is_bat_low = (status_bits[1] == "1") if len(status_bits) > 1 else False
        is_bypass = (status_bits[2] == "1") if len(status_bits) > 2 else False
        is_ups_fail = (status_bits[3] == "1") if len(status_bits) > 3 else False
        beeper_on = (status_bits[7] == "1") if len(status_bits) > 7 else True

        bat_pct = calculate_battery_percentage(bat_v)

        return {
            "input_voltage": in_v,
            "input_fault_voltage": in_fault_v,
            "output_voltage": out_v,
            "load_percent": load_pct,
            "frequency": freq,
            "battery_voltage": bat_v,
            "battery_percent": bat_pct,
            "temperature_c": temp_c,
            "is_online": True,
            "is_on_battery": is_on_bat,
            "is_battery_low": is_bat_low,
            "is_bypass": is_bypass,
            "is_ups_failed": is_ups_fail,
            "beeper_on": beeper_on,
            "raw_status_bits": status_bits,
        }

    except Exception as e:
        logger.error(f"Error consultando puerto serial {port}: {e}")
        return None


def build_ups_outage_alert(device: dict, telemetry: dict) -> str:
    """Construye mensaje corporativo de corte eléctrico / apagón."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    model = html.escape(str(device.get("model") or "6kVA"))
    bat_pct = telemetry.get("battery_percent", 100)
    load_pct = telemetry.get("load_percent", 0)
    temp_c = telemetry.get("temperature_c", 40.0)
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "⚡ <b>ALERTA CRÍTICA: CORTE ELÉCTRICO / APAGÓN DETECTADO</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code> ({model})\n"
        "⚠️ <b>Condición:</b> <b>OPERANDO EN MODO BATERÍA</b>\n"
        f"🔋 <b>Nivel de Batería:</b> {bat_pct}% ({telemetry.get('battery_voltage', 2.25):.2f} V/celda)\n"
        f"📊 <b>Carga de Consumo:</b> {load_pct}% (~{int(load_pct * 60)} Watts)\n"
        f"🌡️ <b>Temperatura Inversor:</b> {temp_c:.1f} °C\n"
        f"🕒 <b>Detectado:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "⚠️ <i>Iniciado conteo de autonomía de respaldo institucional.</i>"
    )


def build_ups_recovery_alert(device: dict, telemetry: dict, duration_str: str) -> str:
    """Construye mensaje corporativo de restablecimiento de energía eléctrica."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    in_v = telemetry.get("input_voltage", 220.0)
    out_v = telemetry.get("output_voltage", 208.0)
    freq = telemetry.get("frequency", 60.0)
    bat_pct = telemetry.get("battery_percent", 100)
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "✅ <b>RECUPERACIÓN: FLUIDO ELÉCTRICO RESTABLECIDO</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code>\n"
        f"🔌 <b>Entrada Comercial:</b> {in_v:.1f} VAC ({freq:.1f} Hz)\n"
        f"⚡ <b>Salida Regulada:</b> {out_v:.1f} VAC (Online Inversor)\n"
        f"⏱️ <b>Tiempo en Batería:</b> {duration_str}\n"
        f"🔋 <b>Estado Batería:</b> {bat_pct}% (Recarga en curso)\n"
        f"🕒 <b>Restablecido:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "🛡️ <i>Alimentación eléctrica comercial operando normalmente.</i>"
    )


def build_ups_battery_low_alert(device: dict, telemetry: dict) -> str:
    """Construye mensaje de alerta cuando el banco de batería está por agotarse."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    bat_pct = telemetry.get("battery_percent", 15)
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "🚨 <b>ALERTA EXTREMA: BATERÍA CRÍTICA EN UPS</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code>\n"
        f"⚠️ <b>Nivel Restante:</b> <b>{bat_pct}% (Agotamiento inminente)</b>\n"
        "⚡ <b>Acción recomendada:</b> Preparar apagado controlado de servidores.\n"
        f"🕒 <b>Reportado:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )


async def poll_and_process_ups(dispatcher: Optional[TelegramDispatcher] = None) -> Optional[dict]:
    """
    Ejecuta un ciclo de recolección de telemetría del UPS,
    actualiza MariaDB y gestiona el despacho reactivo de alertas de energía.
    """
    conn = get_db_connection()
    now = datetime.now()

    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM ups_devices LIMIT 1")
            device = cur.fetchone()

        if not device:
            logger.warning("No se encontró ningún registro en ups_devices.")
            return None

        port = device.get("serial_port") or "/dev/ttyS0"
        baud = int(device.get("baud_rate") or 2400)

        # Leer telemetría física por puerto serial
        telemetry = query_ups_serial(port=port, baud=baud)
        if not telemetry:
            # Marcar fuera de línea si no responde al puerto serial
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE ups_devices SET is_online = 0, updated_at = NOW() WHERE id = %s",
                    (device["id"],)
                )
            conn.commit()
            return None

        # Evaluar transiciones de estado de energía para alertas
        last_state = (device.get("last_alert_state") or "NORMAL").upper()
        alert_enabled = bool(device.get("telegram_alert_enabled", 1))
        target_setting = device.get("telegram_alert_target") or "owner"
        target_chat = get_notification_target(target_setting)

        if dispatcher is None and alert_enabled:
            dispatcher = TelegramDispatcher()

        current_state = "NORMAL"
        outage_since = device.get("outage_since")

        if telemetry["is_on_battery"]:
            current_state = "BATTERY_LOW" if telemetry["is_battery_low"] else "ON_BATTERY"

        # CASO 1: Se corta la energía eléctrica (NORMAL -> ON_BATTERY)
        if current_state in ("ON_BATTERY", "BATTERY_LOW") and last_state == "NORMAL":
            outage_since = now
            if alert_enabled and dispatcher:
                msg = build_ups_outage_alert(device, telemetry)
                ok, err = await dispatcher.send_text(target_chat, msg, parse_mode="HTML")
                if ok:
                    logger.info(f"📤 Alerta de corte eléctrico despachada a {target_chat}")
                else:
                    logger.warning(f"❌ Falló despacho de alerta de corte eléctrico: {err}")

        # CASO 2: Batería baja durante el corte
        elif current_state == "BATTERY_LOW" and last_state == "ON_BATTERY":
            if alert_enabled and dispatcher:
                msg = build_ups_battery_low_alert(device, telemetry)
                ok, err = await dispatcher.send_text(target_chat, msg, parse_mode="HTML")
                if ok:
                    logger.info(f"📤 Alerta de batería baja despachada a {target_chat}")

        # CASO 3: Restablecimiento de la energía (ON_BATTERY / BATTERY_LOW -> NORMAL)
        elif current_state == "NORMAL" and last_state in ("ON_BATTERY", "BATTERY_LOW"):
            dur_str = "Desconocido"
            if outage_since:
                if isinstance(outage_since, datetime):
                    dur_sec = (now - outage_since).total_seconds()
                else:
                    try:
                        ds_dt = datetime.strptime(str(outage_since)[:19], "%Y-%m-%d %H:%M:%S")
                        dur_sec = (now - ds_dt).total_seconds()
                    except Exception:
                        dur_sec = 0
                dur_str = format_duration(dur_sec)

            if alert_enabled and dispatcher:
                msg = build_ups_recovery_alert(device, telemetry, dur_str)
                ok, err = await dispatcher.send_text(target_chat, msg, parse_mode="HTML")
                if ok:
                    logger.info(f"📤 Notificación de energía restablecida despachada a {target_chat}")
                else:
                    logger.warning(f"❌ Falló despacho de energía restablecida: {err}")
            outage_since = None

        # Actualizar tabla ups_devices en MariaDB
        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE ups_devices
                SET input_voltage = %s,
                    input_fault_voltage = %s,
                    output_voltage = %s,
                    load_percent = %s,
                    frequency = %s,
                    battery_voltage = %s,
                    battery_percent = %s,
                    temperature_c = %s,
                    is_online = 1,
                    is_on_battery = %s,
                    is_battery_low = %s,
                    is_bypass = %s,
                    is_ups_failed = %s,
                    beeper_on = %s,
                    last_alert_state = %s,
                    outage_since = %s,
                    last_seen_at = %s,
                    updated_at = NOW()
                WHERE id = %s
                """,
                (
                    telemetry["input_voltage"],
                    telemetry["input_fault_voltage"],
                    telemetry["output_voltage"],
                    telemetry["load_percent"],
                    telemetry["frequency"],
                    telemetry["battery_voltage"],
                    telemetry["battery_percent"],
                    telemetry["temperature_c"],
                    1 if telemetry["is_on_battery"] else 0,
                    1 if telemetry["is_battery_low"] else 0,
                    1 if telemetry["is_bypass"] else 0,
                    1 if telemetry["is_ups_failed"] else 0,
                    1 if telemetry["beeper_on"] else 0,
                    current_state,
                    outage_since,
                    now,
                    device["id"],
                )
            )

            # Insertar registro en historial de telemetría
            cur.execute(
                """
                INSERT INTO ups_telemetry_histories
                (ups_device_id, input_voltage, output_voltage, load_percent,
                 battery_percent, battery_voltage, temperature_c,
                 is_on_battery, is_battery_low, is_bypass, recorded_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    device["id"],
                    telemetry["input_voltage"],
                    telemetry["output_voltage"],
                    telemetry["load_percent"],
                    telemetry["battery_percent"],
                    telemetry["battery_voltage"],
                    telemetry["temperature_c"],
                    1 if telemetry["is_on_battery"] else 0,
                    1 if telemetry["is_battery_low"] else 0,
                    1 if telemetry["is_bypass"] else 0,
                    now,
                )
            )

            # Purgar histórico de más de 30 días
            cur.execute("DELETE FROM ups_telemetry_histories WHERE recorded_at < NOW() - INTERVAL 30 DAY")
        conn.commit()

        return {**device, **telemetry}

    finally:
        conn.close()


async def run_ups_daemon(interval_seconds: int = 10):
    """Bucle de ejecución continua para el demonio de supervisión del UPS."""
    logger.info(f"🚀 Iniciando demonio de monitoreo de UPS (intervalo: {interval_seconds}s)...")
    dispatcher = TelegramDispatcher()

    while True:
        try:
            res = await poll_and_process_ups(dispatcher=dispatcher)
            if res:
                mode_str = "BATERÍA" if res.get("is_on_battery") else "LÍNEA (Red Normal)"
                logger.info(
                    f"⚡ [UPS OK] Modo: {mode_str} | In: {res.get('input_voltage')}V | "
                    f"Out: {res.get('output_voltage')}V | Bat: {res.get('battery_percent')}% | "
                    f"Carga: {res.get('load_percent')}% | Temp: {res.get('temperature_c')}°C"
                )
        except Exception as e:
            logger.error(f"Aviso en ciclo de monitoreo UPS: {e}")

        await asyncio.sleep(interval_seconds)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Gestor y Recolector de Telemetría UPS ZTG LV6KL")
    parser.add_argument("--daemon", action="store_true", help="Ejecutar en modo demonio continuo")
    parser.add_argument("--poll", action="store_true", help="Ejecutar una única consulta y actualizar BD")
    parser.add_argument("--interval", type=int, default=10, help="Intervalo en segundos para modo demonio")
    args = parser.parse_args()

    if args.daemon:
        asyncio.run(run_ups_daemon(interval_seconds=args.interval))
    else:
        result = asyncio.run(poll_and_process_ups())
        if result:
            print("=====================================================")
            print("       ESTADO EN TIEMPO REAL: UPS ZTG LV6KL 6kVA     ")
            print("=====================================================")
            print(f" Estado de Línea:     {'MODO BATERÍA (CORTE ELÉCTRICO)' if result.get('is_on_battery') else 'RED COMERCIAL NORMAL'}")
            print(f" Voltaje de Entrada:  {result.get('input_voltage')} VAC")
            print(f" Voltaje de Salida:   {result.get('output_voltage')} VAC")
            print(f" Frecuencia:          {result.get('frequency')} Hz")
            print(f" Consumo de Carga:    {result.get('load_percent')} %")
            print(f" Nivel de Batería:    {result.get('battery_percent')} % ({result.get('battery_voltage')} V/celda)")
            print(f" Temperatura Interna: {result.get('temperature_c')} °C")
            print(f" Alertas Telegram:    {'ACTIVADAS' if result.get('telegram_alert_enabled') else 'DESACTIVADAS'} (Destino: {result.get('telegram_alert_target')})")
            print("=====================================================")
        else:
            print("No se pudo obtener respuesta del UPS en el puerto serial configurado.")
