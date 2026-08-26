"""
# ==============================================================================
# 📶 ANÁLISIS Y DIAGNÓSTICO AVANZADO DE RED: network_analyzer.py (@IA_ValleSeco_bot)
# Captura de tráfico (.pcap), análisis de paquetes (tshark), ARP, OUI y reportes HTML
# Ubicación: /scripts/telegram-admin-bot/monitor/network_analyzer.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import ipaddress
import logging
import os
import re
import socket
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple

from monitor.checker_base import get_active_network_interface
from monitor.config_parser import get_formatted_datetime

logger = logging.getLogger("monitor.network_analyzer")

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_DIR = BASE_DIR / "config"
AUDIT_DIR = BASE_DIR / "audit"


@dataclass
class NetworkHost:
    ip: str
    hostname: str = "desconocido"
    mac: str = "desconocida"
    vendor: str = ""
    rx: int = 0
    tx: int = 0
    total_traffic: int = 0
    latency_ms: float = 0.0
    is_local: bool = True
    has_mac: bool = False
    is_authorized: bool = True
    issues: List[str] = field(default_factory=list)


def is_ip_in_local_subnet(ip_str: str, my_ip: str, my_mask: str) -> bool:
    """Verifica si una IP pertenece a la misma subred local IPv4."""
    try:
        if not ip_str or not my_ip or not my_mask:
            return False
        network = ipaddress.IPv4Network(f"{my_ip}/{my_mask}", strict=False)
        target = ipaddress.IPv4Address(ip_str)
        return target in network
    except Exception:
        return False


def get_interface_ip_and_mask(interface: str) -> Tuple[str, str]:
    """Obtiene la dirección IPv4 y la máscara de red de la interfaz indicada."""
    try:
        import subprocess
        out = subprocess.check_output(
            ["ip", "-4", "addr", "show", "dev", interface],
            text=True,
            timeout=3.0
        )
        match = re.search(r"inet\s+([0-9.]+)/([0-9]+)", out)
        if match:
            return match.group(1), match.group(2)
    except Exception as e:
        logger.warning(f"No se pudo obtener IP/máscara para {interface}: {e}")
    return "", ""


def load_mac_whitelist() -> Set[str]:
    """Carga la lista blanca de direcciones MAC autorizadas."""
    whitelist_path = CONFIG_DIR / "mac_whitelist.txt"
    if not whitelist_path.exists():
        whitelist_path.write_text(
            "# Lista blanca de direcciones MAC autorizadas (una por linea)\n# 00:11:22:33:44:55\n",
            encoding="utf-8"
        )
        return set()

    macs = set()
    try:
        for line in whitelist_path.read_text(encoding="utf-8").splitlines():
            line = line.strip().lower()
            if line and not line.startswith("#") and re.match(r"^([0-9a-f]{2}:){5}[0-9a-f]{2}$", line):
                macs.add(line)
    except Exception as e:
        logger.error(f"Error al leer {whitelist_path}: {e}")
    return macs


def load_oui_database() -> Dict[str, str]:
    """Carga o construye la base de datos de fabricantes OUI."""
    oui_dict: Dict[str, str] = {}
    oui_path = CONFIG_DIR / "oui.txt"
    if oui_path.exists():
        try:
            for line in oui_path.read_text(encoding="utf-8", errors="ignore").splitlines():
                line = line.strip()
                if line and not line.startswith("#") and "\t" in line:
                    parts = line.split("\t", 1)
                    prefix = parts[0].strip().lower().replace(":", "")
                    oui_dict[prefix] = parts[1].strip()
        except Exception as e:
            logger.error(f"Error al leer {oui_path}: {e}")
    return oui_dict


def resolve_hostname_sync(ip_str: str, mac_str: str, oui_db: Dict[str, str]) -> str:
    """Resuelve el hostname vía DNS inverso o fabricante OUI."""
    # 1. Reverse DNS
    try:
        host, _, _ = socket.gethostbyaddr(ip_str)
        if host and host != ip_str:
            return host
    except Exception:
        pass

    # 2. Fabricante OUI
    if mac_str and mac_str not in ("desconocida", "N/A (externo)", ""):
        clean_mac = mac_str.lower().replace(":", "").replace("-", "")
        if len(clean_mac) >= 6:
            prefix = clean_mac[:6]
            if prefix in oui_db:
                return oui_db[prefix]

    return "desconocido"


async def capture_packets(
    interface: str,
    pcap_path: Path,
    duration_seconds: int = 120
) -> int:
    """Captura tráfico de red con tcpdump durante el tiempo indicado (default 120s)."""
    pcap_path.parent.mkdir(parents=True, exist_ok=True)
    if pcap_path.exists():
        pcap_path.unlink()

    logger.info(f"Iniciando captura con tcpdump en '{interface}' durante {duration_seconds}s...")
    proc = await asyncio.create_subprocess_exec(
        "sudo", "tcpdump", "-i", interface, "-s", "0", "-w", str(pcap_path), "-nn", "-p",
        stdout=asyncio.subprocess.DEVNULL,
        stderr=asyncio.subprocess.DEVNULL
    )

    try:
        await asyncio.sleep(duration_seconds)
    finally:
        try:
            # Terminar proceso de captura
            kill_proc = await asyncio.create_subprocess_exec(
                "sudo", "kill", "-2", str(proc.pid),
                stdout=asyncio.subprocess.DEVNULL,
                stderr=asyncio.subprocess.DEVNULL
            )
            await kill_proc.wait()
        except Exception:
            pass

        try:
            await asyncio.wait_for(proc.wait(), timeout=3.0)
        except Exception:
            try:
                proc.kill()
            except Exception:
                pass

    # Cambiar permisos del archivo generado
    try:
        chmod_proc = await asyncio.create_subprocess_exec(
            "sudo", "chmod", "666", str(pcap_path),
            stdout=asyncio.subprocess.DEVNULL,
            stderr=asyncio.subprocess.DEVNULL
        )
        await chmod_proc.wait()
    except Exception:
        pass

    # Contar paquetes capturados
    pkt_count = 0
    if pcap_path.exists() and pcap_path.stat().st_size > 0:
        try:
            cap_proc = await asyncio.create_subprocess_exec(
                "sudo", "tcpdump", "-r", str(pcap_path), "-nn",
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.DEVNULL
            )
            stdout, _ = await cap_proc.communicate()
            pkt_count = len(stdout.splitlines())
        except Exception as e:
            logger.error(f"Error al contar paquetes pcap: {e}")

    logger.info(f"Captura finalizada. Total paquetes: {pkt_count}")
    return pkt_count


async def parse_pcap_traffic(
    pcap_path: Path,
    my_ip: str,
    my_mask: str
) -> Tuple[Dict[str, str], Dict[str, int], Dict[str, int], Dict[str, int], int, int, int]:
    """
    Analiza el archivo .pcap con tshark:
    - Extrae MACs del tráfico
    - Cuenta TX y RX por IP
    - Detecta tráfico sospechoso (puertos inusuales)
    - Conteo de Broadcast / Multicast
    """
    mac_map: Dict[str, str] = {}
    tx_map: Dict[str, int] = {}
    rx_map: Dict[str, int] = {}
    suspicious_map: Dict[str, int] = {}
    broadcast_count = 0
    multicast_v4_count = 0
    multicast_v6_count = 0

    if not pcap_path.exists() or pcap_path.stat().st_size == 0:
        return mac_map, tx_map, rx_map, suspicious_map, 0, 0, 0

    # 1. Extraer MACs asociadas a IPs locales desde el tráfico
    try:
        proc_mac = await asyncio.create_subprocess_exec(
            "sudo", "tshark", "-r", str(pcap_path), "-T", "fields", "-e", "eth.src", "-e", "ip.src",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc_mac.communicate()
        for line in stdout.decode("utf-8", errors="ignore").splitlines():
            parts = line.strip().split()
            if len(parts) >= 2:
                mac, ip = parts[0].strip(), parts[1].strip()
                if re.match(r"^([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}$", mac) and ip != "0.0.0.0":
                    if is_ip_in_local_subnet(ip, my_ip, my_mask):
                        if ip not in mac_map:
                            mac_map[ip] = mac.lower()
                    else:
                        if ip not in mac_map:
                            mac_map[ip] = "N/A (externo)"
    except Exception as e:
        logger.error(f"Error extrayendo MACs con tshark: {e}")

    # 2. Conteo de TX por IP
    try:
        proc_tx = await asyncio.create_subprocess_exec(
            "sudo", "tshark", "-r", str(pcap_path), "-T", "fields", "-e", "ip.src", "-e", "ipv6.src", "-E", "occurrence=f",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc_tx.communicate()
        for line in stdout.decode("utf-8", errors="ignore").splitlines():
            ip = line.strip()
            if ip and ip not in ("0.0.0.0", "::"):
                tx_map[ip] = tx_map.get(ip, 0) + 1
    except Exception as e:
        logger.error(f"Error calculando TX con tshark: {e}")

    # 3. Conteo de RX por IP
    try:
        proc_rx = await asyncio.create_subprocess_exec(
            "sudo", "tshark", "-r", str(pcap_path), "-T", "fields", "-e", "ip.dst", "-e", "ipv6.dst", "-E", "occurrence=f",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc_rx.communicate()
        for line in stdout.decode("utf-8", errors="ignore").splitlines():
            ip = line.strip()
            if ip and ip not in ("0.0.0.0", "::"):
                rx_map[ip] = rx_map.get(ip, 0) + 1
    except Exception as e:
        logger.error(f"Error calculando RX con tshark: {e}")

    # 4. Conteo de tráfico sospechoso (fuera de puertos estándar 80, 443, 22, 53, 123, 5353)
    try:
        proc_susp = await asyncio.create_subprocess_exec(
            "sudo", "tshark", "-r", str(pcap_path),
            "-Y", "not (tcp.port in {80,443,22,53,123,5353} or udp.port in {53,123,5353})",
            "-T", "fields", "-e", "ip.src", "-e", "ipv6.src", "-E", "occurrence=f",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc_susp.communicate()
        for line in stdout.decode("utf-8", errors="ignore").splitlines():
            ip = line.strip()
            if ip and ip not in ("0.0.0.0", "::"):
                suspicious_map[ip] = suspicious_map.get(ip, 0) + 1
    except Exception as e:
        logger.error(f"Error analizando tráfico sospechoso con tshark: {e}")

    # 5. Broadcast y Multicast
    try:
        proc_dump = await asyncio.create_subprocess_exec(
            "sudo", "tcpdump", "-r", str(pcap_path), "-nn",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc_dump.communicate()
        for line in stdout.decode("utf-8", errors="ignore").splitlines():
            if "255.255.255.255" in line or "ff:ff:ff:ff:ff:ff" in line:
                broadcast_count += 1
            if " 224." in line:
                multicast_v4_count += 1
            if " ff0" in line:
                multicast_v6_count += 1
    except Exception as e:
        logger.error(f"Error contando broadcast/multicast: {e}")

    return mac_map, tx_map, rx_map, suspicious_map, broadcast_count, multicast_v4_count, multicast_v6_count


async def run_arp_and_nmap_scan(
    interface: str,
    my_ip: str,
    my_mask: str
) -> Dict[str, str]:
    """Escanea la subred con arp-scan y tabla ARP local para descubrir dispositivos adicionales."""
    discovered_macs: Dict[str, str] = {}

    # 1. arp-scan
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "arp-scan", f"--interface={interface}", "--localnet", "--ignoredups",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc.communicate()
        for line in stdout.decode("utf-8", errors="ignore").splitlines():
            parts = line.strip().split("\t")
            if len(parts) >= 2 and re.match(r"^[0-9.]+$", parts[0]):
                ip, mac = parts[0].strip(), parts[1].strip().lower()
                discovered_macs[ip] = mac
    except Exception as e:
        logger.warning(f"Fallo al ejecutar arp-scan: {e}")

    # 2. Leer /proc/net/arp
    try:
        arp_path = Path("/proc/net/arp")
        if arp_path.exists():
            for line in arp_path.read_text(encoding="utf-8").splitlines()[1:]:
                fields = line.split()
                if len(fields) >= 6 and fields[5] == interface and fields[3] != "00:00:00:00:00:00":
                    ip, mac = fields[0], fields[3].lower()
                    if ip not in discovered_macs:
                        discovered_macs[ip] = mac
    except Exception:
        pass

    # 3. NDP IPv6
    try:
        proc_v6 = await asyncio.create_subprocess_exec(
            "ip", "-6", "neigh", "show", "dev", interface,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc_v6.communicate()
        for line in stdout.decode("utf-8", errors="ignore").splitlines():
            parts = line.strip().split()
            if len(parts) >= 5 and parts[4] not in ("REACHABLE", "00:00:00:00:00:00"):
                ip_v6, mac_v6 = parts[0], parts[4].lower()
                if ip_v6 not in discovered_macs:
                    discovered_macs[ip_v6] = mac_v6
    except Exception:
        pass

    return discovered_macs


async def ping_measure(ip: str) -> float:
    """Mide la latencia media ICMP Ping hacia un host (3 paquetes)."""
    try:
        cmd = ["ping", "-c", "3", "-W", "1", ip]
        if ":" in ip:
            cmd = ["ping", "-6", "-c", "3", "-W", "1", ip]

        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await proc.communicate()
        if proc.returncode == 0:
            out_str = stdout.decode("utf-8", errors="ignore")
            match = re.search(r"= [0-9.]+/([0-9.]+)/", out_str)
            if match:
                return round(float(match.group(1)), 2)
            return 1.0
    except Exception:
        pass
    return 0.0


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
    AUDIT_DIR.mkdir(parents=True, exist_ok=True)
    fecha_str, hora_str = get_formatted_datetime()
    timestamp_file = datetime.now().strftime("%Y%m%d_%H%M%S")
    md_report_path = AUDIT_DIR / f"reporte_red_{timestamp_file}.md"
    html_report_path = AUDIT_DIR / f"reporte_red_{timestamp_file}.html"

    total_locals = len(hosts_local_con_mac) + len(hosts_local_sin_mac)
    total_externos = len(hosts_externos)
    storm_alert = (broadcast_count > 200 or multicast_v4 > 300 or multicast_v6 > 300)

    # -------------------------------------------------------------
    # 1. RESUMEN EJECUTIVO PARA TELEGRAM (HTML SEGURO)
    # -------------------------------------------------------------
    sum_lines = [
        "🔍 <b>REPORTE DE ANÁLISIS DE RED (Debian)</b>",
        f"<b>Fecha:</b> {html.escape(fecha_str)} {html.escape(hora_str)}",
        f"<b>Interfaz:</b> <code>{html.escape(interface)}</code>",
        f"<b>Red local:</b> <code>{html.escape(my_ip)}/{html.escape(my_mask)}</code>" if my_ip else "<b>Red local:</b> <code>N/A</code>",
        f"<b>Duración de captura:</b> {duration_seconds}s",
        f"<b>Paquetes capturados:</b> <code>{pkt_count:,}</code>",
        f"<b>Hosts locales con MAC:</b> {len(hosts_local_con_mac)}",
        f"<b>Hosts locales sin MAC:</b> {len(hosts_local_sin_mac)}",
        f"<b>Hosts externos (WAN):</b> {total_externos}",
        f"<b>Tráfico Sospechoso:</b> {total_suspicious} paquetes",
    ]

    if storm_alert:
        sum_lines.append("⚠️ <b>ALERTA:</b> Tráfico anormal de Broadcast/Multicast detectado.")

    sum_lines.append("")
    sum_lines.append("📊 <b>Top Dispositivos en la Red:</b>")

    for h in (hosts_local_con_mac + hosts_local_sin_mac)[:10]:
        icon = "🟢" if h.latency_ms > 0 else "⚪"
        lat_text = f"[{h.latency_ms} ms]" if h.latency_ms > 0 else "[Sin respuesta ICMP]"
        auth_flag = " ⚠️ <i>(No autorizada)</i>" if not h.is_authorized else ""
        h_name = html.escape(h.hostname[:22])
        sum_lines.append(f"{icon} <code>{html.escape(h.ip):<15}</code> • <code>{html.escape(h.mac)}</code>{auth_flag}\n   🏷️ <i>{h_name}</i> <code>{lat_text}</code> (TX: {h.tx}, RX: {h.rx})")

    sum_lines.append(f"\n📁 <i>Reportes detallados adjuntos (.md y .html)</i>")
    telegram_summary = "\n".join(sum_lines)

    # -------------------------------------------------------------
    # 2. REPORTE EN MARKDOWN (.MD) CON TABLAS FORMATEADAS
    # -------------------------------------------------------------
    md_lines = [
        "# 🔍 Reporte de Análisis de Red (Debian)",
        "",
        f"- **Fecha:** {fecha_str} {hora_str}",
        f"- **Interfaz:** `{interface}`",
        f"- **Red local:** `{my_ip}/{my_mask}`" if my_ip else "- **Red local:** `N/A`",
        f"- **Duración de captura:** {duration_seconds} segundos",
        f"- **Paquetes capturados:** {pkt_count:,}",
        f"- **Hosts locales con MAC:** {len(hosts_local_con_mac)}",
        f"- **Hosts locales sin MAC:** {len(hosts_local_sin_mac)}",
        f"- **Hosts externos (WAN):** {total_externos}",
        f"- **Tráfico Sospechoso:** {total_suspicious} paquetes",
        ""
    ]

    if total_suspicious > 50:
        md_lines.append("## 🚨 Tráfico Sospechoso Detectado")
        md_lines.append(f"Se detectaron **{total_suspicious}** paquetes fuera de puertos estándar.")
        md_lines.append("")
        md_lines.append("| Dirección IP | Dirección MAC | Paquetes Sospechosos |")
        md_lines.append("| :--- | :--- | :--- |")
        for s_ip, s_mac, s_count in suspicious_hosts:
            md_lines.append(f"| `{s_ip}` | `{s_mac}` | **{s_count}** |")
        md_lines.append("")

    md_lines.append("## 📊 Tabla de Dispositivos en la Red")
    md_lines.append("")

    # Sección 1: Hosts locales con MAC
    if hosts_local_con_mac:
        md_lines.append("### 🔹 Hosts Locales (en la misma red)")
        md_lines.append("")
        md_lines.append("| Dirección IP | Hostname / Fabricante | Dirección MAC | RX (recibidos) | TX (enviados) | Total Tráfico |")
        md_lines.append("| :--- | :--- | :--- | :--- | :--- | :--- |")
        for h in hosts_local_con_mac:
            md_lines.append(f"| `{h.ip}` | {h.hostname} | `{h.mac}` | {h.rx} | {h.tx} | **{h.total_traffic}** |")
        md_lines.append("")

    # Sección 2: Hosts locales sin MAC
    if hosts_local_sin_mac:
        md_lines.append("### 🔸 Hosts Locales (sin MAC detectada)")
        md_lines.append("")
        md_lines.append("| Dirección IP | Hostname / Fabricante | Dirección MAC | RX (recibidos) | TX (enviados) | Total Tráfico |")
        md_lines.append("| :--- | :--- | :--- | :--- | :--- | :--- |")
        for h in hosts_local_sin_mac:
            md_lines.append(f"| `{h.ip}` | {h.hostname} | `{h.mac}` | {h.rx} | {h.tx} | **{h.total_traffic}** |")
        md_lines.append("")

    # Sección 3: Hosts externos
    if hosts_externos:
        md_lines.append("### 🌐 Hosts Externos (Internet / Otras Redes)")
        md_lines.append("")
        md_lines.append("| Dirección IP | Hostname / Fabricante | Dirección MAC | RX (recibidos) | TX (enviados) | Total Tráfico |")
        md_lines.append("| :--- | :--- | :--- | :--- | :--- | :--- |")
        for h in hosts_externos:
            md_lines.append(f"| `{h.ip}` | {h.hostname} | `{h.mac}` | {h.rx} | {h.tx} | **{h.total_traffic}** |")
        md_lines.append("")

    if storm_alert:
        md_lines.append("> ⚠️ **ALERTA GLOBAL:** Posible tormenta (storm) de broadcast o multicast detectada.")
        md_lines.append("")

    md_lines.append("## ⏱️ Latencias ICMP (ordenadas de mayor a menor)")
    md_lines.append("")
    if latencias_ordenadas:
        md_lines.append("| Dispositivo | Latencia (ms) | Dirección MAC | TX | RX | Total Tráfico |")
        md_lines.append("| :--- | :--- | :--- | :--- | :--- | :--- |")
        for h in latencias_ordenadas:
            lat_str = f"**{h.latency_ms:.2f} ms**" if h.latency_ms > 150.0 else f"{h.latency_ms:.2f} ms"
            md_lines.append(f"| `{h.ip}` | {lat_str} | `{h.mac}` | {h.tx} | {h.rx} | {h.total_traffic} |")
    else:
        md_lines.append("Sin datos de latencia registrados.")

    md_lines.append("")
    md_lines.append("---")
    md_lines.append(f"*Reporte de análisis de tráfico generado automáticamente ({duration_seconds} segundos de captura).*")

    md_report_path.write_text("\n".join(md_lines), encoding="utf-8")

    # -------------------------------------------------------------
    # 3. REPORTE HTML INTERACTIVO (.HTML) CON CONTRASTE MEJORADO
    # -------------------------------------------------------------
    html_sections = []

    # Bloque Sospechoso
    if total_suspicious > 50:
        susp_rows = "".join([f"<tr><td><code>{html.escape(s[0])}</code></td><td><code>{html.escape(s[1])}</code></td><td><b>{s[2]}</b></td></tr>" for s in suspicious_hosts])
        html_sections.append(f"""
        <div class="section suspicious">
            <h2>🚨 Tráfico Sospechoso Detectado</h2>
            <p>Se detectaron <strong>{total_suspicious}</strong> paquetes fuera de puertos comunes.</p>
            <table>
                <tr><th>IP</th><th>MAC</th><th>Paquetes Sospechosos</th></tr>
                {susp_rows}
            </table>
        </div>
        """)

    # Tabla de Dispositivos Locales con MAC
    if hosts_local_con_mac:
        rows = "".join([f"<tr><td><code>{html.escape(h.ip)}</code></td><td>{html.escape(h.hostname)}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.rx}</td><td>{h.tx}</td><td><b>{h.total_traffic}</b></td><td>{html.escape(fecha_str)}</td></tr>" for h in hosts_local_con_mac])
        html_sections.append(f"""
        <div class="section local">
            <h3>🔹 Hosts Locales (en la misma red)</h3>
            <table>
                <tr><th>IP</th><th>Hostname</th><th>MAC</th><th>RX</th><th>TX</th><th>Total</th><th>Fecha</th></tr>
                {rows}
            </table>
        </div>
        """)

    # Tabla de Dispositivos Locales sin MAC
    if hosts_local_sin_mac:
        rows = "".join([f"<tr><td><code>{html.escape(h.ip)}</code></td><td>{html.escape(h.hostname)}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.rx}</td><td>{h.tx}</td><td><b>{h.total_traffic}</b></td><td>{html.escape(fecha_str)}</td></tr>" for h in hosts_local_sin_mac])
        html_sections.append(f"""
        <div class="section local-unknown">
            <h3>🔸 Hosts Locales (sin MAC detectada)</h3>
            <table>
                <tr><th>IP</th><th>Hostname</th><th>MAC</th><th>RX</th><th>TX</th><th>Total</th><th>Fecha</th></tr>
                {rows}
            </table>
        </div>
        """)

    # Tabla de Dispositivos Externos
    if hosts_externos:
        rows = "".join([f"<tr><td><code>{html.escape(h.ip)}</code></td><td>{html.escape(h.hostname)}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.rx}</td><td>{h.tx}</td><td><b>{h.total_traffic}</b></td><td>{html.escape(fecha_str)}</td></tr>" for h in hosts_externos])
        html_sections.append(f"""
        <div class="section external">
            <h3>🌐 Hosts Externos (Internet / otras redes)</h3>
            <table>
                <tr><th>IP</th><th>Hostname</th><th>MAC</th><th>RX</th><th>TX</th><th>Total</th><th>Fecha</th></tr>
                {rows}
            </table>
        </div>
        """)

    # Tabla de Latencias
    lat_rows = []
    for h in latencias_ordenadas:
        lat_class = "high-latency" if h.latency_ms > 150.0 else ""
        lat_str = f"{h.latency_ms:.2f}" if h.latency_ms > 0 else "N/A"
        lat_rows.append(f"<tr><td><code>{html.escape(h.ip)}</code></td><td class='{lat_class}'>{lat_str}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.tx}</td><td>{h.rx}</td><td>{h.total_traffic}</td></tr>")

    html_content = f"""<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Análisis de Red - {html.escape(fecha_str)}</title>
    <style>
        body {{ font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f4f7f6; color: #1e293b; }}
        .header {{ background: #1e293b; color: #ffffff; padding: 22px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }}
        .header h1 {{ margin-top: 0; color: #38bdf8; font-size: 24px; }}
        .header p {{ margin: 6px 0; color: #e2e8f0; font-size: 15px; }}
        .header code {{ background: #0f172a; color: #4ade80; font-weight: bold; padding: 3px 8px; border-radius: 4px; border: 1px solid #334155; font-family: monospace; font-size: 14px; }}
        .section {{ background: #ffffff; padding: 18px; margin: 15px 0; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }}
        .local {{ border-left: 5px solid #2ecc71; }}
        .local-unknown {{ border-left: 5px solid #f39c12; }}
        .external {{ border-left: 5px solid #3498db; }}
        .suspicious {{ background-color: #ffebee; border-left: 5px solid #e74c3c; }}
        table {{ width: 100%; border-collapse: collapse; margin: 12px 0; }}
        th, td {{ border: 1px solid #cbd5e1; padding: 10px; text-align: left; font-size: 14px; color: #1e293b; }}
        th {{ background-color: #f1f5f9; color: #334155; font-weight: 600; }}
        tr:hover {{ background-color: #f8fafc; }}
        code {{ background: #f1f5f9; color: #0f172a; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; }}
        .high-latency {{ color: #dc2626; font-weight: bold; }}
        .alert {{ background-color: #fee2e2; border-left: 5px solid #dc2626; padding: 12px; border-radius: 6px; color: #991b1b; }}
        .note {{ background-color: #f0fdf4; border-left: 5px solid #16a34a; padding: 12px; border-radius: 6px; margin-top: 15px; font-size: 13px; color: #166534; }}
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Reporte de Análisis de Red (Debian)</h1>
        <p><strong>Fecha:</strong> {html.escape(fecha_str)} {html.escape(hora_str)} &nbsp;|&nbsp; <strong>Interfaz:</strong> <code>{html.escape(interface)}</code></p>
        <p><strong>Red local:</strong> <code>{html.escape(my_ip)}/{html.escape(my_mask)}</code> &nbsp;|&nbsp; <strong>Duración:</strong> {duration_seconds}s &nbsp;|&nbsp; <strong>Paquetes:</strong> <code>{pkt_count:,}</code></p>
    </div>

    {''.join(html_sections)}

    <div class="section">
        <h2>⏱️ Latencias ICMP (ordenadas de mayor a menor)</h2>
        <table>
            <tr><th>Dispositivo</th><th>Latencia (ms)</th><th>MAC</th><th>TX</th><th>RX</th><th>Total Tráfico</th></tr>
            {''.join(lat_rows)}
        </table>
    </div>

    {'<div class="alert"><p>⚠️ <strong>ALERTA GLOBAL:</strong> Posible tormenta de broadcast/multicast detectada.</p></div>' if storm_alert else ''}

    <div class="note">
        <p><strong>ℹ️ Información del Análisis:</strong><br>
        - <strong>Hosts locales:</strong> Dispositivos dentro de la subred local {html.escape(my_ip)}/{html.escape(my_mask)}.<br>
        - <strong>Hosts externos:</strong> Direcciones IP fuera de la subred local (tráfico WAN/Internet).<br>
        - <strong class="high-latency">Latencias altas (&gt;150 ms)</strong> se destacan en color rojo.
        </p>
    </div>
</body>
</html>"""

    html_report_path.write_text(html_content, encoding="utf-8")

    return telegram_summary, md_report_path, html_report_path


async def execute_network_analysis(
    duration_seconds: int = 120,
    interface_name: Optional[str] = None
) -> Dict[str, any]:
    """
    Ejecución completa del análisis de red:
    1. Detección de interfaz y subred.
    2. Carga de whitelist de MACs y base OUI.
    3. Captura real de paquetes durante `duration_seconds` con tcpdump.
    4. Análisis de tráfico con tshark (TX/RX/Sospechoso/Broadcast).
    5. Escaneo complementario ARP / NDP.
    6. Medición de latencias ICMP (ping).
    7. Generación de reportes (.txt, .html y resumen Telegram).
    """
    t0 = time.perf_counter()
    iface = get_active_network_interface(interface_name or "eth0")
    my_ip, my_mask = get_interface_ip_and_mask(iface)

    known_macs = load_mac_whitelist()
    oui_db = load_oui_database()

    timestamp_str = datetime.now().strftime("%Y%m%d_%H%M%S")
    pcap_path = AUDIT_DIR / f"captura_{timestamp_str}.pcap"

    # 1. Captura con tcpdump (120s por defecto)
    pkt_count = await capture_packets(iface, pcap_path, duration_seconds=duration_seconds)

    # 2. Análisis del archivo pcap con tshark
    (
        traffic_macs,
        tx_map,
        rx_map,
        suspicious_map,
        broadcast_count,
        multicast_v4,
        multicast_v6
    ) = await parse_pcap_traffic(pcap_path, my_ip, my_mask)

    # 3. Escaneo ARP y NDP
    arp_macs = await run_arp_and_nmap_scan(iface, my_ip, my_mask)

    # Consolidar dispositivos descubiertos
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

    # 4. Medición concurrente de latencias para hosts locales y activos
    measure_targets = [h for h in all_hosts.values() if h.is_local or h.total_traffic > 0]
    if measure_targets:
        latencies = await asyncio.gather(*(ping_measure(h.ip) for h in measure_targets))
        for h, lat in zip(measure_targets, latencies):
            h.latency_ms = lat

    # 5. Clasificación de hosts
    hosts_local_con_mac = [h for h in all_hosts.values() if h.is_local and h.has_mac]
    hosts_local_sin_mac = [h for h in all_hosts.values() if h.is_local and not h.has_mac]
    hosts_externos = [h for h in all_hosts.values() if not h.is_local]

    # Ordenar por IP
    hosts_local_con_mac.sort(key=lambda x: x.ip)
    hosts_local_sin_mac.sort(key=lambda x: x.ip)
    hosts_externos.sort(key=lambda x: x.ip)

    # Latencias ordenadas descendentemente
    latencias_ordenadas = sorted(
        [h for h in all_hosts.values() if h.latency_ms > 0],
        key=lambda x: x.latency_ms,
        reverse=True
    )

    # Tráfico sospechoso
    total_suspicious = sum(suspicious_map.values())
    suspicious_hosts = [
        (ip, all_hosts[ip].mac if ip in all_hosts else "desconocida", cnt)
        for ip, cnt in suspicious_map.items() if cnt > 0
    ]

    elapsed = round(time.perf_counter() - t0, 2)

    # 6. Generar reportes
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
        "pcap_file": pcap_path,
        "elapsed_seconds": elapsed
    }
