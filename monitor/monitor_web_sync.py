#!/usr/bin/env python3
"""
==============================================================================
🌐 MONITOR WEB SYNC ENGINE - ATIT Valle Seco
Ubicación: /scripts/telegram-admin-bot/monitor/monitor_web_sync.py
Ejecuta el escaneo de servicios, sedes y proxies en < 2.5 segundos de forma asíncrona
y actualiza la base de datos MySQL y la caché estática de Laravel.
==============================================================================
"""

import asyncio
import json
import os
import re
import socket
import sys
import time
import warnings
from datetime import datetime
from pathlib import Path
import ssl
import httpx
import pymysql

warnings.filterwarnings("ignore", category=DeprecationWarning)

BASE_DIR = Path("/scripts/telegram-admin-bot")
APP_DIR = Path("/var/www/monitoreo") if Path("/var/www/monitoreo").exists() else Path("/var/www/testapp")
SNAPSHOT_FILE = APP_DIR / "storage" / "app" / "public" / "monitoring_snapshot.json"

def create_permissive_ssl_context():
    """Crea un contexto SSL permisivo compatible con servidores legacy (TLS 1.0+, ciphers antiguos, autofirmados)."""
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    try:
        ctx.set_ciphers("DEFAULT@SECLEVEL=0")
    except Exception:
        pass
    try:
        ctx.minimum_version = ssl.TLSVersion.TLSv1
    except Exception:
        pass
    return ctx

SSL_PERMISSIVE_CTX = create_permissive_ssl_context()

# Cargar variables .env de Laravel
def load_env():
    env_file = APP_DIR / ".env"
    env_vars = {
        "DB_HOST": "127.0.0.1",
        "DB_PORT": "3306",
        "DB_DATABASE": "monitoreo_vs",
        "DB_USERNAME": "monitoreo_user",
        "DB_PASSWORD": "VsMonit#2026!SecureKey",
    }
    if env_file.exists():
        for line in env_file.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, v = line.split("=", 1)
                env_vars[k.strip()] = v.strip().strip("\"'").strip("'")
    return env_vars

ENV = load_env()

def get_db_connection():
    return pymysql.connect(
        host=ENV.get("DB_HOST", "127.0.0.1"),
        port=int(ENV.get("DB_PORT", 3306)),
        user=ENV.get("DB_USERNAME", "monitoreo_user"),
        password=ENV.get("DB_PASSWORD", "VsMonit#2026!SecureKey"),
        database=ENV.get("DB_DATABASE", "monitoreo_vs"),
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )

# --- VERIFICADORES ASÍNCRONOS ULTRA-RÁPIDOS ---

async def check_ping(host: str, timeout: float = 1.8) -> tuple[bool, float]:
    """Realiza un ping ICMP asíncrono con timeout corto."""
    if not host or host in ("0.0.0.0", "127.0.0.1"):
        return False, 0.0
    start = time.perf_counter()
    proc = await asyncio.create_subprocess_exec(
        "ping", "-c", "1", "-W", str(int(timeout)), host,
        stdout=asyncio.subprocess.DEVNULL,
        stderr=asyncio.subprocess.DEVNULL
    )
    try:
        await asyncio.wait_for(proc.wait(), timeout=timeout + 0.5)
        elapsed = (time.perf_counter() - start) * 1000.0
        return (proc.returncode == 0), round(elapsed, 1)
    except asyncio.TimeoutError:
        try:
            proc.kill()
        except Exception:
            pass
        return False, 0.0

async def check_tcp_port(host: str, port: int, timeout: float = 1.8) -> tuple[bool, float]:
    """Comprueba conexión TCP a un puerto específico."""
    if not host or not port:
        return False, 0.0
    start = time.perf_counter()
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(host, port),
            timeout=timeout
        )
        writer.close()
        await writer.wait_closed()
        elapsed = (time.perf_counter() - start) * 1000.0
        return True, round(elapsed, 1)
    except Exception:
        return False, 0.0

async def check_web_service(url: str, timeout: float = 3.0) -> tuple[bool, int, float]:
    """Comprueba un aplicativo web vía HTTP/HTTPS con soporte TLS 1.0+ legacy."""
    if not url:
        return False, 0, 0.0
    start = time.perf_counter()
    try:
        async with httpx.AsyncClient(verify=SSL_PERMISSIVE_CTX, timeout=timeout) as client:
            r = await client.get(url)
            elapsed = (time.perf_counter() - start) * 1000.0
            is_ok = (r.status_code in (200, 301, 302, 304, 307, 308, 401))
            return is_ok, r.status_code, round(elapsed, 1)
    except Exception:
        return False, 0, 0.0

async def check_proxy_service(proxy_str: str, auth_userpass: str = None, test_url: str = "https://core.telegram.org/bots", timeout: float = 3.5) -> tuple[bool, float]:
    """Comprueba la operatividad de un proxy corporativo."""
    if not proxy_str:
        return False, 0.0
    
    proxy_url = f"http://{proxy_str}"
    if auth_userpass and ":" in auth_userpass:
        proxy_url = f"http://{auth_userpass}@{proxy_str}"

    start = time.perf_counter()
    try:
        async with httpx.AsyncClient(proxy=proxy_url, verify=SSL_PERMISSIVE_CTX, timeout=timeout) as client:
            r = await client.get(test_url)
            elapsed = (time.perf_counter() - start) * 1000.0
            return (r.status_code == 200), round(elapsed, 1)
    except Exception:
        return False, 0.0

# --- PROCESAMIENTO GENERAL ---

async def evaluate_service(s: dict) -> dict:
    stype = (s.get("type") or "WEB").upper()
    ip = s.get("host_ip") or ""
    url = s.get("web_url") or ""
    port = s.get("port")
    
    is_up = False
    latency = 0.0
    http_code = None

    if stype == "WEB":
        is_up, http_code, latency = await check_web_service(url or f"http://{ip}")
    elif stype == "LDAP":
        target_port = port if port else 389
        is_up, latency = await check_tcp_port(ip, target_port)
    elif stype == "SMTP":
        target_port = port if port else 25
        is_up, latency = await check_tcp_port(ip, target_port)
    elif stype == "CUPS":
        target_port = port if port else 631
        is_up, latency = await check_tcp_port(ip, target_port)
    elif stype == "DNS":
        is_up, latency = await check_tcp_port(ip, 53)
    elif stype == "PROXY":
        proxy_target = ip
        if ":" not in proxy_target and proxy_target:
            proxy_target = f"{proxy_target}:{port or 8080}"
        is_up, latency = await check_proxy_service(proxy_target, s.get("credentials"))
    else: # PING / OTRO
        is_up, latency = await check_ping(ip)

    return {
        "id": s["id"],
        "letter": s["letter"],
        "name": s["name"],
        "type": stype,
        "host_ip": ip,
        "web_url": url,
        "status": "ACTIVO" if is_up else "APAGADO",
        "is_up": is_up,
        "latency_ms": latency,
        "http_code": http_code,
    }

async def evaluate_site(site: dict, devices: list) -> dict:
    ip = site.get("ip") or ""
    is_up, latency = await check_ping(ip)

    # Evaluar dispositivos secundarios concurrentemente (solo configurados y activos)
    valid_devices = [d for d in devices if d.get("is_active") and "NO CONFIGURADO" not in (d.get("name") or "").upper() and d.get("ip") not in ("0.0.0.0", "127.0.0.1", "")]
    dev_tasks = [check_ping(d.get("ip")) for d in valid_devices]
    dev_results = await asyncio.gather(*dev_tasks) if dev_tasks else []

    evaluated_devices = []
    for idx, d in enumerate(valid_devices):
        d_up, d_lat = dev_results[idx]
        evaluated_devices.append({
            "id": d["id"],
            "device_number": d["device_number"],
            "name": d["name"],
            "ip": d["ip"],
            "status": "ACTIVO" if d_up else "APAGADO",
            "is_up": d_up,
            "latency_ms": d_lat
        })

    return {
        "id": site["id"],
        "letter": site["letter"],
        "name": site["name"],
        "ip": ip,
        "address": site.get("address") or "",
        "status": "ACTIVO" if is_up else "APAGADO",
        "is_up": is_up,
        "latency_ms": latency,
        "devices": evaluated_devices,
    }

async def evaluate_proxy(p: dict) -> dict:
    is_up, latency = await check_proxy_service(p.get("ip_port"), p.get("auth_userpass"), p.get("test_url"))
    return {
        "id": p["id"],
        "letter": p["letter"],
        "name": p["name"],
        "ip_port": p.get("ip_port"),
        "status": "ACTIVO" if is_up else "APAGADO",
        "is_up": is_up,
        "latency_ms": latency
    }

async def evaluate_network_device(d: dict) -> dict:
    is_up, latency = await check_ping(d.get("ip"))
    return {
        "id": d["id"],
        "device_number": d["device_number"],
        "name": d["name"],
        "ip": d["ip"],
        "mac": d.get("mac") or "",
        "vendor_data": d.get("vendor_data") or "",
        "access_type": d.get("access_type") or "SIN SOPORTE",
        "access_port": d.get("access_port"),
        "normal_state_msg": d.get("normal_state_msg") or "",
        "error_state_msg": d.get("error_state_msg") or "",
        "status": "ACTIVO" if is_up else "APAGADO",
        "is_up": is_up,
        "latency_ms": latency
    }

def sync_conf_to_db():
    """
    Sincroniza automáticamente /config/monitoreo.conf y bot.conf con MySQL.
    Permite que cualquier cambio manual en los archivos físicos .conf se refleje
    de inmediato en la base de datos y en el sitio web en cada ciclo de escaneo.
    """
    conf_path = BASE_DIR / "config" / "monitoreo.conf"
    bot_conf_path = BASE_DIR / "config" / "bot.conf"

    if not conf_path.exists():
        return

    content = conf_path.read_text(encoding="utf-8", errors="ignore")
    data = {}
    for line in content.splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, v = line.split("=", 1)
            data[k.strip()] = v.strip().strip("\"'").strip("'")

    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            # 1. SERVICIOS (A..Z)
            for i, letter in enumerate([chr(c) for c in range(ord("A"), ord("Z") + 1)]):
                name = data.get(f"NAMESERVICE{letter}")
                if not name:
                    continue
                stype = data.get(f"TYPESERVICE{letter}", "WEB")
                ip = data.get(f"IPSERVICE{letter}") or "0.0.0.0"
                web = data.get(f"WEBSERVICE{letter}") or ""
                cups = data.get(f"CUPSPORTIP{letter}") or ""
                ldap = data.get(f"LDAPPORTIP{letter}") or ""
                smtp = data.get(f"SMTPPORT{letter}") or ""
                iface = data.get(f"NETINTERFACE{letter}") or "eno1"
                dns = data.get(f"TESTHOSTDNS{letter}") or ""
                proxy_auth = data.get(f"PROXYUSERPASSW{letter}") or ""
                normal_msg = data.get(f"NORMALESTATEMSG{letter}") or ""
                error_msg = data.get(f"ERRORESTATEMSG{letter}") or ""

                if stype == "LDAP":
                    port_val = int(ldap) if ldap and ldap.isdigit() else 389
                elif stype == "SMTP":
                    port_val = int(smtp) if smtp and smtp.isdigit() else 25
                elif stype == "CUPS":
                    port_val = int(cups.split(":")[1]) if (":" in cups and cups.split(":")[1].isdigit()) else (int(cups) if cups.isdigit() else 631)
                elif stype == "DNS":
                    port_val = 53
                elif stype == "PROXY":
                    proxy_cfg = data.get(f"PROXYIPPORT{letter}") or ""
                    port_val = int(proxy_cfg.split(":")[1]) if (":" in proxy_cfg and proxy_cfg.split(":")[1].isdigit()) else 8080
                else:
                    port_val = None
                
                is_active = (
                    "NO CONFIGURADO" not in name.upper() 
                    and stype.upper() != "DESACTIVADO" 
                    and (ip not in ("0.0.0.0", "127.0.0.1", "") or (web != "" and "127.0.0.1" not in web))
                )

                sql = """
                    INSERT INTO monitored_services (letter, name, type, host_ip, web_url, port, credentials, check_interface, dns_test_domain, normal_state_msg, error_state_msg, is_active, sort_order, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    name = VALUES(name), type = VALUES(type), host_ip = VALUES(host_ip), web_url = VALUES(web_url), port = VALUES(port),
                    credentials = VALUES(credentials), check_interface = VALUES(check_interface), dns_test_domain = VALUES(dns_test_domain),
                    normal_state_msg = VALUES(normal_state_msg), error_state_msg = VALUES(error_state_msg), is_active = VALUES(is_active), updated_at = NOW()
                """
                cursor.execute(sql, (letter, name, stype, ip, web, port_val, proxy_auth, iface, dns, normal_msg, error_msg, 1 if is_active else 0, i))

            # 2. SEDES Y EQUIPOS (A..H)
            for i, letter in enumerate(["A", "B", "C", "D", "E", "F", "G", "H"]):
                name = data.get(f"NAMESITE{letter}")
                if not name:
                    continue
                ip = data.get(f"IPSITE{letter}") or "0.0.0.0"
                phones = [data.get(f"SITE{letter}TELEFONO{n}") or "" for n in range(1, 9)]
                addr = data.get(f"SITE{letter}DIRECCION") or ""
                normal_msg = data.get(f"NORMALSITE{letter}") or ""
                error_msg = data.get(f"ERRORSITE{letter}") or ""

                is_site_active = ("NO CONFIGURADO" not in name.upper() and ip not in ("0.0.0.0", "127.0.0.1", ""))

                sql_site = """
                    INSERT INTO monitored_sites (letter, name, ip, phone_1, phone_2, phone_3, phone_4, phone_5, phone_6, phone_7, phone_8, address, normal_state_msg, error_state_msg, is_active, sort_order, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    name = VALUES(name), ip = VALUES(ip), phone_1 = VALUES(phone_1), phone_2 = VALUES(phone_2), phone_3 = VALUES(phone_3),
                    phone_4 = VALUES(phone_4), phone_5 = VALUES(phone_5), phone_6 = VALUES(phone_6), phone_7 = VALUES(phone_7), phone_8 = VALUES(phone_8),
                    address = VALUES(address), normal_state_msg = VALUES(normal_state_msg), error_state_msg = VALUES(error_state_msg), is_active = VALUES(is_active), updated_at = NOW()
                """
                cursor.execute(sql_site, (letter, name, ip, phones[0], phones[1], phones[2], phones[3], phones[4], phones[5], phones[6], phones[7], addr, normal_msg, error_msg, 1 if is_site_active else 0, i))
                
                cursor.execute("SELECT id FROM monitored_sites WHERE letter = %s", (letter,))
                site_row = cursor.fetchone()
                if site_row:
                    site_id = site_row["id"]
                    for dev_num in range(1, 9):
                        dev_name = data.get(f"NAMESITE{letter}EQUIPO{dev_num}") or f"Equipo {dev_num}"
                        dev_ip = data.get(f"IPSITE{letter}EQUIPO{dev_num}") or "0.0.0.0"
                        dev_norm = data.get(f"NORMALSITE{letter}EQUIPO{dev_num}") or ""
                        dev_err = data.get(f"ERRORSITE{letter}EQUIPO{dev_num}") or ""
                        
                        is_dev_active = (is_site_active and "NO CONFIGURADO" not in dev_name.upper() and dev_ip not in ("0.0.0.0", "127.0.0.1", ""))

                        sql_dev = """
                            INSERT INTO monitored_site_devices (monitored_site_id, device_number, name, ip, normal_state_msg, error_state_msg, is_active, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE
                            name = VALUES(name), ip = VALUES(ip), normal_state_msg = VALUES(normal_state_msg), error_state_msg = VALUES(error_state_msg), is_active = VALUES(is_active), updated_at = NOW()
                        """
                        cursor.execute(sql_dev, (site_id, dev_num, dev_name, dev_ip, dev_norm, dev_err, 1 if is_dev_active else 0))

            # 3. PROXIES (bot.conf)
            if bot_conf_path.exists():
                bot_content = bot_conf_path.read_text(encoding="utf-8", errors="ignore")
                bdata = {}
                for line in bot_content.splitlines():
                    line = line.strip()
                    if line and not line.startswith("#") and "=" in line:
                        k, v = line.split("=", 1)
                        bdata[k.strip()] = v.strip().strip("\"'").strip("'")

                for letter in ["A", "B", "C", "D"]:
                    pname = bdata.get(f"NAMEPROXY{letter}")
                    pipport = bdata.get(f"IPADDRPORTPROXY{letter}")
                    pauth = bdata.get(f"USERPASSWDPROXY{letter}")
                    if pname and pipport:
                        is_p_active = ("NO CONFIGURADO" not in pname.upper() and pipport != "")
                        sql_p = """
                            INSERT INTO monitored_proxies (letter, name, ip_port, auth_userpass, test_url, is_active, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE
                            name = VALUES(name), ip_port = VALUES(ip_port), auth_userpass = VALUES(auth_userpass), is_active = VALUES(is_active), updated_at = NOW()
                        """
                        cursor.execute(sql_p, (letter, pname, pipport, pauth, 'https://core.telegram.org/bots', 1 if is_p_active else 0))

            # 4. DISPOSITIVOS EN RED VALLE SECO (monitoreo.conf DISPOSITIVO1..30)
            for dev_num in range(1, 31):
                dname = data.get(f"DISPOSITIVO{dev_num}_NAME")
                dip = data.get(f"DISPOSITIVO{dev_num}_IP")
                if not dname or not dip:
                    continue
                dmac = data.get(f"DISPOSITIVO{dev_num}_MAC") or ""
                ddatos = data.get(f"DISPOSITIVO{dev_num}_DATOS") or ""
                daccess = (data.get(f"DISPOSITIVO{dev_num}_ACCESS") or "SIN SOPORTE").upper()
                dport_raw = data.get(f"DISPOSITIVO{dev_num}_PORT")
                if dport_raw and str(dport_raw).strip().isdigit():
                    dport = int(str(dport_raw).strip())
                elif daccess == "TELNET":
                    dport = 23
                elif daccess == "WEB":
                    dport = 80
                elif daccess == "VNC":
                    dport = 5900
                else:
                    dport = None

                dnorm = data.get(f"DISPOSITIVO{dev_num}_NORMAL") or ""
                derr = data.get(f"DISPOSITIVO{dev_num}_ERROR") or ""

                is_dev_active = ("NO CONFIGURADO" not in dname.upper() and dip not in ("0.0.0.0", "127.0.0.1", ""))

                sql_net = """
                    INSERT INTO monitored_network_devices (device_number, name, ip, mac, vendor_data, access_type, access_port, normal_state_msg, error_state_msg, is_active, sort_order, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    device_number = VALUES(device_number), name = VALUES(name), mac = VALUES(mac), vendor_data = VALUES(vendor_data),
                    access_type = VALUES(access_type), access_port = VALUES(access_port),
                    normal_state_msg = VALUES(normal_state_msg), error_state_msg = VALUES(error_state_msg), is_active = VALUES(is_active), sort_order = VALUES(sort_order), updated_at = NOW()
                """
                cursor.execute(sql_net, (dev_num, dname, dip, dmac, ddatos, daccess, dport, dnorm, derr, 1 if is_dev_active else 0, dev_num))
    finally:
        conn.close()

async def run_full_scan():
    start_time = time.perf_counter()
    # Sincronizar archivos .conf físicos con MySQL automáticamente
    try:
        sync_conf_to_db()
    except Exception as e:
        print(f"⚠️ Error sincronizando conf a DB: {e}", file=sys.stderr)

    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM monitored_services WHERE is_active = 1 AND name NOT LIKE '%NO CONFIGURADO%' AND (host_ip != '0.0.0.0' OR web_url != '') ORDER BY sort_order")
            services_db = cursor.fetchall()

            cursor.execute("SELECT * FROM monitored_sites WHERE is_active = 1 AND name NOT LIKE '%NO CONFIGURADO%' AND ip != '0.0.0.0' ORDER BY sort_order")
            sites_db = cursor.fetchall()

            cursor.execute("SELECT * FROM monitored_site_devices WHERE is_active = 1 AND name NOT LIKE '%NO CONFIGURADO%' AND ip != '0.0.0.0' ORDER BY device_number")
            devices_db = cursor.fetchall()

            cursor.execute("SELECT * FROM monitored_proxies WHERE is_active = 1 AND name NOT LIKE '%NO CONFIGURADO%' AND ip_port != '' ORDER BY letter")
            proxies_db = cursor.fetchall()

            cursor.execute("SELECT * FROM monitored_network_devices WHERE is_active = 1 AND name NOT LIKE '%NO CONFIGURADO%' AND ip != '0.0.0.0' ORDER BY sort_order")
            net_devices_db = cursor.fetchall()
    finally:
        conn.close()

    # Mapear dispositivos por site_id
    devices_by_site = {}
    for d in devices_db:
        devices_by_site.setdefault(d["monitored_site_id"], []).append(d)

    # Lanzar tareas concurrentes
    service_tasks = [evaluate_service(s) for s in services_db]
    site_tasks = [evaluate_site(st, devices_by_site.get(st["id"], [])) for st in sites_db]
    proxy_tasks = [evaluate_proxy(p) for p in proxies_db]
    net_device_tasks = [evaluate_network_device(d) for d in net_devices_db]

    all_services, all_sites, all_proxies, all_net_devices = await asyncio.gather(
        asyncio.gather(*service_tasks),
        asyncio.gather(*site_tasks),
        asyncio.gather(*proxy_tasks),
        asyncio.gather(*net_device_tasks)
    )

    # Calcular métricas y estado global
    serv_online = sum(1 for s in all_services if s["is_up"])
    serv_total = len(all_services)

    sites_online = sum(1 for st in all_sites if st["is_up"])
    sites_total = len(all_sites)

    proxies_online = sum(1 for p in all_proxies if p["is_up"])
    proxies_total = len(all_proxies)

    net_online = sum(1 for d in all_net_devices if d["is_up"])
    net_total = len(all_net_devices)

    global_status = "OPERACIONAL"
    if serv_online < (serv_total * 0.7) or sites_online < (sites_total * 0.7):
        global_status = "CRITICO"
    elif serv_online < serv_total or sites_online < sites_total:
        global_status = "DEGRADADO"

    total_duration = round((time.perf_counter() - start_time), 2)
    timestamp_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    snapshot_payload = {
        "timestamp": timestamp_str,
        "duration_seconds": total_duration,
        "global_status": global_status,
        "summary": {
            "services_online": serv_online,
            "services_total": serv_total,
            "sites_online": sites_online,
            "sites_total": sites_total,
            "proxies_online": proxies_online,
            "proxies_total": proxies_total,
            "network_devices_online": net_online,
            "network_devices_total": net_total,
        },
        "services": all_services,
        "sites": all_sites,
        "proxies": all_proxies,
        "network_devices": all_net_devices
    }

    # Guardar en archivo JSON estático de Laravel
    SNAPSHOT_FILE.parent.mkdir(parents=True, exist_ok=True)
    SNAPSHOT_FILE.write_text(json.dumps(snapshot_payload, indent=2, ensure_ascii=False), encoding="utf-8")
    try:
        os.chmod(SNAPSHOT_FILE, 0o666)
    except Exception:
        pass

    # Guardar en base de datos MySQL
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            # 1. Snapshot global
            sql = """
                INSERT INTO monitoring_snapshots 
                (global_status, services_online, services_total, sites_online, sites_total, proxies_online, proxies_total, payload_json, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
            """
            cursor.execute(sql, (
                global_status,
                serv_online,
                serv_total,
                sites_online,
                sites_total,
                proxies_online,
                proxies_total,
                json.dumps(snapshot_payload)
            ))
            # Mantener solo los últimos 100 snapshots para ahorrar espacio
            cursor.execute("DELETE FROM monitoring_snapshots WHERE id NOT IN (SELECT id FROM (SELECT id FROM monitoring_snapshots ORDER BY id DESC LIMIT 100) AS t)")

            # 2. Histórico de chequeos individuales por servicio
            hist_sql = """
                INSERT INTO service_check_histories 
                (monitored_service_id, is_up, latency_ms, http_code, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            hist_records = []
            for s in all_services:
                if "id" in s and s["id"]:
                    hist_records.append((
                        s["id"],
                        1 if s.get("is_up") else 0,
                        float(s.get("latency_ms", 0.0) or 0.0),
                        str(s.get("http_code") or "")[:10],
                        str(s.get("status") or "")[:255]
                    ))
            if hist_records:
                cursor.executemany(hist_sql, hist_records)

            # Mantener retención de últimos 30 días de historial de servicios
            cursor.execute("DELETE FROM service_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

            # 3. Histórico de chequeos individuales por sede
            site_hist_sql = """
                INSERT INTO site_check_histories 
                (monitored_site_id, is_up, latency_ms, devices_online, devices_total, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            site_hist_records = []
            for st in all_sites:
                if "id" in st and st["id"]:
                    devs = st.get("devices", [])
                    devs_online = sum(1 for d in devs if d.get("is_up"))
                    devs_total = len(devs)
                    status_msg = "Enlace Operativo" if st.get("is_up") else "Enlace Caído / Timeout"
                    site_hist_records.append((
                        st["id"],
                        1 if st.get("is_up") else 0,
                        float(st.get("latency_ms", 0.0) or 0.0),
                        devs_online,
                        devs_total,
                        status_msg
                    ))
            if site_hist_records:
                cursor.executemany(site_hist_sql, site_hist_records)

            # Mantener retención de últimos 30 días de historial de sedes
            cursor.execute("DELETE FROM site_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

            # 4. Histórico de chequeos individuales por proxy
            proxy_hist_sql = """
                INSERT INTO proxy_check_histories 
                (monitored_proxy_id, is_up, latency_ms, http_code, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            proxy_hist_records = []
            for p in all_proxies:
                if "id" in p and p["id"]:
                    status_msg = "Proxy Operativo / Respondiendo" if p.get("is_up") else "Proxy Inaccesible / Falló Túnel"
                    proxy_hist_records.append((
                        p["id"],
                        1 if p.get("is_up") else 0,
                        float(p.get("latency_ms", 0.0) or 0.0),
                        "200" if p.get("is_up") else None,
                        status_msg
                    ))
            if proxy_hist_records:
                cursor.executemany(proxy_hist_sql, proxy_hist_records)

            # Mantener retención de últimos 30 días de historial de proxies
            cursor.execute("DELETE FROM proxy_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

            # 5. Histórico de chequeos individuales por dispositivo de red Valle Seco
            net_hist_sql = """
                INSERT INTO network_device_check_histories 
                (monitored_network_device_id, is_up, latency_ms, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            net_hist_records = []
            for nd in all_net_devices:
                if "id" in nd and nd["id"]:
                    status_msg = "Dispositivo Operativo / Enlace Activo" if nd.get("is_up") else "Dispositivo Caído / Inalcanzable"
                    net_hist_records.append((
                        nd["id"],
                        1 if nd.get("is_up") else 0,
                        float(nd.get("latency_ms", 0.0) or 0.0),
                        status_msg
                    ))
            if net_hist_records:
                cursor.executemany(net_hist_sql, net_hist_records)

            # Mantener retención de últimos 30 días de historial de dispositivos de red
            cursor.execute("DELETE FROM network_device_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")
    finally:
        conn.close()

    print(f"✅ Escaneo completado en {total_duration}s. Estado: {global_status} | Servicios: {serv_online}/{serv_total} | Sedes: {sites_online}/{sites_total} | Proxies: {proxies_online}/{proxies_total} | Disp. Valle Seco: {net_online}/{net_total}")

if __name__ == "__main__":
    asyncio.run(run_full_scan())
