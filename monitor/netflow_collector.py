#!/usr/bin/env python3
# ==============================================================================
# 🌊 RECEPTOR Y COLECTOR NETFLOW: netflow_collector.py (@IA_ValleSeco_bot)
# Captura de flujos NetFlow v5 en UDP 2055, agregación por minuto y Top Talkers
# Ubicación: /scripts/telegram-admin-bot/monitor/netflow_collector.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

from __future__ import annotations

import argparse
import asyncio
import logging
import os
import socket
import struct
import sys
import time
from collections import defaultdict
from datetime import datetime, timedelta
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

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = LOG_DIR / "netflow_collector.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [netflow] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("netflow")

DEFAULT_NETFLOW_PORT = 2055

# Formatos de empaquetado binario para NetFlow v5
NETFLOW_V5_HEADER_FMT = "!HHIIIIBBH"
NETFLOW_V5_HEADER_SIZE = struct.calcsize(NETFLOW_V5_HEADER_FMT)  # 24 bytes

NETFLOW_V5_RECORD_FMT = "!IIIHHIIIIHHBBBBHHBBH"
NETFLOW_V5_RECORD_SIZE = struct.calcsize(NETFLOW_V5_RECORD_FMT)  # 48 bytes


class FlowBuffer:
    """Buffer en memoria con agregación de flujos por ventana de 1 minuto."""

    def __init__(self):
        self.lock = asyncio.Lock()
        # Clave: (exporter_ip, src_ip, dst_ip, src_port, dst_port, protocol, window_start_str, window_end_str)
        # Valor: [bytes, packets]
        self.aggregated: Dict[Tuple, List[int]] = defaultdict(lambda: [0, 0])
        self.last_flush_time = time.time()
        self.last_top_talkers_time = time.time()

    async def add_flow(
        self,
        exporter_ip: str,
        src_ip: str,
        dst_ip: str,
        src_port: int,
        dst_port: int,
        protocol: int,
        byte_count: int,
        packet_count: int,
        timestamp: float
    ):
        # Calcular límites de la ventana de 1 minuto
        dt = datetime.fromtimestamp(timestamp)
        window_start = dt.replace(second=0, microsecond=0)
        window_end = window_start + timedelta(minutes=1)

        key = (
            exporter_ip,
            src_ip,
            dst_ip,
            src_port,
            dst_port,
            protocol,
            window_start.strftime("%Y-%m-%d %H:%M:%S"),
            window_end.strftime("%Y-%m-%d %H:%M:%S")
        )

        async with self.lock:
            self.aggregated[key][0] += byte_count
            self.aggregated[key][1] += packet_count

    async def flush_to_database(self) -> int:
        """Vuelca los flujos agregados a la tabla netflow_records en lote."""
        async with self.lock:
            if not self.aggregated:
                return 0
            items_to_save = list(self.aggregated.items())
            self.aggregated.clear()
            self.last_flush_time = time.time()

        records_data = []
        for key, counts in items_to_save:
            exporter_ip, src_ip, dst_ip, src_port, dst_port, protocol, w_start, w_end = key
            bytes_cnt, pkts_cnt = counts
            records_data.append((
                exporter_ip,
                src_ip,
                dst_ip,
                src_port,
                dst_port,
                protocol,
                bytes_cnt,
                pkts_cnt,
                w_start,
                w_end
            ))

        if not records_data:
            return 0

        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.executemany(
                    """
                    INSERT INTO netflow_records
                    (exporter_ip, src_ip, dst_ip, src_port, dst_port, protocol, bytes, packets, window_start, window_end)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """,
                    records_data
                )
                conn.commit()
            logger.info(f"💾 Guardados {len(records_data)} registros agregados de NetFlow en base de datos.")
            return len(records_data)
        except Exception as e:
            logger.error(f"Error volcando registros NetFlow a BD: {e}")
            return 0
        finally:
            conn.close()

    async def compute_top_talkers(self, window_minutes: int = 5):
        """Calcula y materializa los Top Talkers en la tabla netflow_top_talkers."""
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                # Determinar ventana reciente
                now = datetime.now()
                window_end = now.replace(second=0, microsecond=0)
                window_start = window_end - timedelta(minutes=window_minutes)
                w_start_str = window_start.strftime("%Y-%m-%d %H:%M:%S")
                w_end_str = window_end.strftime("%Y-%m-%d %H:%M:%S")

                # Calcular total bytes en la ventana
                cur.execute(
                    """
                    SELECT SUM(bytes) as total_bytes, SUM(packets) as total_packets
                    FROM netflow_records
                    WHERE window_start >= %s AND window_end <= %s
                    """,
                    (w_start_str, w_end_str)
                )
                total_row = cur.fetchone()
                total_bytes = int(total_row["total_bytes"] or 0)
                total_packets = int(total_row["total_packets"] or 0)

                if total_bytes <= 0:
                    return

                # 1. Top Talkers por src_ip
                cur.execute(
                    """
                    SELECT src_ip as val, SUM(bytes) as b, SUM(packets) as p
                    FROM netflow_records
                    WHERE window_start >= %s AND window_end <= %s
                    GROUP BY src_ip
                    ORDER BY b DESC
                    LIMIT 10
                    """,
                    (w_start_str, w_end_str)
                )
                top_sources = cur.fetchall()

                # 2. Top Talkers por dst_ip
                cur.execute(
                    """
                    SELECT dst_ip as val, SUM(bytes) as b, SUM(packets) as p
                    FROM netflow_records
                    WHERE window_start >= %s AND window_end <= %s
                    GROUP BY dst_ip
                    ORDER BY b DESC
                    LIMIT 10
                    """,
                    (w_start_str, w_end_str)
                )
                top_destinations = cur.fetchall()

                # 3. Top por protocolo
                cur.execute(
                    """
                    SELECT protocol as val, SUM(bytes) as b, SUM(packets) as p
                    FROM netflow_records
                    WHERE window_start >= %s AND window_end <= %s
                    GROUP BY protocol
                    ORDER BY b DESC
                    LIMIT 5
                    """,
                    (w_start_str, w_end_str)
                )
                top_protocols = cur.fetchall()

                # Insertar en netflow_top_talkers
                inserts = []
                for row in top_sources:
                    pct = round((float(row["b"]) / total_bytes) * 100, 2)
                    inserts.append((w_start_str, w_end_str, 'src_ip', str(row["val"]), row["b"], row["p"], pct))

                for row in top_destinations:
                    pct = round((float(row["b"]) / total_bytes) * 100, 2)
                    inserts.append((w_start_str, w_end_str, 'dst_ip', str(row["val"]), row["b"], row["p"], pct))

                for row in top_protocols:
                    pct = round((float(row["b"]) / total_bytes) * 100, 2)
                    proto_id = int(row["val"] or 0)
                    proto_label = {6: "TCP", 17: "UDP", 1: "ICMP", 47: "GRE", 50: "ESP", 89: "OSPF"}.get(proto_id, f"Proto({proto_id})")
                    inserts.append((w_start_str, w_end_str, 'protocol', proto_label, row["b"], row["p"], pct))

                if inserts:
                    cur.executemany(
                        """
                        INSERT INTO netflow_top_talkers
                        (window_start, window_end, rank_type, rank_value, bytes, packets, percentage)
                        VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """,
                        inserts
                    )
                    conn.commit()
                    logger.info(f"📊 Materializados {len(inserts)} registros de Top Talkers ({w_start_str} - {w_end_str}).")
        except Exception as e:
            logger.error(f"Error calculando Top Talkers: {e}")
        finally:
            conn.close()


GLOBAL_BUFFER = FlowBuffer()


class NetflowProtocol(asyncio.DatagramProtocol):
    """Protocolo asíncrono para decodificación y recepción de NetFlow v5."""

    def __init__(self, buffer: FlowBuffer):
        self.buffer = buffer
        self.transport = None

    def connection_made(self, transport: asyncio.DatagramTransport):
        self.transport = transport

    def datagram_received(self, data: bytes, addr: Tuple[str, int]):
        exporter_ip = addr[0]
        if len(data) < NETFLOW_V5_HEADER_SIZE:
            logger.debug(f"Paquete NetFlow descartado por tamaño insuficiente ({len(data)} bytes) desde {exporter_ip}")
            return

        try:
            # Desempaquetar encabezado v5
            version, count, sys_uptime, unix_secs, unix_nsecs, flow_seq, engine_type, engine_id, sampling = \
                struct.unpack_from(NETFLOW_V5_HEADER_FMT, data, 0)

            if version != 5:
                logger.debug(f"Versión de NetFlow no soportada ({version}) desde {exporter_ip}")
                return

            offset = NETFLOW_V5_HEADER_SIZE
            timestamp = float(unix_secs) if unix_secs > 0 else time.time()

            for _ in range(count):
                if offset + NETFLOW_V5_RECORD_SIZE > len(data):
                    break

                record = struct.unpack_from(NETFLOW_V5_RECORD_FMT, data, offset)
                offset += NETFLOW_V5_RECORD_SIZE

                src_ip_raw, dst_ip_raw, nexthop_raw, inp, outp, pkts, octets, first, last, srcport, dstport, \
                    pad1, tcp_flags, prot, tos, src_as, dst_as, smask, dmask, pad2 = record

                src_ip = socket.inet_ntoa(struct.pack("!I", src_ip_raw))
                dst_ip = socket.inet_ntoa(struct.pack("!I", dst_ip_raw))

                asyncio.create_task(
                    self.buffer.add_flow(
                        exporter_ip=exporter_ip,
                        src_ip=src_ip,
                        dst_ip=dst_ip,
                        src_port=srcport,
                        dst_port=dstport,
                        protocol=prot,
                        byte_count=octets,
                        packet_count=pkts,
                        timestamp=timestamp
                    )
                )

            logger.debug(f"Procesados {count} flujos de NetFlow v5 desde {exporter_ip}")
        except Exception as e:
            logger.error(f"Error decodificando paquete NetFlow desde {exporter_ip}: {e}")


async def periodic_buffer_flusher(buffer: FlowBuffer):
    """Tarea periódica de volcado a MariaDB cada 60 segundos y cálculo de Top Talkers cada 5 minutos."""
    while True:
        try:
            await asyncio.sleep(60)
            await buffer.flush_to_database()

            # Calcular Top Talkers cada 5 minutos (300 seg)
            if time.time() - buffer.last_top_talkers_time >= 300:
                buffer.last_top_talkers_time = time.time()
                await buffer.compute_top_talkers(window_minutes=5)
        except asyncio.CancelledError:
            break
        except Exception as e:
            logger.error(f"Error en tarea de volcado periódico: {e}")


async def run_netflow_collector(
    port: int = DEFAULT_NETFLOW_PORT,
    buffer: Optional[FlowBuffer] = None
) -> Tuple[asyncio.DatagramTransport, asyncio.Task]:
    """Inicia el recolector de NetFlow en bucle asíncrono."""
    buf = buffer or GLOBAL_BUFFER
    loop = asyncio.get_running_loop()
    transport, protocol = await loop.create_datagram_endpoint(
        lambda: NetflowProtocol(buf),
        local_addr=("0.0.0.0", port)
    )
    logger.info(f"🌊 Recolector NetFlow v5 vinculado en 0.0.0.0:{port} (UDP)")
    flusher_task = asyncio.create_task(periodic_buffer_flusher(buf))
    return transport, flusher_task


async def send_test_netflow(target_ip: str = "127.0.0.1", port: int = DEFAULT_NETFLOW_PORT):
    """Envía paquetes sintéticos de NetFlow v5 para validar captura y agregación."""
    loop = asyncio.get_running_loop()
    logger.info(f"Enviando paquetes sintéticos de NetFlow v5 a {target_ip}:{port}...")

    transport, _ = await loop.create_datagram_endpoint(
        asyncio.DatagramProtocol,
        remote_addr=(target_ip, port)
    )

    now_secs = int(time.time())
    hdr = struct.pack(NETFLOW_V5_HEADER_FMT, 5, 2, 500000, now_secs, 0, 1, 0, 0, 0)

    # Flujo 1: Tráfico HTTPS a Google DNS
    rec1 = struct.pack(
        NETFLOW_V5_RECORD_FMT,
        int(socket.inet_aton("10.20.23.50").hex(), 16),
        int(socket.inet_aton("8.8.8.8").hex(), 16),
        int(socket.inet_aton("10.20.23.1").hex(), 16),
        1, 2, 15, 24500, 490000, 500000,
        51234, 443, 0, 16, 6, 0, 0, 0, 24, 24, 0
    )

    # Flujo 2: DNS queries
    rec2 = struct.pack(
        NETFLOW_V5_RECORD_FMT,
        int(socket.inet_aton("10.20.23.88").hex(), 16),
        int(socket.inet_aton("1.1.1.1").hex(), 16),
        int(socket.inet_aton("10.20.23.1").hex(), 16),
        1, 2, 4, 380, 495000, 500000,
        61111, 53, 0, 0, 17, 0, 0, 0, 24, 24, 0
    )

    packet = hdr + rec1 + rec2
    transport.sendto(packet)
    await asyncio.sleep(0.3)
    transport.close()
    logger.info("Paquete NetFlow sintético enviado con 2 flujos exitosamente.")


def show_top_talkers(rank_type: str = "src_ip"):
    """Muestra en consola los últimos Top Talkers guardados."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT window_start, window_end, rank_type, rank_value, bytes, packets, percentage
                FROM netflow_top_talkers
                WHERE rank_type = %s
                ORDER BY id DESC
                LIMIT 10
                """,
                (rank_type,)
            )
            rows = cur.fetchall()

            print(f"\n📊 TOP TALKERS NETFLOW (Tipo: {rank_type}):")
            print("=" * 70)
            if not rows:
                print("No hay registros disponibles de Top Talkers aún.")
            for r in rows:
                bytes_mb = round(r["bytes"] / 1048576, 2)
                print(f"• {r['rank_value']:<25} | {bytes_mb:>8} MB ({r['percentage']}%) | {r['packets']} paquetes")
            print("=" * 70 + "\n")
    finally:
        conn.close()


def main():
    parser = argparse.ArgumentParser(description="Colector Asíncrono de NetFlow v5 (Fase 6)")
    parser.add_argument("--port", type=int, default=DEFAULT_NETFLOW_PORT, help="Puerto UDP para escuchar (por defecto 2055)")
    parser.add_argument("--test-flow", action="store_true", help="Enviar paquetes sintéticos de NetFlow v5 para pruebas")
    parser.add_argument("--top", choices=["src_ip", "dst_ip", "protocol"], default=None, help="Consultar los últimos Top Talkers")
    parser.add_argument("--listen", action="store_true", help="Ejecutar recolector en bucle principal continuo")
    args = parser.parse_args()

    if args.test_flow:
        asyncio.run(send_test_netflow(port=args.port))
        return

    if args.top:
        show_top_talkers(rank_type=args.top)
        return

    async def runner():
        transport, flusher = await run_netflow_collector(port=args.port)
        logger.info("🌊 Demonio NetFlow escuchando activamente. Presione Ctrl+C para salir.")
        try:
            while True:
                await asyncio.sleep(3600)
        except (KeyboardInterrupt, asyncio.CancelledError):
            logger.info("Deteniendo recolector NetFlow...")
            flusher.cancel()
            transport.close()
            # Guardar remanentes
            await GLOBAL_BUFFER.flush_to_database()

    asyncio.run(runner())


if __name__ == "__main__":
    main()
