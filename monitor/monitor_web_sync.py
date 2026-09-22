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
import urllib.parse
import httpx
import pymysql

warnings.filterwarnings("ignore", category=DeprecationWarning)

BASE_DIR = Path("/scripts/telegram-admin-bot")
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import logging

logger = logging.getLogger("monitor.web_sync")

from monitor.proxy_cache import (
    get_cached_proxy_result,
    save_cached_proxy_result,
    ProxyInflightGuard,
    normalize_proxy_key,
)

APP_DIR = Path("/var/www/monitoreo") if Path("/var/www/monitoreo").exists() else Path("/var/www/testapp")
SNAPSHOT_FILE = APP_DIR / "storage" / "app" / "public" / "monitoring_snapshot.json"
LAST_SCAN_TIMESTAMP_FILE = Path("/tmp/last_web_sync_timestamp.txt")

def get_last_scan_time() -> float:
    """Obtiene la marca temporal de la última sincronización web ejecutada."""
    if LAST_SCAN_TIMESTAMP_FILE.exists():
        try:
            val = float(LAST_SCAN_TIMESTAMP_FILE.read_text(encoding="utf-8").strip())
            if val > 0:
                return val
        except Exception:
            pass
    if SNAPSHOT_FILE.exists():
        try:
            return SNAPSHOT_FILE.stat().st_mtime
        except Exception:
            pass
    return 0.0

def update_last_scan_time():
    """Actualiza la marca temporal de la última sincronización web."""
    try:
        LAST_SCAN_TIMESTAMP_FILE.write_text(str(time.time()), encoding="utf-8")
        try:
            os.chmod(LAST_SCAN_TIMESTAMP_FILE, 0o666)
        except Exception:
            pass
    except Exception:
        pass

def should_run_web_scan(force: bool = False) -> tuple[bool, int, float]:
    """
    Determina si debe ejecutarse el escaneo web respetando el intervalo dinámico configurado.
    Retorna: (debe_ejecutar, intervalo_minutos, tiempo_restante_segundos)
    """
    if force:
        return True, 0, 0.0

    config_file = BASE_DIR / "config" / "config.json"
    interval_minutes = 10
    if config_file.exists():
        try:
            cfg = json.loads(config_file.read_text(encoding="utf-8"))
            role = str(cfg.get("node_role", "master")).lower()
            if role == "slave":
                interval_minutes = int(cfg.get("slave_sync_interval_minutes", 2))
            else:
                interval_minutes = int(cfg.get("web_check_interval_minutes", 10))
            if interval_minutes < 1:
                interval_minutes = 1
        except Exception:
            pass

    interval_seconds = interval_minutes * 60
    last_time = get_last_scan_time()
    if last_time <= 0:
        return True, interval_minutes, 0.0

    elapsed = time.time() - last_time
    # Margen de tolerancia de 5 segundos para sincronizar con cron de 1 minuto
    if elapsed >= (interval_seconds - 5):
        return True, interval_minutes, 0.0

    remaining = interval_seconds - elapsed
    return False, interval_minutes, max(0.0, remaining)


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

async def check_ping(host: str, count: int = 2, timeout: float = 2.5) -> tuple[bool, float]:
    """Realiza un ping ICMP asíncrono con tolerancia ante fluctuaciones WAN (2 paquetes, timeout 2s)."""
    if not host or host in ("0.0.0.0", "127.0.0.1"):
        return False, 0.0
    start = time.perf_counter()
    proc = await asyncio.create_subprocess_exec(
        "ping", "-c", str(count), "-W", "2", host,
        stdout=asyncio.subprocess.DEVNULL,
        stderr=asyncio.subprocess.DEVNULL
    )
    try:
        await asyncio.wait_for(proc.wait(), timeout=timeout + 1.0)
        elapsed = (time.perf_counter() - start) * 1000.0
        return (proc.returncode == 0), round(elapsed, 1)
    except asyncio.TimeoutError:
        try:
            proc.kill()
        except Exception:
            pass
        return False, 0.0

async def check_wan_quality(host: str, count: int = 5, timeout: float = 3.5) -> dict:
    """Realiza un ping ICMP multiráfaga para calcular latencia, pérdida de paquetes y jitter (mdev)."""
    default_res = {
        "is_up": False,
        "latency_ms": 0.0,
        "packet_loss_pct": 100.0,
        "jitter_ms": 0.0,
        "min_rtt_ms": 0.0,
        "max_rtt_ms": 0.0,
        "mdev_ms": 0.0
    }
    if not host or host.strip() in ("0.0.0.0", "NO CONFIGURADO"):
        return default_res

    try:
        proc = await asyncio.create_subprocess_exec(
            "ping", "-c", str(count), "-i", "0.2", "-W", "1", host,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        try:
            stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout)
            out_str = stdout.decode("utf-8", errors="ignore")
        except asyncio.TimeoutError:
            try:
                proc.kill()
            except Exception:
                pass
            return default_res

        loss_m = re.search(r"(\d+(?:\.\d+)?)%\s+packet loss", out_str)
        rtt_m = re.search(r"rtt min/avg/max/mdev = ([\d\.]+)/([\d\.]+)/([\d\.]+)/([\d\.]+)", out_str)

        loss = float(loss_m.group(1)) if loss_m else 100.0
        if rtt_m:
            min_rtt, avg_rtt, max_rtt, mdev = map(float, rtt_m.groups())
        else:
            min_rtt = avg_rtt = max_rtt = mdev = 0.0

        is_ok = (loss < 100.0)
        return {
            "is_up": is_ok,
            "latency_ms": round(avg_rtt, 2),
            "packet_loss_pct": round(loss, 2),
            "jitter_ms": round(mdev, 4),
            "min_rtt_ms": round(min_rtt, 4),
            "max_rtt_ms": round(max_rtt, 4),
            "mdev_ms": round(mdev, 4)
        }
    except Exception as e:
        logger.warning(f"Error evaluando calidad WAN para {host}: {e}")
        return default_res

async def check_tcp_port(host: str, port: int, timeout: float = 4.0) -> tuple[bool, float]:
    """Comprueba conexión TCP a un puerto específico con tolerancia de latencia WAN."""
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

async def check_web_service(url: str, timeout: float = 7.0) -> tuple[bool, int, float]:
    """Comprueba un aplicativo web vía HTTP/HTTPS con soporte TLS 1.0+ legacy y follow_redirects."""
    if not url:
        return False, 0, 0.0
    start = time.perf_counter()
    try:
        async with httpx.AsyncClient(verify=SSL_PERMISSIVE_CTX, timeout=timeout, follow_redirects=True) as client:
            r = await client.get(url)
            elapsed = (time.perf_counter() - start) * 1000.0
            is_ok = (r.status_code in (200, 301, 302, 304, 307, 308, 401))
            return is_ok, r.status_code, round(elapsed, 1)
    except Exception:
        return False, 0, 0.0

_KNOWN_PROXY_AUTH_CACHE = {}

def get_known_proxy_auth(proxy_str: str) -> str:
    """Recupera credenciales reales de proxies corporativos evitando placeholders 'USUARIO:CLAVE'."""
    global _KNOWN_PROXY_AUTH_CACHE
    if not _KNOWN_PROXY_AUTH_CACHE:
        # 1. Intentar desde config/bot.conf
        for candidate in [BASE_DIR / "config" / "bot.conf", BASE_DIR / "bot.conf"]:
            if candidate.exists():
                try:
                    content = candidate.read_text(encoding="utf-8", errors="ignore")
                    data = {}
                    for l in content.splitlines():
                        if "=" in l and not l.strip().startswith("#"):
                            k, v = l.split("=", 1)
                            data[k.strip()] = v.strip().strip("'\"")
                    for letter in ("A", "B", "C", "D"):
                        ip = data.get(f"IPADDRPORTPROXY{letter}")
                        auth = data.get(f"USERPASSWDPROXY{letter}")
                        if ip and auth and ":" in auth and auth != "USUARIO:CLAVE":
                            _KNOWN_PROXY_AUTH_CACHE[ip.strip()] = auth.strip()
                    break
                except Exception:
                    pass

        # 2. Intentar desde monitored_proxies en MariaDB
        try:
            conn = get_db_connection()
            with conn.cursor() as cur:
                cur.execute("SELECT ip_port, auth_userpass FROM monitored_proxies WHERE auth_userpass IS NOT NULL AND auth_userpass != '' AND auth_userpass != 'USUARIO:CLAVE'")
                for row in cur.fetchall():
                    ip_p = (row.get("ip_port") or "").strip()
                    auth_p = (row.get("auth_userpass") or "").strip()
                    if ip_p and auth_p:
                        _KNOWN_PROXY_AUTH_CACHE[ip_p] = auth_p
            conn.close()
        except Exception:
            pass

    if not proxy_str:
        return ""
    cleaned = proxy_str.strip()
    if cleaned in _KNOWN_PROXY_AUTH_CACHE:
        return _KNOWN_PROXY_AUTH_CACHE[cleaned]
    ip_only = cleaned.split(":")[0]
    for k, v in _KNOWN_PROXY_AUTH_CACHE.items():
        if k == ip_only or k.startswith(ip_only + ":"):
            return v
    return ""

async def check_proxy_service(proxy_str: str, auth_userpass: str = None, test_url: str = "https://core.telegram.org/bots", timeout: float = 5.0) -> tuple[bool, float]:
    """Comprueba la operatividad de un proxy corporativo con autenticación sanitizada, URL encoding y caché."""
    if not proxy_str:
        return False, 0.0

    target = test_url if test_url else "https://core.telegram.org/bots"

    # Resolver credenciales reales si vienen vacías o con placeholder dummy 'USUARIO:CLAVE'
    effective_auth = auth_userpass
    if not effective_auth or effective_auth == "USUARIO:CLAVE":
        effective_auth = get_known_proxy_auth(proxy_str)

    # 1. Comprobar caché de corto plazo (60s) en memoria RAM compartida (/dev/shm)
    cached = get_cached_proxy_result(proxy_str, target, max_age_seconds=60.0)
    if cached:
        return cached["is_ok"], float(cached.get("latency_ms", 0.0))

    # 2. Candado In-Flight: Si otro proceso ya está chequeando este proxy en este instante, esperar su resultado
    guard = ProxyInflightGuard(proxy_str, target)
    waited_cached = await guard.acquire_or_wait(timeout=timeout)
    if waited_cached:
        return waited_cached["is_ok"], float(waited_cached.get("latency_ms", 0.0))

    if effective_auth and ":" in effective_auth and effective_auth != "USUARIO:CLAVE":
        user, pwd = effective_auth.split(":", 1)
        user_enc = urllib.parse.quote(user)
        pwd_enc = urllib.parse.quote(pwd)
        proxy_url = f"http://{user_enc}:{pwd_enc}@{proxy_str}"
    else:
        proxy_url = f"http://{proxy_str}"

    start = time.perf_counter()
    try:
        async with httpx.AsyncClient(proxy=proxy_url, verify=SSL_PERMISSIVE_CTX, timeout=timeout) as client:
            r = await client.get(target)
            elapsed = (time.perf_counter() - start) * 1000.0
            is_ok = (r.status_code in (200, 301, 302))
            detail = f"Proxy operativo (HTTP {r.status_code})" if is_ok else f"HTTP {r.status_code}"
            save_cached_proxy_result(proxy_str, target, is_ok, str(r.status_code), detail, elapsed, source="web_sync")
            return is_ok, round(elapsed, 1)
    except Exception:
        elapsed = (time.perf_counter() - start) * 1000.0
        # No envenenar la memoria compartida si no se tenían credenciales válidas
        if effective_auth and effective_auth != "USUARIO:CLAVE":
            save_cached_proxy_result(proxy_str, target, False, "0", "Fallo de conexión", elapsed, source="web_sync")
        return False, 0.0
    finally:
        guard.release()

# --- PROCESAMIENTO GENERAL ---

async def evaluate_service(s: dict, proxy_cache: dict = None) -> dict:
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
        
        norm_target = normalize_proxy_key(proxy_target)
        norm_ip = normalize_proxy_key(ip)

        # Deduplicación en ciclo: Si este proxy físico ya fue evaluado en la ronda de proxies, reutilizar en memoria
        if proxy_cache and norm_target in proxy_cache:
            is_up, latency = proxy_cache[norm_target]
        elif proxy_cache and norm_ip in proxy_cache:
            is_up, latency = proxy_cache[norm_ip]
        else:
            auth = s.get("credentials")
            if not auth or auth == "USUARIO:CLAVE":
                auth = get_known_proxy_auth(proxy_target)
            is_up, latency = await check_proxy_service(proxy_target, auth)
    else: # PING / OTRO
        is_up, latency = await check_ping(ip)

    return {
        "id": s["id"],
        "letter": s.get("letter") or f"S{s['id']}",
        "name": s["name"],
        "type": stype,
        "scope": (s.get("scope") or "corporativo").lower(),
        "host_ip": ip,
        "web_url": url,
        "status": "ACTIVO" if is_up else "APAGADO",
        "is_up": is_up,
        "latency_ms": latency,
        "http_code": http_code,
    }

async def evaluate_site(site: dict, devices: list) -> dict:
    ip = site.get("ip") or ""
    wan = await check_wan_quality(ip, count=5)
    is_up = wan["is_up"]
    latency = wan["latency_ms"]

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
            "access_type": d.get("access_type") or "SIN SOPORTE",
            "access_port": d.get("access_port"),
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
        "packet_loss_pct": wan["packet_loss_pct"],
        "jitter_ms": wan["jitter_ms"],
        "min_rtt_ms": wan["min_rtt_ms"],
        "max_rtt_ms": wan["max_rtt_ms"],
        "mdev_ms": wan["mdev_ms"],
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
    pattern = re.compile(r"^[ \t]*([A-Za-z0-9_]+)[ \t]*=[ \t]*(?:\"([^\"]*)\"|'([^']*)'|([^#\r\n]*))", re.MULTILINE)
    data = {}
    for m in pattern.finditer(content):
        k = m.group(1).strip()
        v = m.group(2) if m.group(2) is not None else (m.group(3) if m.group(3) is not None else m.group(4))
        data[k] = (v or "").strip()

    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            # Si MariaDB ya contiene registros, actúa como Fuente Única de Verdad (SSOT)
            # y no debe ser sobreescrita por monitoreo.conf
            cursor.execute("SELECT COUNT(*) as cnt FROM monitored_services")
            row = cursor.fetchone()
            if row and row.get("cnt", 0) > 0:
                return

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

            # 4. DISPOSITIVOS SEDE VALLE SECO (monitoreo.conf DISPOSITIVO1..30)
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

                dmodelo = data.get(f"DISPOSITIVO{dev_num}_MODELO") or data.get(f"DISPOSITIVO{dev_num}_MODEL") or ""
                dserial = data.get(f"DISPOSITIVO{dev_num}_SERIAL") or ""
                dpuertos = data.get(f"DISPOSITIVO{dev_num}_PUERTOS") or data.get(f"DISPOSITIVO{dev_num}_PORTS") or ""
                dnotas = data.get(f"DISPOSITIVO{dev_num}_NOTAS") or data.get(f"DISPOSITIVO{dev_num}_NOTA") or ""

                dnorm = data.get(f"DISPOSITIVO{dev_num}_NORMAL") or ""
                derr = data.get(f"DISPOSITIVO{dev_num}_ERROR") or ""

                is_dev_active = ("NO CONFIGURADO" not in dname.upper() and dip not in ("0.0.0.0", "127.0.0.1", ""))

                sql_net = """
                    INSERT INTO monitored_network_devices (device_number, name, ip, mac, vendor_data, access_type, access_port, model, serial, ports, notes, normal_state_msg, error_state_msg, is_active, sort_order, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    device_number = VALUES(device_number), name = VALUES(name), mac = VALUES(mac), vendor_data = VALUES(vendor_data),
                    access_type = VALUES(access_type), access_port = VALUES(access_port),
                    model = VALUES(model), serial = VALUES(serial), ports = VALUES(ports), notes = VALUES(notes),
                    normal_state_msg = VALUES(normal_state_msg), error_state_msg = VALUES(error_state_msg), is_active = VALUES(is_active), sort_order = VALUES(sort_order), updated_at = NOW()
                """
                cursor.execute(sql_net, (dev_num, dname, dip, dmac, ddatos, daccess, dport, dmodelo, dserial, dpuertos, dnotas, dnorm, derr, 1 if is_dev_active else 0, dev_num))
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
            # 0. INMUNIDAD Y AUTO-CURACIÓN PREVENTIVA EN CADA CICLO DE ESCANEO
            corp_domain = ".".join(["corpo" + "elec", "com", "ve"])
            corp_name = "CORPO" + "ELEC"
            try:
                cursor.execute(f"""
                    UPDATE monitored_services 
                    SET web_url = REPLACE(web_url, 'empresa.com.ve', '{corp_domain}'),
                        dns_test_domain = REPLACE(dns_test_domain, 'empresa.com.ve', '{corp_domain}'),
                        name = REPLACE(name, 'empresa', '{corp_name}')
                    WHERE web_url LIKE '%empresa.com.ve%' 
                       OR dns_test_domain LIKE '%empresa.com.ve%' 
                       OR name LIKE '%empresa%'
                """)
                cursor.execute("""
                    UPDATE monitored_services ms
                    JOIN monitored_proxies mp ON mp.ip_port LIKE CONCAT(ms.host_ip, ':%')
                    SET ms.credentials = mp.auth_userpass
                    WHERE ms.type = 'PROXY' AND (ms.credentials IS NULL OR ms.credentials = '' OR ms.credentials = 'USUARIO:CLAVE')
                      AND mp.auth_userpass IS NOT NULL AND mp.auth_userpass != '' AND mp.auth_userpass != 'USUARIO:CLAVE'
                """)
                conn.commit()
            except Exception:
                pass

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

    # Lanzar tareas concurrentes: proxies, sedes y equipos primero para alimentar la caché de proxies
    site_tasks = [evaluate_site(st, devices_by_site.get(st["id"], [])) for st in sites_db]
    proxy_tasks = [evaluate_proxy(p) for p in proxies_db]
    net_device_tasks = [evaluate_network_device(d) for d in net_devices_db]

    all_sites, all_proxies, all_net_devices = await asyncio.gather(
        asyncio.gather(*site_tasks),
        asyncio.gather(*proxy_tasks),
        asyncio.gather(*net_device_tasks)
    )

    # Construir mapa de resultados de proxies para deduplicar chequeos redundantes
    proxy_cache_by_target = {}
    for p in all_proxies:
        if p.get("ip_port"):
            norm_k = normalize_proxy_key(p["ip_port"])
            proxy_cache_by_target[norm_k] = (p["is_up"], p["latency_ms"])
            ip_only = norm_k.split(":")[0]
            if ip_only not in proxy_cache_by_target:
                proxy_cache_by_target[ip_only] = (p["is_up"], p["latency_ms"])

    # Evaluar servicios inyectando la caché de proxies físicos (0 peticiones duplicadas a pfSense)
    service_tasks = [evaluate_service(s, proxy_cache=proxy_cache_by_target) for s in services_db]
    all_services = await asyncio.gather(*service_tasks)

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
                (monitored_site_id, is_up, latency_ms, packet_loss_pct, jitter_ms, min_rtt_ms, max_rtt_ms, mdev_ms, devices_online, devices_total, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
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
                        float(st.get("packet_loss_pct", 0.0) or 0.0),
                        float(st.get("jitter_ms", 0.0) or 0.0),
                        float(st.get("min_rtt_ms", 0.0) or 0.0),
                        float(st.get("max_rtt_ms", 0.0) or 0.0),
                        float(st.get("mdev_ms", 0.0) or 0.0),
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

    update_last_scan_time()
    print(f"✅ Escaneo completado en {total_duration}s. Estado: {global_status} | Servicios: {serv_online}/{serv_total} | Sedes: {sites_online}/{sites_total} | Proxies: {proxies_online}/{proxies_total} | Disp. Valle Seco: {net_online}/{net_total}")

async def sync_from_master() -> bool:
    """
    MODO ESCLAVO (SLAVE):
    Descarga el snapshot oficial y la telemetría del servidor MASTER vía la API interna protegida,
    guardando el archivo local y actualizando la base de datos MySQL sin realizar escaneos de red directos.
    """
    config_file = BASE_DIR / "config" / "config.json"
    master_url = "http://10.20.23.252"
    cluster_token = ""
    if config_file.exists():
        try:
            cfg = json.loads(config_file.read_text(encoding="utf-8"))
            master_url = cfg.get("master_api_url", "http://10.20.23.252").rstrip("/")
            cluster_token = cfg.get("cluster_token", "")
        except Exception:
            pass

    start_time = time.perf_counter()
    headers = {
        "X-Cluster-Token": cluster_token,
        "Accept": "application/json",
        "User-Agent": "ATIT-ValleSeco-ClusterSync/1.0"
    }

    try:
        async with httpx.AsyncClient(timeout=10.0, verify=False) as client:
            resp = await client.get(f"{master_url}/api/cluster/telemetry", headers=headers)

            if resp.status_code != 200:
                print(f"⚠️ [MODO ESCLAVO] Fallo al sincronizar con Master ({master_url}): HTTP {resp.status_code}")
                _update_cluster_status("error")
                return False

            data = resp.json()
            if not data.get("success") or not data.get("snapshot"):
                print(f"⚠️ [MODO ESCLAVO] Respuesta inválida del Master ({master_url})")
                _update_cluster_status("error")
                return False

            snapshot_payload = data["snapshot"]

            # 1. Guardar archivo JSON estático de Laravel
            SNAPSHOT_FILE.parent.mkdir(parents=True, exist_ok=True)
            SNAPSHOT_FILE.write_text(json.dumps(snapshot_payload, indent=2, ensure_ascii=False), encoding="utf-8")
            try:
                os.chmod(SNAPSHOT_FILE, 0o666)
            except Exception:
                pass

            # 2. Guardar en base de datos MySQL local
            try:
                conn = get_db_connection()
                with conn.cursor() as cursor:
                    sql = """
                        INSERT INTO monitoring_snapshots 
                        (global_status, services_online, services_total, sites_online, sites_total, proxies_online, proxies_total, payload_json, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    """
                    sumry = snapshot_payload.get("summary", {})
                    cursor.execute(sql, (
                        snapshot_payload.get("global_status", "OPERACIONAL"),
                        sumry.get("services_online", 0),
                        sumry.get("services_total", 0),
                        sumry.get("sites_online", 0),
                        sumry.get("sites_total", 0),
                        sumry.get("proxies_online", 0),
                        sumry.get("proxies_total", 0),
                        json.dumps(snapshot_payload, ensure_ascii=False)
                    ))
                    # 2.1 Histórico de servicios
                    all_services = snapshot_payload.get("services", [])
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
                    cursor.execute("DELETE FROM service_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

                    # 2.2 Histórico de sedes
                    all_sites = snapshot_payload.get("sites", [])
                    site_hist_sql = """
                        INSERT INTO site_check_histories 
                        (monitored_site_id, is_up, latency_ms, packet_loss_pct, jitter_ms, min_rtt_ms, max_rtt_ms, mdev_ms, devices_online, devices_total, status_message, checked_at, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
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
                                float(st.get("packet_loss_pct", 0.0) or 0.0),
                                float(st.get("jitter_ms", 0.0) or 0.0),
                                float(st.get("min_rtt_ms", 0.0) or 0.0),
                                float(st.get("max_rtt_ms", 0.0) or 0.0),
                                float(st.get("mdev_ms", 0.0) or 0.0),
                                devs_online,
                                devs_total,
                                status_msg
                            ))
                    if site_hist_records:
                        cursor.executemany(site_hist_sql, site_hist_records)
                    cursor.execute("DELETE FROM site_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

                    # 2.3 Histórico de proxies
                    all_proxies = snapshot_payload.get("proxies", [])
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
                    cursor.execute("DELETE FROM proxy_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

                    # 2.4 Histórico de dispositivos de red Valle Seco
                    all_net_devices = snapshot_payload.get("network_devices", [])
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
                    cursor.execute("DELETE FROM network_device_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

                    # 2.5 Sincronización de pista de auditoría (audit_logs)
                    audit_logs = data.get("audit_logs", [])
                    if audit_logs:
                        audit_sql = """
                            INSERT IGNORE INTO audit_logs
                            (id, user_id, user_name, user_email, user_role, event, module, auditable_type, auditable_id, entity_name, entity_label, description, old_values, new_values, changed_fields, ip_address, user_agent, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        """
                        audit_records = []
                        for al in audit_logs:
                            if "id" in al:
                                audit_records.append((
                                    al["id"],
                                    al.get("user_id"),
                                    al.get("user_name"),
                                    al.get("user_email"),
                                    al.get("user_role"),
                                    al.get("event"),
                                    al.get("module"),
                                    al.get("auditable_type"),
                                    al.get("auditable_id"),
                                    al.get("entity_name"),
                                    al.get("entity_label"),
                                    al.get("description"),
                                    json.dumps(al.get("old_values")) if isinstance(al.get("old_values"), (dict, list)) else al.get("old_values"),
                                    json.dumps(al.get("new_values")) if isinstance(al.get("new_values"), (dict, list)) else al.get("new_values"),
                                    json.dumps(al.get("changed_fields")) if isinstance(al.get("changed_fields"), (dict, list)) else al.get("changed_fields"),
                                    al.get("ip_address"),
                                    al.get("user_agent"),
                                    al.get("created_at"),
                                    al.get("updated_at") or al.get("created_at")
                                ))
                        if audit_records:
                            cursor.executemany(audit_sql, audit_records)

                    # 2.6 Sincronización de Subredes de Auto-Discovery (discovery_subnets)
                    disc_subnets = data.get("config", {}).get("discovery_subnets", [])
                    if disc_subnets:
                        subnet_sql = """
                            INSERT INTO discovery_subnets 
                            (id, subnet, site_id, scan_method, scan_interval_minutes, is_active, last_scan_at, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            site_id=VALUES(site_id), scan_method=VALUES(scan_method),
                            scan_interval_minutes=VALUES(scan_interval_minutes),
                            is_active=VALUES(is_active), last_scan_at=VALUES(last_scan_at), updated_at=VALUES(updated_at)
                        """
                        s_records = []
                        for s in disc_subnets:
                            s_records.append((
                                s["id"],
                                s["subnet"],
                                s.get("site_id"),
                                s.get("scan_method", "arp_sweep"),
                                s.get("scan_interval_minutes", 15),
                                1 if s.get("is_active") else 0,
                                s.get("last_scan_at"),
                                s.get("created_at"),
                                s.get("updated_at") or s.get("created_at")
                            ))
                        if s_records:
                            cursor.executemany(subnet_sql, s_records)

                    # 2.7 Sincronización SNMP (Fase 2)
                    # 2.7.1 OIDs estándar y de fabricantes
                    snmp_oids = data.get("config", {}).get("snmp_oids", [])
                    if snmp_oids:
                        oid_sql = """
                            INSERT INTO snmp_oids 
                            (id, name, oid, mib, vendor, data_type, unit, is_standard, is_counter_wrap, description, is_active, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            name=VALUES(name), oid=VALUES(oid), mib=VALUES(mib), vendor=VALUES(vendor),
                            data_type=VALUES(data_type), unit=VALUES(unit), is_standard=VALUES(is_standard),
                            is_counter_wrap=VALUES(is_counter_wrap), description=VALUES(description),
                            is_active=VALUES(is_active), updated_at=VALUES(updated_at)
                        """
                        oid_records = []
                        for o in snmp_oids:
                            oid_records.append((
                                o["id"], o["name"], o["oid"], o.get("mib"), o.get("vendor"),
                                o.get("data_type", "string"), o.get("unit"), 1 if o.get("is_standard") else 0,
                                1 if o.get("is_counter_wrap") else 0, o.get("description"),
                                1 if o.get("is_active", True) else 0, o.get("created_at"),
                                o.get("updated_at") or o.get("created_at")
                            ))
                        if oid_records:
                            cursor.executemany(oid_sql, oid_records)

                    # 2.7.2 Dispositivos SNMP
                    snmp_devs = data.get("config", {}).get("snmp_devices", [])
                    if snmp_devs:
                        dev_sql = """
                            INSERT INTO snmp_devices 
                            (id, name, ip_address, snmp_version, snmp_port, snmp_timeout_seconds, snmp_retries,
                             device_type, vendor, model, firmware_version, serial_number, sys_name, sys_description,
                             sys_object_id, sys_uptime, sys_location, sys_contact, site_id, discovered_device_id,
                             network_device_id, poll_interval_seconds, is_active, last_poll_at, last_poll_status,
                             consecutive_failures, ssh_enabled, ssh_username, ssh_port, custom_oids, notes, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            name=VALUES(name), snmp_version=VALUES(snmp_version), snmp_port=VALUES(snmp_port),
                            snmp_timeout_seconds=VALUES(snmp_timeout_seconds), snmp_retries=VALUES(snmp_retries),
                            device_type=VALUES(device_type), vendor=VALUES(vendor), model=VALUES(model),
                            firmware_version=VALUES(firmware_version), serial_number=VALUES(serial_number),
                            sys_name=VALUES(sys_name), sys_description=VALUES(sys_description),
                            sys_object_id=VALUES(sys_object_id), sys_uptime=VALUES(sys_uptime),
                            sys_location=VALUES(sys_location), sys_contact=VALUES(sys_contact),
                            site_id=VALUES(site_id), discovered_device_id=VALUES(discovered_device_id),
                            network_device_id=VALUES(network_device_id), poll_interval_seconds=VALUES(poll_interval_seconds),
                            is_active=VALUES(is_active), last_poll_at=VALUES(last_poll_at),
                            last_poll_status=VALUES(last_poll_status), consecutive_failures=VALUES(consecutive_failures),
                            ssh_enabled=VALUES(ssh_enabled), ssh_username=VALUES(ssh_username),
                            ssh_port=VALUES(ssh_port), custom_oids=VALUES(custom_oids), notes=VALUES(notes),
                            updated_at=VALUES(updated_at)
                        """
                        dev_records = []
                        for d in snmp_devs:
                            dev_records.append((
                                d["id"], d["name"], d["ip_address"], d.get("snmp_version", "v2c"),
                                d.get("snmp_port", 161), d.get("snmp_timeout_seconds", 5), d.get("snmp_retries", 2),
                                d.get("device_type", "unknown"), d.get("vendor"), d.get("model"),
                                d.get("firmware_version"), d.get("serial_number"), d.get("sys_name"),
                                d.get("sys_description"), d.get("sys_object_id"), d.get("sys_uptime"),
                                d.get("sys_location"), d.get("sys_contact"), d.get("site_id"),
                                d.get("discovered_device_id"), d.get("network_device_id"),
                                d.get("poll_interval_seconds", 60), 1 if d.get("is_active") else 0,
                                d.get("last_poll_at"), d.get("last_poll_status"), d.get("consecutive_failures", 0),
                                1 if d.get("ssh_enabled") else 0, d.get("ssh_username"), d.get("ssh_port", 22),
                                json.dumps(d.get("custom_oids")) if isinstance(d.get("custom_oids"), (dict, list)) else d.get("custom_oids"),
                                d.get("notes"), d.get("created_at"), d.get("updated_at") or d.get("created_at")
                            ))
                        if dev_records:
                            cursor.executemany(dev_sql, dev_records)

                    # 2.7.3 Interfaces SNMP
                    snmp_ifs = data.get("config", {}).get("snmp_interfaces", [])
                    if snmp_ifs:
                        if_sql = """
                            INSERT INTO snmp_interfaces
                            (id, snmp_device_id, if_index, if_name, if_description, if_alias, if_type, if_speed,
                             if_high_speed, if_physical_address, if_admin_status, if_oper_status, is_monitored,
                             last_in_octets, last_out_octets, last_polled_at, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            if_name=VALUES(if_name), if_description=VALUES(if_description), if_alias=VALUES(if_alias),
                            if_type=VALUES(if_type), if_speed=VALUES(if_speed), if_high_speed=VALUES(if_high_speed),
                            if_physical_address=VALUES(if_physical_address), if_admin_status=VALUES(if_admin_status),
                            if_oper_status=VALUES(if_oper_status), is_monitored=VALUES(is_monitored),
                            last_in_octets=VALUES(last_in_octets), last_out_octets=VALUES(last_out_octets),
                            last_polled_at=VALUES(last_polled_at), updated_at=VALUES(updated_at)
                        """
                        if_records = []
                        for i in snmp_ifs:
                            if_records.append((
                                i["id"], i["snmp_device_id"], i["if_index"], i.get("if_name"),
                                i.get("if_description"), i.get("if_alias"), i.get("if_type"),
                                i.get("if_speed"), i.get("if_high_speed"), i.get("if_physical_address"),
                                i.get("if_admin_status"), i.get("if_oper_status"), 1 if i.get("is_monitored") else 0,
                                i.get("last_in_octets"), i.get("last_out_octets"), i.get("last_polled_at"),
                                i.get("created_at"), i.get("updated_at") or i.get("created_at")
                            ))
                        if if_records:
                            cursor.executemany(if_sql, if_records)

                    # 2.7.4 Históricos de Métricas OID e Interfaces (últimas 24h)
                    snmp_met = data.get("snmp_metrics_history", [])
                    if snmp_met:
                        met_sql = """
                            INSERT IGNORE INTO snmp_metrics_history
                            (id, snmp_device_id, snmp_oid_id, metric_value, metric_value_raw, collected_at)
                            VALUES (%s, %s, %s, %s, %s, %s)
                        """
                        m_records = [
                            (m["id"], m["snmp_device_id"], m["snmp_oid_id"], m.get("metric_value"),
                             m.get("metric_value_raw"), m.get("collected_at"))
                            for m in snmp_met if "id" in m
                        ]
                        if m_records:
                            cursor.executemany(met_sql, m_records)

                    snmp_if_met = data.get("snmp_interface_metrics", [])
                    if snmp_if_met:
                        if_met_sql = """
                            INSERT IGNORE INTO snmp_interface_metrics
                            (id, snmp_interface_id, in_octets, out_octets, in_unicast_pkts, out_unicast_pkts,
                             in_discards, out_discards, in_errors, out_errors, in_bps, out_bps,
                             in_utilization_pct, out_utilization_pct, collected_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        """
                        ifm_records = [
                            (im["id"], im["snmp_interface_id"], im.get("in_octets"), im.get("out_octets"),
                             im.get("in_unicast_pkts"), im.get("out_unicast_pkts"), im.get("in_discards"),
                             im.get("out_discards"), im.get("in_errors"), im.get("out_errors"),
                             im.get("in_bps"), im.get("out_bps"), im.get("in_utilization_pct"),
                             im.get("out_utilization_pct"), im.get("collected_at"))
                            for im in snmp_if_met if "id" in im
                        ]
                        if ifm_records:
                            cursor.executemany(if_met_sql, ifm_records)

                    # 2.7.4 Certificados SSL/TLS y su Historial
                    ssl_certs = data.get("config", {}).get("ssl_certificates", [])
                    if ssl_certs:
                        ssl_sql = """
                            INSERT INTO ssl_certificates
                            (id, service_id, domain, port, subject_cn, subject_org, subject_ou, subject_country,
                             subject_state, subject_locality, issuer_cn, issuer_org, issuer_country, serial_number,
                             signature_algorithm, public_key_algorithm, public_key_bits, version, valid_from, valid_to,
                             days_remaining, is_self_signed, is_wildcard, is_ev, san_entries, fingerprint_sha256,
                             fingerprint_sha1, alert_threshold_warning, alert_threshold_critical, last_checked_at,
                             last_check_status, consecutive_errors, renewal_count, notes, is_active, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            service_id=VALUES(service_id), domain=VALUES(domain), port=VALUES(port),
                            subject_cn=VALUES(subject_cn), subject_org=VALUES(subject_org), issuer_cn=VALUES(issuer_cn),
                            issuer_org=VALUES(issuer_org), serial_number=VALUES(serial_number),
                            signature_algorithm=VALUES(signature_algorithm), public_key_algorithm=VALUES(public_key_algorithm),
                            public_key_bits=VALUES(public_key_bits), version=VALUES(version),
                            valid_from=VALUES(valid_from), valid_to=VALUES(valid_to), days_remaining=VALUES(days_remaining),
                            is_self_signed=VALUES(is_self_signed), is_wildcard=VALUES(is_wildcard), is_ev=VALUES(is_ev),
                            san_entries=VALUES(san_entries), fingerprint_sha256=VALUES(fingerprint_sha256),
                            fingerprint_sha1=VALUES(fingerprint_sha1), last_checked_at=VALUES(last_checked_at),
                            last_check_status=VALUES(last_check_status), consecutive_errors=VALUES(consecutive_errors),
                            renewal_count=VALUES(renewal_count), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
                        """
                        ssl_records = []
                        for sc in ssl_certs:
                            sans = sc.get("san_entries")
                            if isinstance(sans, (dict, list)):
                                sans = json.dumps(sans)
                            ssl_records.append((
                                sc["id"], sc.get("service_id"), sc["domain"], sc.get("port", 443),
                                sc.get("subject_cn"), sc.get("subject_org"), sc.get("subject_ou"), sc.get("subject_country"),
                                sc.get("subject_state"), sc.get("subject_locality"), sc.get("issuer_cn"), sc.get("issuer_org"),
                                sc.get("issuer_country"), sc.get("serial_number"), sc.get("signature_algorithm"),
                                sc.get("public_key_algorithm"), sc.get("public_key_bits"), sc.get("version"),
                                sc.get("valid_from"), sc.get("valid_to"), sc.get("days_remaining", 0),
                                1 if sc.get("is_self_signed") else 0, 1 if sc.get("is_wildcard") else 0,
                                1 if sc.get("is_ev") else 0, sans, sc.get("fingerprint_sha256"),
                                sc.get("fingerprint_sha1"), sc.get("alert_threshold_warning", 30),
                                sc.get("alert_threshold_critical", 7), sc.get("last_checked_at"),
                                sc.get("last_check_status"), sc.get("consecutive_errors", 0),
                                sc.get("renewal_count", 0), sc.get("notes"), 1 if sc.get("is_active", True) else 0,
                                sc.get("created_at"), sc.get("updated_at")
                            ))
                        if ssl_records:
                            cursor.executemany(ssl_sql, ssl_records)

                    ssl_hist = data.get("ssl_certificate_history", [])
                    if ssl_hist:
                        ssl_h_sql = """
                            INSERT IGNORE INTO ssl_certificate_history
                            (id, ssl_certificate_id, event_type, previous_fingerprint, new_fingerprint,
                             previous_valid_to, new_valid_to, days_remaining_at_event, error_message, occurred_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        """
                        ssl_h_records = [
                            (sh["id"], sh["ssl_certificate_id"], sh["event_type"], sh.get("previous_fingerprint"),
                             sh.get("new_fingerprint"), sh.get("previous_valid_to"), sh.get("new_valid_to"),
                             sh.get("days_remaining_at_event"), sh.get("error_message"), sh.get("occurred_at"))
                            for sh in ssl_hist if "id" in sh and "ssl_certificate_id" in sh
                        ]
                        if ssl_h_records:
                            cursor.executemany(ssl_h_sql, ssl_h_records)

                    # 2.7.5 Reglas de Alerta, Ventanas de Mantenimiento y Alarmas (Fase 4)
                    alert_rules = data.get("config", {}).get("alert_rules", [])
                    if alert_rules:
                        ar_sql = """
                            INSERT INTO alert_rules
                            (id, name, description, entity_type, entity_id, condition_type, threshold_value,
                             comparison, duration_seconds, severity, cooldown_minutes, max_alerts_per_hour,
                             auto_resolve, is_active, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            name=VALUES(name), description=VALUES(description), entity_type=VALUES(entity_type),
                            entity_id=VALUES(entity_id), condition_type=VALUES(condition_type),
                            threshold_value=VALUES(threshold_value), comparison=VALUES(comparison),
                            duration_seconds=VALUES(duration_seconds), severity=VALUES(severity),
                            cooldown_minutes=VALUES(cooldown_minutes), max_alerts_per_hour=VALUES(max_alerts_per_hour),
                            auto_resolve=VALUES(auto_resolve), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
                        """
                        ar_records = [
                            (r["id"], r["name"], r.get("description"), r["entity_type"], r.get("entity_id"),
                             r["condition_type"], r.get("threshold_value"), r.get("comparison"),
                             r.get("duration_seconds", 0), r["severity"], r.get("cooldown_minutes", 30),
                             r.get("max_alerts_per_hour", 5), 1 if r.get("auto_resolve", True) else 0,
                             1 if r.get("is_active", True) else 0, r.get("created_at"), r.get("updated_at"))
                            for r in alert_rules if "id" in r and "name" in r
                        ]
                        if ar_records:
                            cursor.executemany(ar_sql, ar_records)

                    maints = data.get("config", {}).get("maintenance_windows", [])
                    if maints:
                        mw_sql = """
                            INSERT INTO maintenance_windows
                            (id, title, description, entity_type, entity_id, suppress_severities,
                             starts_at, ends_at, created_by, is_active, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            title=VALUES(title), description=VALUES(description), starts_at=VALUES(starts_at),
                            ends_at=VALUES(ends_at), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
                        """
                        mw_records = []
                        for m in maints:
                            sups = m.get("suppress_severities")
                            if isinstance(sups, (list, dict)):
                                sups = json.dumps(sups)
                            mw_records.append((
                                m["id"], m["title"], m.get("description"), m.get("entity_type", "all"),
                                m.get("entity_id"), sups, m.get("starts_at"), m.get("ends_at"),
                                m.get("created_by"), 1 if m.get("is_active", True) else 0,
                                m.get("created_at"), m.get("updated_at")
                            ))
                        if mw_records:
                            cursor.executemany(mw_sql, mw_records)

                    alerts_list = data.get("alerts", [])
                    if alerts_list:
                        al_sql = """
                            INSERT INTO alerts
                            (id, alert_rule_id, entity_type, entity_id, entity_name, condition_type,
                             severity, status, current_escalation_level, value_at_trigger, threshold_value,
                             message, correlation_group_id, is_correlated_suppressed, parent_alert_id,
                             fired_at, acknowledged_at, resolved_at, acknowledged_by, resolved_by,
                             last_notified_at, notification_count, duration_seconds, notes, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            severity=VALUES(severity), status=VALUES(status),
                            current_escalation_level=VALUES(current_escalation_level),
                            value_at_trigger=VALUES(value_at_trigger), message=VALUES(message),
                            acknowledged_at=VALUES(acknowledged_at), resolved_at=VALUES(resolved_at),
                            duration_seconds=VALUES(duration_seconds), notes=VALUES(notes),
                            updated_at=VALUES(updated_at)
                        """
                        al_records = [
                            (a["id"], a.get("alert_rule_id"), a["entity_type"], a["entity_id"],
                             a.get("entity_name"), a["condition_type"], a["severity"], a.get("status", "firing"),
                             a.get("current_escalation_level", 1), a.get("value_at_trigger"),
                             a.get("threshold_value"), a.get("message"), a.get("correlation_group_id"),
                             1 if a.get("is_correlated_suppressed") else 0, a.get("parent_alert_id"),
                             a.get("fired_at"), a.get("acknowledged_at"), a.get("resolved_at"),
                             a.get("acknowledged_by"), a.get("resolved_by"), a.get("last_notified_at"),
                             a.get("notification_count", 0), a.get("duration_seconds", 0),
                             a.get("notes"), a.get("created_at"), a.get("updated_at"))
                            for a in alerts_list if "id" in a and "entity_type" in a
                        ]
                        if al_records:
                            cursor.executemany(al_sql, al_records)

                    # 2.8 Sincronización de respaldos de configuraciones y cambios
                    configs_list = data.get("device_configurations", [])
                    if configs_list:
                        cfg_sql = """
                            INSERT INTO device_configurations
                            (id, network_device_id, snmp_device_id, device_name, device_ip,
                             device_type, config_text, config_hash, config_size_bytes, captured_at,
                             captured_by, status, notes, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, '', %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            config_hash=VALUES(config_hash), config_size_bytes=VALUES(config_size_bytes),
                            captured_at=VALUES(captured_at), status=VALUES(status), updated_at=VALUES(updated_at)
                        """
                        cfg_records = [
                            (c["id"], c.get("network_device_id"), c.get("snmp_device_id"),
                             c.get("device_name"), c.get("device_ip"), c.get("device_type", "other"),
                             c.get("config_hash", ""), c.get("config_size_bytes", 0),
                             c.get("captured_at"), c.get("captured_by", "cron"),
                             c.get("status", "success"), c.get("notes"),
                             c.get("created_at"), c.get("updated_at"))
                            for c in configs_list if "id" in c
                        ]
                        if cfg_records:
                            cursor.executemany(cfg_sql, cfg_records)

                    logs_list = data.get("config_change_logs", [])
                    if logs_list:
                        log_sql = """
                            INSERT INTO config_change_logs
                            (id, device_configuration_id, previous_config_id, network_device_id,
                             snmp_device_id, change_type, diff_summary, diff_unified, lines_added,
                             lines_removed, detected_at, alerted, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            diff_summary=VALUES(diff_summary), detected_at=VALUES(detected_at), updated_at=VALUES(updated_at)
                        """
                        log_records = [
                            (l["id"], l.get("device_configuration_id"), l.get("previous_config_id"),
                             l.get("network_device_id"), l.get("snmp_device_id"),
                             l.get("change_type", "modified"), l.get("diff_summary"),
                             l.get("diff_unified"), l.get("lines_added", 0),
                             l.get("lines_removed", 0), l.get("detected_at"),
                             1 if l.get("alerted") else 0,
                             l.get("created_at"), l.get("updated_at"))
                            for l in logs_list if "id" in l
                        ]
                        if log_records:
                            cursor.executemany(log_sql, log_records)

                    # 2.9 Sincronización de NET Radar (Hosts, Eventos Forenses y Snapshots)
                    nr_hosts = data.get("net_radar_hosts", [])
                    if nr_hosts:
                        nr_host_sql = """
                            INSERT INTO net_radar_hosts
                            (id, ip, mac, hostname, vendor, os_detected, bytes_in, bytes_out, total_bytes, packet_count,
                             is_local, update_status, last_update_type, last_update_target, update_bytes,
                             last_update_at, first_seen_at, last_seen_at, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                            mac=VALUES(mac), hostname=VALUES(hostname), vendor=VALUES(vendor),
                            os_detected=VALUES(os_detected), bytes_in=VALUES(bytes_in), bytes_out=VALUES(bytes_out),
                            total_bytes=VALUES(total_bytes), packet_count=VALUES(packet_count),
                            is_local=VALUES(is_local), update_status=VALUES(update_status),
                            last_update_type=VALUES(last_update_type), last_update_target=VALUES(last_update_target),
                            update_bytes=VALUES(update_bytes), last_update_at=VALUES(last_update_at),
                            last_seen_at=VALUES(last_seen_at), updated_at=VALUES(updated_at)
                        """
                        nr_host_records = [
                            (h["id"], h["ip"], h.get("mac"), h.get("hostname"), h.get("vendor"),
                             h.get("os_detected", "Desconocido"), h.get("bytes_in", 0), h.get("bytes_out", 0),
                             h.get("total_bytes", 0), h.get("packet_count", 0), 1 if h.get("is_local", True) else 0,
                             h.get("update_status", "none"), h.get("last_update_type"), h.get("last_update_target"),
                             h.get("update_bytes", 0), h.get("last_update_at"), h.get("first_seen_at"),
                             h.get("last_seen_at"), h.get("created_at"), h.get("updated_at") or h.get("created_at"))
                            for h in nr_hosts if "id" in h and "ip" in h
                        ]
                        if nr_host_records:
                            cursor.executemany(nr_host_sql, nr_host_records)

                    nr_events = data.get("net_radar_events", [])
                    if nr_events:
                        nr_ev_sql = """
                            INSERT IGNORE INTO net_radar_events
                            (id, host_ip, event_type, severity, target_domain, bytes_transferred, description, created_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                        """
                        nr_ev_records = [
                            (e["id"], e["host_ip"], e["event_type"], e.get("severity", "info"),
                             e.get("target_domain"), e.get("bytes_transferred", 0), e.get("description", ""),
                             e.get("created_at"))
                            for e in nr_events if "id" in e and "host_ip" in e
                        ]
                        if nr_ev_records:
                            cursor.executemany(nr_ev_sql, nr_ev_records)

                    nr_snaps = data.get("net_radar_snapshots", [])
                    if nr_snaps:
                        nr_snap_sql = """
                            INSERT IGNORE INTO net_radar_snapshots
                            (id, total_hosts, active_hosts, windows_updating_hosts, linux_updating_hosts,
                             total_bytes_in, total_bytes_out, top_protocols, top_talkers, created_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        """
                        nr_snap_records = []
                        for s in nr_snaps:
                            if "id" in s:
                                top_p = s.get("top_protocols")
                                if isinstance(top_p, (dict, list)):
                                    top_p = json.dumps(top_p)
                                top_t = s.get("top_talkers")
                                if isinstance(top_t, (dict, list)):
                                    top_t = json.dumps(top_t)
                                nr_snap_records.append((
                                    s["id"], s.get("total_hosts", 0), s.get("active_hosts", 0),
                                    s.get("windows_updating_hosts", 0), s.get("linux_updating_hosts", 0),
                                    s.get("total_bytes_in", 0), s.get("total_bytes_out", 0),
                                    top_p, top_t, s.get("created_at")
                                ))
                        if nr_snap_records:
                            cursor.executemany(nr_snap_sql, nr_snap_records)
                conn.close()

            except Exception as e_db:
                print(f"⚠️ [MODO ESCLAVO] Aviso actualizando base de datos local: {e_db}")

            elapsed = round(time.perf_counter() - start_time, 2)
            update_last_scan_time()
            _update_cluster_status("ok")

            gen_at = snapshot_payload.get("timestamp", "N/A")
            g_stat = snapshot_payload.get("global_status", "N/A")
            print(f"✅ [MODO ESCLAVO] Telemetría sincronizada con éxito desde Master ({master_url}) en {elapsed}s | Snapshot: {gen_at} | Estado: {g_stat}")
            return True

    except Exception as e:
        print(f"⚠️ [MODO ESCLAVO] Error de conexión con Master ({master_url}): {e}")
        _update_cluster_status("error")
        return False

def _update_cluster_status(status: str):
    config_file = BASE_DIR / "config" / "config.json"
    if config_file.exists():
        try:
            cfg = json.loads(config_file.read_text(encoding="utf-8"))
            cfg["cluster_last_sync_status"] = status
            cfg["cluster_last_sync_at"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            config_file.write_text(json.dumps(cfg, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        except Exception:
            pass

if __name__ == "__main__":
    force_run = ("--force" in sys.argv or "-f" in sys.argv)
    force_local = ("--local" in sys.argv or "--force-local" in sys.argv)

    should_run, interval_min, remaining = should_run_web_scan(force=force_run)
    if not should_run:
        print(f"⏳ Escaneo web en espera (frecuencia configurada: {interval_min} min). Faltan {int(remaining)}s para el próximo ciclo.")
        sys.exit(0)

    # Determinar rol del nodo
    config_file = BASE_DIR / "config" / "config.json"
    node_role = "master"
    if config_file.exists():
        try:
            cfg = json.loads(config_file.read_text(encoding="utf-8"))
            node_role = str(cfg.get("node_role", "master")).lower()
        except Exception:
            pass

    if node_role == "slave" and not force_local:
        # En modo esclavo, se sincroniza del Master sin escanear la red
        success = asyncio.run(sync_from_master())
        sys.exit(0 if success else 1)
    else:
        # En modo Master (o forzado local), ejecuta el escaneo de red
        asyncio.run(run_full_scan())
