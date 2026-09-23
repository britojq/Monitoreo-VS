#!/usr/bin/env python3
# ==============================================================================
# 🛰️ DEMONIO MAESTRO DE TELEMETRÍA PUSH: push_telemetry_daemon.py (@IA_ValleSeco_bot)
# Orquestador unificado de captura SNMP Traps (162), Syslog (514) y NetFlow (2055)
# Ubicación: /scripts/telegram-admin-bot/monitor/push_telemetry_daemon.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

from __future__ import annotations

import argparse
import asyncio
import json
import logging
import os
import signal
import sys
import time
from pathlib import Path
from typing import Optional

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.snmp_trap_receiver import run_trap_receiver, DEFAULT_TRAP_PORT
from monitor.syslog_receiver import run_syslog_receiver, DEFAULT_SYSLOG_PORT
from monitor.netflow_collector import run_netflow_collector, DEFAULT_NETFLOW_PORT, GLOBAL_BUFFER

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = LOG_DIR / "push_telemetry_daemon.log"
STATUS_FILE = Path("/dev/shm/push_telemetry_daemon.status")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [push.daemon] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("push.daemon")


def write_status(running: bool, ports: dict, error: Optional[str] = None):
    """Escribe el estado operativo del demonio en memoria volátil (/dev/shm)."""
    status_data = {
        "running": running,
        "pid": os.getpid() if running else None,
        "updated_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "uptime_seconds": round(time.time() - getattr(write_status, "start_time", time.time()), 1),
        "ports": ports,
        "error": error
    }
    try:
        STATUS_FILE.write_text(json.dumps(status_data, indent=2), encoding="utf-8")
    except Exception as e:
        logger.warning(f"No se pudo escribir estado en {STATUS_FILE}: {e}")


write_status.start_time = time.time()


async def heartbeat_loop(ports: dict):
    """Actualiza periódicamente el archivo de status con el latido del demonio."""
    while True:
        try:
            write_status(running=True, ports=ports)
            await asyncio.sleep(15)
        except asyncio.CancelledError:
            break
        except Exception as e:
            logger.error(f"Error en bucle de heartbeat: {e}")


async def main_supervisor(trap_port: int, syslog_port: int, netflow_port: int):
    """Supervisa e inicia concurrentemente los 3 receptores push."""
    logger.info("=" * 65)
    logger.info("🚀 INICIANDO DEMONIO MAESTRO DE TELEMETRÍA PUSH (FASE 6)")
    logger.info(f"• SNMP Traps:   UDP {trap_port}")
    logger.info(f"• Syslog Live:  UDP {syslog_port}")
    logger.info(f"• NetFlow v5:   UDP {netflow_port}")
    logger.info("=" * 65)

    ports_config = {
        "snmp_traps": trap_port,
        "syslog": syslog_port,
        "netflow": netflow_port,
    }

    # 1. Iniciar SNMP Trap Receiver
    try:
        snmp_engine, snmp_transport = await run_trap_receiver(port=trap_port)
    except Exception as e_trap:
        logger.error(f"Fallo al iniciar receptor de SNMP Traps: {e_trap}")
        snmp_transport = None

    # 2. Iniciar Syslog Receiver
    try:
        syslog_transport, syslog_proto = await run_syslog_receiver(port=syslog_port)
    except Exception as e_syslog:
        logger.error(f"Fallo al iniciar receptor de Syslog: {e_syslog}")
        syslog_transport = None

    # 3. Iniciar NetFlow Collector
    try:
        netflow_transport, flusher_task = await run_netflow_collector(port=netflow_port)
    except Exception as e_netflow:
        logger.error(f"Fallo al iniciar colector NetFlow: {e_netflow}")
        netflow_transport = None
        flusher_task = None

    # Registrar heartbeat
    write_status(running=True, ports=ports_config)
    heartbeat_task = asyncio.create_task(heartbeat_loop(ports_config))

    logger.info("✅ Todos los servicios de telemetría push están operando activamente.")

    stop_event = asyncio.Event()

    def stop_signal_handler():
        logger.info("🛑 Señal de terminación recibida. Cerrando conexiones push...")
        stop_event.set()

    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        try:
            loop.add_signal_handler(sig, stop_signal_handler)
        except NotImplementedError:
            pass

    try:
        await stop_event.wait()
    finally:
        logger.info("Realizando apagado limpio de servicios...")
        heartbeat_task.cancel()
        if snmp_transport:
            try:
                snmp_transport.close_transport()
            except Exception:
                pass
        if syslog_transport:
            try:
                syslog_transport.close()
            except Exception:
                pass
        if netflow_transport:
            try:
                netflow_transport.close()
            except Exception:
                pass
        if flusher_task:
            flusher_task.cancel()

        # Volcado final de flujos restantes
        try:
            await GLOBAL_BUFFER.flush_to_database()
        except Exception:
            pass

        write_status(running=False, ports=ports_config)
        logger.info("🏁 Demonio de telemetría push detenido correctamente.")


def print_status():
    """Consulta y muestra el estado actual del demonio de telemetría push."""
    if not STATUS_FILE.exists():
        print("\n❌ El demonio de telemetría push no está en ejecución (sin archivo de estado).\n")
        return

    try:
        data = json.loads(STATUS_FILE.read_text(encoding="utf-8"))
        is_running = data.get("running", False)
        pid = data.get("pid", "N/A")
        uptime = data.get("uptime_seconds", 0)
        ports = data.get("ports", {})
        updated = data.get("updated_at", "N/A")

        status_str = "🟢 ACTIVO Y ESCUCHANDO" if is_running else "🔴 DETENIDO"

        print("\n" + "=" * 60)
        print("🛰️ ESTADO DEL DEMONIO DE TELEMETRÍA PUSH (FASE 6)")
        print("=" * 60)
        print(f"• Estado:           {status_str}")
        print(f"• PID:              {pid}")
        print(f"• Uptime:           {uptime} segundos")
        print(f"• Último Latido:    {updated}")
        print("• Puertos UDP:")
        print(f"   - SNMP Traps:    {ports.get('snmp_traps', 'N/A')}")
        print(f"   - Syslog Live:   {ports.get('syslog', 'N/A')}")
        print(f"   - NetFlow v5:    {ports.get('netflow', 'N/A')}")
        print("=" * 60 + "\n")
    except Exception as e:
        print(f"\nError leyendo estado: {e}\n")


def main():
    parser = argparse.ArgumentParser(description="Demonio Maestro de Telemetría Push (Fase 6)")
    parser.add_argument("--trap-port", type=int, default=DEFAULT_TRAP_PORT, help="Puerto UDP para SNMP Traps")
    parser.add_argument("--syslog-port", type=int, default=DEFAULT_SYSLOG_PORT, help="Puerto UDP para Syslog")
    parser.add_argument("--netflow-port", type=int, default=DEFAULT_NETFLOW_PORT, help="Puerto UDP para NetFlow")
    parser.add_argument("--status", action="store_true", help="Consultar estado de ejecución del demonio")
    args = parser.parse_args()

    if args.status:
        print_status()
        return

    asyncio.run(main_supervisor(
        trap_port=args.trap_port,
        syslog_port=args.syslog_port,
        netflow_port=args.netflow_port
    ))


if __name__ == "__main__":
    main()
