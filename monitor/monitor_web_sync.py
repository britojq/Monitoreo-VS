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
APP_DIR = Path("/var/www/testapp")
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
        "DB_DATABASE": "testapp",
        "DB_USERNAME": "testapp",
        "DB_PASSWORD": "12345678",
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
        user=ENV.get("DB_USERNAME", "testapp"),
        password=ENV.get("DB_PASSWORD", "12345678"),
        database=ENV.get("DB_DATABASE", "testapp"),
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
    elif stype in ("SMTP", "LDAP", "CUPS"):
        default_ports = {"SMTP": 25, "LDAP": 389, "CUPS": 631}
        target_port = port if port else default_ports.get(stype, 80)
        is_up, latency = await check_tcp_port(ip, target_port)
    elif stype == "DNS":
        is_up, latency = await check_tcp_port(ip, 53)
    elif stype == "PROXY":
        is_up, latency = await check_proxy_service(s.get("host_ip") or f"{ip}:{port or 8080}", s.get("credentials"))
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

                port_val = int(smtp) if smtp and smtp.isdigit() else (int(ldap) if ldap and ldap.isdigit() else (int(cups) if cups and cups.isdigit() else None))
                
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

    all_services, all_sites, all_proxies = await asyncio.gather(
        asyncio.gather(*service_tasks),
        asyncio.gather(*site_tasks),
        asyncio.gather(*proxy_tasks)
    )

    # Calcular métricas y estado global
    serv_online = sum(1 for s in all_services if s["is_up"])
    serv_total = len(all_services)

    sites_online = sum(1 for st in all_sites if st["is_up"])
    sites_total = len(all_sites)

    proxies_online = sum(1 for p in all_proxies if p["is_up"])
    proxies_total = len(all_proxies)

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
        },
        "services": all_services,
        "sites": all_sites,
        "proxies": all_proxies
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
    finally:
        conn.close()

    print(f"✅ Escaneo completado en {total_duration}s. Estado: {global_status} | Servicios: {serv_online}/{serv_total} | Sedes: {sites_online}/{sites_total} | Proxies: {proxies_online}/{proxies_total}")

if __name__ == "__main__":
    asyncio.run(run_full_scan())
