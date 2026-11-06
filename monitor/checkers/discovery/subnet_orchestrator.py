"""
Orquestador de auto-discovery de subredes y sincronización con inventario ITAM.
"""
import asyncio
from datetime import datetime
import ipaddress
import json
import logging
import socket
import time
from typing import Any, Dict, List, Optional

from monitor.checkers.discovery.ping_sweeper import (
    get_kernel_arp_table,
    get_local_interfaces_map,
    sweep_subnet_async,
)
from monitor.checkers.discovery.port_fingerprinter import (
    deep_fingerprint_hosts,
    infer_device_type,
    lookup_oui_vendor,
)
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.discovery.orchestrator")


def scan_subnet(subnet_str: str, site_id: Optional[int] = None, executed_by: str = "cron") -> Dict[str, Any]:
    """Ejecuta el ciclo integral de auto-discovery en una subred."""
    start_time = time.time()
    started_at = datetime.now()
    logger.info(f"▶ [INICIO ESCANEO] Subred: {subnet_str} (Ejecutado por: {executed_by})")
    scan_id = None

    try:
        with db_cursor() as cur:
            if site_id is None:
                cur.execute("SELECT site_id FROM discovery_subnets WHERE subnet = %s LIMIT 1", (subnet_str,))
                sub_row = cur.fetchone()
                if sub_row and sub_row.get("site_id"):
                    site_id = sub_row["site_id"]

            cur.execute("""
                INSERT INTO discovery_scans (scan_type, subnet, site_id, started_at, status, executed_by)
                VALUES ('arp_sweep', %s, %s, %s, 'running', %s)
            """, (subnet_str, site_id, started_at, executed_by))
            scan_id = cur.lastrowid

            known_network_devices: Dict[str, int] = {}
            cur.execute("SELECT id, ip, mac FROM monitored_network_devices WHERE is_active = 1")
            for row in cur.fetchall():
                if row.get("mac"):
                    known_network_devices[row["mac"].upper()] = row["id"]
                if row.get("ip"):
                    known_network_devices[row["ip"]] = row["id"]

            known_assets: Dict[str, Dict[str, Any]] = {}
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

        live_hosts = asyncio.run(sweep_subnet_async(subnet_str))
        arp_table = get_kernel_arp_table()
        local_interfaces = get_local_interfaces_map()

        hosts_map: Dict[str, Dict[str, Any]] = {h["ip"]: h for h in live_hosts}
        try:
            target_network = ipaddress.ip_network(subnet_str, strict=False)
        except ValueError:
            target_network = None

        if target_network:
            for arp_ip, _ in arp_table.items():
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

        with db_cursor() as cur:
            if target_network:
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
                        except ValueError:
                            pass

        fp_map = deep_fingerprint_hosts(list(detected_ips_set))

        with db_cursor() as cur:
            for host_info in all_detected_hosts:
                ip = host_info["ip"]
                fp = fp_map.get(ip, {})
                hostname = fp.get("hostname") or host_info.get("hostname")
                os_detected = fp.get("os_detected")
                open_ports_list = fp.get("open_ports", [])
                json_ports = json.dumps(open_ports_list) if open_ports_list else None
                inferred_type = fp.get("inferred_type")
                mac = arp_table.get(ip) or local_interfaces.get(ip)

                asset_match = known_assets.get(mac) if mac else None
                if not asset_match:
                    asset_match = known_assets.get(ip)

                if not mac and asset_match and asset_match.get("mac_address"):
                    cand_mac = asset_match["mac_address"].strip().upper()
                    if cand_mac and not cand_mac.startswith("IP:"):
                        mac = cand_mac

                if not mac:
                    mac = f"IP:{ip}"

                devices_found += 1
                vendor, oui_prefix = lookup_oui_vendor(mac)

                if (not vendor or vendor == "Fabricante Desconocido") and asset_match and asset_match.get("brand"):
                    vendor = asset_match["brand"]

                device_type = infer_device_type(
                    vendor,
                    hostname or "",
                    asset_match.get("type") if asset_match else None,
                    inferred_type
                )

                cur.execute("""
                    SELECT * FROM discovered_devices 
                    WHERE mac_address = %s OR (ip_address = %s AND mac_address LIKE 'IP:%%')
                    ORDER BY id ASC LIMIT 1
                """, (mac, ip))
                existing = cur.fetchone()

                linked_id = known_network_devices.get(mac) or known_network_devices.get(ip)
                if not linked_id and asset_match and asset_match.get("monitored_network_device_id"):
                    linked_id = asset_match["monitored_network_device_id"]

                effective_site_id = site_id
                if asset_match and asset_match.get("monitored_site_id"):
                    effective_site_id = asset_match["monitored_site_id"]

                is_auth = 1 if (linked_id or asset_match) else 0
                status = "clasificado" if (linked_id or asset_match) else "pendiente"

                if not existing:
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
                        "Conciliado con ITAM" if asset_match else "Descubierto por escaneo"
                    ))
                    dev_id = cur.lastrowid
                    cur.execute("""
                        INSERT INTO discovered_device_history 
                        (discovered_device_id, event_type, previous_value, new_value, occurred_at)
                        VALUES (%s, 'first_seen', NULL, %s, NOW())
                    """, (dev_id, f"IP inicial: {ip}, Fabricante: {vendor}"))
                else:
                    dev_id = existing["id"]
                    old_ip = existing.get("ip_address")
                    old_status = existing.get("classification_status")

                    if old_ip and old_ip != ip:
                        cur.execute("""
                            INSERT INTO discovered_device_history 
                            (discovered_device_id, event_type, previous_value, new_value, occurred_at)
                            VALUES (%s, 'ip_changed', %s, %s, NOW())
                        """, (dev_id, old_ip, ip))

                    if not existing.get("is_active"):
                        cur.execute("""
                            INSERT INTO discovered_device_history 
                            (discovered_device_id, event_type, previous_value, new_value, occurred_at)
                            VALUES (%s, 'came_online', 'inactivo', 'activo', NOW())
                        """, (dev_id,))

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
            cur.execute("""
                UPDATE discovery_scans 
                SET finished_at = NOW(),
                    duration_seconds = %s,
                    devices_found = %s,
                    new_devices = %s,
                    status = 'completed'
                WHERE id = %s
            """, (duration, devices_found, new_devices_count, scan_id))

            cur.execute("UPDATE discovery_subnets SET last_scan_at = NOW() WHERE subnet = %s", (subnet_str,))

        logger.info(f"✅ [ESCANEO COMPLETADO] Subred: {subnet_str} | Encontrados: {devices_found} | Nuevos: {new_devices_count}")
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
                with db_cursor() as cur:
                    cur.execute("""
                        UPDATE discovery_scans 
                        SET finished_at = NOW(),
                            status = 'failed',
                            error_message = %s
                        WHERE id = %s
                    """, (str(e), scan_id))
            except Exception:
                pass
        return {"success": False, "error": str(e), "subnet": subnet_str}


def scan_all_active_subnets(executed_by: str = "cron", force: bool = False):
    """Escanea secuencialmente todas las subredes activas."""
    with db_cursor() as cur:
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

    if not subnets:
        logger.info("No hay subredes activas que requieran escaneo en este intervalo.")
        return

    logger.info(f"Iniciando ciclo de descubrimiento en {len(subnets)} subredes activas (force={force})...")
    for s in subnets:
        scan_subnet(s["subnet"], site_id=s.get("site_id"), executed_by=executed_by)
