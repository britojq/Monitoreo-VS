"""
Módulo base de chequeo de protocolos para el sistema de monitoreo.
Implementa verificaciones asíncronas para WEB, DNS, PROXY, SMTP, DHCP, CUPS, LDAP y PING.
"""

from __future__ import annotations

import asyncio
import logging
import re
import socket
from typing import Tuple

import httpx

logger = logging.getLogger(__name__)


async def check_web(url: str, timeout: float = 4.0) -> Tuple[bool, str, str]:
    """Verifica disponibilidad de una aplicación web vía HTTP/HTTPS."""
    if not url or url.startswith("0.0.0.0"):
        return False, "0", "URL inválida o no configurada"

    try:
        async with httpx.AsyncClient(verify=False, timeout=timeout, follow_redirects=True) as client:
            resp = await client.get(url)
            code = resp.status_code
            if code in (200, 301, 302, 307, 308):
                return True, str(code), f"HTTP {code} OK"
            return False, str(code), f"HTTP {code} Error"
    except httpx.TimeoutException:
        return False, "0", "Timeout al conectar al sitio web"
    except Exception as e:
        return False, "0", f"Error de conexión: {e}"


async def check_dns(dns_server: str, test_host: str, timeout: float = 3.5) -> Tuple[bool, str, str]:
    """Verifica resolución DNS usando dig @dns_server test_host."""
    if not dns_server or dns_server.startswith("0.0.0.0"):
        return False, "0", "Servidor DNS no configurado"

    target_host = test_host if test_host else "corpoelec.gob.ve"
    try:
        proc = await asyncio.create_subprocess_exec(
            "dig", f"@{dns_server}", target_host, "+time=2", "+tries=1",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")
        if proc.returncode == 0 and (";; Got answer:" in out_str or "ANSWER: " in out_str):
            return True, "200", "Servicio DNS responde correctamente"
        return False, "0", f"Sin respuesta DNS válida: {out_str[:120]}"
    except asyncio.TimeoutError:
        return False, "0", "Timeout en consulta DNS"
    except Exception as e:
        return False, "0", f"Error ejecutando dig: {e}"


async def check_proxy(proxy_url: str, test_url: str = "https://core.telegram.org/bots", timeout: float = 4.5) -> Tuple[bool, str, str]:
    """Verifica navegación a través de un proxy corporativo (Squid/pfSense)."""
    if not proxy_url:
        return False, "0", "Proxy no configurado"

    target = test_url if test_url else "https://api.telegram.org"
    try:
        async with httpx.AsyncClient(proxy=proxy_url, verify=False, timeout=timeout) as client:
            resp = await client.get(target)
            code = resp.status_code
            if code in (200, 301, 302):
                return True, str(code), f"Proxy operativo (HTTP {code})"
            return False, str(code), f"Proxy respondió HTTP {code}"
    except httpx.TimeoutException:
        return False, "0", "Timeout al probar proxy"
    except Exception as e:
        return False, "0", f"Fallo de conexión por proxy: {e}"


async def check_smtp(ip: str, port: str = "25", timeout: float = 4.0) -> Tuple[bool, str, str]:
    """Verifica la respuesta de un servidor SMTP conectando al socket y enviando HELO."""
    if not ip or ip.startswith("0.0.0.0"):
        return False, "0", "IP SMTP no configurada"

    port_int = int(port) if port.isdigit() else 25
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(ip, port_int), timeout=timeout
        )
        banner = await asyncio.wait_for(reader.readline(), timeout=2.0)
        writer.write(b"HELO mail.corpoelec.gob.ve\r\n")
        await writer.drain()
        reply = await asyncio.wait_for(reader.readline(), timeout=2.0)
        writer.write(b"QUIT\r\n")
        await writer.drain()
        writer.close()
        await writer.wait_closed()

        banner_str = banner.decode("utf-8", errors="ignore").strip()
        return True, "250", f"SMTP responde: {banner_str[:80]}"
    except asyncio.TimeoutError:
        return False, "0", "Timeout esperando respuesta SMTP"
    except Exception as e:
        return False, "0", f"Fallo al conectar a servidor SMTP ({ip}:{port_int}): {e}"


async def check_dhcp(interface: str = "eth0", timeout: float = 5.0) -> Tuple[bool, str, str]:
    """Verifica si un servidor DHCP ofrece concesiones en la red local."""
    iface = interface if interface else "eth0"
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "nmap", "--script", "broadcast-dhcp-discover", "-e", iface,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")
        if "DHCP Message Type: DHCPOFFER" in out_str:
            return True, "200", "Servidor DHCP activo (DHCPOFFER recibido)"
        return False, "0", "Servidor DHCP no responde u ofertas no recibidas"
    except asyncio.TimeoutError:
        return False, "0", "Timeout en broadcast DHCP"
    except Exception as e:
        return False, "0", f"Error verificando DHCP: {e}"


async def check_cups(cups_ip_port: str, timeout: float = 4.0) -> Tuple[bool, str, str]:
    """Verifica la cola de impresión de un servidor CUPS."""
    if not cups_ip_port or cups_ip_port.startswith("0.0.0.0"):
        return False, "0", "Servidor CUPS no configurado"

    try:
        proc = await asyncio.create_subprocess_exec(
            "lpstat", "-h", cups_ip_port, "-t", "-o",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")
        if "Ready to print" in out_str or "scheduler is running" in out_str or proc.returncode == 0:
            return True, "200", "Servidor CUPS operativo"
        return False, "0", f"CUPS no responde: {out_str[:100]}"
    except asyncio.TimeoutError:
        return False, "0", "Timeout en consulta CUPS"
    except Exception as e:
        return False, "0", f"Error consultando CUPS: {e}"


async def check_ldap(ip: str, port: str = "389", timeout: float = 3.5) -> Tuple[bool, str, str]:
    """Verifica conexión TCP al puerto LDAP (389 / 636)."""
    if not ip or ip.startswith("0.0.0.0"):
        return False, "0", "IP LDAP no configurada"

    port_int = int(port) if port.isdigit() else 389
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(ip, port_int), timeout=timeout
        )
        writer.close()
        await writer.wait_closed()
        return True, "200", f"Puerto LDAP ({ip}:{port_int}) abierto y respondiendo"
    except asyncio.TimeoutError:
        return False, "0", f"Timeout conectando al puerto LDAP {port_int}"
    except Exception as e:
        return False, "0", f"Fallo al conectar a LDAP ({ip}:{port_int}): {e}"


async def check_ping(ip: str, count: int = 3, timeout: float = 3.0) -> Tuple[bool, str, str]:
    """Verifica conectividad ICMP (Ping) hacia una dirección IP."""
    if not ip or ip.startswith("0.0.0.0") or ip == "127.0.0.1":
        return False, "0", "IP no asignada o inválida (0.0.0.0)"

    try:
        proc = await asyncio.create_subprocess_exec(
            "ping", "-qc", str(count), "-W", "2", ip,
            stdout=asyncio.subprocess.DEVNULL,
            stderr=asyncio.subprocess.DEVNULL
        )
        code = await asyncio.wait_for(proc.wait(), timeout=timeout)
        if code == 0:
            return True, "200", f"Responde a Ping ICMP ({ip})"
        return False, "0", f"Sin respuesta de Ping ({ip})"
    except asyncio.TimeoutError:
        return False, "0", f"Timeout en Ping ({ip})"
    except Exception as e:
        return False, "0", f"Error ejecutando Ping ({ip}): {e}"
