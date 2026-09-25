#!/usr/bin/env python3
# ==============================================================================
# 📥 RECEPTOR DE TRAPS SNMP PUSH: snmp_trap_receiver.py (@IA_ValleSeco_bot)
# Demonio de captura y análisis de traps SNMP v1/v2c en tiempo real (UDP 162)
# Ubicación: /scripts/telegram-admin-bot/monitor/snmp_trap_receiver.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

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

from monitor.telegram_dispatcher import TelegramDispatcher

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = LOG_DIR / "snmp_traps.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [snmp.trap] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("snmp.trap")

# Mapeo de OIDs estándar y de fabricantes a tipos legibles y severidades
TRAP_DICTIONARY: Dict[str, Dict[str, str]] = {
    # MIB-II / SNMPv2-MIB Traps Estándar (RFC 3418)
    "1.3.6.1.6.3.1.1.5.1": {
        "type": "coldStart",
        "severity": "warning",
        "desc": "Reinicio en frío del equipo (coldStart)"
    },
    "1.3.6.1.6.3.1.1.5.2": {
        "type": "warmStart",
        "severity": "info",
        "desc": "Reinicio en caliente del equipo (warmStart)"
    },
    "1.3.6.1.6.3.1.1.5.3": {
        "type": "linkDown",
        "severity": "critical",
        "desc": "Interfaz de red caída (linkDown)"
    },
    "1.3.6.1.6.3.1.1.5.4": {
        "type": "linkUp",
        "severity": "info",
        "desc": "Interfaz de red restablecida (linkUp)"
    },
    "1.3.6.1.6.3.1.1.5.5": {
        "type": "authenticationFailure",
        "severity": "warning",
        "desc": "Fallo de autenticación o comunidad SNMP incorrecta"
    },
    "1.3.6.1.6.3.1.1.5.6": {
        "type": "egpNeighborLoss",
        "severity": "critical",
        "desc": "Pérdida de vecino EGP/BGP"
    },

    # Cisco System Traps
    "1.3.6.1.4.1.9.9.43.2.0.1": {
        "type": "ciscoConfigManEvent",
        "severity": "info",
        "desc": "Evento de gestión de configuración Cisco (escritura o guardado)"
    },
    "1.3.6.1.4.1.9.9.13.3.0.1": {
        "type": "ciscoEnvMonShutdown",
        "severity": "emergency",
        "desc": "Apagado inminente por monitor ambiental (temperatura/voltaje crítico)"
    },
    "1.3.6.1.4.1.9.9.13.3.0.2": {
        "type": "ciscoEnvMonVoltage",
        "severity": "critical",
        "desc": "Alerta de voltaje fuera de rango"
    },
    "1.3.6.1.4.1.9.9.13.3.0.3": {
        "type": "ciscoEnvMonTemperature",
        "severity": "critical",
        "desc": "Alerta de temperatura crítica en chasis"
    },
    "1.3.6.1.4.1.9.9.13.3.0.4": {
        "type": "ciscoEnvMonFan",
        "severity": "warning",
        "desc": "Falla o advertencia en ventilador de chasis"
    },
    "1.3.6.1.4.1.9.9.13.3.0.5": {
        "type": "ciscoEnvMonRedundantSupply",
        "severity": "warning",
        "desc": "Falla en fuente de poder redundante"
    },

    # UPS Traps (RFC 1628)
    "1.3.6.1.2.1.33.2.0.1": {
        "type": "upsTrapOnBattery",
        "severity": "critical",
        "desc": "UPS operando con batería (corte de energía de red)"
    },
    "1.3.6.1.2.1.33.2.0.2": {
        "type": "upsTrapTestCompleted",
        "severity": "info",
        "desc": "Autodiagnóstico de batería de UPS completado"
    },
    "1.3.6.1.2.1.33.2.0.3": {
        "type": "upsTrapAlarmEntryAdded",
        "severity": "warning",
        "desc": "Nueva condición de alarma detectada en UPS"
    },
}

DEFAULT_TRAP_PORT = 162
FALLBACK_TRAP_PORT = 10162
OWNER_PRIVATE_CHAT_ID = 38914901


def resolve_trap_info(trap_oid: str) -> Dict[str, str]:
    """Resuelve la metadata (tipo, severidad, descripción) para un OID de trap."""
    if trap_oid in TRAP_DICTIONARY:
        return TRAP_DICTIONARY[trap_oid].copy()

    severity = "info"
    oid_lower = trap_oid.lower()
    if any(k in oid_lower for k in ("down", "fail", "alarm", "error", "emerg", "crit")):
        severity = "critical"
    elif any(k in oid_lower for k in ("warn", "change")):
        severity = "warning"

    return {
        "type": "genericTrap",
        "severity": severity,
        "desc": f"Trap SNMP no estándar ({trap_oid})"
    }


def get_known_communities() -> List[str]:
    """Obtiene comunidades SNMP activas registradas en la base de datos."""
    communities = ["public", "private", "monitoreo", "valle_seco"]
    try:
        conn = get_db_connection()
        with conn.cursor() as cur:
            cur.execute("SELECT ip_address FROM snmp_devices WHERE is_active = 1")
            _ = cur.fetchall()
        conn.close()
    except Exception as e:
        logger.warning(f"No se pudieron cargar comunidades personalizadas de BD: {e}")
    return list(set(communities))


def match_device_id(source_ip: str) -> Optional[int]:
    """Busca si la IP emisora corresponde a un dispositivo SNMP registrado."""
    try:
        conn = get_db_connection()
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM snmp_devices WHERE ip_address = %s LIMIT 1",
                (source_ip,)
            )
            row = cur.fetchone()
            if row:
                conn.close()
                return row["id"]
            
            # Buscar en network_devices
            cur.execute(
                "SELECT id FROM monitored_network_devices WHERE ip = %s LIMIT 1",
                (source_ip,)
            )
            row_net = cur.fetchone()
            conn.close()
            if row_net:
                return row_net["id"]
    except Exception as e:
        logger.warning(f"Error buscando dispositivo para IP {source_ip}: {e}")
    return None


def store_trap(
    source_ip: str,
    trap_oid: str,
    trap_type: str,
    varbinds: List[Dict[str, str]],
    severity: str
) -> int:
    """Inserta el trap recibido en snmp_traps_received."""
    device_id = match_device_id(source_ip)
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO snmp_traps_received
                (snmp_device_id, source_ip, trap_oid, trap_type, varbinds, severity, processed, received_at)
                VALUES (%s, %s, %s, %s, %s, %s, 0, NOW())
                """,
                (
                    device_id,
                    source_ip,
                    trap_oid,
                    trap_type,
                    json.dumps(varbinds, ensure_ascii=False),
                    severity
                )
            )
            trap_id = cur.lastrowid
            conn.commit()
            return trap_id
    finally:
        conn.close()


async def notify_owner_trap(source_ip: str, trap_type: str, trap_oid: str, severity: str, varbinds: List[Dict[str, str]]):
    """
    Notifica traps críticos exclusivamente al chat privado del Administrador.
    🚨 En estricto cumplimiento de la REGLA DE ORO #1: NUNCA enviar a grupos.
    """
    if severity not in ("critical", "emergency") and trap_type != "linkDown":
        return

    sev_emoji = "🚨" if severity == "emergency" else "🔴" if severity == "critical" else "⚠️"
    msg = (
        f"{sev_emoji} <b>ALERTA DE TELEMETRÍA PUSH: SNMP TRAP</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Origen:</b> <code>{html.escape(source_ip)}</code>\n"
        f"⚡ <b>Tipo:</b> <code>{html.escape(trap_type)}</code>\n"
        f"🏷️ <b>OID:</b> <code>{html.escape(trap_oid)}</code>\n"
        f"💥 <b>Severidad:</b> <b>{severity.upper()}</b>\n"
        f"⏰ <b>Hora:</b> {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
    )

    if varbinds:
        msg += "\n<b>Variables Recibidas (VarBinds):</b>\n"
        for vb in varbinds[:4]:
            oid_str = vb.get("oid", "")
            val_str = vb.get("value", "")
            msg += f"• <code>{html.escape(oid_str[-20:])}</code> = <code>{html.escape(str(val_str)[:60])}</code>\n"

    dispatcher = TelegramDispatcher()
    try:
        await dispatcher.send_text(OWNER_PRIVATE_CHAT_ID, msg)
        logger.info(f"Notificación de trap crítico enviada a Owner {OWNER_PRIVATE_CHAT_ID}")
    except Exception as e:
        logger.error(f"Error despachando notificación de trap a Owner: {e}")


def handle_trap_pdu(snmpEngine, stateReference, contextEngineId, contextName, varBinds, cbCtx):
    """Callback invocado por pysnmp al recibir una PDU de notificación SNMP."""
    try:
        transportDomain, transportAddress = snmpEngine.message_dispatcher.get_transport_info(stateReference)
        source_ip = str(transportAddress[0])
    except Exception:
        source_ip = "127.0.0.1"

    trap_oid = "1.3.6.1.6.3.1.1.5.0"
    varbinds_list: List[Dict[str, str]] = []

    for name, val in varBinds:
        oid_str = str(name)
        val_str = val.prettyPrint()
        varbinds_list.append({"oid": oid_str, "value": val_str})
        # Si coincide con snmpTrapOID (1.3.6.1.6.3.1.1.4.1.0), este es el OID específico del trap
        if oid_str == "1.3.6.1.6.3.1.1.4.1.0":
            trap_oid = val_str

    trap_meta = TRAP_DICTIONARY.get(trap_oid, {})
    trap_type = trap_meta.get("type", "genericTrap")
    severity = trap_meta.get("severity", "info")

    if not trap_meta:
        # Heurística para OIDs desconocidos
        oid_lower = trap_oid.lower()
        if any(k in oid_lower for k in ("down", "fail", "alarm", "error", "emerg", "crit")):
            severity = "critical"
        elif any(k in oid_lower for k in ("warn", "change")):
            severity = "warning"

    logger.info(f"📥 Trap recibido desde {source_ip}: OID={trap_oid}, Type={trap_type}, Severity={severity}")

    try:
        trap_id = store_trap(source_ip, trap_oid, trap_type, varbinds_list, severity)
        logger.info(f"Trap registrado exitosamente en DB con ID #{trap_id}")
    except Exception as e_db:
        logger.error(f"Error guardando trap en BD: {e_db}")

    # Notificar si es crítico
    if severity in ("critical", "emergency") or trap_type == "linkDown":
        asyncio.create_task(notify_owner_trap(source_ip, trap_type, trap_oid, severity, varbinds_list))


async def run_trap_receiver(port: int = DEFAULT_TRAP_PORT) -> Tuple[Any, Any]:
    """Inicia el servidor UDP receptor de SNMP Traps usando pysnmp."""
    from pysnmp.entity import engine, config
    from pysnmp.carrier.asyncio.dgram import udp
    from pysnmp.entity.rfc3413 import ntfrcv

    snmp_engine = engine.SnmpEngine()

    actual_port = port
    try:
        transport = udp.UdpAsyncioTransport().open_server_mode(('0.0.0.0', actual_port))
        config.add_transport(
            snmp_engine,
            udp.DOMAIN_NAME,
            transport
        )
        logger.info(f"🛰️ Receptor SNMP Traps vinculado exitosamente en 0.0.0.0:{actual_port} (UDP)")
    except PermissionError:
        logger.warning(f"Permiso denegado para vincular en puerto {actual_port}. Evaluando puerto alternativo {FALLBACK_TRAP_PORT}...")
        actual_port = FALLBACK_TRAP_PORT
        transport = udp.UdpAsyncioTransport().open_server_mode(('0.0.0.0', actual_port))
        config.add_transport(
            snmp_engine,
            udp.DOMAIN_NAME,
            transport
        )
        logger.info(f"🛰️ Receptor SNMP Traps vinculado en fallback 0.0.0.0:{actual_port} (UDP)")

    # Registrar comunidades
    communities = get_known_communities()
    for idx, comm in enumerate(communities):
        area_name = f"area-{idx}-{comm}"
        config.add_v1_system(snmp_engine, area_name, comm)

    # Configurar receptor
    ntfrcv.NotificationReceiver(snmp_engine, handle_trap_pdu)
    return snmp_engine, transport


async def send_test_trap(target_ip: str = "127.0.0.1", port: int = DEFAULT_TRAP_PORT) -> bool:
    """Envía un trap SNMP sintético (linkDown) para propósitos de prueba."""
    from pysnmp.entity.engine import SnmpEngine
    from pysnmp.hlapi.asyncio import (
        CommunityData,
        ContextData,
        NotificationType,
        ObjectIdentity,
        OctetString,
        UdpTransportTarget,
        send_notification,
    )

    logger.info(f"Enviando trap sintético de prueba a {target_ip}:{port}...")
    try:
        target = await UdpTransportTarget.create((target_ip, port), timeout=2.0, retries=0)
        errorIndication, errorStatus, errorIndex, varBinds = await send_notification(
            SnmpEngine(),
            CommunityData("public"),
            target,
            ContextData(),
            "trap",
            NotificationType(
                ObjectIdentity("1.3.6.1.6.3.1.1.5.3")  # linkDown
            ).add_varbinds(
                ("1.3.6.1.2.1.2.2.1.1.1", OctetString("GigabitEthernet0/1")),
                ("1.3.6.1.2.1.2.2.1.2.1", OctetString("Enlace Principal"))
            )
        )
        if errorIndication:
            logger.error(f"Error al enviar trap sintético: {errorIndication}")
            return False
        logger.info("Trap sintético enviado con éxito.")
        return True
    except Exception as e:
        logger.error(f"Excepción enviando trap de prueba: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(description="Receptor Asíncrono de SNMP Traps (Fase 6)")
    parser.add_argument("--port", type=int, default=DEFAULT_TRAP_PORT, help="Puerto UDP para escuchar (por defecto 162)")
    parser.add_argument("--test-trap", action="store_true", help="Enviar un trap sintético a localhost para verificar")
    parser.add_argument("--listen", action="store_true", help="Ejecutar receptor en bucle principal continuo")
    args = parser.parse_args()

    if args.test_trap:
        asyncio.run(send_test_trap(port=args.port))
        return

    async def runner():
        engine_obj, transport = await run_trap_receiver(port=args.port)
        logger.info("📡 Demonio SNMP Traps escuchando activamente. Presione Ctrl+C para salir.")
        try:
            while True:
                await asyncio.sleep(3600)
        except (KeyboardInterrupt, asyncio.CancelledError):
            logger.info("Deteniendo receptor SNMP Traps...")
            transport.close_transport()

    asyncio.run(runner())


if __name__ == "__main__":
    main()
