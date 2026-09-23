#!/usr/bin/env python3
# ==============================================================================
# 📋 RECEPTOR CENTRALIZADO DE SYSLOG: syslog_receiver.py (@IA_ValleSeco_bot)
# Captura asíncrona de eventos RFC 3164 / RFC 5424 en tiempo real (UDP 514)
# Ubicación: /scripts/telegram-admin-bot/monitor/syslog_receiver.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

from __future__ import annotations

import argparse
import asyncio
import html
import logging
import os
import re
import socket
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
LOG_FILE = LOG_DIR / "syslog_events.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [syslog] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("syslog")

DEFAULT_SYSLOG_PORT = 514
FALLBACK_SYSLOG_PORT = 10514
OWNER_PRIVATE_CHAT_ID = 38914901

# Regex para RFC 5424
RE_RFC5424 = re.compile(
    r"^<(?P<pri>\d{1,3})>1\s+(?P<timestamp>\S+)\s+(?P<hostname>\S+)\s+(?P<appname>\S+)\s+(?P<procid>\S+)\s+(?P<msgid>\S+)\s+(?P<sd>\[.*?\]|-)\s*(?P<msg>.*)$"
)

# Regex para RFC 3164 (BSD syslog & Cisco / Network switches)
RE_RFC3164 = re.compile(
    r"^<(?P<pri>\d{1,3})>(?:(?P<timestamp>[A-Za-z]{3}\s+\d+\s+\d{2}:\d{2}:\d{2})\s+)?(?:(?P<hostname>[a-zA-Z0-9\._-]+)\s+)?(?P<program>[%a-zA-Z0-9_\.\-\/]+)(?:\[(?P<pid>\d+)\])?:\s*(?P<msg>.*)$"
)

# Regex simple con solo PRI
RE_SIMPLE_PRI = re.compile(r"^<(?P<pri>\d{1,3})>(?P<msg>.*)$")

SEVERITY_NAMES = {
    0: "Emergency",
    1: "Alert",
    2: "Critical",
    3: "Error",
    4: "Warning",
    5: "Notice",
    6: "Informational",
    7: "Debug"
}

FACILITY_NAMES = {
    0: "kernel",
    1: "user",
    2: "mail",
    3: "daemon",
    4: "auth",
    5: "syslog",
    6: "lpr",
    7: "news",
    8: "uucp",
    9: "cron",
    10: "authpriv",
    11: "ftp",
    12: "ntp",
    13: "security",
    14: "console",
    15: "solaris-cron",
    16: "local0",
    17: "local1",
    18: "local2",
    19: "local3",
    20: "local4",
    21: "local5",
    22: "local6",
    23: "local7"
}

# Cache de debounce para notificaciones a Telegram (evitar tormentas de spam)
_NOTIFICATION_DEBOUNCE: Dict[str, float] = {}
DEBOUNCE_COOLDOWN_SECONDS = 300.0  # 5 minutos por clave


def parse_syslog_message(raw_text: str, source_ip: str) -> Dict[str, Any]:
    """Parsea una línea de syslog aplicando RFC 5424, RFC 3164 o fallback simple."""
    raw_clean = raw_text.strip()
    
    # 1. Intentar RFC 5424
    m = RE_RFC5424.match(raw_clean)
    if m:
        pri = int(m.group("pri"))
        facility = pri // 8
        severity = pri % 8
        hostname = m.group("hostname")
        program = m.group("appname")
        msg = m.group("msg")
        return {
            "source_ip": source_ip,
            "hostname": hostname if hostname != "-" else None,
            "facility": facility,
            "severity": severity,
            "program": program if program != "-" else None,
            "message": msg.strip(),
            "raw_message": raw_clean
        }

    # 2. Intentar RFC 3164
    m = RE_RFC3164.match(raw_clean)
    if m:
        pri = int(m.group("pri"))
        facility = pri // 8
        severity = pri % 8
        hostname = m.group("hostname")
        program = m.group("program")
        msg = m.group("msg")
        return {
            "source_ip": source_ip,
            "hostname": hostname,
            "facility": facility,
            "severity": severity,
            "program": program,
            "message": msg.strip(),
            "raw_message": raw_clean
        }

    # 3. Intentar PRI simple
    m = RE_SIMPLE_PRI.match(raw_clean)
    if m:
        pri = int(m.group("pri"))
        facility = pri // 8
        severity = pri % 8
        msg = m.group("msg").strip()
        # Intentar extraer programa si empieza con palabra:
        parts = msg.split(":", 1)
        if len(parts) == 2 and len(parts[0]) < 30 and " " not in parts[0]:
            program = parts[0]
            clean_msg = parts[1].strip()
        else:
            program = "syslog"
            clean_msg = msg
        return {
            "source_ip": source_ip,
            "hostname": None,
            "facility": facility,
            "severity": severity,
            "program": program,
            "message": clean_msg,
            "raw_message": raw_clean
        }

    # 4. Fallback genérico sin PRI
    return {
        "source_ip": source_ip,
        "hostname": None,
        "facility": 16,  # local0
        "severity": 6,   # info
        "program": "raw",
        "message": raw_clean,
        "raw_message": raw_clean
    }


def store_syslog_event(event: Dict[str, Any]) -> int:
    """Guarda el evento parseado en la tabla syslog_events."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO syslog_events
                (source_ip, hostname, facility, severity, program, message, raw_message, received_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
                """,
                (
                    event["source_ip"],
                    event["hostname"],
                    event["facility"],
                    event["severity"],
                    event["program"],
                    event["message"],
                    event["raw_message"]
                )
            )
            event_id = cur.lastrowid
            conn.commit()
            return event_id
    finally:
        conn.close()


async def check_and_notify_syslog_alert(event: Dict[str, Any]):
    """
    Evalúa si el evento amerita notificación inmediata a Telegram para severidades críticas (<= 3).
    🚨 En estricto cumplimiento de la REGLA DE ORO #1: NUNCA enviar a grupos, solo a Owner ID 38914901.
    """
    severity = event.get("severity", 6)
    if severity > 3:  # Solo notificar Emergency (0), Alert (1), Critical (2), Error (3)
        return

    source_ip = event["source_ip"]
    program = event.get("program") or "syslog"
    debounce_key = f"{source_ip}:{program}:{severity}"

    now_ts = time.time()
    last_notified = _NOTIFICATION_DEBOUNCE.get(debounce_key, 0.0)
    if now_ts - last_notified < DEBOUNCE_COOLDOWN_SECONDS:
        logger.debug(f"Syslog alert debounced for key: {debounce_key}")
        return

    _NOTIFICATION_DEBOUNCE[debounce_key] = now_ts

    sev_name = SEVERITY_NAMES.get(severity, f"Sev{severity}").upper()
    sev_emoji = "🚨" if severity <= 1 else "🔴" if severity == 2 else "⚠️"

    msg = (
        f"{sev_emoji} <b>ALERTA DE SYSLOG: {sev_name}</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Origen:</b> <code>{html.escape(source_ip)}</code>"
    )
    if event.get("hostname"):
        msg += f" (<code>{html.escape(event['hostname'])}</code>)"
    msg += (
        f"\n⚙️ <b>Programa:</b> <code>{html.escape(program)}</code>\n"
        f"💥 <b>Severidad:</b> <b>{sev_name} (Nivel {severity})</b>\n"
        f"⏰ <b>Hora:</b> {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
        f"\n📝 <b>Mensaje:</b>\n"
        f"<code>{html.escape(event['message'][:500])}</code>"
    )

    dispatcher = TelegramDispatcher()
    try:
        await dispatcher.send_text(OWNER_PRIVATE_CHAT_ID, msg)
        logger.info(f"Notificación de syslog crítico despachada a Owner {OWNER_PRIVATE_CHAT_ID} ({debounce_key})")
    except Exception as e:
        logger.error(f"Error enviando notificación de syslog a Owner: {e}")


class SyslogProtocol(asyncio.DatagramProtocol):
    """Protocolo asíncrono para recepción de paquetes Syslog UDP."""

    def connection_made(self, transport: asyncio.DatagramTransport):
        self.transport = transport

    def datagram_received(self, data: bytes, addr: Tuple[str, int]):
        source_ip = addr[0]
        try:
            raw_text = data.decode("utf-8", errors="replace")
        except Exception:
            raw_text = str(data)

        # Parsear evento
        event = parse_syslog_message(raw_text, source_ip)
        logger.info(
            f"📥 Syslog [{SEVERITY_NAMES.get(event['severity'], '?').upper()}] de {source_ip} "
            f"({event.get('program') or 'syslog'}): {event['message'][:80]}"
        )

        # Almacenar en DB en hilo secundario para no bloquear el loop
        try:
            asyncio.create_task(self._process_event(event))
        except Exception as e:
            logger.error(f"Error creando tarea para procesar syslog: {e}")

    async def _process_event(self, event: Dict[str, Any]):
        try:
            await asyncio.to_thread(store_syslog_event, event)
            await check_and_notify_syslog_alert(event)
        except Exception as e:
            logger.error(f"Error procesando evento syslog en background: {e}")


async def run_syslog_receiver(port: int = DEFAULT_SYSLOG_PORT) -> Tuple[asyncio.DatagramTransport, SyslogProtocol]:
    """Inicia el servidor UDP para captura de Syslog."""
    loop = asyncio.get_running_loop()
    actual_port = port
    try:
        transport, protocol = await loop.create_datagram_endpoint(
            lambda: SyslogProtocol(),
            local_addr=("0.0.0.0", actual_port)
        )
        logger.info(f"📋 Receptor Syslog vinculado exitosamente en 0.0.0.0:{actual_port} (UDP)")
        return transport, protocol
    except PermissionError:
        logger.warning(f"Permiso denegado para vincular en puerto {actual_port}. Probando puerto alternativo {FALLBACK_SYSLOG_PORT}...")
        actual_port = FALLBACK_SYSLOG_PORT
        transport, protocol = await loop.create_datagram_endpoint(
            lambda: SyslogProtocol(),
            local_addr=("0.0.0.0", actual_port)
        )
        logger.info(f"📋 Receptor Syslog vinculado en fallback 0.0.0.0:{actual_port} (UDP)")
        return transport, protocol


async def send_test_syslog(target_ip: str = "127.0.0.1", port: int = DEFAULT_SYSLOG_PORT):
    """Envía un paquete de syslog sintético para propósitos de prueba."""
    loop = asyncio.get_running_loop()
    logger.info(f"Enviando mensaje syslog de prueba a {target_ip}:{port}...")
    try:
        transport, _ = await loop.create_datagram_endpoint(
            asyncio.DatagramProtocol,
            remote_addr=(target_ip, port)
        )
        test_msg = b"<187>Sep 22 15:40:00 switch-core-01 %LINK-3-UPDOWN: Interface GigabitEthernet0/5, changed state to down"
        transport.sendto(test_msg)
        await asyncio.sleep(0.3)
        transport.close()
        logger.info("Mensaje syslog de prueba enviado con éxito.")
    except Exception as e:
        logger.error(f"Error enviando mensaje syslog sintético: {e}")


def search_syslog(query: Optional[str] = None, device_ip: Optional[str] = None, limit: int = 15):
    """Consulta eventos recientes en MariaDB."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            sql = "SELECT id, source_ip, hostname, severity, program, message, received_at FROM syslog_events WHERE 1=1"
            params = []
            if device_ip:
                sql += " AND source_ip = %s"
                params.append(device_ip)
            if query:
                sql += " AND (message LIKE %s OR program LIKE %s OR hostname LIKE %s)"
                term = f"%{query}%"
                params.extend([term, term, term])
            sql += " ORDER BY id DESC LIMIT %s"
            params.append(limit)

            cur.execute(sql, tuple(params))
            rows = cur.fetchall()

            print(f"\n📋 RESULTADOS DE SYSLOG ({len(rows)} eventos encontrados):")
            print("=" * 75)
            for r in rows:
                sev_str = SEVERITY_NAMES.get(r['severity'], str(r['severity'])).upper()
                print(f"[{r['received_at']}] [{sev_str}] {r['source_ip']} ({r['program']}): {r['message']}")
            print("=" * 75 + "\n")
    finally:
        conn.close()


def main():
    parser = argparse.ArgumentParser(description="Receptor Centralizado de Syslog (Fase 6)")
    parser.add_argument("--port", type=int, default=DEFAULT_SYSLOG_PORT, help="Puerto UDP para escuchar (por defecto 514)")
    parser.add_argument("--test-packet", action="store_true", help="Enviar un paquete de syslog sintético para prueba")
    parser.add_argument("--search", type=str, help="Buscar en mensajes recientes de syslog")
    parser.add_argument("--device", type=str, help="Filtrar eventos por IP del dispositivo emisor")
    parser.add_argument("--listen", action="store_true", help="Ejecutar receptor en bucle principal continuo")
    args = parser.parse_args()

    if args.test_packet:
        asyncio.run(send_test_syslog(port=args.port))
        return

    if args.search or args.device:
        search_syslog(query=args.search, device_ip=args.device)
        return

    async def runner():
        transport, _ = await run_syslog_receiver(port=args.port)
        logger.info("📋 Demonio de Syslog escuchando activamente. Presione Ctrl+C para salir.")
        try:
            while True:
                await asyncio.sleep(3600)
        except (KeyboardInterrupt, asyncio.CancelledError):
            logger.info("Deteniendo receptor Syslog...")
            transport.close()

    asyncio.run(runner())


if __name__ == "__main__":
    main()
