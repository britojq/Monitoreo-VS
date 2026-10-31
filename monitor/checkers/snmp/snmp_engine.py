"""
# ==============================================================================
# 📡 MOTOR ASÍNCRONO DE CONSULTAS SNMP: snmp_engine.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Ejecución asíncrona de comandos SNMP GET (RFC 1213 y MIBs privadas).
# Ubicación: /opt/sonar/monitor/checkers/snmp/snmp_engine.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Any, Dict, List, Optional

from pysnmp.hlapi.asyncio import (
    CommunityData,
    ContextData,
    ObjectIdentity,
    ObjectType,
    SnmpEngine,
    UdpTransportTarget,
    get_cmd,
)

from monitor.infrastructure.db_context import db_cursor
from monitor.infrastructure.laravel_encryptor import decrypt_laravel_string

logger = logging.getLogger("monitor.checkers.snmp.engine")


async def poll_device_oids(device: Dict[str, Any], oids: List[Dict[str, Any]]) -> Dict[str, Any]:
    """
    Realiza peticiones SNMP GET concurrentes para una lista de OIDs en un dispositivo.
    Actualiza la información estándar del sistema en snmp_devices.
    """
    ip = device["ip_address"]
    version = device.get("snmp_version", "v2c").lower()
    port = int(device.get("snmp_port", 161))
    timeout = float(device.get("snmp_timeout_seconds", 5))
    retries = int(device.get("snmp_retries", 2))

    community = "public"
    if device.get("snmp_community_encrypted"):
        decrypted = decrypt_laravel_string(device["snmp_community_encrypted"])
        if decrypted:
            community = decrypted

    snmp_engine = SnmpEngine()
    results: List[Dict[str, Any]] = []
    status = "success"
    error_msg: Optional[str] = None
    sys_name: Optional[str] = None
    sys_uptime: Optional[int] = None
    sys_descr: Optional[str] = None
    sys_location: Optional[str] = None

    try:
        auth_data = CommunityData(community, mpModel=(1 if version == "v2c" else 0))
        transport = await UdpTransportTarget.create((ip, port), timeout=timeout, retries=retries)

        # 1. Consultar OIDs estándar del sistema (RFC 1213 MIB-II)
        try:
            err_ind, err_stat, _, var_binds = await get_cmd(
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

                # Actualizar información viva del dispositivo en MariaDB SSOT
                if (sys_name or sys_uptime or sys_descr or sys_location) and device.get("id"):
                    try:
                        with db_cursor(autocommit=True) as cur:
                            cur.execute(
                                """
                                UPDATE snmp_devices
                                SET sys_name = COALESCE(%s, sys_name),
                                    sys_uptime = COALESCE(%s, sys_uptime),
                                    sys_description = COALESCE(%s, sys_description),
                                    sys_location = COALESCE(%s, sys_location)
                                WHERE id = %s
                                """,
                                (sys_name, sys_uptime, sys_descr, sys_location, device["id"]),
                            )
                    except Exception as e_up:
                        logger.debug(f"Aviso actualizando sys info para {ip}: {e_up}")

        except Exception as e_sys:
            status = "timeout" if "timeout" in str(e_sys).lower() else "error"
            error_msg = str(e_sys)

        # 2. Consultar OIDs específicos configurados
        if status == "success" and oids:
            for oid_info in oids:
                oid_str = oid_info.get("custom_oid") or oid_info.get("oid")
                if not oid_str:
                    continue

                try:
                    e_ind, e_stat, _, vbs = await get_cmd(
                        snmp_engine,
                        auth_data,
                        transport,
                        ContextData(),
                        ObjectType(ObjectIdentity(oid_str)),
                    )
                    if not e_ind and not e_stat:
                        for vb in vbs:
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
