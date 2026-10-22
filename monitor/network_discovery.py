#!/usr/bin/env python3
# ==============================================================================
# 🛰️ MOTOR DE AUTO-DISCOVERY Y DETECCIÓN ANTI-ROGUE: network_discovery.py
# Escaneo inteligente de subredes, lookup de fabricantes OUI y bitácora histórica
# Ubicación: /scripts/telegram-admin-bot/monitor/network_discovery.py
# ==============================================================================

from __future__ import annotations

import argparse
import asyncio
import ipaddress
import json
import logging
import os
import re
import socket
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Set, Tuple
import xml.etree.ElementTree as ET

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

try:
    from monitor.monitor_web_sync import get_db_connection
except ImportError:
    import pymysql
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
LOG_FILE = LOG_DIR / "network_discovery.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [discovery] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("discovery")


def get_local_interfaces_map() -> Dict[str, str]:
    """
    Retorna un diccionario {ip: mac_normalizada} de las interfaces locales del propio servidor.
    Esto previene que la propia máquina (ej. 192.168.1.220) se registre con MAC sintética 'IP:192.168.1.220'.
    """
    local_map: Dict[str, str] = {}
    try:
        res = subprocess.run(["ip", "-j", "addr"], capture_output=True, text=True, timeout=3)
        if res.returncode == 0 and res.stdout.strip():
            data = json.loads(res.stdout)
            for iface in data:
                mac = (iface.get("address") or "").strip().upper()
                if not mac or mac == "00:00:00:00:00:00":
                    continue
                for addr in iface.get("addr_info", []):
                    ip = addr.get("local")
                    if ip and not ip.startswith("127."):
                        local_map[ip] = mac
    except Exception as e:
        logger.debug(f"Aviso leyendo ip -j addr: {e}")
    return local_map


def get_kernel_arp_table() -> Dict[str, str]:
    """
    Obtiene la tabla ARP del kernel de Linux sin requerir privilegios de root.
    Prioriza 'ip -j neigh' y utiliza '/proc/net/arp' como fallback.
    Retorna un diccionario {ip: mac_normalizada}.
    """
    arp_map: Dict[str, str] = {}

    # Intento 1: ip -j neigh
    try:
        res = subprocess.run(
            ["ip", "-j", "neigh"],
            capture_output=True,
            text=True,
            timeout=3
        )
        if res.returncode == 0 and res.stdout.strip():
            entries = json.loads(res.stdout)
            for item in entries:
                ip = item.get("dst")
                mac = item.get("lladdr")
                state = item.get("state", [])
                if ip and mac and "FAILED" not in state:
                    clean_mac = mac.strip().upper()
                    if len(clean_mac) == 17 and clean_mac != "00:00:00:00:00:00":
                        arp_map[ip] = clean_mac
            if arp_map:
                return arp_map
    except Exception as e:
        logger.debug(f"Aviso leyendo ip -j neigh: {e}")

    # Intento 2: /proc/net/arp
    proc_arp = Path("/proc/net/arp")
    if proc_arp.exists():
        try:
            lines = proc_arp.read_text(encoding="utf-8").splitlines()[1:]
            for line in lines:
                parts = line.split()
                if len(parts) >= 4:
                    ip = parts[0]
                    mac = parts[3].upper()
                    flags = parts[2]
                    if mac != "00:00:00:00:00:00" and flags != "0x0":
                        arp_map[ip] = mac
        except Exception as e:
            logger.debug(f"Aviso leyendo /proc/net/arp: {e}")

    return arp_map


def lookup_oui_vendor(mac: str, conn) -> Tuple[Optional[str], Optional[str]]:
    """
    Resuelve el fabricante del dispositivo a partir de los primeros 3 octetos de la MAC.
    Retorna (vendor_name, oui_prefix).
    """
    if not mac or len(mac) < 8:
        return ("Dispositivo Desconocido", None)

    prefix = mac[:8].upper()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT vendor_name FROM oui_vendors WHERE oui_prefix = %s LIMIT 1", (prefix,))
            row = cur.fetchone()
            if row and row.get("vendor_name"):
                return (row["vendor_name"], prefix)
    except Exception as e:
        logger.debug(f"Error consultando OUI {prefix}: {e}")

    return ("Fabricante Desconocido", prefix)


def infer_device_type(
    vendor: Optional[str],
    hostname: str,
    asset_type: Optional[str] = None,
    inferred_type: Optional[str] = None
) -> str:
    """
    Clasifica el tipo de dispositivo de acuerdo con las categorías autorizadas:
    router, switch, firewall, server, workstation, printer, ap, camera, ups, phone, unknown.
    Si se proporciona un tipo directo del inventario ITAM o inferido por puertos, lo respeta y normaliza.
    """
    if asset_type:
        asset_clean = asset_type.lower().strip()
        if asset_clean in ["router", "switch", "firewall", "server", "printer", "ap", "camera", "ups", "phone"]:
            return asset_clean
        if asset_clean in ["desktop", "laptop", "pc", "workstation"]:
            return "workstation"
        if asset_clean in ["ip_phone"]:
            return "phone"

    if inferred_type and inferred_type in ["router", "switch", "firewall", "server", "printer", "ap", "camera", "ups", "phone", "workstation"]:
        return inferred_type

    text = f"{vendor or ''} {hostname}".lower()
    if any(k in text for k in ["switch", "c2960", "c3750", "catalyst", "procurve", "edgeswitch", "sw-", "sw_"]):
        return "switch"
    if any(k in text for k in ["router", "mikrotik", "pfsense", "fortinet", "gateway", "gw-"]):
        return "router"
    if any(k in text for k in ["vmware", "kvm", "qemu", "server", "srv", "poweredge", "proliant", "debian", "ubuntu", "linux"]):
        return "server"
    if any(k in text for k in ["printer", "laserjet", "epson", "xerox", "canon"]):
        return "printer"
    if any(k in text for k in ["ap-", "unifi", "ubiquiti", "wireless", "access point", "tp-link"]):
        return "ap"
    if any(k in text for k in ["camera", "cam", "dahua", "hikvision"]):
        return "camera"
    if any(k in text for k in ["apc", "schneider", "ups"]):
        return "ups"
    if any(k in text for k in ["avaya", "grandstream", "yealink", "polycom", "voip", "phone", "telefono", "teléfono"]):
        return "phone"
    if any(k in text for k in ["hp prodesk", "hp inc", "dell inc", "intel", "lenovo", "micro-star", "msi", "foxconn", "hon hai", "workstation", "desktop", "laptop", "pc-", "windows"]):
        return "workstation"

    return "unknown"


def deep_fingerprint_hosts(ip_list: List[str]) -> Dict[str, Dict[str, Any]]:
    """
    Realiza una inspección dirigida y rápida sobre los hosts activos detectados:
    - Escanea puertos clave: 135, 139, 445, 22, 80, 443, 3389, 5800, 5900, 9100.
    - Ejecuta scripts NetBIOS y SMB para extraer hostname y OS exacto de Windows.
    - Aplica sondeo de TTL para inferir Linux en hosts sin puertos abiertos.
    Retorna un diccionario {ip: {'hostname': ..., 'os_detected': ..., 'open_ports': [...], 'inferred_type': ...}}.
    """
    if not ip_list:
        return {}

    fp_results: Dict[str, Dict[str, Any]] = {}
    nmap_path = "/usr/bin/nmap"
    if not os.path.exists(nmap_path):
        return fp_results

    try:
        cmd = [
            nmap_path, "-Pn", "-sT",
            "-p", "22,80,135,139,443,445,3389,5800,5900,9100",
            "--script", "smb-os-discovery,nbstat",
            "-T4", "-oX", "-"
        ] + ip_list

        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        if proc.returncode == 0 and proc.stdout.strip():
            root = ET.fromstring(proc.stdout)
            for host in root.findall("host"):
                ip = None
                hostname = None
                os_name = None
                open_ports: List[int] = []

                for addr in host.findall("address"):
                    if addr.get("addrtype") == "ipv4":
                        ip = addr.get("addr")

                if not ip:
                    continue

                hostnames_elem = host.find("hostnames")
                if hostnames_elem is not None:
                    hn = hostnames_elem.find("hostname")
                    if hn is not None and hn.get("name"):
                        hostname = hn.get("name")

                ports_elem = host.find("ports")
                if ports_elem is not None:
                    for p in ports_elem.findall("port"):
                        state_elem = p.find("state")
                        if state_elem is not None and state_elem.get("state") == "open":
                            try:
                                open_ports.append(int(p.get("portid")))
                            except (ValueError, TypeError):
                                pass

                hostscript = host.find("hostscript")
                if hostscript is not None:
                    for script in hostscript.findall("script"):
                        sid = script.get("id")
                        if sid == "smb-os-discovery":
                            out = script.get("output", "")
                            for line in out.splitlines():
                                if "OS:" in line:
                                    os_name = line.split("OS:", 1)[1].strip()
                                elif "Computer name:" in line and not hostname:
                                    hostname = line.split("Computer name:", 1)[1].strip()
                        elif sid == "nbstat":
                            out = script.get("output", "")
                            for line in out.splitlines():
                                if "NetBIOS name:" in line and not hostname:
                                    hostname = line.split("NetBIOS name:", 1)[1].split(",")[0].strip()

                # Deducción refinada de Sistema Operativo si no vino de SMB
                if not os_name:
                    if 22 in open_ports and 135 not in open_ports:
                        os_name = "Linux / Unix (SSH)"
                    elif 135 in open_ports or 445 in open_ports or 3389 in open_ports:
                        os_name = "Windows (MSRPC / SMB)"
                    elif 9100 in open_ports:
                        os_name = "Impresora de Red"

                inferred_type = None
                if 9100 in open_ports:
                    inferred_type = "printer"
                elif 135 in open_ports or 445 in open_ports or 3389 in open_ports or 5900 in open_ports or 22 in open_ports:
                    inferred_type = "workstation"

                fp_results[ip] = {
                    "hostname": hostname,
                    "os_detected": os_name,
                    "open_ports": open_ports,
                    "inferred_type": inferred_type,
                }
    except Exception as e:
        logger.warning(f"Aviso en deep_fingerprint_hosts: {e}")

    # Chequeo rápido de TTL para hosts sin puertos abiertos o sin SO detectado
    for ip in ip_list:
        if ip not in fp_results:
            fp_results[ip] = {"hostname": None, "os_detected": None, "open_ports": [], "inferred_type": None}
        if not fp_results[ip].get("os_detected"):
            try:
                p_res = subprocess.run(["ping", "-c", "1", "-W", "1", ip], capture_output=True, text=True, timeout=2)
                if p_res.returncode == 0:
                    ttl_match = re.search(r"ttl=(\d+)", p_res.stdout.lower())
                    if ttl_match:
                        ttl_val = int(ttl_match.group(1))
                        if ttl_val <= 64:
                            fp_results[ip]["os_detected"] = "Linux / Unix"
                            fp_results[ip]["inferred_type"] = "workstation"
                        elif ttl_val <= 128:
                            fp_results[ip]["os_detected"] = "Windows"
                            fp_results[ip]["inferred_type"] = "workstation"
            except Exception:
                pass

    return fp_results


async def sweep_subnet_async(subnet_str: str, rate_limit_pps: int = 150) -> List[Dict[str, Any]]:
    """
    Realiza un escaneo activo de la subred para despertar respuestas ARP y verificar hosts activos.
    Combina nmap (si disponible) con ping sweep asíncrono no bloqueante.
    """
    live_hosts: List[Dict[str, Any]] = []

    try:
        net = ipaddress.ip_network(subnet_str, strict=False)
    except ValueError as e:
        logger.error(f"Subred inválida {subnet_str}: {e}")
        return live_hosts

    # Si nmap está disponible, usar nmap con TCP SYN a puertos Windows (135, 445) y Web (80, 443) para superar bloqueo de ICMP
    nmap_path = "/usr/bin/nmap"
    if os.path.exists(nmap_path):
        logger.info(f"Ejecutando descubrimiento acelerado con Nmap en {subnet_str}...")
        try:
            cmd = [nmap_path, "-sn", "-PS135,445,80,443", "-PA80,443", "-T4", "--min-rate", str(rate_limit_pps), str(net)]
            proc = await asyncio.create_subprocess_exec(
                *cmd,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE
            )
            stdout, _ = await proc.communicate()
            output = stdout.decode("utf-8", errors="ignore")

            current_ip = None
            current_host = None
            for line in output.splitlines():
                if "Nmap scan report for" in line:
                    match = re.search(r"for\s+(?:([^\s()]+)\s+\()?([0-9.]+)\)?", line)
                    if match:
                        current_host = match.group(1) if match.group(1) != match.group(2) else None
                        current_ip = match.group(2)
                        live_hosts.append({"ip": current_ip, "hostname": current_host})
            logger.info(f"Nmap detectó {len(live_hosts)} hosts activos en {subnet_str}.")
            return live_hosts
        except Exception as e:
            logger.warning(f"Fallo en escaneo Nmap ({e}), recurriendo a ping sweep nativo...")

    # Fallback nativo: Ping sweep asíncrono con semáforo de concurrencia
    sem = asyncio.Semaphore(rate_limit_pps)

    async def ping_single(ip_str: str):
        async with sem:
            try:
                proc = await asyncio.create_subprocess_exec(
                    "ping", "-c", "1", "-W", "1", ip_str,
                    stdout=asyncio.subprocess.DEVNULL,
                    stderr=asyncio.subprocess.DEVNULL
                )
                rc = await proc.wait()
                if rc == 0:
                    # Intentar reverse DNS rápido (timeout 0.3s)
                    host = None
                    try:
                        host = socket.gethostbyaddr(ip_str)[0]
                    except Exception:
                        pass
                    return {"ip": ip_str, "hostname": host}
            except Exception:
                pass
            return None

    tasks = [ping_single(str(ip)) for ip in net.hosts()]
    results = await asyncio.gather(*tasks, return_exceptions=True)
    live_hosts = [r for r in results if isinstance(r, dict) and r.get("ip")]
    logger.info(f"Ping sweep completado: {len(live_hosts)} hosts activos detectados.")
    return live_hosts


def scan_subnet(subnet_str: str, site_id: Optional[int] = None, executed_by: str = "cron") -> Dict[str, Any]:
    """
    Ejecuta el ciclo integral de auto-discovery en una subred:
    1. Escaneo de red para despertar respuestas.
    2. Cruce con tabla ARP para identificar MACs y OUI.
    3. Cotejo contra base de datos: nuevos, existentes, cambios de IP, rogue.
    4. Registro de auditoría en 'discovery_scans' y 'discovered_device_history'.
    """
    start_time = time.time()
    started_at = datetime.now()
    logger.info(f"▶ [INICIO ESCANEO] Subred: {subnet_str} (Ejecutado por: {executed_by})")

    conn = get_db_connection()
    scan_id = None

    try:
        # Si site_id no fue provisto, buscar si la subred tiene una sede asociada en discovery_subnets
        if site_id is None:
            try:
                with conn.cursor() as cur:
                    cur.execute("SELECT site_id FROM discovery_subnets WHERE subnet = %s LIMIT 1", (subnet_str,))
                    sub_row = cur.fetchone()
                    if sub_row and sub_row.get("site_id"):
                        site_id = sub_row["site_id"]
            except Exception as e:
                logger.warning(f"No se pudo consultar site_id para subred {subnet_str}: {e}")

        # 1. Registrar inicio de escaneo en discovery_scans
        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO discovery_scans (scan_type, subnet, site_id, started_at, status, executed_by)
                VALUES ('arp_sweep', %s, %s, %s, 'running', %s)
            """, (subnet_str, site_id, started_at, executed_by))
            scan_id = cur.lastrowid
            conn.commit()

        # 2. Ejecutar escaneo activo para refrescar ARP
        live_hosts = asyncio.run(sweep_subnet_async(subnet_str))

        # 3. Leer tabla ARP actualizada del kernel e interfaces locales
        arp_table = get_kernel_arp_table()
        local_interfaces = get_local_interfaces_map()

        # 4. Obtener catálogo existente de dispositivos de red y de inventario ITAM para auto-vinculación
        known_network_devices: Dict[str, int] = {}
        with conn.cursor() as cur:
            cur.execute("SELECT id, ip, mac FROM monitored_network_devices WHERE is_active = 1")
            for row in cur.fetchall():
                if row.get("mac"):
                    known_network_devices[row["mac"].upper()] = row["id"]
                if row.get("ip"):
                    known_network_devices[row["ip"]] = row["id"]

        known_assets: Dict[str, Dict[str, Any]] = {}
        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, name, type, brand, model, ip_address, mac_address, monitored_site_id, monitored_network_device_id 
                FROM inventory_assets
            """)
            for row in cur.fetchall():
                mac_val = (row.get("mac_address") or "").strip().upper()
                ip_val = (row.get("ip_address") or "").strip()
                if mac_val and mac_val != "00:00:00:00:00:00" and not mac_val.startswith("IP:"):
                    known_assets[mac_val] = row
                if ip_val:
                    known_assets[ip_val] = row

        # Consolidar todos los hosts detectados: tanto los que respondieron al escaneo
        # como los que respondieron a nivel de enlace (ARP) en la subred analizada
        hosts_map: Dict[str, Dict[str, Any]] = {h["ip"]: h for h in live_hosts}
        try:
            target_network = ipaddress.ip_network(subnet_str, strict=False)
        except ValueError:
            target_network = None

        if target_network:
            for arp_ip, arp_mac in arp_table.items():
                try:
                    if ipaddress.ip_address(arp_ip) in target_network:
                        if arp_ip not in hosts_map:
                            rev_host = None
                            try:
                                rev_host = socket.gethostbyaddr(arp_ip)[0]
                            except Exception:
                                pass
                            hosts_map[arp_ip] = {"ip": arp_ip, "hostname": rev_host}
                except ValueError:
                    continue

        all_detected_hosts = list(hosts_map.values())
        detected_ips_set = set(hosts_map.keys())
        devices_found = 0
        new_devices_count = 0

        # 4.1 Marcar como inactivos los dispositivos de esta subred que no respondieron en este barrido
        if target_network:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT id, ip_address, mac_address, is_active FROM discovered_devices
                    WHERE is_active = 1
                """)
                for dev in cur.fetchall():
                    dev_ip = dev.get("ip_address")
                    if dev_ip:
                        try:
                            if ipaddress.ip_address(dev_ip) in target_network and dev_ip not in detected_ips_set:
                                cur.execute("UPDATE discovered_devices SET is_active = 0 WHERE id = %s", (dev["id"],))
                                cur.execute("""
                                    INSERT INTO discovered_device_history 
                                    (discovered_device_id, event_type, previous_value, new_value, occurred_at)
                                    VALUES (%s, 'went_offline', 'activo', 'inactivo', NOW())
                                """, (dev["id"],))
                                logger.info(f"💤 [DISPOSITIVO INACTIVO/APAGADO] ID: {dev['id']} | IP: {dev_ip} | MAC: {dev.get('mac_address')}")
                        except ValueError:
                            pass
                conn.commit()

        # 4.2 Ejecutar huella digital profunda sobre todos los hosts detectados
        logger.info(f"🔍 Ejecutando huella digital profunda sobre {len(detected_ips_set)} hosts activos...")
        fp_map = deep_fingerprint_hosts(list(detected_ips_set))

        # Procesar cada host detectado
        for host_info in all_detected_hosts:
            ip = host_info["ip"]
            fp = fp_map.get(ip, {})
            hostname = fp.get("hostname") or host_info.get("hostname")
            os_detected = fp.get("os_detected")
            open_ports_list = fp.get("open_ports", [])
            json_ports = json.dumps(open_ports_list) if open_ports_list else None
            inferred_type = fp.get("inferred_type")
            mac = arp_table.get(ip) or local_interfaces.get(ip)

            # Cotejar contra el inventario ITAM
            asset_match = known_assets.get(mac) if mac else None
            if not asset_match:
                asset_match = known_assets.get(ip)

            # Si no hay MAC en ARP ni local, pero el activo ITAM tiene MAC física registrada, usarla
            if not mac and asset_match and asset_match.get("mac_address"):
                cand_mac = asset_match["mac_address"].strip().upper()
                if cand_mac and not cand_mac.startswith("IP:"):
                    mac = cand_mac

            # Fallback sintético sólo si no se dispone de ninguna MAC física
            if not mac:
                mac = f"IP:{ip}"

            devices_found += 1
            vendor, oui_prefix = lookup_oui_vendor(mac, conn)

            # Si el OUI fue desconocido pero en inventario tenemos la marca oficial, adoptarla
            if (not vendor or vendor == "Fabricante Desconocido") and asset_match and asset_match.get("brand"):
                vendor = asset_match["brand"]

            device_type = infer_device_type(
                vendor,
                hostname or "",
                asset_match.get("type") if asset_match else None,
                inferred_type
            )

            with conn.cursor() as cur:
                # Verificar si ya existe este dispositivo (por MAC física o por IP si era sintética)
                cur.execute("""
                    SELECT * FROM discovered_devices 
                    WHERE mac_address = %s OR (ip_address = %s AND mac_address LIKE 'IP:%%')
                    ORDER BY id ASC LIMIT 1
                """, (mac, ip))
                existing = cur.fetchone()

                # Resolver vinculación con dispositivo de red y sede
                linked_id = known_network_devices.get(mac) or known_network_devices.get(ip)
                if not linked_id and asset_match and asset_match.get("monitored_network_device_id"):
                    linked_id = asset_match["monitored_network_device_id"]

                effective_site_id = site_id
                if asset_match and asset_match.get("monitored_site_id"):
                    effective_site_id = asset_match["monitored_site_id"]

                is_auth = 1 if (linked_id or asset_match) else 0
                status = "clasificado" if (linked_id or asset_match) else "pendiente"

                if not existing:
                    # Dispositivo nuevo detectado
                    new_devices_count += 1
                    cur.execute("""
                        INSERT INTO discovered_devices 
                        (mac_address, ip_address, hostname, vendor, oui_prefix, device_type,
                         os_detected, open_ports,
                         site_id, classification_status, linked_network_device_id, first_seen,
                         last_seen, seen_count, is_active, discovery_method, is_authorized, notes)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), 1, 1, 'nmap_scan', %s, %s)
                    """, (
                        mac, ip, hostname, vendor, oui_prefix, device_type,
                        os_detected, json_ports,
                        effective_site_id, status, linked_id, is_auth,
                        "Conciliado automáticamente con Inventario ITAM" if asset_match else "Descubierto automáticamente por escaneo de red"
                    ))
                    dev_id = cur.lastrowid

                    # Bitácora de primer avistamiento
                    cur.execute("""
                        INSERT INTO discovered_device_history 
                        (discovered_device_id, event_type, previous_value, new_value, occurred_at)
                        VALUES (%s, 'first_seen', NULL, %s, NOW())
                    """, (dev_id, f"IP inicial: {ip}, Fabricante: {vendor}"))

                    logger.info(f"✨ [NUEVO DISPOSITIVO] IP: {ip} | MAC: {mac} | Fabricante: {vendor} ({device_type})")
                else:
                    # Dispositivo existente: actualizar telemetría
                    dev_id = existing["id"]
                    old_ip = existing.get("ip_address")
                    old_status = existing.get("classification_status")

                    # Si cambió de IP, auditar el cambio
                    if old_ip and old_ip != ip:
                        cur.execute("""
                            INSERT INTO discovered_device_history 
                            (discovered_device_id, event_type, previous_value, new_value, occurred_at)
                            VALUES (%s, 'ip_changed', %s, %s, NOW())
                        """, (dev_id, old_ip, ip))
                        logger.warning(f"⚠️ [CAMBIO DE IP] MAC: {mac} cambió de {old_ip} a {ip}")

                    # Si estaba inactivo, auditar reactivación
                    if not existing.get("is_active"):
                        cur.execute("""
                            INSERT INTO discovered_device_history 
                            (discovered_device_id, event_type, previous_value, new_value, occurred_at)
                            VALUES (%s, 'came_online', 'inactivo', 'activo', NOW())
                        """, (dev_id,))

                    # Si antes estaba pendiente y ahora coincide con inventario, auto-promover a clasificado y autorizado
                    new_status = "clasificado" if (is_auth or old_status == "clasificado") else old_status
                    new_auth = 1 if (is_auth or existing.get("is_authorized")) else 0

                    cur.execute("""
                        UPDATE discovered_devices 
                        SET ip_address = %s,
                            mac_address = %s,
                            hostname = COALESCE(%s, hostname),
                            vendor = COALESCE(%s, vendor),
                            oui_prefix = COALESCE(%s, oui_prefix),
                            device_type = CASE WHEN device_type = 'unknown' OR device_type = 'workstation' THEN %s ELSE device_type END,
                            os_detected = COALESCE(%s, os_detected),
                            open_ports = COALESCE(%s, open_ports),
                            last_seen = NOW(),
                            seen_count = seen_count + 1,
                            is_active = 1,
                            is_authorized = %s,
                            classification_status = %s,
                            linked_network_device_id = COALESCE(%s, linked_network_device_id),
                            site_id = COALESCE(site_id, %s)
                        WHERE id = %s
                    """, (
                        ip, mac, hostname, vendor, oui_prefix, device_type,
                        os_detected, json_ports,
                        new_auth, new_status, linked_id, effective_site_id, dev_id
                    ))

        duration = round(time.time() - start_time, 2)

        # 5. Cerrar auditoría del escaneo
        with conn.cursor() as cur:
            cur.execute("""
                UPDATE discovery_scans 
                SET finished_at = NOW(),
                    duration_seconds = %s,
                    devices_found = %s,
                    new_devices = %s,
                    status = 'completed'
                WHERE id = %s
            """, (duration, devices_found, new_devices_count, scan_id))

            # Actualizar timestamp en la subred
            cur.execute("""
                UPDATE discovery_subnets 
                SET last_scan_at = NOW() 
                WHERE subnet = %s
            """, (subnet_str,))
            conn.commit()

        logger.info(f"✅ [ESCANEO COMPLETADO] Subred: {subnet_str} | Encontrados: {devices_found} | Nuevos: {new_devices_count} | Duración: {duration}s")
        return {
            "success": True,
            "subnet": subnet_str,
            "devices_found": devices_found,
            "new_devices": new_devices_count,
            "duration_seconds": duration,
        }

    except Exception as e:
        logger.error(f"❌ Error durante el escaneo de {subnet_str}: {e}")
        if scan_id:
            try:
                with conn.cursor() as cur:
                    cur.execute("""
                        UPDATE discovery_scans 
                        SET finished_at = NOW(),
                            status = 'failed',
                            error_message = %s
                        WHERE id = %s
                    """, (str(e), scan_id))
                    conn.commit()
            except Exception:
                pass
        return {"success": False, "error": str(e), "subnet": subnet_str}
    finally:
        conn.close()


def scan_all_active_subnets(executed_by: str = "cron", force: bool = False):
    """Escanea secuencialmente todas las subredes activas según su scan_interval_minutes."""
    conn = get_db_connection()
    subnets = []
    try:
        with conn.cursor() as cur:
            if force:
                cur.execute("SELECT id, subnet, site_id, scan_interval_minutes, rate_limit_pps FROM discovery_subnets WHERE is_active = 1")
            else:
                cur.execute("""
                    SELECT id, subnet, site_id, scan_interval_minutes, rate_limit_pps 
                    FROM discovery_subnets 
                    WHERE is_active = 1
                      AND (last_scan_at IS NULL OR TIMESTAMPDIFF(MINUTE, last_scan_at, NOW()) >= scan_interval_minutes)
                """)
            subnets = cur.fetchall()
    finally:
        conn.close()

    if not subnets:
        logger.info("No hay subredes activas que requieran escaneo en este intervalo.")
        return

    logger.info(f"Iniciando ciclo de descubrimiento en {len(subnets)} subredes activas (force={force})...")
    for s in subnets:
        scan_subnet(s["subnet"], site_id=s.get("site_id"), executed_by=executed_by)


def main():
    parser = argparse.ArgumentParser(description="Motor de Auto-Discovery y Detección Anti-Rogue")
    parser.add_argument("--subnet", type=str, help="Subred específica a escanear (ej: 192.168.1.0/24)")
    parser.add_argument("--all", action="store_true", help="Escanear todas las subredes activas pendientes de escaneo")
    parser.add_argument("--force", action="store_true", help="Forzar escaneo de subredes ignorando intervalo")
    parser.add_argument("--manual", action="store_true", help="Marca la ejecución como manual")

    args = parser.parse_args()
    executed_by = "manual" if args.manual else "cron"

    if args.subnet:
        scan_subnet(args.subnet, executed_by=executed_by)
    elif args.all:
        scan_all_active_subnets(executed_by=executed_by, force=args.force)
    else:
        # Por defecto escanea la subred local de Valle Seco
        scan_subnet("192.168.1.0/24", executed_by=executed_by)


if __name__ == "__main__":
    main()
