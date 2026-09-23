#!/usr/bin/env python3
# ==============================================================================
# ⚡ DISPATCHER WAKE-ON-LAN: wol_sender.py (@IA_ValleSeco_bot)
# Emisor de Magic Packets para encendido remoto de equipos de red y servidores
# Ubicación: /scripts/telegram-admin-bot/monitor/wol_sender.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

from __future__ import annotations

import argparse
import json
import logging
import re
import socket
import sys
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import pymysql

try:
    from monitor.monitor_web_sync import get_db_connection
except ImportError:
    def get_db_connection():
        return pymysql.connect(
            host="127.0.0.1",
            user="root",
            password="",
            database="monitoreo_vs",
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = LOG_DIR / "wol_sender.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [wol] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("wol")


def normalize_mac(mac: str) -> str:
    """Normaliza y valida una dirección MAC a formato estándar XX:XX:XX:XX:XX:XX."""
    cleaned = re.sub(r"[^a-fA-F0-9]", "", mac.strip())
    if len(cleaned) != 12:
        raise ValueError(f"Dirección MAC inválida: '{mac}'. Debe contener 12 dígitos hexadecimales.")
    return ":".join(cleaned[i:i+2].upper() for i in range(0, 12, 2))


def create_magic_packet(mac: str) -> bytes:
    """Construye el Magic Packet de Wake-on-LAN (6x 0xFF + 16x MAC)."""
    norm_mac = normalize_mac(mac)
    mac_bytes = bytes.fromhex(norm_mac.replace(":", ""))
    return b"\xff" * 6 + mac_bytes * 16


def send_magic_packet(mac: str, broadcast_ip: str = "255.255.255.255", port: int = 9) -> bool:
    """Envía un Magic Packet vía broadcast UDP."""
    try:
        packet = create_magic_packet(mac)
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as sock:
            sock.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
            sock.sendto(packet, (broadcast_ip, port))
        logger.info(f"Magic Packet enviado exitosamente a {mac} vía {broadcast_ip}:{port}")
        return True
    except Exception as e:
        logger.error(f"Fallo al enviar Magic Packet a {mac} ({broadcast_ip}): {e}")
        return False


class WolManager:
    """Gestor de dispositivos Wake-on-LAN y despacho registrado en base de datos."""

    def __init__(self):
        pass

    def list_devices(self) -> List[Dict[str, Any]]:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT id, name, mac_address, ip_address, broadcast_address, site_id, "
                    "is_enabled, last_woken_at, created_at FROM wol_devices ORDER BY name ASC"
                )
                return cursor.fetchall()
        finally:
            conn.close()

    def get_device(self, identifier: str | int) -> Optional[Dict[str, Any]]:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                if str(identifier).isdigit():
                    cursor.execute("SELECT * FROM wol_devices WHERE id = %s", (int(identifier),))
                    row = cursor.fetchone()
                    if row:
                        return row

                # Buscar por MAC normalizada
                try:
                    norm = normalize_mac(str(identifier))
                    cursor.execute("SELECT * FROM wol_devices WHERE mac_address = %s", (norm,))
                    row = cursor.fetchone()
                    if row:
                        return row
                except ValueError:
                    pass

                # Buscar por nombre
                cursor.execute("SELECT * FROM wol_devices WHERE name LIKE %s", (f"%{identifier}%",))
                return cursor.fetchone()
        finally:
            conn.close()

    def add_device(
        self,
        name: str,
        mac: str,
        ip: Optional[str] = None,
        broadcast: str = "255.255.255.255",
        site_id: Optional[int] = None,
    ) -> Dict[str, Any]:
        norm_mac = normalize_mac(mac)
        broadcast = broadcast.strip() if broadcast else "255.255.255.255"
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    INSERT INTO wol_devices (name, mac_address, ip_address, broadcast_address, site_id, is_enabled, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, 1, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        ip_address = VALUES(ip_address),
                        broadcast_address = VALUES(broadcast_address),
                        site_id = VALUES(site_id),
                        updated_at = VALUES(updated_at)
                    """,
                    (name, norm_mac, ip, broadcast, site_id, now, now)
                )
            logger.info(f"Dispositivo WoL registrado/actualizado: {name} ({norm_mac})")
            return {"status": "ok", "mac": norm_mac, "name": name}
        finally:
            conn.close()

    def wake(self, identifier: str | int, broadcast_override: Optional[str] = None, port: int = 9) -> Dict[str, Any]:
        dev = self.get_device(identifier)
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        if dev:
            mac = dev["mac_address"]
            bcast = broadcast_override or dev.get("broadcast_address") or "255.255.255.255"
            name = dev["name"]
            dev_id = dev["id"]
        else:
            # Si no está en BD pero es una MAC válida, enviar directamente
            try:
                mac = normalize_mac(str(identifier))
                bcast = broadcast_override or "255.255.255.255"
                name = f"Dispositivo {mac}"
                dev_id = None
            except ValueError:
                return {
                    "status": "error",
                    "message": f"No se encontró el dispositivo '{identifier}' ni es una MAC válida.",
                }

        success = send_magic_packet(mac, broadcast_ip=bcast, port=port)
        if success and dev_id:
            conn = get_db_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute(
                        "UPDATE wol_devices SET last_woken_at = %s, updated_at = %s WHERE id = %s",
                        (now, now, dev_id)
                    )
            finally:
                conn.close()

        return {
            "status": "ok" if success else "error",
            "name": name,
            "mac": mac,
            "broadcast": bcast,
            "woken_at": now if success else None,
        }


def main():
    parser = argparse.ArgumentParser(description="Emisor y Gestor de Wake-on-LAN (Fase 7)")
    parser.add_argument("--wake", metavar="IDENTIFIER", help="Enviar Magic Packet a dispositivo por ID, MAC o Nombre")
    parser.add_argument("--list", action="store_true", help="Listar dispositivos WoL registrados")
    parser.add_argument("--add", nargs=2, metavar=("NAME", "MAC"), help="Registrar nuevo equipo WoL")
    parser.add_argument("--ip", metavar="IP", help="IP asignada al equipo (opcional con --add)")
    parser.add_argument("--broadcast", default="255.255.255.255", help="Dirección de broadcast (def: 255.255.255.255)")
    parser.add_argument("--port", type=int, default=9, help="Puerto UDP para el Magic Packet (def: 9)")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON")
    args = parser.parse_args()

    manager = WolManager()

    if args.add:
        name, mac = args.add
        try:
            res = manager.add_device(name=name, mac=mac, ip=args.ip, broadcast=args.broadcast)
            if args.json:
                print(json.dumps(res, indent=2))
            else:
                print(f"✅ Dispositivo WoL '{name}' [{res['mac']}] registrado exitosamente.")
        except Exception as e:
            print(f"❌ Error al registrar dispositivo WoL: {e}", file=sys.stderr)
            sys.exit(1)
        return

    if args.wake:
        res = manager.wake(args.wake, broadcast_override=args.broadcast if args.broadcast != "255.255.255.255" else None, port=args.port)
        if args.json:
            print(json.dumps(res, indent=2))
        else:
            if res.get("status") == "ok":
                print(f"⚡ Magic Packet enviado con éxito a {res['name']} ({res['mac']}) vía {res['broadcast']}:{args.port}")
            else:
                print(f"❌ Error al enviar Magic Packet: {res.get('message', 'Fallo de red')}", file=sys.stderr)
                sys.exit(1)
        return

    if args.list:
        devices = manager.list_devices()
        if args.json:
            print(json.dumps(devices, default=str, indent=2))
        else:
            print("\n============================================================")
            print("⚡ DISPOSITIVOS WAKE-ON-LAN REGISTRADOS")
            print("============================================================")
            if not devices:
                print("No hay dispositivos registrados en 'wol_devices'.")
            for d in devices:
                woken = d['last_woken_at'].strftime('%Y-%m-%d %H:%M:%S') if d.get('last_woken_at') else 'Nunca'
                print(f"• [ID: {d['id']:02d}] {d['name']:<25} | MAC: {d['mac_address']} | IP: {d.get('ip_address') or 'N/A':<15} | Despertado: {woken}")
            print("============================================================\n")
        return

    parser.print_help()


if __name__ == "__main__":
    main()
