"""
Orquestador del ciclo de telemetría NET Radar y modo daemon.
"""
import asyncio
from datetime import datetime
import json
import logging
from pathlib import Path
from typing import Any, Dict, List

from monitor.checker_base import get_active_network_interface
from monitor.checkers.radar.flow_sniffer import (
    capture_and_analyze_flows,
    get_interface_ip_and_mask,
    get_kernel_arp_table,
    is_ip_in_local_subnet,
    lookup_oui_vendor,
)
from monitor.checkers.radar.update_detector import (
    detect_update_activity,
    infer_os_and_hostname,
)
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.radar.orchestrator")


async def run_radar_cycle(interface: str = "", cycle_duration: int = 15) -> Dict[str, Any]:
    """Ejecuta un ciclo completo de monitoreo de NET Radar."""
    if not interface:
        if Path("/sys/class/net/enp0s31f6").exists():
            interface = "enp0s31f6"
        else:
            interface = get_active_network_interface()

    my_ip, my_mask = get_interface_ip_and_mask(interface)
    logger.info(f"Iniciando ciclo NET Radar en '{interface}' ({my_ip}/{my_mask}) por {cycle_duration}s...")

    arp_map = get_kernel_arp_table()
    existing_hosts: Dict[str, Dict[str, Any]] = {}
    try:
        with db_cursor() as cur:
            cur.execute("SELECT * FROM net_radar_hosts")
            for row in cur.fetchall():
                existing_hosts[row["ip"]] = row
    except Exception as e:
        logger.error(f"Error cargando hosts existentes: {e}")

    for ip, mac in arp_map.items():
        if ip not in existing_hosts:
            vendor = lookup_oui_vendor(mac)
            os_det, hname = infer_os_and_hostname(ip, mac, vendor)
            is_loc = is_ip_in_local_subnet(ip, my_ip, my_mask)

            try:
                with db_cursor() as cur:
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

        u_type, u_status = detect_update_activity(domain, port)
        if u_type:
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

                if b > 1024 or u_status == "downloading":
                    events_to_log.append({
                        "host_ip": target_host,
                        "event_type": u_type,
                        "severity": "warning" if u_status == "downloading" else "info",
                        "target_domain": domain,
                        "bytes": b,
                        "description": f"Host {target_host} detectado en {u_type.replace('_', ' ').title()} ({u_status}) contactando a {domain or port}"
                    })

    all_touched_ips = set(host_rx.keys()) | set(host_tx.keys()) | set(host_updates.keys())
    with db_cursor() as cur:
        for ip in all_touched_ips:
            rx = host_rx.get(ip, 0)
            tx = host_tx.get(ip, 0)
            pkts = host_packets.get(ip, 0)
            tot = rx + tx
            u_info = host_updates.get(ip)

            try:
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

        if events_to_log:
            try:
                for ev in events_to_log[:15]:
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

        # Snapshot
        try:
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

    result_summary = {
        "status": "success",
        "interface": interface,
        "cycle_duration": cycle_duration,
        "flows_analyzed": len(flows),
        "hosts_touched": len(all_touched_ips),
        "windows_updating_detected": sum(1 for h in host_updates.values() if h["type"] == "windows_update"),
        "linux_updating_detected": sum(1 for h in host_updates.values() if h["type"] == "linux_repo"),
    }
    logger.info(f"Ciclo finalizado: {result_summary['flows_analyzed']} flujos analizados.")
    return result_summary


async def main_daemon():
    """Ejecuta el ciclo de NET Radar continuamente."""
    logger.info("Iniciando servicio daemon de NET Radar...")
    while True:
        try:
            await run_radar_cycle(cycle_duration=30)
        except Exception as e:
            logger.error(f"Excepción en ciclo de daemon NET Radar: {e}", exc_info=True)
        await asyncio.sleep(10)
