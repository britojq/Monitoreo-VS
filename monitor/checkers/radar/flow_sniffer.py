"""
Sniffer de flujos de red y descubrimiento ARP de kernel con tshark y tcpdump.
"""
import asyncio
import ipaddress
import json
import logging
from pathlib import Path
import re
import subprocess
import time
from typing import Any, Dict, List, Tuple

from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.radar.sniffer")


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


def lookup_oui_vendor(mac: str, conn=None) -> str:
    """Resuelve el fabricante del dispositivo a partir del prefijo OUI consultando MariaDB (SSOT)."""
    if not mac or len(mac) < 8:
        return "Desconocido"

    prefix = mac[:8].upper()
    try:
        with db_cursor() as cur:
            cur.execute("SELECT vendor_name FROM oui_vendors WHERE oui_prefix = %s LIMIT 1", (prefix,))
            row = cur.fetchone()
            if row and row.get("vendor_name"):
                return row["vendor_name"]
    except Exception as e:
        logger.debug(f"Aviso consultando OUI en MariaDB: {e}")
    return "Fabricante Desconocido"


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
    """Captura tráfico de red y extrae flujos con tshark."""
    pcap_temp = Path(f"/tmp/net_radar_{int(time.time())}.pcap")
    if pcap_temp.exists():
        pcap_temp.unlink()

    flows: List[Dict[str, Any]] = []
    try:
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
        logger.error(f"Error durante captura de flujos: {e}")
    finally:
        if pcap_temp.exists():
            try:
                pcap_temp.unlink()
            except Exception:
                pass

    return flows
