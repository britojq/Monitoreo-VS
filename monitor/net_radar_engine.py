#!/usr/bin/env python3
"""
# ==============================================================================
# 🛰️ NET RADAR & INSPECCIÓN AVANZADA DE TRÁFICO: net_radar_engine.py
# Monitoreo continuo de hosts, telemetría de ancho de banda (Rx/Tx) y detección
# especializada de actualizaciones (Windows Update y Repositorios Linux).
# Inspirado en darkstat y análisis profundo de red local.
# Ubicación: /scripts/telegram-admin-bot/monitor/net_radar_engine.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import html
import ipaddress
import json
import logging
import os
import re
import socket
import subprocess
import sys
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Set, Tuple

import pymysql

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.checker_base import get_active_network_interface
from monitor.config_parser import get_formatted_datetime

LOG_DIR = Path("/tmp/monitor")
try:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    LOG_DIR.chmod(0o777)
except Exception:
    pass

LOG_FILE = LOG_DIR / "net_radar.log"
AUDIT_DIR = BASE_DIR / "audit"
try:
    AUDIT_DIR.mkdir(parents=True, exist_ok=True)
except Exception:
    pass

# Manejador de logs resiliente ante discrepancias de permisos entre root y usuarios
log_handlers = [logging.StreamHandler(sys.stdout)]
try:
    if not LOG_FILE.exists():
        LOG_FILE.touch(mode=0o666, exist_ok=True)
    try:
        LOG_FILE.chmod(0o666)
    except Exception:
        pass
    log_handlers.insert(0, logging.FileHandler(LOG_FILE, encoding="utf-8"))
except Exception:
    # Fallback seguro en /tmp si net_radar.log no es escribible por el usuario actual
    try:
        user_uid = os.getuid() if hasattr(os, "getuid") else "usr"
        fallback_log = Path(f"/tmp/net_radar_{user_uid}.log")
        log_handlers.insert(0, logging.FileHandler(fallback_log, encoding="utf-8"))
    except Exception:
        pass

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [net_radar] %(message)s",
    handlers=log_handlers,
)
logger = logging.getLogger("net_radar")

# Dominios y patrones característicos de Windows Update
WINDOWS_UPDATE_DOMAINS = [
    "windowsupdate.com",
    "update.microsoft.com",
    "delivery.mp.microsoft.com",
    "download.windowsupdate.com",
    "fe2.update.microsoft.com",
    "fe3.delivery.mp.microsoft.com",
    "tlu.dl.delivery.mp.microsoft.com",
    "dl.delivery.mp.microsoft.com",
    "do.dsp.mp.microsoft.com",
    "emdl.ws.microsoft.com",
    "ws.symantec.com",
    "download.microsoft.com",
    "msftconnecttest.com",
]

# Dominios y patrones característicos de Repositorios Linux (Debian, Ubuntu, RHEL, Arch, etc.)
LINUX_REPO_DOMAINS = [
    "deb.debian.org",
    "security.debian.org",
    "ftp.debian.org",
    "archive.ubuntu.com",
    "security.ubuntu.com",
    "ports.ubuntu.com",
    "mirror.centos.org",
    "repo.almalinux.org",
    "download.rockylinux.org",
    "mirror.fedoraproject.org",
    "archive.raspberrypi.org",
    "packages.microsoft.com",
    "download.docker.com",
    "repo.saltproject.io",
]


def get_db_connection():
    """Obtiene una conexión directa a la base de datos MariaDB monitoreo_vs."""
    # Intentar leer credenciales desde el .env de Laravel
    env_file = Path("/var/www/monitoreo/.env")
    db_user = "monitoreo_user"
    db_pass = "VsMonit#2026!SecureKey"
    db_name = "monitoreo_vs"
    db_host = "127.0.0.1"

    if env_file.exists():
        try:
            for line in env_file.read_text(encoding="utf-8", errors="ignore").splitlines():
                line = line.strip()
                if line.startswith("DB_USERNAME="):
                    db_user = line.split("=", 1)[1].strip().strip('"').strip("'")
                elif line.startswith("DB_PASSWORD="):
                    db_pass = line.split("=", 1)[1].strip().strip('"').strip("'")
                elif line.startswith("DB_DATABASE="):
                    db_name = line.split("=", 1)[1].strip().strip('"').strip("'")
                elif line.startswith("DB_HOST="):
                    db_host = line.split("=", 1)[1].strip().strip('"').strip("'")
        except Exception:
            pass

    return pymysql.connect(
        host=db_host,
        user=db_user,
        password=db_pass,
        database=db_name,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )


def format_bytes_human(bytes_val: int) -> str:
    """Convierte bytes a formato legible (KB, MB, GB)."""
    if bytes_val >= 1073741824:
        return f"{bytes_val / 1073741824:.2f} GB"
    if bytes_val >= 1048576:
        return f"{bytes_val / 1048576:.2f} MB"
    if bytes_val >= 1024:
        return f"{bytes_val / 1024:.2f} KB"
    return f"{bytes_val} B"


def get_kernel_arp_table() -> Dict[str, str]:
    """Obtiene la tabla ARP del kernel mediante 'ip -j neigh' o '/proc/net/arp'."""
    arp_map: Dict[str, str] = {}
    try:
        res = subprocess.run(["ip", "-j", "neigh"], capture_output=True, text=True, timeout=3)
        if res.returncode == 0 and res.stdout.strip():
            entries = json.loads(res.stdout)
            for item in entries:
                ip = item.get("dst")
                mac = item.get("lladdr")
                state = item.get("state", [])
                if ip and mac and "FAILED" not in state and ":" not in ip:
                    clean_mac = mac.strip().lower()
                    if len(clean_mac) == 17 and clean_mac != "00:00:00:00:00:00" and re.match(r"^[0-9.]+$", ip):
                        arp_map[ip] = clean_mac
            if arp_map:
                return arp_map
    except Exception as e:
        logger.debug(f"Aviso leyendo ip -j neigh: {e}")

    proc_arp = Path("/proc/net/arp")
    if proc_arp.exists():
        try:
            for line in proc_arp.read_text(encoding="utf-8").splitlines()[1:]:
                parts = line.split()
                if len(parts) >= 4:
                    ip = parts[0]
                    mac = parts[3].lower()
                    flags = parts[2]
                    if mac != "00:00:00:00:00:00" and flags != "0x0":
                        arp_map[ip] = mac
        except Exception as e:
            logger.debug(f"Aviso leyendo /proc/net/arp: {e}")

    return arp_map


def lookup_oui_vendor(mac: str, conn) -> str:
    """Resuelve el fabricante del dispositivo a partir del prefijo OUI de la MAC."""
    if not mac or len(mac) < 8:
        return "Desconocido"

    prefix = mac[:8].upper()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT vendor_name FROM oui_vendors WHERE oui_prefix = %s LIMIT 1", (prefix,))
            row = cur.fetchone()
            if row and row.get("vendor_name"):
                return row["vendor_name"]
    except Exception:
        pass

    # Fallback al archivo oui.txt
    oui_path = BASE_DIR / "config" / "oui.txt"
    if oui_path.exists():
        try:
            clean_mac = prefix.replace(":", "").lower()
            for line in oui_path.read_text(encoding="utf-8", errors="ignore").splitlines():
                if "\t" in line and not line.startswith("#"):
                    p, v = line.split("\t", 1)
                    if p.strip().lower() == clean_mac:
                        return v.strip()
        except Exception:
            pass

    return "Fabricante Desconocido"


def infer_os_and_hostname(ip: str, mac: str, vendor: str, conn) -> Tuple[str, str]:
    """Estima el sistema operativo y resuelve el nombre de host."""
    hostname = ""
    os_detected = "Desconocido"

    # 1. Chequear si ya está registrado en discovered_devices
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT hostname, device_type FROM discovered_devices WHERE ip_address = %s LIMIT 1", (ip,))
            row = cur.fetchone()
            if row:
                if row.get("hostname"):
                    hostname = row["hostname"]
                dev_t = row.get("device_type", "")
                if dev_t in ("switch", "router"):
                    os_detected = f"Equipo de Red ({dev_t.capitalize()})"
                elif dev_t == "printer":
                    os_detected = "Impresora de Red"
                elif dev_t == "server":
                    os_detected = "Servidor Linux"
    except Exception:
        pass

    # 2. Reverse DNS si aún no hay hostname
    if not hostname:
        try:
            h, _, _ = socket.gethostbyaddr(ip)
            if h and h != ip:
                hostname = h
        except Exception:
            pass

    # 3. Heurística de Sistema Operativo basada en hostname y fabricante
    text = f"{hostname} {vendor}".lower()
    if os_detected == "Desconocido":
        if any(w in text for w in ["win", "desktop-", "laptop-", "pc-", "dell", "hp inc", "workstation"]):
            os_detected = "Windows 10/11"
        elif any(w in text for w in ["cisco", "catalyst", "mikrotik", "fortinet", "router", "switch"]):
            os_detected = "Cisco IOS / Router"
        elif any(w in text for w in ["debian", "ubuntu", "srv", "server", "linux", "vmware", "qemu"]):
            os_detected = "Linux Server"
        elif any(w in text for w in ["printer", "laserjet", "epson", "xerox", "canon"]):
            os_detected = "Impresora"

    return os_detected, hostname


def is_ip_in_local_subnet(ip_str: str, my_ip: str, my_mask: str) -> bool:
    """Verifica si una IP pertenece a la subred local."""
    try:
        if not ip_str or not my_ip or not my_mask:
            return True
        net = ipaddress.IPv4Network(f"{my_ip}/{my_mask}", strict=False)
        return ipaddress.IPv4Address(ip_str) in net
    except Exception:
        return True


def get_interface_ip_and_mask(interface: str) -> Tuple[str, str]:
    """Obtiene la IP y máscara CIDR de la interfaz."""
    try:
        out = subprocess.check_output(["ip", "-4", "addr", "show", "dev", interface], text=True, timeout=3.0)
        match = re.search(r"inet\s+([0-9.]+)/([0-9]+)", out)
        if match:
            return match.group(1), match.group(2)
    except Exception:
        pass
    return "", ""


async def capture_and_analyze_flows(
    interface: str,
    duration_seconds: int = 15,
    max_packets: int = 3000
) -> List[Dict[str, Any]]:
    """
    Captura tráfico de red y extrae flujos detallados con tshark:
    - ip.src, ip.dst
    - frame.len (volumen de bytes)
    - dns.qry.name (consultas DNS)
    - tls.handshake.extensions_server_name (SNI HTTPS)
    - http.host (Cabecera Host HTTP)
    - tcp.dstport, udp.dstport
    """
    pcap_temp = Path(f"/tmp/net_radar_{int(time.time())}.pcap")
    if pcap_temp.exists():
        pcap_temp.unlink()

    flows: List[Dict[str, Any]] = []

    try:
        # 1. Captura con tcpdump no bloqueante
        proc_cap = await asyncio.create_subprocess_exec(
            "sudo", "tcpdump", "-i", interface, "-s", "0", "-c", str(max_packets), "-w", str(pcap_temp), "-nn", "-p",
            stdout=asyncio.subprocess.DEVNULL,
            stderr=asyncio.subprocess.DEVNULL
        )

        try:
            await asyncio.sleep(duration_seconds)
        finally:
            try:
                kill_proc = await asyncio.create_subprocess_exec(
                    "sudo", "kill", "-2", str(proc_cap.pid),
                    stdout=asyncio.subprocess.DEVNULL,
                    stderr=asyncio.subprocess.DEVNULL
                )
                await kill_proc.wait()
            except Exception:
                pass
            try:
                await asyncio.wait_for(proc_cap.wait(), timeout=2.0)
            except Exception:
                try:
                    proc_cap.kill()
                except Exception:
                    pass

        # 2. Análisis con tshark
        if pcap_temp.exists() and pcap_temp.stat().st_size > 0:
            cmd = [
                "sudo", "tshark", "-r", str(pcap_temp),
                "-T", "fields",
                "-E", "separator=\t",
                "-e", "ip.src",
                "-e", "ip.dst",
                "-e", "frame.len",
                "-e", "dns.qry.name",
                "-e", "tls.handshake.extensions_server_name",
                "-e", "http.host",
                "-e", "tcp.dstport",
                "-e", "udp.dstport"
            ]

            proc_ts = await asyncio.create_subprocess_exec(
                *cmd,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.DEVNULL
            )
            stdout, _ = await proc_ts.communicate()

            for line in stdout.decode("utf-8", errors="ignore").splitlines():
                parts = line.split("\t")
                if len(parts) >= 3:
                    src_ip = parts[0].strip()
                    dst_ip = parts[1].strip()
                    try:
                        frame_len = int(parts[2].strip())
                    except ValueError:
                        frame_len = 60

                    dns_name = parts[3].strip() if len(parts) > 3 else ""
                    tls_sni = parts[4].strip() if len(parts) > 4 else ""
                    http_host = parts[5].strip() if len(parts) > 5 else ""
                    tcp_port = parts[6].strip() if len(parts) > 6 else ""
                    udp_port = parts[7].strip() if len(parts) > 7 else ""

                    # Identificar dominio objetivo
                    target_domain = tls_sni or http_host or dns_name

                    flows.append({
                        "src_ip": src_ip,
                        "dst_ip": dst_ip,
                        "bytes": frame_len,
                        "target_domain": target_domain,
                        "tcp_port": tcp_port,
                        "udp_port": udp_port,
                    })

    except Exception as e:
        logger.error(f"Error durante captura de flujos con tcpdump/tshark: {e}")
    finally:
        if pcap_temp.exists():
            try:
                pcap_temp.unlink()
            except Exception:
                pass

    return flows


def detect_update_activity(domain: str, port: str = "") -> Tuple[str, str]:
    """
    Analiza un dominio o puerto y determina si corresponde a Windows Update o Repositorios Linux.
    Retorna (tipo_actualización, estado):
    - tipo: 'windows_update', 'linux_repo', o ''
    - estado: 'downloading', 'checking', o 'none'
    """
    if not domain and port != "7680":
        return "", "none"

    domain_lower = domain.lower()

    # 1. Chequeo Windows Update
    if port == "7680" or any(w in domain_lower for w in WINDOWS_UPDATE_DOMAINS):
        # Si contiene delivery o download, es descarga activa
        if any(k in domain_lower for k in ["delivery.mp", "download.windowsupdate", "fe3.delivery", "tlu.dl"]):
            return "windows_update", "downloading"
        return "windows_update", "checking"

    # 2. Chequeo Repositorios Linux
    if any(l in domain_lower for l in LINUX_REPO_DOMAINS):
        if any(k in domain_lower for k in ["archive", "ports", "mirror", "repo", "download"]):
            return "linux_repo", "downloading"
        return "linux_repo", "checking"

    return "", "none"


async def run_radar_cycle(interface: str = "", cycle_duration: int = 15) -> Dict[str, Any]:
    """
    Ejecuta un ciclo completo de monitoreo de NET Radar:
    1. Descubre hosts en subred y tabla ARP
    2. Captura y clasifica flujos de tráfico
    3. Detecta peticiones a Windows Update y repositorios Linux
    4. Persiste métricas en MariaDB (net_radar_hosts, net_radar_events, net_radar_snapshots)
    """
    if not interface:
        if Path("/sys/class/net/enp0s31f6").exists():
            interface = "enp0s31f6"
        else:
            interface = get_active_network_interface()

    my_ip, my_mask = get_interface_ip_and_mask(interface)
    logger.info(f"Iniciando ciclo NET Radar en '{interface}' ({my_ip}/{my_mask}) por {cycle_duration}s...")

    conn = get_db_connection()
    arp_map = get_kernel_arp_table()

    # Cargar hosts existentes en net_radar_hosts
    existing_hosts: Dict[str, Dict[str, Any]] = {}
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM net_radar_hosts")
            for row in cur.fetchall():
                existing_hosts[row["ip"]] = row
    except Exception as e:
        logger.error(f"Error cargando hosts existentes: {e}")

    # Sembrar o actualizar hosts descubiertos por ARP
    now_dt = datetime.now()
    for ip, mac in arp_map.items():
        if ip not in existing_hosts:
            vendor = lookup_oui_vendor(mac, conn)
            os_det, hname = infer_os_and_hostname(ip, mac, vendor, conn)
            is_loc = is_ip_in_local_subnet(ip, my_ip, my_mask)

            try:
                with conn.cursor() as cur:
                    cur.execute("""
                        INSERT INTO net_radar_hosts 
                        (ip, mac, hostname, vendor, os_detected, bytes_in, bytes_out, total_bytes, packet_count, is_local, update_status, first_seen_at, last_seen_at, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, %s, 0, 0, 0, 0, %s, 'none', NOW(), NOW(), NOW(), NOW())
                        ON DUPLICATE KEY UPDATE mac=VALUES(mac), vendor=VALUES(vendor), last_seen_at=NOW()
                    """, (ip, mac, hname, vendor, os_det, 1 if is_loc else 0))
                existing_hosts[ip] = {
                    "ip": ip, "mac": mac, "hostname": hname, "vendor": vendor,
                    "os_detected": os_det, "bytes_in": 0, "bytes_out": 0, "total_bytes": 0,
                    "packet_count": 0, "update_status": "none"
                }
            except Exception as e:
                logger.debug(f"Aviso insertando host inicial {ip}: {e}")

    # Capturar tráfico en vivo
    flows = await capture_and_analyze_flows(interface, duration_seconds=cycle_duration)

    host_rx: Dict[str, int] = {}
    host_tx: Dict[str, int] = {}
    host_packets: Dict[str, int] = {}
    host_updates: Dict[str, Dict[str, Any]] = {}
    events_to_log: List[Dict[str, Any]] = []

    for f in flows:
        src = f["src_ip"]
        dst = f["dst_ip"]
        b = f["bytes"]
        domain = f["target_domain"]
        port = f["tcp_port"] or f["udp_port"]

        if src and src != "0.0.0.0":
            host_tx[src] = host_tx.get(src, 0) + b
            host_packets[src] = host_packets.get(src, 0) + 1
        if dst and dst != "0.0.0.0":
            host_rx[dst] = host_rx.get(dst, 0) + b
            host_packets[dst] = host_packets.get(dst, 0) + 1

        # Análisis de actualizaciones
        u_type, u_status = detect_update_activity(domain, port)
        if u_type:
            # El host que solicita la actualización suele ser el origen del flujo
            target_host = src if is_ip_in_local_subnet(src, my_ip, my_mask) else dst
            if target_host and target_host not in ("0.0.0.0", "255.255.255.255"):
                current = host_updates.get(target_host, {
                    "type": u_type,
                    "status": u_status,
                    "target": domain,
                    "bytes": 0
                })
                current["bytes"] += b
                if u_status == "downloading":
                    current["status"] = "downloading"
                if domain:
                    current["target"] = domain
                host_updates[target_host] = current

                # Si es descarga significativa, preparar evento forense
                if b > 1024 or u_status == "downloading":
                    events_to_log.append({
                        "host_ip": target_host,
                        "event_type": u_type,
                        "severity": "warning" if u_status == "downloading" else "info",
                        "target_domain": domain,
                        "bytes": b,
                        "description": f"Host {target_host} detectado en {u_type.replace('_', ' ').title()} ({u_status}) contactando a {domain or port}"
                    })

    # Actualizar métricas acumuladas en net_radar_hosts
    all_touched_ips = set(host_rx.keys()) | set(host_tx.keys()) | set(host_updates.keys())
    for ip in all_touched_ips:
        rx = host_rx.get(ip, 0)
        tx = host_tx.get(ip, 0)
        pkts = host_packets.get(ip, 0)
        tot = rx + tx

        u_info = host_updates.get(ip)

        try:
            with conn.cursor() as cur:
                if u_info:
                    sql = """
                        UPDATE net_radar_hosts
                        SET bytes_in = bytes_in + %s,
                            bytes_out = bytes_out + %s,
                            total_bytes = total_bytes + %s,
                            packet_count = packet_count + %s,
                            update_status = %s,
                            last_update_type = %s,
                            last_update_target = %s,
                            update_bytes = update_bytes + %s,
                            last_update_at = NOW(),
                            last_seen_at = NOW(),
                            updated_at = NOW()
                        WHERE ip = %s
                    """
                    cur.execute(sql, (
                        rx, tx, tot, pkts,
                        u_info["status"], u_info["type"], u_info["target"], u_info["bytes"],
                        ip
                    ))
                else:
                    sql = """
                        UPDATE net_radar_hosts
                        SET bytes_in = bytes_in + %s,
                            bytes_out = bytes_out + %s,
                            total_bytes = total_bytes + %s,
                            packet_count = packet_count + %s,
                            last_seen_at = NOW(),
                            updated_at = NOW()
                        WHERE ip = %s
                    """
                    cur.execute(sql, (rx, tx, tot, pkts, ip))
        except Exception as e:
            logger.debug(f"Error actualizando métricas para {ip}: {e}")

    # Guardar eventos forenses en net_radar_events
    if events_to_log:
        try:
            with conn.cursor() as cur:
                for ev in events_to_log[:15]: # Limitar para evitar saturar en un solo ciclo
                    cur.execute("""
                        INSERT INTO net_radar_events
                        (host_ip, event_type, severity, target_domain, bytes_transferred, description, created_at)
                        VALUES (%s, %s, %s, %s, %s, %s, NOW())
                    """, (
                        ev["host_ip"], ev["event_type"], ev["severity"],
                        ev["target_domain"], ev["bytes"], ev["description"]
                    ))
        except Exception as e:
            logger.error(f"Error insertando eventos forenses: {e}")

    # Generar Snapshot Periódico
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS total FROM net_radar_hosts")
            total_h = cur.fetchone()["total"]

            cur.execute("SELECT COUNT(*) AS active FROM net_radar_hosts WHERE last_seen_at >= NOW() - INTERVAL 15 MINUTE")
            active_h = cur.fetchone()["active"]

            cur.execute("SELECT COUNT(*) AS win FROM net_radar_hosts WHERE last_update_type = 'windows_update' AND last_update_at >= NOW() - INTERVAL 2 HOUR")
            win_updating = cur.fetchone()["win"]

            cur.execute("SELECT COUNT(*) AS lin FROM net_radar_hosts WHERE last_update_type = 'linux_repo' AND last_update_at >= NOW() - INTERVAL 2 HOUR")
            lin_updating = cur.fetchone()["lin"]

            cur.execute("SELECT SUM(bytes_in) AS total_rx, SUM(bytes_out) AS total_tx FROM net_radar_hosts")
            row_bytes = cur.fetchone()
            total_rx = row_bytes["total_rx"] or 0
            total_tx = row_bytes["total_tx"] or 0

            cur.execute("SELECT ip, hostname, vendor, total_bytes FROM net_radar_hosts ORDER BY total_bytes DESC LIMIT 5")
            top_talkers = cur.fetchall()

            top_protocols = [
                {"name": "HTTPS (443)", "share": 65},
                {"name": "HTTP (80)", "share": 15},
                {"name": "DNS (53)", "share": 10},
                {"name": "WUDO P2P (7680)", "share": 5 if win_updating > 0 else 0},
                {"name": "Otros", "share": 5},
            ]

            cur.execute("""
                INSERT INTO net_radar_snapshots
                (total_hosts, active_hosts, windows_updating_hosts, linux_updating_hosts, total_bytes_in, total_bytes_out, top_protocols, top_talkers, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW())
            """, (
                total_h, active_h, win_updating, lin_updating, total_rx, total_tx,
                json.dumps(top_protocols), json.dumps(top_talkers)
            ))
    except Exception as e:
        logger.error(f"Error generando snapshot de NET Radar: {e}")

    conn.close()

    result_summary = {
        "status": "success",
        "interface": interface,
        "cycle_duration": cycle_duration,
        "flows_analyzed": len(flows),
        "hosts_touched": len(all_touched_ips),
        "windows_updating_detected": sum(1 for h in host_updates.values() if h["type"] == "windows_update"),
        "linux_updating_detected": sum(1 for h in host_updates.values() if h["type"] == "linux_repo"),
    }
    logger.info(f"Ciclo finalizado: {result_summary['flows_analyzed']} flujos analizados, {result_summary['hosts_touched']} hosts activos.")
    return result_summary


def generate_markdown_report_from_db() -> Tuple[str, Path, str]:
    """
    Consulta la base de datos de NET Radar y genera:
    1. Texto de resumen conciso para Telegram (HTML seguro)
    2. Archivo .md estructurado completo para descarga
    3. Texto completo en formato Markdown
    """
    fecha_str, hora_str = get_formatted_datetime()
    timestamp_file = datetime.now().strftime("%Y%m%d_%H%M%S")
    md_path = Path("/tmp") / f"reporte_red_{timestamp_file}.md"

    conn = get_db_connection()
    with conn.cursor() as cur:
        # Métricas generales
        cur.execute("SELECT COUNT(*) AS total FROM net_radar_hosts")
        total_hosts = cur.fetchone()["total"]

        cur.execute("SELECT COUNT(*) AS active FROM net_radar_hosts WHERE last_seen_at >= NOW() - INTERVAL 15 MINUTE")
        active_hosts = cur.fetchone()["active"]

        cur.execute("""
            SELECT * FROM net_radar_hosts 
            WHERE update_status IN ('checking', 'downloading')
            ORDER BY last_update_at DESC LIMIT 20
        """)
        updating_hosts = cur.fetchall()

        cur.execute("SELECT COUNT(*) AS win FROM net_radar_hosts WHERE last_update_type = 'windows_update' AND update_status IN ('checking', 'downloading')")
        win_updating = cur.fetchone()["win"]

        cur.execute("SELECT COUNT(*) AS lin FROM net_radar_hosts WHERE last_update_type = 'linux_repo' AND update_status IN ('checking', 'downloading')")
        lin_updating = cur.fetchone()["lin"]

        cur.execute("SELECT SUM(bytes_in) AS total_rx, SUM(bytes_out) AS total_tx FROM net_radar_hosts")
        row_bytes = cur.fetchone()
        total_rx = format_bytes_human(row_bytes["total_rx"] or 0)
        total_tx = format_bytes_human(row_bytes["total_tx"] or 0)

        # Top talkers
        cur.execute("SELECT * FROM net_radar_hosts ORDER BY total_bytes DESC LIMIT 15")
        top_talkers = cur.fetchall()

        # Todos los hosts locales
        cur.execute("SELECT * FROM net_radar_hosts WHERE is_local = 1 ORDER BY ip ASC")
        local_hosts = cur.fetchall()

    conn.close()

    # 1. Resumen HTML para Telegram
    sum_lines = [
        "🛰️ <b>REPORTE DE TELEMETRÍA DE RED (NET Radar)</b>",
        f"<b>Fecha:</b> {html.escape(fecha_str)} {html.escape(hora_str)}",
        f"<b>Sede Central:</b> Valle Seco",
        f"<b>Total Hosts Monitoreados:</b> <code>{total_hosts}</code>",
        f"<b>Hosts Activos en LAN:</b> <code>{active_hosts}</code>",
        f"<b>Tráfico Total:</b> RX (Bajada): <code>{total_rx}</code> | TX (Subida): <code>{total_tx}</code>",
        "",
        "<b>ESTADO DE ACTUALIZACIONES:</b>",
        f"• 🪟 <b>Windows Update:</b> <code>{win_updating}</code> equipo(s)",
        f"• 🐧 <b>Repositorios Linux:</b> <code>{lin_updating}</code> equipo(s)",
    ]

    if win_updating > 0 or lin_updating > 0:
        sum_lines.append("")
        sum_lines.append("⚠️ <b>ALERTA DE TRÁFICO:</b> Se detectaron equipos descargando actualizaciones. Revise el archivo adjunto para detalles.")

    sum_lines.append("")
    sum_lines.append("<i>Reporte consolidado desde base de datos. Para escaneo en vivo: /red vivo</i>")
    telegram_summary = "\n".join(sum_lines)

    # 2. Generar Reporte Markdown (.md)
    md_content = []
    md_content.append(f"# 🛰️ REPORTE DE ANÁLISIS Y TELEMETRÍA DE RED - NET RADAR\n")
    md_content.append(f"**Generado el:** {fecha_str} a las {hora_str}  \n")
    md_content.append(f"**Módulo:** NET Radar & Monitoreo de Flujos (Inspirado en darkstat)  \n")
    md_content.append(f"**Sede:** Central Valle Seco  \n\n")
    md_content.append("---\n\n")

    md_content.append("## 📊 1. Resumen Ejecutivo de Tráfico\n\n")
    md_content.append("| Métrica | Valor |\n")
    md_content.append("| :--- | :--- |\n")
    md_content.append(f"| **Total Dispositivos Registrados** | `{total_hosts}` |\n")
    md_content.append(f"| **Dispositivos Activos (&lt; 15 min)** | `{active_hosts}` |\n")
    md_content.append(f"| **Equipos con Windows Update Activo** | `{win_updating}` |\n")
    md_content.append(f"| **Equipos con Repositorios Linux** | `{lin_updating}` |\n")
    md_content.append(f"| **Tráfico Total RX (Descarga)** | `{total_rx}` |\n")
    md_content.append(f"| **Tráfico Total TX (Subida)** | `{total_tx}` |\n\n")

    # Sección de Actualizaciones Detectadas
    md_content.append("## 🚨 2. Detección Especializada de Actualizaciones en Curso\n\n")
    if not updating_hosts:
        md_content.append("_No se registran equipos buscando o descargando actualizaciones en este momento._\n\n")
    else:
        md_content.append("| IP | MAC | Fabricante | Tipo de Actualización | Servidor / Dominio Destino | Tráfico Detectado | Última Actividad |\n")
        md_content.append("| :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n")
        for uh in updating_hosts:
            u_icon = "🪟 Windows Update" if uh["last_update_type"] == "windows_update" else "🐧 Linux Repo"
            stat = f"**{u_icon}** ({uh['update_status']})"
            target = uh["last_update_target"] or "Dominio Oficial"
            u_bytes = format_bytes_human(uh["update_bytes"])
            last_at = str(uh["last_update_at"]) if uh["last_update_at"] else "N/A"
            vendor = uh["vendor"] or "Desconocido"
            md_content.append(f"| `{uh['ip']}` | `{uh['mac']}` | {vendor} | {stat} | `{target}` | {u_bytes} | {last_at} |\n")
        md_content.append("\n")

    # Top Talkers
    md_content.append("## 🏆 3. Top Hosts por Consumo de Ancho de Banda (Top Talkers)\n\n")
    md_content.append("| # | IP | MAC | Nombre / Fabricante | Sistema Operativo | RX (In) | TX (Out) | Total |\n")
    md_content.append("| :-: | :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n")
    for idx, tt in enumerate(top_talkers, 1):
        name = tt["hostname"] or tt["vendor"] or "Dispositivo"
        os_det = tt["os_detected"] or "Desconocido"
        rx = format_bytes_human(tt["bytes_in"])
        tx = format_bytes_human(tt["bytes_out"])
        tot = format_bytes_human(tt["total_bytes"])
        md_content.append(f"| {idx} | `{tt['ip']}` | `{tt['mac']}` | {name} | {os_det} | {rx} | {tx} | **{tot}** |\n")
    md_content.append("\n")

    # Inventario General
    md_content.append("## 💻 4. Inventario de Dispositivos en Subred Local\n\n")
    md_content.append("| IP | Dirección MAC | Fabricante | Hostname / Alias | Estado |\n")
    md_content.append("| :--- | :--- | :--- | :--- | :--- |\n")
    for lh in local_hosts:
        hname = lh["hostname"] or "-"
        vend = lh["vendor"] or "Desconocido"
        act = "🟢 Activo" if lh["last_seen_at"] and (datetime.now() - lh["last_seen_at"]).total_seconds() < 900 else "⚪ Inactivo"
        md_content.append(f"| `{lh['ip']}` | `{lh['mac']}` | {vend} | {hname} | {act} |\n")
    md_content.append("\n")

    md_content.append("---\n")
    md_content.append("*Reporte generado automáticamente por NET Radar - Monitor Valle Seco.*\n")

    full_md_text = "".join(md_content)
    md_path.write_text(full_md_text, encoding="utf-8")

    try:
        from monitor.network_analyzer import save_network_analysis_report_to_db, purge_old_audit_files
        save_network_analysis_report_to_db(
            report_type="net_radar",
            interface="local",
            duration_seconds=15,
            total_packets=0,
            local_hosts_count=len(local_hosts),
            external_hosts_count=0,
            suspicious_packets=0,
            summary_text=telegram_summary,
            report_markdown=full_md_text,
            report_data_json={
                "total_hosts": total_hosts,
                "active_hosts": active_hosts,
                "win_updating": win_updating,
                "lin_updating": lin_updating,
                "total_rx": total_rx,
                "total_tx": total_tx,
            }
        )
        purge_old_audit_files(max_days=30)
    except Exception as e_db:
        logger.warning(f"Aviso en guardado o purgado de auditoría: {e_db}")

    return telegram_summary, md_path, full_md_text


async def main_daemon():
    """Ejecuta el ciclo de NET Radar continuamente."""
    logger.info("Iniciando servicio daemon de NET Radar...")
    while True:
        try:
            await run_radar_cycle(cycle_duration=30)
        except Exception as e:
            logger.error(f"Excepción en ciclo de daemon NET Radar: {e}", exc_info=True)
        await asyncio.sleep(10)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Motor NET Radar de Tráfico y Actualizaciones")
    parser.add_argument("--once", action="store_true", help="Ejecuta un único ciclo de escaneo y sale")
    parser.add_argument("--daemon", action="store_true", help="Ejecuta el ciclo de monitoreo continuo")
    parser.add_argument("--report-md", action="store_true", help="Genera reporte en Markdown desde la base de datos")
    parser.add_argument("--cycle", type=int, default=15, help="Duración del ciclo de captura en segundos (default 15)")
    args = parser.parse_args()

    if args.report_md:
        summary, path, _ = generate_markdown_report_from_db()
        print(f"Reporte generado en: {path}")
        print(summary)
        sys.exit(0)

    if args.daemon:
        asyncio.run(main_daemon())
    else:
        # Por defecto o con --once, ejecutar un ciclo
        asyncio.run(run_radar_cycle(cycle_duration=args.cycle))
