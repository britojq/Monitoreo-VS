"""
# ==============================================================================
# 📡 MOTOR DE RECOLECCIÓN Y TELEMETRÍA SNMP: snmp_poller.py (@IA_ValleSeco_bot)
# Extracción de métricas de red, switches Cisco, routers, firewalls pfSense y UPS
# Ubicación: /scripts/telegram-admin-bot/monitor/snmp_poller.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import base64
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

import pymysql
from cryptography.hazmat.primitives import padding
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from pysnmp.hlapi.asyncio import *

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)
CACHE_DIR = Path("/dev/shm/monitoreo_snmp_cache")
CACHE_DIR.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [snmp.poller] %(message)s",
    handlers=[
        logging.FileHandler(LOG_DIR / "snmp_poller.log", encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("snmp.poller")

# Configuración de base de datos
DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "monitoreo_user",
    "password": "password",
    "database": "monitoreo_vs",
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,
    "autocommit": True,
}


def get_db_connection():
    return pymysql.connect(**DB_CONFIG)


def get_laravel_app_key() -> bytes:
    """Extrae la clave AES de Laravel desde el archivo .env."""
    env_paths = [
        Path("/var/www/monitoreo/.env"),
        Path(BASE_DIR / "web_portal/.env"),
    ]
    raw_key = ""
    for p in env_paths:
        if p.exists():
            try:
                for line in p.read_text(encoding="utf-8").splitlines():
                    if line.startswith("APP_KEY=") and "=" in line:
                        raw_key = line.split("=", 1)[1].strip("\"' ")
                        break
            except Exception:
                pass
        if raw_key:
            break

    if raw_key.startswith("base64:"):
        return base64.b64decode(raw_key[7:])
    return raw_key.encode("utf-8")


def decrypt_laravel_string(encrypted_str: str) -> str:
    """Descifra cadenas cifradas por Laravel (AES-256-CBC)."""
    if not encrypted_str:
        return ""

    # Cache en memoria compartida /dev/shm con TTL de 5 minutos
    cache_file = CACHE_DIR / f"cred_{abs(hash(encrypted_str))}.json"
    now_ts = time.time()
    if cache_file.exists():
        try:
            cached_data = json.loads(cache_file.read_text(encoding="utf-8"))
            if now_ts - cached_data.get("time", 0) < 300:
                return cached_data.get("val", "")
        except Exception:
            pass

    try:
        key = get_laravel_app_key()
        payload = json.loads(base64.b64decode(encrypted_str).decode("utf-8"))
        iv = base64.b64decode(payload["iv"])
        ciphertext = base64.b64decode(payload["value"])

        cipher = Cipher(algorithms.AES(key), modes.CBC(iv))
        decryptor = cipher.decryptor()
        padded = decryptor.update(ciphertext) + decryptor.finalize()

        unpadder = padding.PKCS7(128).unpadder()
        unpadded = unpadder.update(padded) + unpadder.finalize()
        val = unpadded.decode("utf-8", errors="ignore")

        # Si viene serializado por PHP (ej: s:6:"public";)
        if val.startswith("s:") and '"' in val:
            val = val.split('"')[1]

        # Guardar en cache /dev/shm
        try:
            cache_file.write_text(json.dumps({"val": val, "time": now_ts}), encoding="utf-8")
        except Exception:
            pass

        return val
    except Exception as e:
        logger.debug(f"Error descifrando cadena: {e}")
        return ""


async def poll_device_oids(device: Dict[str, Any], oids: List[Dict[str, Any]]) -> Dict[str, Any]:
    """
    Realiza peticiones SNMP GET concurrentes para una lista de OIDs en un dispositivo.
    """
    ip = device["ip_address"]
    version = device.get("snmp_version", "v2c").lower()
    port = device.get("snmp_port", 161)
    timeout = device.get("snmp_timeout_seconds", 5)
    retries = device.get("snmp_retries", 2)

    community = "public"
    if device.get("snmp_community_encrypted"):
        decrypted = decrypt_laravel_string(device["snmp_community_encrypted"])
        if decrypted:
            community = decrypted

    snmp_engine = SnmpEngine()
    results = []
    status = "success"
    error_msg = None
    sys_name = None
    sys_uptime = None
    sys_descr = None
    sys_location = None

    try:
        auth_data = CommunityData(community, mpModel=(1 if version == "v2c" else 0))
        transport = await UdpTransportTarget.create((ip, port), timeout=timeout, retries=retries)

        # 1. Consultar OIDs estándar del sistema (RFC 1213)
        try:
            err_ind, err_stat, err_idx, var_binds = await get_cmd(
                snmp_engine,
                auth_data,
                transport,
                ContextData(),
                ObjectType(ObjectIdentity("1.3.6.1.2.1.1.1.0")),  # sysDescr
                ObjectType(ObjectIdentity("1.3.6.1.2.1.1.3.0")),  # sysUpTime
                ObjectType(ObjectIdentity("1.3.6.1.2.1.1.5.0")),  # sysName
                ObjectType(ObjectIdentity("1.3.6.1.2.1.1.6.0")),  # sysLocation
            )

            if err_ind:
                status = "timeout" if "timeout" in str(err_ind).lower() else "error"
                error_msg = str(err_ind)
            elif err_stat:
                status = "error"
                error_msg = str(err_stat.prettyPrint())
            else:
                for vb in var_binds:
                    oid_s = str(vb[0])
                    v_raw = vb[1].prettyPrint()
                    if oid_s == "1.3.6.1.2.1.1.1.0":
                        sys_descr = v_raw[:500]
                    elif oid_s == "1.3.6.1.2.1.1.3.0":
                        try:
                            sys_uptime = int(v_raw)
                        except ValueError:
                            pass
                    elif oid_s == "1.3.6.1.2.1.1.5.0":
                        sys_name = v_raw[:255]
                    elif oid_s == "1.3.6.1.2.1.1.6.0":
                        sys_location = v_raw[:255]

                # Si obtuvimos información del sistema, actualizar en la tabla snmp_devices
                if (sys_name or sys_uptime or sys_descr or sys_location) and device.get("id"):
                    try:
                        c_up = get_db_connection()
                        with c_up.cursor() as cur_up:
                            cur_up.execute("""
                                UPDATE snmp_devices
                                SET sys_name = COALESCE(%s, sys_name),
                                    sys_uptime = COALESCE(%s, sys_uptime),
                                    sys_description = COALESCE(%s, sys_description),
                                    sys_location = COALESCE(%s, sys_location)
                                WHERE id = %s
                            """, (sys_name, sys_uptime, sys_descr, sys_location, device["id"]))
                        c_up.close()
                    except Exception as e_up:
                        logger.debug(f"Aviso actualizando sys info para {ip}: {e_up}")

        except Exception as e_sys:
            status = "timeout" if "timeout" in str(e_sys).lower() else "error"
            error_msg = str(e_sys)

        # 2. Si el dispositivo respondió al sistema, consultar OIDs específicos
        if status == "success" and oids:
            for oid_info in oids:
                oid_str = oid_info.get("custom_oid") or oid_info.get("oid")
                if not oid_str:
                    continue

                try:
                    error_ind, error_stat, error_idx, var_binds = await get_cmd(
                        snmp_engine,
                        auth_data,
                        transport,
                        ContextData(),
                        ObjectType(ObjectIdentity(oid_str)),
                    )

                    if error_ind:
                        break
                    elif error_stat:
                        break
                    else:
                        for vb in var_binds:
                            val_str = vb[1].prettyPrint()
                            num_val = None
                            try:
                                num_val = float(val_str)
                            except ValueError:
                                pass

                            results.append({
                                "oid_id": oid_info.get("id"),
                                "oid_str": oid_str,
                                "name": oid_info.get("name"),
                                "val_raw": val_str,
                                "val_num": num_val,
                                "unit": oid_info.get("unit"),
                            })
                except Exception as e_oid:
                    logger.debug(f"Error consultando OID {oid_str} en {ip}: {e_oid}")
    except Exception as e:
        status = "error"
        error_msg = str(e)
    finally:
        snmp_engine.close_dispatcher()

    return {
        "device_id": device.get("id"),
        "ip": ip,
        "status": status,
        "success": (status == "success"),
        "sys_name": sys_name,
        "sys_uptime": sys_uptime,
        "sys_description": sys_descr,
        "sys_location": sys_location,
        "error": error_msg,
        "metrics": results,
    }


async def discover_interfaces(device: Dict[str, Any]) -> List[Dict[str, Any]]:
    """
    Descubre interfaces físicas y lógicas vía ifTable walk (RFC 1213 / IF-MIB).
    Inserta o actualiza en la tabla snmp_interfaces.
    """
    ip = device["ip_address"]
    dev_id = device["id"]
    timeout = device.get("snmp_timeout_seconds", 5)
    retries = device.get("snmp_retries", 2)

    community = "public"
    if device.get("snmp_community_encrypted"):
        dec = decrypt_laravel_string(device["snmp_community_encrypted"])
        if dec:
            community = dec

    logger.info(f"🔍 [DISCOVERY IFACE] Descubriendo interfaces en {ip}...")
    snmp_engine = SnmpEngine()
    discovered_interfaces: Dict[int, Dict[str, Any]] = {}

    try:
        auth_data = CommunityData(community, mpModel=1)
        transport = await UdpTransportTarget.create((ip, device.get("snmp_port", 161)), timeout=timeout, retries=retries)

        current_oid = "1.3.6.1.2.1.2.2.1"
        for _ in range(60):  # Hasta 60 lotes de 50 registros (hasta 3000 entradas MIB)
            err_ind, err_stat, err_idx, var_bind_table = await bulk_cmd(
                snmp_engine,
                auth_data,
                transport,
                ContextData(),
                0,
                50,
                ObjectType(ObjectIdentity(current_oid)),
                lexicographicMode=False,
            )
            if err_ind or err_stat or not var_bind_table:
                break

            last_vb = None
            stopped = False
            for vb in var_bind_table:
                last_vb = vb
                oid_str = str(vb[0])
                if not oid_str.startswith("1.3.6.1.2.1.2.2.1."):
                    stopped = True
                    break

                val = vb[1].prettyPrint()
                parts = oid_str.split(".")
                if len(parts) >= 11:
                    field_id = int(parts[9])
                    try:
                        if_index = int(parts[10])
                    except ValueError:
                        continue

                    if if_index not in discovered_interfaces:
                        discovered_interfaces[if_index] = {
                            "snmp_device_id": dev_id,
                            "if_index": if_index,
                            "if_name": f"if_{if_index}",
                            "if_description": "",
                            "if_type": None,
                            "if_speed": None,
                            "if_physical_address": None,
                            "if_admin_status": "down",
                            "if_oper_status": "down",
                            "last_in_octets": None,
                            "last_out_octets": None,
                        }

                    item = discovered_interfaces[if_index]
                    if field_id == 2:  # ifDescr
                        item["if_description"] = val[:255]
                        item["if_name"] = val[:255]
                    elif field_id == 3:  # ifType
                        try:
                            item["if_type"] = int(val)
                        except ValueError:
                            pass
                    elif field_id == 5:  # ifSpeed
                        try:
                            item["if_speed"] = int(val)
                        except ValueError:
                            pass
                    elif field_id == 6:  # ifPhysAddress
                        item["if_physical_address"] = val[:17]
                    elif field_id == 7:  # ifAdminStatus
                        item["if_admin_status"] = "up" if val == "1" else "down"
                    elif field_id == 8:  # ifOperStatus
                        item["if_oper_status"] = "up" if val == "1" else "down"
                    elif field_id == 10:  # ifInOctets
                        try:
                            item["last_in_octets"] = int(val)
                        except ValueError:
                            pass
                    elif field_id == 16:  # ifOutOctets
                        try:
                            item["last_out_octets"] = int(val)
                        except ValueError:
                            pass

            if stopped or not last_vb or not str(last_vb[0]).startswith("1.3.6.1.2.1.2.2.1."):
                break
            current_oid = str(last_vb[0])
    except Exception as e:
        logger.warning(f"Aviso descubriendo interfaces en {ip}: {e}")
    finally:
        snmp_engine.close_dispatcher()

    # Guardar en base de datos
    results = list(discovered_interfaces.values())
    if results:
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                upsert_sql = """
                    INSERT INTO snmp_interfaces
                    (snmp_device_id, if_index, if_name, if_description, if_type, if_speed, if_physical_address,
                     if_admin_status, if_oper_status, last_in_octets, last_out_octets, last_polled_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    if_name = VALUES(if_name),
                    if_description = VALUES(if_description),
                    if_type = VALUES(if_type),
                    if_speed = VALUES(if_speed),
                    if_physical_address = VALUES(if_physical_address),
                    if_admin_status = VALUES(if_admin_status),
                    if_oper_status = VALUES(if_oper_status),
                    last_in_octets = VALUES(last_in_octets),
                    last_out_octets = VALUES(last_out_octets),
                    last_polled_at = NOW(),
                    updated_at = NOW()
                """
                records = []
                for iface in results:
                    records.append((
                        iface["snmp_device_id"],
                        iface["if_index"],
                        iface["if_name"],
                        iface["if_description"],
                        iface["if_type"],
                        iface["if_speed"],
                        iface["if_physical_address"],
                        iface["if_admin_status"],
                        iface["if_oper_status"],
                        iface["last_in_octets"],
                        iface["last_out_octets"],
                    ))
                cur.executemany(upsert_sql, records)
            logger.info(f"✅ Se guardaron/actualizaron {len(results)} interfaces para {ip}.")
        finally:
            conn.close()

    return results


async def poll_interface_metrics(device: Dict[str, Any]) -> None:
    """
    Consulta octetos de entrada y salida para calcular in_bps y out_bps con delta de tiempo y counter wrap.
    """
    dev_id = device["id"]
    ip = device["ip_address"]
    conn = get_db_connection()
    interfaces = []
    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, if_index, if_speed, if_high_speed, last_in_octets, last_out_octets, last_polled_at
                FROM snmp_interfaces
                WHERE snmp_device_id = %s AND (is_monitored = 1 OR if_oper_status = 'up')
            """, (dev_id,))
            interfaces = cur.fetchall()
    finally:
        conn.close()

    if not interfaces:
        return

    # Consultar métricas actuales vía SNMP para cada interfaz
    community = "public"
    if device.get("snmp_community_encrypted"):
        dec = decrypt_laravel_string(device["snmp_community_encrypted"])
        if dec:
            community = dec

    snmp_engine = SnmpEngine()
    try:
        auth_data = CommunityData(community, mpModel=1)
        transport = await UdpTransportTarget.create((ip, device.get("snmp_port", 161)), timeout=4, retries=1)

        metric_records = []
        update_if_records = []
        now_dt = datetime.now()

        for iface in interfaces:
            idx = iface["if_index"]
            oid_in = f"1.3.6.1.2.1.2.2.1.10.{idx}"
            oid_out = f"1.3.6.1.2.1.2.2.1.16.{idx}"

            try:
                err_ind, err_stat, _, vbs = await get_cmd(
                    snmp_engine,
                    auth_data,
                    transport,
                    ContextData(),
                    ObjectType(ObjectIdentity(oid_in)),
                    ObjectType(ObjectIdentity(oid_out)),
                )
                if not err_ind and not err_stat and len(vbs) >= 2:
                    new_in = int(vbs[0][1].prettyPrint())
                    new_out = int(vbs[1][1].prettyPrint())

                    prev_in = iface.get("last_in_octets")
                    prev_out = iface.get("last_out_octets")
                    last_poll = iface.get("last_polled_at")

                    in_bps = 0.0
                    out_bps = 0.0
                    in_util = 0.0
                    out_util = 0.0

                    if prev_in is not None and prev_out is not None and last_poll:
                        delta_t = max(1.0, (now_dt - last_poll).total_seconds())

                        # Manejo de counter wrap 32-bit (2^32 = 4294967296)
                        delta_in = (new_in - prev_in) if new_in >= prev_in else (4294967296 - prev_in + new_in)
                        delta_out = (new_out - prev_out) if new_out >= prev_out else (4294967296 - prev_out + new_out)

                        in_bps = round((delta_in * 8) / delta_t, 4)
                        out_bps = round((delta_out * 8) / delta_t, 4)

                        speed = iface.get("if_high_speed") * 1000000 if iface.get("if_high_speed") else (iface.get("if_speed") or 0)
                        if speed > 0:
                            in_util = min(100.0, round((in_bps / speed) * 100, 2))
                            out_util = min(100.0, round((out_bps / speed) * 100, 2))

                    metric_records.append((
                        iface["id"], new_in, new_out, in_bps, out_bps, in_util, out_util
                    ))
                    update_if_records.append((new_in, new_out, iface["id"]))
            except Exception:
                pass

        if metric_records:
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.executemany("""
                        INSERT INTO snmp_interface_metrics
                        (snmp_interface_id, in_octets, out_octets, in_bps, out_bps, in_utilization_pct, out_utilization_pct, collected_at)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
                    """, metric_records)

                    cur.executemany("""
                        UPDATE snmp_interfaces
                        SET last_in_octets = %s, last_out_octets = %s, last_polled_at = NOW()
                        WHERE id = %s
                    """, update_if_records)
            finally:
                conn.close()
    finally:
        snmp_engine.close_dispatcher()


async def run_snmp_poll(device_ids: Optional[List[int]] = None) -> Dict[str, Any]:
    """
    Orquestador principal de Polling SNMP:
    1. Carga dispositivos activos
    2. Ejecuta sondeo concurrente con Semaphore(10)
    3. Persiste métricas históricas
    4. Actualiza estado y contador de fallos
    """
    conn = get_db_connection()
    devices = []
    try:
        with conn.cursor() as cur:
            if device_ids:
                format_ids = ",".join(["%s"] * len(device_ids))
                cur.execute(f"SELECT * FROM snmp_devices WHERE id IN ({format_ids}) AND is_active = 1", device_ids)
            else:
                cur.execute("SELECT * FROM snmp_devices WHERE is_active = 1")
            devices = cur.fetchall()

            # Cargar mapa de OIDs por dispositivo
            for dev in devices:
                cur.execute("""
                    SELECT o.id, o.name, o.oid, o.unit, o.data_type, o.is_counter_wrap, d_o.custom_oid
                    FROM snmp_device_oids d_o
                    JOIN snmp_oids o ON d_o.snmp_oid_id = o.id
                    WHERE d_o.snmp_device_id = %s AND d_o.is_active = 1
                """, (dev["id"],))
                dev["oids"] = cur.fetchall()
    finally:
        conn.close()

    if not devices:
        logger.info("No hay dispositivos SNMP activos configurados para sondeo.")
        return {"status": "ok", "polled": 0}

    semaphore = asyncio.Semaphore(10)
    start_time = time.perf_counter()

    async def _safe_poll(dev: Dict[str, Any]):
        async with semaphore:
            res = await poll_device_oids(dev, dev.get("oids", []))

            # Actualizar estado en base de datos
            c = get_db_connection()
            try:
                with c.cursor() as cur:
                    if res["status"] == "success":
                        cur.execute("""
                            UPDATE snmp_devices
                            SET last_poll_at = NOW(),
                                last_poll_status = 'success',
                                consecutive_failures = 0
                            WHERE id = %s
                        """, (dev["id"],))

                        # Guardar métricas
                        if res["metrics"]:
                            metric_sql = """
                                INSERT INTO snmp_metrics_history
                                (snmp_device_id, snmp_oid_id, metric_value, metric_value_raw, collected_at)
                                VALUES (%s, %s, %s, %s, NOW())
                            """
                            recs = [
                                (dev["id"], m["oid_id"], m["val_num"], m["val_raw"])
                                for m in res["metrics"]
                            ]
                            cur.executemany(metric_sql, recs)
                    else:
                        cur.execute("""
                            UPDATE snmp_devices
                            SET last_poll_at = NOW(),
                                last_poll_status = %s,
                                consecutive_failures = consecutive_failures + 1
                            WHERE id = %s
                        """, (res["status"], dev["id"]))

                    # Purgar métricas viejas (> 30 días)
                    cur.execute("DELETE FROM snmp_metrics_history WHERE collected_at < NOW() - INTERVAL 30 DAY")
            finally:
                c.close()

            # Si el equipo respondió, consultar métricas de interfaces
            if res["status"] == "success":
                await poll_interface_metrics(dev)

            return res

    tasks = [_safe_poll(d) for d in devices]
    results = await asyncio.gather(*tasks, return_exceptions=True)
    elapsed = round(time.perf_counter() - start_time, 2)

    successful = sum(1 for r in results if isinstance(r, dict) and r.get("status") == "success")
    logger.info(f"📊 [POLL SNMP COMPLETADO] Dispositivos: {len(devices)} | Éxito: {successful} | Tiempo: {elapsed}s")

    return {
        "status": "completed",
        "total": len(devices),
        "successful": successful,
        "elapsed_seconds": elapsed,
    }


def snmp_walk_arp_table(device: Dict[str, Any]) -> List[Dict[str, str]]:
    """
    Extrae ipNetToMediaTable (1.3.6.1.2.1.4.22.1.2) para passive discovery de MAC/IP.
    """
    # Función de apoyo sincrónica/asincrónica para topología pasiva
    return []


def print_status():
    """Muestra el estado de dispositivos SNMP y métricas en consola."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, name, ip_address, device_type, vendor, snmp_version, last_poll_status, consecutive_failures, last_poll_at
                FROM snmp_devices
                ORDER BY id ASC
            """)
            devices = cur.fetchall()

            cur.execute("SELECT COUNT(*) as total FROM snmp_metrics_history")
            metrics_count = cur.fetchone()["total"]

            cur.execute("SELECT COUNT(*) as total FROM snmp_interfaces")
            iface_count = cur.fetchone()["total"]

        print("\n" + "=" * 65)
        print("📡 ESTADO DEL SUBSISTEMA DE MONITOREO SNMP (VALLE SECO)")
        print("=" * 65)
        print(f"• Total Dispositivos Monitoreados: {len(devices)}")
        print(f"• Métricas Registradas en Historial: {metrics_count}")
        print(f"• Interfaces de Red Descubiertas:    {iface_count}")
        print("-" * 65)
        for d in devices:
            status_icon = "🟢" if d["last_poll_status"] == "success" else ("⏳" if not d["last_poll_status"] else "🔴")
            last_p = d["last_poll_at"] or "Nunca"
            print(f"{status_icon} [{d['id']}] {d['name']} ({d['ip_address']}) | Tipo: {d['device_type']} | Estado: {d['last_poll_status'] or 'Pendiente'} | Fallos: {d['consecutive_failures']} | Último Poll: {last_p}")
        print("=" * 65 + "\n")
    finally:
        conn.close()


def main():
    parser = argparse.ArgumentParser(description="Motor de Recolección y Telemetría SNMP Valle Seco")
    parser.add_argument("--poll", action="store_true", help="Ejecutar ciclo de sondeo SNMP")
    parser.add_argument("--device", type=int, help="ID de dispositivo específico a sondear")
    parser.add_argument("--interfaces", type=int, help="Descubrir interfaces para un ID de dispositivo")
    parser.add_argument("--status", action="store_true", help="Consultar estado de equipos SNMP")

    args = parser.parse_args()

    if args.status:
        print_status()
        return

    if args.interfaces:
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM snmp_devices WHERE id = %s", (args.interfaces,))
                dev = cur.fetchone()
            if dev:
                asyncio.run(discover_interfaces(dev))
            else:
                print(f"Dispositivo con ID {args.interfaces} no encontrado.")
        finally:
            conn.close()
        return

    dev_ids = [args.device] if args.device else None
    asyncio.run(run_snmp_poll(device_ids=dev_ids))


if __name__ == "__main__":
    main()
