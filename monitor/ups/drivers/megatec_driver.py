"""
# ==============================================================================
# 🔋 UPS MEGATEC DRIVER: megatec_driver.py
# Controlador industrial de comunicación para protocolo Megatec (Q1, I, F)
# Ubicación: /opt/sonar/monitor/ups/drivers/megatec_driver.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import re
import time
from pathlib import Path
from typing import Any, Dict, Optional

from monitor.ups.domain.battery_policy import calculate_battery_percentage
from monitor.ups.drivers.usb_device_info import get_usb_device_info

logger = logging.getLogger("ups.service")


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
        is_on_bat = (status_bits[0] == "1")
        is_bat_low = (status_bits[1] == "1")
        is_bypass = (status_bits[2] == "1")
        is_ups_fail = (status_bits[3] == "1")
        is_test_in_progress = (status_bits[5] == "1")
        beeper_on = (status_bits[7] == "1")

        # Filtro de coherencia física
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

        firmware_match = re.search(r"([RV]\d+(?:\.\d+)+)", clean, re.IGNORECASE)
        firmware = firmware_match.group(1) if firmware_match else ""

        remaining = clean
        if firmware:
            remaining = clean.replace(firmware, "").strip()

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
