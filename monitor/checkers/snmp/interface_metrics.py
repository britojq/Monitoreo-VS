"""
# ==============================================================================
# 📈 TELEMETRÍA Y MÉTRICAS DE INTERFACES: interface_metrics.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Cálculo de tasas de transferencia (bps), saturación y manejo de counter-wrap.
# Ubicación: /opt/sonar/monitor/checkers/snmp/interface_metrics.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from datetime import datetime
from typing import Any, Dict, List

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

logger = logging.getLogger("monitor.checkers.snmp.metrics")


async def poll_interface_metrics(device: Dict[str, Any]) -> None:
    """
    Consulta octetos de entrada y salida para calcular in_bps y out_bps con delta de tiempo y counter wrap.
    Persiste en snmp_interface_metrics y actualiza snmp_interfaces.
    """
    dev_id = device["id"]
    ip = device["ip_address"]

    interfaces: List[Dict[str, Any]] = []
    with db_cursor() as cur:
        cur.execute(
            """
            SELECT id, if_index, if_speed, if_high_speed, last_in_octets, last_out_octets, last_polled_at
            FROM snmp_interfaces
            WHERE snmp_device_id = %s AND (is_monitored = 1 OR if_oper_status = 'up')
            """,
            (dev_id,),
        )
        interfaces = cur.fetchall()

    if not interfaces:
        return

    community = "public"
    if device.get("snmp_community_encrypted"):
        dec = decrypt_laravel_string(device["snmp_community_encrypted"])
        if dec:
            community = dec

    snmp_engine = SnmpEngine()
    try:
        auth_data = CommunityData(community, mpModel=1)
        transport = await UdpTransportTarget.create(
            (ip, int(device.get("snmp_port", 161))),
            timeout=4.0,
            retries=1,
        )

        metric_records: List[tuple] = []
        update_if_records: List[tuple] = []
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

                        speed = (
                            iface.get("if_high_speed") * 1000000
                            if iface.get("if_high_speed")
                            else (iface.get("if_speed") or 0)
                        )
                        if speed > 0:
                            in_util = min(100.0, round((in_bps / speed) * 100, 2))
                            out_util = min(100.0, round((out_bps / speed) * 100, 2))

                    metric_records.append((
                        iface["id"], new_in, new_out, in_bps, out_bps, in_util, out_util
                    ))
                    update_if_records.append((new_in, new_out, iface["id"]))
            except Exception as e_poll:
                logger.debug(f"Aviso consultando métricas iface {idx} en {ip}: {e_poll}")

        if metric_records:
            with db_cursor(autocommit=True) as cur:
                cur.executemany(
                    """
                    INSERT INTO snmp_interface_metrics
                    (snmp_interface_id, in_octets, out_octets, in_bps, out_bps, in_utilization_pct, out_utilization_pct, collected_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
                    """,
                    metric_records,
                )
                cur.executemany(
                    """
                    UPDATE snmp_interfaces
                    SET last_in_octets = %s, last_out_octets = %s, last_polled_at = NOW()
                    WHERE id = %s
                    """,
                    update_if_records,
                )
    finally:
        snmp_engine.close_dispatcher()
