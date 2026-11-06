"""
# ==============================================================================
# 🔍 DESCUBRIDOR DE INTERFACES DE RED: interface_discoverer.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Sondeo ifTable (RFC 1213 / IF-MIB) y catálogo de puertos físicos y lógicos.
# Ubicación: /opt/sonar/monitor/checkers/snmp/interface_discoverer.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Any, Dict, List

from pysnmp.hlapi.asyncio import (
    CommunityData,
    ContextData,
    ObjectIdentity,
    ObjectType,
    SnmpEngine,
    UdpTransportTarget,
    bulk_cmd,
)

from monitor.infrastructure.db_context import db_cursor
from monitor.infrastructure.laravel_encryptor import decrypt_laravel_string

logger = logging.getLogger("monitor.checkers.snmp.interfaces")


async def discover_interfaces(device: Dict[str, Any]) -> List[Dict[str, Any]]:
    """
    Descubre interfaces físicas y lógicas vía ifTable walk (RFC 1213 / IF-MIB).
    Inserta o actualiza en la tabla snmp_interfaces de MariaDB (SSOT).
    """
    ip = device["ip_address"]
    dev_id = device["id"]
    timeout = float(device.get("snmp_timeout_seconds", 5))
    retries = int(device.get("snmp_retries", 2))

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
        transport = await UdpTransportTarget.create(
            (ip, int(device.get("snmp_port", 161))),
            timeout=timeout,
            retries=retries,
        )

        current_oid = "1.3.6.1.2.1.2.2.1"
        for _ in range(60):  # Lotes de 50 registros (hasta 3000 entradas)
            err_ind, err_stat, _, var_bind_table = await bulk_cmd(
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
                    if field_id == 2:
                        item["if_description"] = val[:255]
                        item["if_name"] = val[:255]
                    elif field_id == 3:
                        try:
                            item["if_type"] = int(val)
                        except ValueError:
                            pass
                    elif field_id == 5:
                        try:
                            item["if_speed"] = int(val)
                        except ValueError:
                            pass
                    elif field_id == 6:
                        item["if_physical_address"] = val[:17]
                    elif field_id == 7:
                        item["if_admin_status"] = "up" if val == "1" else "down"
                    elif field_id == 8:
                        item["if_oper_status"] = "up" if val == "1" else "down"
                    elif field_id == 10:
                        try:
                            item["last_in_octets"] = int(val)
                        except ValueError:
                            pass
                    elif field_id == 16:
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

    results = list(discovered_interfaces.values())
    if results:
        with db_cursor(autocommit=True) as cur:
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
            records = [
                (
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
                )
                for iface in results
            ]
            cur.executemany(upsert_sql, records)
        logger.info(f"✅ Se guardaron/actualizaron {len(results)} interfaces para {ip}.")

    return results
