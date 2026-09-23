#!/usr/bin/env python3
# ==============================================================================
# 📦 GESTOR DE CICLO DE VIDA DE HARDWARE: lifecycle_manager.py (@IA_ValleSeco_bot)
# Auditoría de garantías, fin de soporte (EOL/EOS), baterías y estado de discos
# Ubicación: /scripts/telegram-admin-bot/monitor/lifecycle_manager.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

from __future__ import annotations

import argparse
import json
import logging
import sys
from datetime import datetime, date
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
LOG_FILE = LOG_DIR / "lifecycle_manager.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [lifecycle] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("lifecycle")


class LifecycleManager:
    """Administrador de ciclo de vida de equipamiento físico e infraestructura."""

    def __init__(self):
        pass

    def get_all_items(self) -> List[Dict[str, Any]]:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT h.*, 
                           COALESCE(s.name, n.name, CONCAT('Equipo #', h.entity_id)) AS device_name,
                           COALESCE(s.ip_address, n.ip, 'N/A') AS ip_address
                    FROM hardware_lifecycle h
                    LEFT JOIN snmp_devices s ON h.entity_type = 'snmp_device' AND h.entity_id = s.id
                    LEFT JOIN monitored_network_devices n ON h.entity_type = 'network_device' AND h.entity_id = n.id
                    ORDER BY h.warranty_end_date ASC, h.id ASC
                    """
                )
                items = cursor.fetchall()
                today = date.today()

                for it in items:
                    w_end = it.get("warranty_end_date")
                    if w_end:
                        it["warranty_days"] = (w_end - today).days
                        if it["warranty_days"] < 0:
                            it["warranty_status"] = "Vencida"
                        elif it["warranty_days"] <= 30:
                            it["warranty_status"] = "Por Vencer (< 30d)"
                        elif it["warranty_days"] <= 90:
                            it["warranty_status"] = "Próxima (< 90d)"
                        else:
                            it["warranty_status"] = "Vigente"
                    else:
                        it["warranty_days"] = None
                        it["warranty_status"] = "Sin Registro"

                    eol = it.get("eol_date")
                    if eol:
                        it["eol_days"] = (eol - today).days
                        it["is_eol"] = it["eol_days"] < 0
                    else:
                        it["eol_days"] = None
                        it["is_eol"] = False

                return items
        finally:
            conn.close()

    def get_summary(self) -> Dict[str, Any]:
        items = self.get_all_items()
        total = len(items)
        warranty_expired = sum(1 for x in items if x.get("warranty_days") is not None and x["warranty_days"] < 0)
        warranty_expiring_soon = sum(1 for x in items if x.get("warranty_days") is not None and 0 <= x["warranty_days"] <= 60)
        warranty_valid = sum(1 for x in items if x.get("warranty_days") is not None and x["warranty_days"] > 60)
        eol_reached = sum(1 for x in items if x.get("is_eol"))
        disk_issues = sum(1 for x in items if x.get("disk_health_status") in ("warning", "failing"))

        return {
            "total_tracked": total,
            "warranty_valid": warranty_valid,
            "warranty_expiring_soon": warranty_expiring_soon,
            "warranty_expired": warranty_expired,
            "eol_reached": eol_reached,
            "disk_issues": disk_issues,
        }

    def register_item(
        self,
        entity_type: str,
        entity_id: int,
        serial_number: Optional[str] = None,
        purchase_date: Optional[str] = None,
        warranty_end_date: Optional[str] = None,
        eol_date: Optional[str] = None,
        eos_date: Optional[str] = None,
        battery_last_replaced: Optional[str] = None,
        disk_health_status: str = "ok",
        firmware_version: Optional[str] = None,
        notes: Optional[str] = None,
    ) -> Dict[str, Any]:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    INSERT INTO hardware_lifecycle 
                    (entity_type, entity_id, serial_number, purchase_date, warranty_end_date, eol_date, eos_date, battery_last_replaced, disk_health_status, firmware_version, notes, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        serial_number = VALUES(serial_number),
                        purchase_date = VALUES(purchase_date),
                        warranty_end_date = VALUES(warranty_end_date),
                        eol_date = VALUES(eol_date),
                        eos_date = VALUES(eos_date),
                        battery_last_replaced = VALUES(battery_last_replaced),
                        disk_health_status = VALUES(disk_health_status),
                        firmware_version = VALUES(firmware_version),
                        notes = VALUES(notes),
                        updated_at = NOW()
                    """,
                    (
                        entity_type,
                        entity_id,
                        serial_number,
                        purchase_date,
                        warranty_end_date,
                        eol_date,
                        eos_date,
                        battery_last_replaced,
                        disk_health_status,
                        firmware_version,
                        notes,
                    )
                )
            logger.info(f"Registro de ciclo de vida guardado para {entity_type} #{entity_id} (SN: {serial_number})")
            return {"status": "ok", "entity_type": entity_type, "entity_id": entity_id}
        finally:
            conn.close()


def main():
    parser = argparse.ArgumentParser(description="Gestor de Ciclo de Vida de Hardware (Fase 7)")
    parser.add_argument("--summary", action="store_true", help="Mostrar resumen de garantías y fin de soporte")
    parser.add_argument("--list", action="store_true", help="Listar todos los activos y su estado de ciclo de vida")
    parser.add_argument("--register", nargs=2, metavar=("TYPE", "ID"), help="Registrar o actualizar ciclo de vida para entidad (ej. snmp_device 1)")
    parser.add_argument("--serial", help="Número de serie del activo")
    parser.add_argument("--warranty-end", help="Fecha fin de garantía (YYYY-MM-DD)")
    parser.add_argument("--eol", help="Fecha Fin de Vida EOL (YYYY-MM-DD)")
    parser.add_argument("--disk-status", choices=["ok", "warning", "failing", "unknown"], default="ok", help="Estado de salud SMART/Disco")
    parser.add_argument("--firmware", help="Versión de firmware actual")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON")
    args = parser.parse_args()

    manager = LifecycleManager()

    if args.register:
        etype, eid = args.register
        res = manager.register_item(
            entity_type=etype,
            entity_id=int(eid),
            serial_number=args.serial,
            warranty_end_date=args.warranty_end,
            eol_date=args.eol,
            disk_health_status=args.disk_status,
            firmware_version=args.firmware,
        )
        if args.json:
            print(json.dumps(res, indent=2))
        else:
            print(f"✅ Ciclo de vida registrado para {etype} #{eid}.")
        return

    if args.list:
        items = manager.get_all_items()
        if args.json:
            print(json.dumps(items, default=str, indent=2))
        else:
            print("\n============================================================")
            print("📦 REGISTRO DE CICLO DE VIDA DE HARDWARE")
            print("============================================================")
            if not items:
                print("No hay registros en 'hardware_lifecycle'.")
            for it in items:
                w_str = f"{it['warranty_status']} ({it['warranty_days']}d)" if it.get("warranty_days") is not None else "Sin garantía"
                print(f"• [#{it['id']:02d}] {it['device_name']:<25} | S/N: {it.get('serial_number') or 'N/A':<15} | Garantía: {w_str:<20} | SMART: {it['disk_health_status'].upper()}")
            print("============================================================\n")
        return

    # Por defecto --summary
    summary = manager.get_summary()
    if args.json:
        print(json.dumps(summary, indent=2))
    else:
        print("\n============================================================")
        print("📦 RESUMEN EJECUTIVO DE CICLO DE VIDA (VALLE SECO)")
        print("============================================================")
        print(f"• Total Activos Registrados:     {summary['total_tracked']}")
        print(f"• Garantías Vigentes:            {summary['warranty_valid']}")
        print(f"• Garantías Próximas a Vencer:   {summary['warranty_expiring_soon']}")
        print(f"• Garantías Vencidas:            {summary['warranty_expired']}")
        print(f"• Equipos en Fin de Vida (EOL):  {summary['eol_reached']}")
        print(f"• Alarmas de Salud de Disco:     {summary['disk_issues']}")
        print("============================================================\n")


if __name__ == "__main__":
    main()
