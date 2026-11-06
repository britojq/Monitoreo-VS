"""
Analizador de flujos de tráfico .pcap con tshark, escaneo ARP y latencias ICMP.
"""
import asyncio
import logging
from pathlib import Path
import re
from typing import Dict, Tuple

from monitor.checkers.analyzer.oui_resolver import is_ip_in_local_subnet

logger = logging.getLogger("monitor.checkers.analyzer.pcap_parser")


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
