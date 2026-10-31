"""
Orquestador del análisis profundo de red.
"""
import asyncio
from datetime import datetime
import json
import logging
from pathlib import Path
import time
from typing import Dict, List, Optional, Tuple

from monitor.checkers.analyzer.dtos import NetworkHost
from monitor.checkers.analyzer.html_report_builder import generate_html_network_report
from monitor.checkers.analyzer.oui_resolver import (
    get_interface_ip_and_mask,
    is_ip_in_local_subnet,
    load_mac_whitelist,
    load_oui_database,
    resolve_hostname_sync,
)
from monitor.checkers.analyzer.pcap_parser import (
    parse_pcap_traffic,
    ping_measure,
    run_arp_and_nmap_scan,
)
from monitor.checkers.analyzer.tcpdump_runner import capture_packets
from monitor.checkers.analyzer.text_report_builder import (
    generate_markdown_report,
    generate_telegram_summary,
)
from monitor.checker_base import get_active_network_interface
from monitor.config_parser import get_formatted_datetime
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.analyzer.orchestrator")


def save_network_analysis_report_to_db(
    report_type: str,
    interface: str,
    duration_seconds: int,
    total_packets: int,
    local_hosts_count: int,
    external_hosts_count: int,
    suspicious_packets: int,
    summary_text: str,
    report_markdown: str,
    report_data_json: Optional[dict] = None
) -> Optional[int]:
    """Guarda el reporte en network_analysis_reports y aplica retención de 30 días."""
    try:
        with db_cursor() as cur:
            sql = """
                INSERT INTO network_analysis_reports
                (report_type, interface, duration_seconds, total_packets, local_hosts_count, external_hosts_count, suspicious_packets, summary_text, report_markdown, report_data_json, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
            """
            cur.execute(sql, (
                (report_type or "network_analyzer")[:50],
                (interface or "eth0")[:32],
                duration_seconds,
                total_packets,
                local_hosts_count,
                external_hosts_count,
                suspicious_packets,
                summary_text,
                report_markdown,
                json.dumps(report_data_json, ensure_ascii=False) if report_data_json else None
            ))
            report_id = cur.lastrowid
            cur.execute("DELETE FROM network_analysis_reports WHERE created_at < NOW() - INTERVAL 30 DAY")
            return report_id
    except Exception as e:
        logger.warning(f"No se pudo guardar reporte de red en BD: {e}")
        return None


def purge_old_audit_files(max_days: int = 30) -> int:
    """Purga capturas .pcap (>1h) y reportes (.html, .md, .txt) que superen max_days."""
    deleted_count = 0
    now = time.time()
    max_age_sec = max_days * 86400
    temp_dir = Path("/tmp")

    for pcap_f in temp_dir.glob("captura_*.pcap"):
        try:
            if (now - pcap_f.stat().st_mtime) > 3600:
                pcap_f.unlink()
                deleted_count += 1
                logger.info(f"Captura pcap residual eliminada de /tmp: {pcap_f.name}")
        except Exception as e:
            logger.warning(f"No se pudo eliminar captura residual {pcap_f}: {e}")

    for pattern in ("reporte_red_*.html", "reporte_red_*.md", "reporte_red_*.txt"):
        for rep_f in temp_dir.glob(pattern):
            try:
                if (now - rep_f.stat().st_mtime) > max_age_sec:
                    rep_f.unlink()
                    deleted_count += 1
                    logger.info(f"Reporte antiguo purgado de /tmp: {rep_f.name}")
            except Exception as e:
                logger.warning(f"No se pudo eliminar reporte {rep_f}: {e}")

    return deleted_count


def build_txt_and_html_reports(
    hosts_local_con_mac: List[NetworkHost],
    hosts_local_sin_mac: List[NetworkHost],
    hosts_externos: List[NetworkHost],
    latencias_ordenadas: List[NetworkHost],
    suspicious_hosts: List[Tuple[str, str, int]],
    total_suspicious: int,
    broadcast_count: int,
    multicast_v4: int,
    multicast_v6: int,
    interface: str,
    my_ip: str,
    my_mask: str,
    pkt_count: int,
    duration_seconds: int
) -> Tuple[str, Path, Path]:
    """Genera el resumen de Telegram, el reporte en Markdown (.md) y el reporte Web (.html)."""
    fecha_str, hora_str = get_formatted_datetime()
    timestamp_file = datetime.now().strftime("%Y%m%d_%H%M%S")
    temp_dir = Path("/tmp")
    md_report_path = temp_dir / f"reporte_red_{timestamp_file}.md"
    html_report_path = temp_dir / f"reporte_red_{timestamp_file}.html"

    total_locals = len(hosts_local_con_mac) + len(hosts_local_sin_mac)
    total_externos = len(hosts_externos)
    storm_alert = (broadcast_count > 200 or multicast_v4 > 300 or multicast_v6 > 300)

    telegram_summary = generate_telegram_summary(
        hosts_local_con_mac=hosts_local_con_mac,
        hosts_local_sin_mac=hosts_local_sin_mac,
        hosts_externos=hosts_externos,
        total_suspicious=total_suspicious,
        storm_alert=storm_alert,
        interface=interface,
        my_ip=my_ip,
        my_mask=my_mask,
        pkt_count=pkt_count,
        duration_seconds=duration_seconds,
        fecha_str=fecha_str,
        hora_str=hora_str,
    )

    md_content = generate_markdown_report(
        hosts_local_con_mac=hosts_local_con_mac,
        hosts_local_sin_mac=hosts_local_sin_mac,
        hosts_externos=hosts_externos,
        latencias_ordenadas=latencias_ordenadas,
        suspicious_hosts=suspicious_hosts,
        total_suspicious=total_suspicious,
        storm_alert=storm_alert,
        interface=interface,
        my_ip=my_ip,
        my_mask=my_mask,
        pkt_count=pkt_count,
        duration_seconds=duration_seconds,
        fecha_str=fecha_str,
        hora_str=hora_str,
    )
    md_report_path.write_text(md_content, encoding="utf-8")

    html_content = generate_html_network_report(
        hosts_local_con_mac=hosts_local_con_mac,
        hosts_local_sin_mac=hosts_local_sin_mac,
        hosts_externos=hosts_externos,
        latencias_ordenadas=latencias_ordenadas,
        suspicious_hosts=suspicious_hosts,
        total_suspicious=total_suspicious,
        storm_alert=storm_alert,
        interface=interface,
        my_ip=my_ip,
        my_mask=my_mask,
        pkt_count=pkt_count,
        duration_seconds=duration_seconds,
        fecha_str=fecha_str,
        hora_str=hora_str,
    )
    html_report_path.write_text(html_content, encoding="utf-8")

    try:
        report_data = {
            "interface": interface,
            "my_ip": my_ip,
            "my_mask": my_mask,
            "total_packets": pkt_count,
            "duration_seconds": duration_seconds,
            "local_hosts_count": total_locals,
            "external_hosts_count": total_externos,
            "suspicious_packets": total_suspicious,
            "hosts_local_con_mac": [{"ip": h.ip, "mac": h.mac, "hostname": h.hostname, "rx": h.rx, "tx": h.tx, "traffic": h.total_traffic} for h in hosts_local_con_mac],
            "hosts_local_sin_mac": [{"ip": h.ip, "mac": h.mac, "hostname": h.hostname, "rx": h.rx, "tx": h.tx, "traffic": h.total_traffic} for h in hosts_local_sin_mac],
            "hosts_externos": [{"ip": h.ip, "hostname": h.hostname, "rx": h.rx, "tx": h.tx, "traffic": h.total_traffic} for h in hosts_externos[:50]],
        }
        save_network_analysis_report_to_db(
            report_type="network_analyzer",
            interface=interface,
            duration_seconds=duration_seconds,
            total_packets=pkt_count,
            local_hosts_count=total_locals,
            external_hosts_count=total_externos,
            suspicious_packets=total_suspicious,
            summary_text=telegram_summary,
            report_markdown=md_content,
            report_data_json=report_data
        )
    except Exception as e_db:
        logger.warning(f"Aviso guardando reporte en BD: {e_db}")

    try:
        purge_old_audit_files(max_days=30)
    except Exception as e_purge:
        logger.warning(f"Error en purgado automático de auditoría: {e_purge}")

    return telegram_summary, md_report_path, html_report_path


async def execute_network_analysis(
    duration_seconds: int = 120,
    interface_name: Optional[str] = None
) -> Dict[str, any]:
    """Ejecución completa del análisis de red."""
    t0 = time.perf_counter()
    iface = get_active_network_interface(interface_name or "eth0")
    my_ip, my_mask = get_interface_ip_and_mask(iface)

    known_macs = load_mac_whitelist()
    oui_db = load_oui_database()

    timestamp_str = datetime.now().strftime("%Y%m%d_%H%M%S")
    pcap_path = Path("/tmp") / f"captura_{timestamp_str}.pcap"

    pkt_count = await capture_packets(iface, pcap_path, duration_seconds=duration_seconds)
    (
        traffic_macs,
        tx_map,
        rx_map,
        suspicious_map,
        broadcast_count,
        multicast_v4,
        multicast_v6
    ) = await parse_pcap_traffic(pcap_path, my_ip, my_mask)

    try:
        if pcap_path.exists():
            pcap_path.unlink()
            logger.info(f"Evidencia cruda (.pcap) eliminada: {pcap_path.name}")
    except Exception as e:
        logger.warning(f"No se pudo eliminar pcap crudo: {e}")

    arp_macs = await run_arp_and_nmap_scan(iface, my_ip, my_mask)

    all_ips = set(tx_map.keys()) | set(rx_map.keys()) | set(arp_macs.keys()) | set(traffic_macs.keys())
    all_hosts: Dict[str, NetworkHost] = {}

    for ip in all_ips:
        if not ip or ip in ("0.0.0.0", "::"):
            continue

        is_local = is_ip_in_local_subnet(ip, my_ip, my_mask)
        mac = arp_macs.get(ip) or traffic_macs.get(ip) or ("desconocida" if is_local else "N/A (externo)")
        has_mac = bool(mac and mac not in ("desconocida", "N/A (externo)"))
        is_auth = (mac.lower() in known_macs) if (known_macs and has_mac) else True

        hostname = await asyncio.to_thread(resolve_hostname_sync, ip, mac, oui_db)

        tx = tx_map.get(ip, 0)
        rx = rx_map.get(ip, 0)
        total = tx + rx

        all_hosts[ip] = NetworkHost(
            ip=ip,
            hostname=hostname,
            mac=mac,
            rx=rx,
            tx=tx,
            total_traffic=total,
            is_local=is_local,
            has_mac=has_mac,
            is_authorized=is_auth
        )

    measure_targets = [h for h in all_hosts.values() if h.is_local or h.total_traffic > 0]
    if measure_targets:
        latencies = await asyncio.gather(*(ping_measure(h.ip) for h in measure_targets))
        for h, lat in zip(measure_targets, latencies):
            h.latency_ms = lat

    hosts_local_con_mac = [h for h in all_hosts.values() if h.is_local and h.has_mac]
    hosts_local_sin_mac = [h for h in all_hosts.values() if h.is_local and not h.has_mac]
    hosts_externos = [h for h in all_hosts.values() if not h.is_local]

    hosts_local_con_mac.sort(key=lambda x: x.ip)
    hosts_local_sin_mac.sort(key=lambda x: x.ip)
    hosts_externos.sort(key=lambda x: x.ip)

    latencias_ordenadas = sorted(
        [h for h in all_hosts.values() if h.latency_ms > 0],
        key=lambda x: x.latency_ms,
        reverse=True
    )

    total_suspicious = sum(suspicious_map.values())
    suspicious_hosts = [
        (ip, all_hosts[ip].mac if ip in all_hosts else "desconocida", cnt)
        for ip, cnt in suspicious_map.items() if cnt > 0
    ]

    elapsed = round(time.perf_counter() - t0, 2)

    summary_text, md_report, html_report = build_txt_and_html_reports(
        hosts_local_con_mac=hosts_local_con_mac,
        hosts_local_sin_mac=hosts_local_sin_mac,
        hosts_externos=hosts_externos,
        latencias_ordenadas=latencias_ordenadas,
        suspicious_hosts=suspicious_hosts,
        total_suspicious=total_suspicious,
        broadcast_count=broadcast_count,
        multicast_v4=multicast_v4,
        multicast_v6=multicast_v6,
        interface=iface,
        my_ip=my_ip,
        my_mask=my_mask,
        pkt_count=pkt_count,
        duration_seconds=duration_seconds
    )

    return {
        "interface": iface,
        "my_ip": my_ip,
        "my_mask": my_mask,
        "pkt_count": pkt_count,
        "duration_seconds": duration_seconds,
        "total_hosts": len(all_hosts),
        "hosts_local_con_mac": len(hosts_local_con_mac),
        "hosts_local_sin_mac": len(hosts_local_sin_mac),
        "hosts_externos": len(hosts_externos),
        "total_suspicious": total_suspicious,
        "summary_text": summary_text,
        "md_report": md_report,
        "txt_report": md_report,
        "html_report": html_report,
        "pcap_file": None,
        "elapsed_seconds": elapsed
    }


async def run_full_network_analysis(
    capture_duration: int = 60,
    interface: Optional[str] = None
) -> Tuple[str, Optional[str], Optional[str], Optional[str]]:
    """Fachada compatible con NetworkStrategy: retorna (resumen_txt, pcap, md_path, html_path)."""
    res = await execute_network_analysis(duration_seconds=capture_duration, interface_name=interface)
    md_p = str(res["md_report"]) if res.get("md_report") else None
    html_p = str(res["html_report"]) if res.get("html_report") else None
    return res.get("summary_text", ""), None, md_p, html_p
