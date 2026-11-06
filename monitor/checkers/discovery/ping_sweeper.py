"""
Barrido de red ICMP/Nmap asíncrono y resolución de tablas ARP locales.
"""
import asyncio
import ipaddress
import json
import logging
import os
from pathlib import Path
import re
import socket
import subprocess
from typing import Any, Dict, List

logger = logging.getLogger("monitor.checkers.discovery.ping_sweeper")


def get_local_interfaces_map() -> Dict[str, str]:
    """Retorna un diccionario {ip: mac_normalizada} de las interfaces locales."""
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
    """Obtiene la tabla ARP del kernel de Linux ({ip: mac_normalizada})."""
    arp_map: Dict[str, str] = {}
    try:
        res = subprocess.run(["ip", "-j", "neigh"], capture_output=True, text=True, timeout=3)
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


async def sweep_subnet_async(subnet_str: str, rate_limit_pps: int = 150) -> List[Dict[str, Any]]:
    """Realiza un escaneo activo de la subred para despertar respuestas ARP."""
    live_hosts: List[Dict[str, Any]] = []
    try:
        net = ipaddress.ip_network(subnet_str, strict=False)
    except ValueError as e:
        logger.error(f"Subred inválida {subnet_str}: {e}")
        return live_hosts

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

    sem = asyncio.Semaphore(rate_limit_pps)

    async def ping_single(ip_str: str):
        """Envía sondas ICMP asíncronas para comprobar accesibilidad de una dirección IP.

        Args:
            ip_str: Parámetro operativo de entrada.
        """
        async with sem:
            try:
                proc = await asyncio.create_subprocess_exec(
                    "ping", "-c", "1", "-W", "1", ip_str,
                    stdout=asyncio.subprocess.DEVNULL,
                    stderr=asyncio.subprocess.DEVNULL
                )
                rc = await proc.wait()
                if rc == 0:
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
