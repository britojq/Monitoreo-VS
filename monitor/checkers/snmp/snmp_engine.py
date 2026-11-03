"""
# ==============================================================================
# 📡 MOTOR ASÍNCRONO DE CONSULTAS SNMP: snmp_engine.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Ejecución asíncrona de comandos SNMP GET (RFC 1213 y MIBs privadas).
# Validación estricta de inputs de red bajo OWASP A03:2021.
# Ubicación: /opt/sonar/monitor/checkers/snmp/snmp_engine.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import ipaddress
import logging
import re
from dataclasses import dataclass
from typing import Any, Dict, List, Optional, Union

from pysnmp.hlapi.asyncio import (
    CommunityData,
    ContextData,
    ObjectIdentity,
    ObjectType,
    SnmpEngine,
    UdpTransportTarget,
    get_cmd,
)

from monitor.domain.exceptions import SnmpDeviceValidationError
from monitor.infrastructure.db_context import db_cursor
from monitor.infrastructure.laravel_encryptor import decrypt_laravel_string

logger = logging.getLogger("monitor.checkers.snmp.engine")


@dataclass(frozen=True)
class SNMPDeviceConfig:
    """
    Configuración tipada e inmutable para un dispositivo de red SNMP.

    Valida exhaustivamente la dirección IP, el rango de puertos y los parámetros de red
    antes de interactuar con el socket de transporte, mitigando inyecciones y fallos no controlados.

    Args:
        ip_address: Dirección IPv4 o IPv6 sintácticamente válida.
        id: Identificador numérico del dispositivo en MariaDB (opcional).
        snmp_version: Versión del protocolo ('v1', 'v2c' o 'v3').
        snmp_port: Puerto de escucha del agente SNMP (1 a 65535).
        snmp_timeout_seconds: Tiempo límite de espera en segundos.
        snmp_retries: Número de reintentos ante pérdida de datagramas UDP.
        snmp_community_encrypted: Clave comunitaria cifrada con el esquema de Laravel.

    Raises:
        SnmpDeviceValidationError: Si la IP es inválida, el puerto está fuera de rango
            o la versión no es soportada.
    """
    ip_address: str
    id: Optional[int] = None
    snmp_version: str = "v2c"
    snmp_port: int = 161
    snmp_timeout_seconds: float = 5.0
    snmp_retries: int = 2
    snmp_community_encrypted: Optional[str] = None

    def __post_init__(self) -> None:
        if not self.ip_address or not isinstance(self.ip_address, str):
            raise SnmpDeviceValidationError("La dirección IP del dispositivo SNMP es requerida y debe ser un string.")

        clean_ip = self.ip_address.strip()
        try:
            ipaddress.ip_address(clean_ip)
        except ValueError as err:
            raise SnmpDeviceValidationError(f"Dirección IP SNMP inválida '{self.ip_address}': {err}") from err

        if not (1 <= self.snmp_port <= 65535):
            raise SnmpDeviceValidationError(f"Puerto SNMP fuera de rango legal (1-65535): {self.snmp_port}")

        if self.snmp_version.lower() not in ("v1", "v2c", "v3"):
            raise SnmpDeviceValidationError(
                f"Versión SNMP no soportada '{self.snmp_version}'. Use 'v1', 'v2c' o 'v3'."
            )


async def poll_device_oids(
    device: Union[SNMPDeviceConfig, Dict[str, Any]],
    oids: List[Dict[str, Any]],
) -> Dict[str, Any]:
    """
    Realiza peticiones SNMP GET concurrentes para una lista de OIDs en un dispositivo.

    Valida la configuración del dispositivo mediante SNMPDeviceConfig y actualiza
    la información estándar del sistema en snmp_devices (RFC 1213 MIB-II).

    Args:
        device: Instancia de SNMPDeviceConfig o diccionario con atributos de conexión.
        oids: Lista de diccionarios describiendo OIDs a consultar.

    Returns:
        Dict[str, Any]: Resultados del sondeo, métricas obtenidas y estado de la operación.
    """
    # 1. Validación estricta mediante DTO inmutable
    cfg: SNMPDeviceConfig
    if isinstance(device, SNMPDeviceConfig):
        cfg = device
    else:
        try:
            cfg = SNMPDeviceConfig(
                id=device.get("id"),
                ip_address=str(device.get("ip_address", "")),
                snmp_version=str(device.get("snmp_version", "v2c")).lower(),
                snmp_port=int(device.get("snmp_port", 161)),
                snmp_timeout_seconds=float(device.get("snmp_timeout_seconds", 5)),
                snmp_retries=int(device.get("snmp_retries", 2)),
                snmp_community_encrypted=device.get("snmp_community_encrypted"),
            )
        except (SnmpDeviceValidationError, ValueError, TypeError) as val_err:
            logger.warning(f"Rechazado sondeo SNMP por validación inválida de dispositivo: {val_err}")
            return {
                "device_id": device.get("id") if isinstance(device, dict) else None,
                "ip": device.get("ip_address") if isinstance(device, dict) else "",
                "status": "validation_error",
                "success": False,
                "sys_name": None,
                "sys_uptime": None,
                "sys_description": None,
                "sys_location": None,
                "error": str(val_err),
                "metrics": [],
            }

    ip = cfg.ip_address.strip()
    version = cfg.snmp_version
    port = cfg.snmp_port
    timeout = cfg.snmp_timeout_seconds
    retries = cfg.snmp_retries

    community = "public"
    if cfg.snmp_community_encrypted:
        decrypted = decrypt_laravel_string(cfg.snmp_community_encrypted)
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
                err_str = str(err_ind).lower()
                status = "timeout" if any(k in err_str for k in ("timeout", "timed out", "timedout")) else "error"
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
                if (sys_name or sys_uptime or sys_descr or sys_location) and cfg.id:
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
                                (sys_name, sys_uptime, sys_descr, sys_location, cfg.id),
                            )
                    except Exception as e_up:
                        logger.debug(f"Aviso actualizando sys info para {ip}: {e_up}")

        except Exception as e_sys:
            e_str = str(e_sys).lower()
            status = "timeout" if any(k in e_str for k in ("timeout", "timed out", "timedout")) else "error"
            error_msg = str(e_sys)

        # 2. Consultar OIDs específicos configurados
        if status == "success" and oids:
            for oid_info in oids:
                oid_str = str(oid_info.get("custom_oid") or oid_info.get("oid") or "").strip()
                if not oid_str:
                    continue

                # Validar formato ASN.1 OID (ej: .1.3.6.1... o 1.3.6.1...)
                if not re.match(r"^\.?(\d+\.)*\d+$", oid_str):
                    logger.warning(f"Omitiendo OID con formato inválido '{oid_str}' en dispositivo {ip}")
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
        "device_id": cfg.id,
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
