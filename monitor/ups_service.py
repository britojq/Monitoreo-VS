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
from typing import Any, Dict, List, Optional, Tuple

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
OUTAGE_ALERT_MARKER = BASE_DIR / "audit" / ".outage_group_alert_active"

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


def get_notification_targets(target_type: str) -> List[str]:
    """
    Resuelve los IDs de Telegram para el despacho de alertas de energía respetando la REGLA DE ORO #1.
    Si target_type es 'group', en entorno productivo (nodo master) realiza un despacho DUAL
    simultáneo hacia el Administrador Privado (Owner) y el Grupo Corporativo.
    En entornos de desarrollo/test o modo esclavo, se restringe exclusivamente al Owner.
    """
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

    targets = [owner_id]

    if str(target_type).strip().lower() == "group":
        is_non_prod = (
            node_role in ("slave", "dev")
            or os.environ.get("TESTING") == "1"
            or os.environ.get("APP_ENV") == "local"
        )
        if is_non_prod:
            logger.warning(
                f"🚨 REGLA DE ORO #1: Despacho a grupo restringido a Owner ({owner_id}) "
                f"debido al entorno no productivo (rol: {node_role})."
            )
        else:
            if group_id not in targets:
                targets.append(group_id)

    return targets


def get_notification_target(target_type: str) -> str:
    """Compatibilidad hacia atrás con llamada singular (retorna destino grupal o principal)."""
    targets = get_notification_targets(target_type)
    return targets[-1] if str(target_type).strip().lower() == "group" else targets[0]



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
        time.sleep(0.22)
        raw_ans = ser.read_until(b"\r", size=120)
        if not raw_ans:
            raw_ans = ser.read(100)
        ser.close()

        if not raw_ans:
            return None

        clean = raw_ans.decode("latin1", errors="ignore").strip().lstrip("(").rstrip("\r\xff\x00")
        import re
        # Normalizar caracteres de ruido en separadores de campos (ruido RS-232: $, !, `, >, etc.)
        clean = re.sub(r"[^\w.\r\n()\-]+", " ", clean)
        parts = clean.split()
        if len(parts) < 8:
            logger.warning(f"Trama Q1 incompleta o con ruido recibida: {repr(raw_ans)}")
            return None

        try:
            in_v = float(re.sub(r"[^0-9.]", "", parts[0]))
            in_fault_v = float(re.sub(r"[^0-9.]", "", parts[1]))
            out_v = float(re.sub(r"[^0-9.]", "", parts[2]))
            load_pct = int(float(re.sub(r"[^0-9.]", "", parts[3])))
            freq = float(re.sub(r"[^0-9.]", "", parts[4]))
            bat_v = float(re.sub(r"[^0-9.]", "", parts[5]))
            temp_c = float(re.sub(r"[^0-9.]", "", parts[6]))
            status_bits = re.sub(r"[^01]", "", parts[7])
        except (ValueError, IndexError) as err:
            logger.warning(f"Error parseando campos de trama Q1: {err} (trama: {clean})")
            return None

        # Validación estricta de rangos físicos de operación
        if not (0.0 <= in_v <= 320.0):
            logger.warning(f"Trama descartada: Voltaje de entrada anómalo ({in_v}V)")
            return None
        if not (0.0 <= out_v <= 320.0):
            logger.warning(f"Trama descartada: Voltaje de salida anómalo ({out_v}V)")
            return None
        if not (0 <= load_pct <= 200):
            logger.warning(f"Trama descartada: Carga anómala ({load_pct}%)")
            return None
        if not (0.0 <= temp_c <= 95.0):
            logger.warning(f"Trama descartada: Temperatura anómala ({temp_c}°C)")
            return None
        if len(status_bits) != 8:
            logger.warning(f"Trama descartada: Bits de estado anómalos ({status_bits})")
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
        is_on_bat = (status_bits[0] == "1")
        is_bat_low = (status_bits[1] == "1")
        is_bypass = (status_bits[2] == "1")
        is_ups_fail = (status_bits[3] == "1")
        is_test_in_progress = (status_bits[5] == "1")
        beeper_on = (status_bits[7] == "1")

        # Filtro de coherencia física para evitar falsos positivos por jitter serial:
        # Si la entrada comercial tiene voltaje saludable (170V - 270V) y no hay prueba de batería en curso,
        # es físicamente imposible que haya un corte de luz. Se preserva modo LÍNEA.
        if is_on_bat and (170.0 <= in_v <= 270.0) and not is_test_in_progress:
            logger.warning(
                f"Falso positivo de batería suprimido: Entrada={in_v}V en rango normal 220V "
                f"(status={status_bits}). Preservando modo LÍNEA."
            )
            is_on_bat = False

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


def query_ups_info(port: str = "/dev/ttyS0", baud: int = 2400, timeout: float = 0.5) -> Optional[Dict[str, Any]]:
    """Consulta la información de modelo y fabricante del UPS mediante la instrucción Megatec 'I'."""
    try:
        import serial
    except ImportError:
        return None

    if not Path(port).exists():
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

        ser.write(b"I\r")
        time.sleep(0.18)
        raw_ans = ser.read(100)
        ser.close()

        if not raw_ans:
            return None

        clean = raw_ans.decode("latin1", errors="ignore").strip().lstrip("#").rstrip("\r\n\xff\x00")
        if not clean:
            return None

        import re
        # Extraer versión de firmware legítima (ej: R1.01.55 o V1.02.00)
        firmware_match = re.search(r"([RV]\d+(?:\.\d+)+)", clean, re.IGNORECASE)
        firmware = firmware_match.group(1) if firmware_match else ""

        # Remover versión de firmware para analizar identificador de modelo/fabricante
        remaining = clean
        if firmware:
            remaining = clean.replace(firmware, "").strip()

        # Detectar si remaining corresponde a bytes de relleno de ROM (ej: 222222222222222222222262222, FFFFFFFF, etc.)
        is_corrupt_padding = False
        if not remaining or re.search(r"(.)\1{4,}", remaining) or (len(remaining) > 5 and len(set(remaining)) <= 2):
            is_corrupt_padding = True

        if is_corrupt_padding:
            brand = "ZTG"
            model = "ZTG LV6KL 6kVA"
            firmware = firmware or "R1.01.55"
        else:
            parts = remaining.split()
            if len(parts) >= 2:
                brand = parts[0]
                model = " ".join(parts)
            elif len(parts) == 1:
                brand = "Genérico"
                model = parts[0]
            else:
                brand = "ZTG"
                model = "ZTG LV6KL 6kVA"

        return {
            "raw_info": clean,
            "brand": brand,
            "model": model,
            "firmware_version": firmware or "R1.01.55",
            "is_corrupt_padding": is_corrupt_padding,
        }
    except Exception as e:
        logger.debug(f"Aviso al consultar información (I) en {port}: {e}")
        return None


def query_ups_rating(port: str = "/dev/ttyS0", baud: int = 2400, timeout: float = 0.5) -> Optional[Dict[str, Any]]:
    """Consulta las especificaciones nominales de placa del UPS mediante la instrucción Megatec 'F'."""
    try:
        import serial
    except ImportError:
        return None

    if not Path(port).exists():
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

        ser.write(b"F\r")
        time.sleep(0.18)
        raw_ans = ser.read(100)
        ser.close()

        if not raw_ans:
            return None

        clean = raw_ans.decode("latin1", errors="ignore").strip().lstrip("#").rstrip("\r\n\xff\x00")
        parts = clean.split()
        if len(parts) < 4:
            return None

        import re
        rating_v = float(re.sub(r"[^0-9.]", "", parts[0]))
        rating_i = float(re.sub(r"[^0-9.]", "", parts[1]))
        rating_bat_v = float(re.sub(r"[^0-9.]", "", parts[2]))
        rating_freq = float(re.sub(r"[^0-9.]", "", parts[3]))

        return {
            "raw_rating": clean,
            "rating_voltage": rating_v,
            "rating_current": rating_i,
            "rating_battery_voltage": rating_bat_v,
            "rating_frequency": rating_freq,
        }
    except Exception as e:
        logger.debug(f"Aviso al consultar rating (F) en {port}: {e}")
        return None


def get_usb_device_info(port: str) -> Dict[str, str]:
    """Extrae metadatos USB (vendor, model, serial) mediante /sys en Linux si es un puerto ttyUSB o ttyACM."""
    info = {"usb_vendor": "", "usb_model": "", "usb_serial": ""}
    port_name = Path(port).name
    sys_path = Path(f"/sys/class/tty/{port_name}/device")
    if not sys_path.exists():
        return info

    try:
        parent = sys_path
        for _ in range(4):
            parent = parent.parent
            manufacturer_file = parent / "manufacturer"
            product_file = parent / "product"
            serial_file = parent / "serial"
            if product_file.exists() and not info["usb_model"]:
                info["usb_model"] = product_file.read_text(encoding="utf-8", errors="ignore").strip()
            if manufacturer_file.exists() and not info["usb_vendor"]:
                info["usb_vendor"] = manufacturer_file.read_text(encoding="utf-8", errors="ignore").strip()
            if serial_file.exists() and not info["usb_serial"]:
                info["usb_serial"] = serial_file.read_text(encoding="utf-8", errors="ignore").strip()
    except Exception:
        pass

    return info


def query_ups_hardware(port: str = "/dev/ttyS0", baud: int = 2400, timeout: float = 0.5) -> Dict[str, Any]:
    """Interroga en vivo al hardware del UPS enviando instrucciones Megatec Q1, I y F y descriptores USB."""
    telemetry = query_ups_serial(port=port, baud=baud, timeout=timeout)
    info = query_ups_info(port=port, baud=baud, timeout=timeout)
    rating = query_ups_rating(port=port, baud=baud, timeout=timeout)
    usb = get_usb_device_info(port)

    success = bool(telemetry is not None or info is not None or rating is not None)

    return {
        "success": success,
        "port": port,
        "baud": baud,
        "telemetry": telemetry,
        "info": info,
        "rating": rating,
        "usb": usb,
    }


def sync_ups_to_inventory(conn, device_id: int, model: str, serial_number: Optional[str], serial_port: str) -> None:
    """Propaga en cascada la identidad del UPS hacia inventory_peripherals (Regla de Oro #8)."""
    try:
        with conn.cursor() as cur:
            asset_id = None
            try:
                cur.execute("SELECT inventory_asset_id FROM ups_devices WHERE id = %s", (device_id,))
                row = cur.fetchone()
                asset_id = row.get("inventory_asset_id") if row else None
            except Exception:
                asset_id = None

            if not asset_id:
                try:
                    cur.execute("SELECT id FROM inventory_assets WHERE ip_address = '192.168.1.250' OR name LIKE '%MONITOREO%' LIMIT 1")
                    asset_row = cur.fetchone()
                    if asset_row:
                        asset_id = asset_row["id"]
                        try:
                            cur.execute("UPDATE ups_devices SET inventory_asset_id = %s WHERE id = %s", (asset_id, device_id))
                        except Exception:
                            pass
                except Exception:
                    pass

            if not asset_id:
                return

            import re
            clean_model = model or "ZTG LV6KL 6kVA"
            if "222222" in clean_model or re.search(r"(.)\1{4,}", clean_model):
                clean_model = "ZTG LV6KL 6kVA"

            parts = clean_model.split(maxsplit=1)
            brand = "ZTG"
            if len(parts) == 2:
                brand = parts[0]
                clean_model = parts[1]
            elif clean_model.startswith("ZTG"):
                brand = "ZTG"
                clean_model = clean_model.replace("ZTG", "").strip() or "LV6KL 6kVA"

            cur.execute("SELECT id FROM inventory_peripherals WHERE current_asset_id = %s AND type = 'ups' LIMIT 1", (asset_id,))
            periph = cur.fetchone()
            if periph:
                cur.execute(
                    """
                    UPDATE inventory_peripherals
                    SET brand = %s, model = %s, serial_number = COALESCE(%s, serial_number),
                        notes = %s, updated_at = NOW()
                    WHERE id = %s
                    """,
                    (brand, clean_model, serial_number, f"UPS de respaldo eléctrico de rack/servidor conectado por puerto serial {serial_port}", periph["id"])
                )
            else:
                cur.execute("SELECT COUNT(*) as c FROM inventory_peripherals WHERE type = 'ups'")
                cnt = (cur.fetchone() or {}).get("c", 0) + 1
                tag = f"UPS-{cnt:05d}"
                cur.execute(
                    """
                    INSERT INTO inventory_peripherals
                    (asset_tag, type, brand, model, serial_number, status, monitored_site_id, current_asset_id, notes, created_at, updated_at)
                    VALUES (%s, 'ups', %s, %s, %s, 'assigned', 1, %s, %s, NOW(), NOW())
                    """,
                    (tag, brand, clean_model, serial_number, asset_id, f"UPS de respaldo eléctrico de rack/servidor conectado por puerto serial {serial_port}")
                )
            conn.commit()
    except Exception as e:
        logger.warning(f"No se pudo sincronizar en cascada hacia inventory_peripherals: {e}")


def list_system_serial_ports() -> List[str]:
    """Retorna lista priorizada de puertos seriales detectados en el sistema operativo."""
    import glob
    # Priorizar adaptadores USB ya que se conectan para sustituir o añadir puertos COM
    usb_ports = sorted(glob.glob("/dev/ttyUSB*"))
    acm_ports = sorted(glob.glob("/dev/ttyACM*"))
    # Puertos COM integrados en placa base
    s_ports = sorted(glob.glob("/dev/ttyS[0-3]"))

    ports: List[str] = []
    for p in usb_ports + acm_ports + s_ports:
        if p not in ports and Path(p).exists():
            ports.append(p)
    return ports


def scan_and_detect_ups(
    preferred_port: Optional[str] = None,
    preferred_baud: Optional[int] = None,
    baud_candidates: Tuple[int, ...] = (2400, 9600, 1200, 4800, 19200),
    timeout: float = 0.5,
) -> Optional[Tuple[str, int, Dict[str, Any]]]:
    """
    Escanea sistemáticamente los puertos seriales y velocidades para detectar
    un UPS que responda a la trama industrial Megatec Q1.
    Retorna (puerto, baudios, telemetria) o None.
    """
    all_ports = list_system_serial_ports()

    # Priorizar puerto preferido si existe
    ports_to_test: List[str] = []
    if preferred_port and Path(preferred_port).exists():
        ports_to_test.append(preferred_port)
    for p in all_ports:
        if p not in ports_to_test:
            ports_to_test.append(p)

    # Priorizar velocidad preferida si se especificó
    bauds_to_test: List[int] = []
    if preferred_baud and preferred_baud in baud_candidates:
        bauds_to_test.append(preferred_baud)
    for b in baud_candidates:
        if b not in bauds_to_test:
            bauds_to_test.append(b)

    for port in ports_to_test:
        for baud in bauds_to_test:
            try:
                res = query_ups_serial(port=port, baud=baud, timeout=timeout)
                if res and isinstance(res, dict) and res.get("is_online"):
                    logger.info(f"✨ UPS respondido exitosamente en {port} @ {baud} bps")
                    # Enriquecer con interrogación de hardware
                    res["info"] = query_ups_info(port=port, baud=baud, timeout=timeout)
                    res["rating"] = query_ups_rating(port=port, baud=baud, timeout=timeout)
                    res["usb_meta"] = get_usb_device_info(port)
                    return port, baud, res
            except Exception as e:
                logger.debug(f"Fallo sondeo en {port} @ {baud}: {e}")
                continue

    return None


def build_ups_outage_alert(device: dict, telemetry: dict, elapsed_str: str = "45 seg") -> str:
    """Construye mensaje corporativo de falla de servicio eléctrico confirmada (>45 seg en batería)."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    model = html.escape(str(device.get("model") or "6kVA"))
    bat_pct = telemetry.get("battery_percent", 100)
    load_pct = telemetry.get("load_percent", 0)
    temp_c = telemetry.get("temperature_c", 40.0)
    raw_out = float(telemetry.get("output_voltage") or 208.0)
    out_equipos = round(raw_out * (110.0 / 208.0), 1) if raw_out > 150 else raw_out
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "⚡ <b>ALERTA CRÍTICA: FALLA EN SERVICIO ELÉCTRICO CONFIRMADA</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code> ({model})\n"
        f"⚠️ <b>Condición:</b> <b>FALLA CONFIRMADA (MÁS DE 45 SEG EN MODO BATERÍA)</b>\n"
        "🔌 <b>Entrada Comercial (220V):</b> 0.0 VAC (Interrumpida)\n"
        f"⚡ <b>Salida a Equipos (110V):</b> {out_equipos:.1f} VAC (Activa)\n"
        f"⏱️ <b>Tiempo en Batería:</b> {elapsed_str} acumulados\n"
        f"🔋 <b>Nivel de Batería:</b> {bat_pct}% ({telemetry.get('battery_voltage', 2.25):.2f} V/celda)\n"
        f"📊 <b>Carga de Consumo:</b> {load_pct}% (~{int(load_pct * 60)} Watts)\n"
        f"🌡️ <b>Temperatura Inversor:</b> {temp_c:.1f} °C\n"
        f"🕒 <b>Confirmado:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "⚠️ <i>Conteo de autonomía de respaldo corporativo en progreso.</i>"
    )


def build_ups_fluctuation_alert(device: dict, telemetry: dict, duration_str: str) -> str:
    """Construye mensaje técnico de micro-fluctuación de voltaje (≤45 seg) para el Administrador."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    in_v = float(telemetry.get("input_voltage") or 220.0)
    raw_out = float(telemetry.get("output_voltage") or 208.0)
    out_equipos = round(raw_out * (110.0 / 208.0), 1) if raw_out > 150 else raw_out
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "⚠️ <b>AVISO TÉCNICO: MICRO-FLUCTUACIÓN DE VOLTAJE ABSORBIDA</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code>\n"
        f"⏱️ <b>Duración en Batería:</b> <b>{duration_str}</b> (≤ 45 seg)\n"
        f"🔌 <b>Entrada Red Comercial:</b> {in_v:.1f} VAC (Normalizada)\n"
        f"⚡ <b>Salida a Servidores:</b> {out_equipos:.1f} VAC (Sin interrupción)\n"
        f"🕒 <b>Fecha y Hora:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "🛡️ <i>Evento transitorio amortiguado por el UPS. Servidores protegidos.</i>"
    )


def build_ups_recovery_alert(device: dict, telemetry: dict, duration_str: str) -> str:
    """Construye mensaje corporativo de restablecimiento de energía eléctrica."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    in_v = float(telemetry.get("input_voltage") or 220.0)
    raw_out = float(telemetry.get("output_voltage") or 208.0)
    out_equipos = round(raw_out * (110.0 / 208.0), 1) if raw_out > 150 else raw_out
    freq = float(telemetry.get("frequency") or 60.0)
    bat_pct = telemetry.get("battery_percent", 100)
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "✅ <b>RECUPERACIÓN: FLUIDO ELÉCTRICO RESTABLECIDO</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code>\n"
        f"🔌 <b>Entrada Comercial (220V):</b> {in_v:.1f} VAC ({freq:.1f} Hz)\n"
        f"⚡ <b>Salida a Equipos (110V):</b> {out_equipos:.1f} VAC (Inversor L-L: {raw_out:.1f} VAC)\n"
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
    actualiza MariaDB y gestiona el despacho reactivo de alertas de energía
    con filtro de persistencia (debounce) de 45 segundos para evitar spam en el grupo.
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

        # 1. Leer telemetría física por puerto serial configurado
        telemetry = query_ups_serial(port=port, baud=baud)

        # 2. Si un ciclo falla por ruido puntual, reintentar una vez tras breve pausa (evita escaneo innecesario)
        if not telemetry:
            time.sleep(0.3)
            telemetry = query_ups_serial(port=port, baud=baud)

        # 3. Solo si persiste sin responder tras reintento, activar autodescubrimiento inteligente
        if not telemetry:
            discovered = scan_and_detect_ups(preferred_port=port, preferred_baud=baud)
            if discovered:
                disc_port, disc_baud, telemetry = discovered
                info = telemetry.get("info")
                rating = telemetry.get("rating")
                usb_meta = telemetry.get("usb_meta") or get_usb_device_info(disc_port)

                logger.warning(
                    f"🔄 Autodescubrimiento Serial: UPS detectado en '{disc_port}' @ {disc_baud} bps "
                    f"(anteriormente: '{port}' @ {baud} bps). Sincronizando identidad..."
                )
                port = disc_port
                baud = disc_baud

                new_model = None
                new_name = None
                firmware_version = None

                # REGLA DE ORO #7 (MariaDB SSOT) y ERROR #2: NUNCA pisar un nombre o modelo corporativo legítimo ya existente
                existing_name = str(device.get("name") or "")
                existing_model = str(device.get("model") or "")
                name_is_corrupt = "222222" in existing_name or "generico" in existing_name.lower() or "nuevo ups" in existing_name.lower()
                model_is_corrupt = "222222" in existing_model or "generico" in existing_model.lower()

                if info and info.get("firmware_version"):
                    firmware_version = info["firmware_version"]

                # Si el registro en BD está vacío o corrupto con bytes de relleno, sanitizarlo inmediatamente
                if not existing_name or name_is_corrupt:
                    new_name = "UPS ZTG LV6KL - Sede Valle Seco"
                if not existing_model or model_is_corrupt:
                    new_model = "ZTG LV6KL 6kVA"

                update_fields = [
                    "serial_port = %s",
                    "baud_rate = %s",
                    "is_online = 1",
                    "updated_at = NOW()",
                ]
                params = [disc_port, disc_baud]

                if new_name:
                    update_fields.append("name = %s")
                    params.append(new_name)
                if new_model:
                    update_fields.append("model = %s")
                    params.append(new_model)

                if firmware_version:
                    update_fields.append("firmware_version = %s")
                    params.append(firmware_version)

                if rating:
                    update_fields.extend([
                        "rating_voltage = %s",
                        "rating_current = %s",
                        "rating_battery_voltage = %s",
                        "rating_frequency = %s"
                    ])
                    params.extend([
                        rating["rating_voltage"],
                        rating["rating_current"],
                        rating["rating_battery_voltage"],
                        rating["rating_frequency"]
                    ])

                params.append(device["id"])
                sql = f"UPDATE ups_devices SET {', '.join(update_fields)} WHERE id = %s"

                with conn.cursor() as cur:
                    cur.execute(sql, tuple(params))
                conn.commit()

                if new_model:
                    sync_ups_to_inventory(conn, device["id"], new_model, device.get("serial_number"), disc_port)

        if not telemetry:
            # Marcar fuera de línea si no responde en ningún puerto serial
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE ups_devices SET is_online = 0, updated_at = NOW() WHERE id = %s",
                    (device["id"],)
                )
            conn.commit()
            return None

        # Evaluar transiciones de estado de energía para alertas
        last_state = (device.get("last_alert_state") or "NORMAL").upper()
        group_alert_sent = bool(device.get("group_alert_sent", 0))
        alert_enabled = bool(device.get("telegram_alert_enabled", 1))
        target_setting = device.get("telegram_alert_target") or "owner"
        target_chats = get_notification_targets(target_setting)

        if dispatcher is None and alert_enabled:
            dispatcher = TelegramDispatcher()

        current_state = "NORMAL"
        outage_since = device.get("outage_since")

        if telemetry["is_on_battery"]:
            current_state = "BATTERY_LOW" if telemetry["is_battery_low"] else "ON_BATTERY"

        # CASO 1: Inicio de operación en modo batería (NORMAL -> ON_BATTERY / BATTERY_LOW)
        if current_state in ("ON_BATTERY", "BATTERY_LOW") and last_state == "NORMAL":
            outage_since = now
            group_alert_sent = False
            try:
                with conn.cursor() as cur_ev:
                    cur_ev.execute(
                        """
                        INSERT INTO ups_power_events
                        (ups_device_id, event_type, started_at, input_voltage, status, notified_group, notified_target, description, created_at, updated_at)
                        VALUES (%s, 'fluctuation', %s, %s, 'ongoing', 0, 'none', 'Evento de energía en curso (modo batería)', NOW(), NOW())
                        """,
                        (device["id"], now, telemetry["input_voltage"])
                    )
                conn.commit()
            except Exception as e_ev:
                logger.warning(f"No se pudo registrar inicio en ups_power_events: {e_ev}")

        # CASO 2: Permanece operando en batería: evaluar persistencia (> 45 segundos)
        if current_state in ("ON_BATTERY", "BATTERY_LOW"):
            elapsed_sec = 0.0
            if outage_since:
                if isinstance(outage_since, datetime):
                    elapsed_sec = (now - outage_since).total_seconds()
                else:
                    try:
                        ds_dt = datetime.strptime(str(outage_since)[:19], "%Y-%m-%d %H:%M:%S")
                        elapsed_sec = (now - ds_dt).total_seconds()
                    except Exception:
                        elapsed_sec = 0.0

            # Si excedió los 45 segundos y aún no se ha alertado al grupo
            if elapsed_sec >= 45.0 and not group_alert_sent:
                group_alert_sent = True

                # Crear archivo testigo persistente para el protocolo de arranque post-apagón
                try:
                    OUTAGE_ALERT_MARKER.parent.mkdir(parents=True, exist_ok=True)
                    marker_data = {
                        "outage_since": str(outage_since),
                        "alerted_at": now.strftime("%Y-%m-%d %H:%M:%S"),
                        "device_id": device["id"],
                        "device_name": device.get("name", "UPS ZTG LV6KL"),
                        "input_voltage": telemetry.get("input_voltage", 0.0),
                    }
                    OUTAGE_ALERT_MARKER.write_text(json.dumps(marker_data, ensure_ascii=False), encoding="utf-8")
                except Exception as e_m:
                    logger.warning(f"No se pudo escribir archivo testigo de corte: {e_m}")

                # Actualizar el evento actual a 'outage'
                try:
                    with conn.cursor() as cur_ev:
                        cur_ev.execute(
                            """
                            UPDATE ups_power_events
                            SET event_type = 'outage',
                                notified_group = 1,
                                notified_target = 'group',
                                description = 'Falla de servicio eléctrico confirmada (>45s en batería)',
                                updated_at = NOW()
                            WHERE ups_device_id = %s AND status = 'ongoing'
                            ORDER BY started_at DESC LIMIT 1
                            """,
                            (device["id"],)
                        )
                    conn.commit()
                except Exception as e_ev:
                    logger.warning(f"No se pudo actualizar evento a outage: {e_ev}")

                # Despachar alerta de falla eléctrica confirmada al Grupo y al Owner
                if alert_enabled and dispatcher:
                    msg = build_ups_outage_alert(device, telemetry, format_duration(elapsed_sec))
                    for t_chat in target_chats:
                        ok, err = await dispatcher.send_text(t_chat, msg, parse_mode="HTML")
                        if ok:
                            logger.info(f"📤 Alerta de corte eléctrico confirmada despachada a {t_chat}")
                        else:
                            logger.warning(f"❌ Falló despacho de corte eléctrico a {t_chat}: {err}")

            # Alerta secundaria si la batería entra en nivel bajo durante el corte
            if current_state == "BATTERY_LOW" and last_state == "ON_BATTERY":
                if alert_enabled and dispatcher:
                    msg = build_ups_battery_low_alert(device, telemetry)
                    for t_chat in target_chats:
                        ok, err = await dispatcher.send_text(t_chat, msg, parse_mode="HTML")
                        if ok:
                            logger.info(f"📤 Alerta de batería baja despachada a {t_chat}")
                        else:
                            logger.warning(f"❌ Falló despacho de batería baja a {t_chat}: {err}")

        # CASO 3: Restablecimiento de la energía (ON_BATTERY / BATTERY_LOW -> NORMAL)
        elif current_state == "NORMAL" and last_state in ("ON_BATTERY", "BATTERY_LOW"):
            dur_sec = 0.0
            if outage_since:
                if isinstance(outage_since, datetime):
                    dur_sec = (now - outage_since).total_seconds()
                else:
                    try:
                        ds_dt = datetime.strptime(str(outage_since)[:19], "%Y-%m-%d %H:%M:%S")
                        dur_sec = (now - ds_dt).total_seconds()
                    except Exception:
                        dur_sec = 0.0
            dur_str = format_duration(dur_sec)

            # Clasificar y cerrar el evento en ups_power_events
            ev_type = "outage" if (group_alert_sent or dur_sec > 45.0) else "fluctuation"
            ev_desc = (
                f"Corte de fluido eléctrico resuelto (duración: {dur_str})"
                if ev_type == "outage"
                else f"Micro-fluctuación de voltaje absorbida por batería (duración: {dur_str})"
            )
            try:
                with conn.cursor() as cur_ev:
                    cur_ev.execute(
                        """
                        UPDATE ups_power_events
                        SET ended_at = %s,
                            duration_seconds = %s,
                            event_type = %s,
                            status = 'resolved',
                            description = %s,
                            updated_at = NOW()
                        WHERE ups_device_id = %s AND status = 'ongoing'
                        ORDER BY started_at DESC LIMIT 1
                        """,
                        (now, int(round(dur_sec)), ev_type, ev_desc, device["id"])
                    )
                conn.commit()
            except Exception as e_ev:
                logger.warning(f"No se pudo cerrar evento en ups_power_events: {e_ev}")

            # Subcaso A: Si se notificó al grupo (>45s) -> Notificar restablecimiento al Grupo y al Owner
            if group_alert_sent:
                try:
                    OUTAGE_ALERT_MARKER.unlink(missing_ok=True)
                except Exception:
                    pass

                if alert_enabled and dispatcher:
                    msg = build_ups_recovery_alert(device, telemetry, dur_str)
                    for t_chat in target_chats:
                        ok, err = await dispatcher.send_text(t_chat, msg, parse_mode="HTML")
                        if ok:
                            logger.info(f"📤 Notificación de energía restablecida despachada a {t_chat}")
                        else:
                            logger.warning(f"❌ Falló despacho de energía restablecida a {t_chat}: {err}")

            # Subcaso B: Micro-fluctuación (≤ 45s) -> Silencio total al Grupo, aviso técnico al Owner según configuración
            else:
                config_file = BASE_DIR / "config" / "config.json"
                owner_id = "38914901"
                notify_micro = True
                cooldown_sec = 1800  # 30 minutos de amortiguación
                if config_file.exists():
                    try:
                        cfg_t = json.loads(config_file.read_text(encoding="utf-8"))
                        owner_id = str(cfg_t.get("owner_id", "38914901"))
                        notify_micro = bool(cfg_t.get("ups_notify_micro_fluctuations", True))
                        cooldown_sec = int(cfg_t.get("ups_fluctuation_cooldown_seconds", 1800))
                    except Exception:
                        pass

                # Filtro de ruido: Transitorios espurios menores a 8 segundos no se notifican
                is_spurious = (dur_sec < 8.0)

                # Control de frecuencia / Cooldown anti-tormenta
                last_alert_file = Path("/tmp/monitor/last_ups_micro_alert.txt")
                can_notify_cooldown = True
                if last_alert_file.exists():
                    try:
                        last_ts = float(last_alert_file.read_text().strip())
                        if (time.time() - last_ts) < cooldown_sec:
                            can_notify_cooldown = False
                    except Exception:
                        pass

                if not is_spurious and notify_micro and can_notify_cooldown and alert_enabled and dispatcher:
                    msg = build_ups_fluctuation_alert(device, telemetry, dur_str)
                    ok, err = await dispatcher.send_text(owner_id, msg, parse_mode="HTML")
                    if ok:
                        logger.info(f"📤 Aviso técnico de micro-fluctuación despachado en privado al Owner ({owner_id})")
                        try:
                            last_alert_file.parent.mkdir(parents=True, exist_ok=True)
                            last_alert_file.write_text(str(time.time()))
                        except Exception:
                            pass
                    else:
                        logger.warning(f"❌ Falló despacho de micro-fluctuación al Owner: {err}")
                else:
                    motivo = "ruido transitorio (<8s)" if is_spurious else ("desactivado por configuración" if not notify_micro else f"cooldown activo ({cooldown_sec}s)")
                    logger.info(f"ℹ️ Micro-fluctuación registrada en base de datos ({dur_str}). Alerta Telegram omitida ({motivo}).")

            outage_since = None
            group_alert_sent = False

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
                    group_alert_sent = %s,
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
                    1 if group_alert_sent else 0,
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
