"""
# ==============================================================================
# 🧪 MÓDULO BASE DE CHEQUEO DE PROTOCOLOS: checker_base.py (@IA_ValleSeco_bot)
# Verificaciones de red (HTTP/S, DNS, SMTP, LDAP, DHCP, CUPS, Ping y Formateo HTML)
# Ubicación: /scripts/telegram-admin-bot/monitor/checker_base.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
from pathlib import Path
import re
import socket
import time
from typing import Tuple

import httpx
from monitor.proxy_cache import (
    get_cached_proxy_result,
    save_cached_proxy_result,
    ProxyInflightGuard
)

logger = logging.getLogger(__name__)


def get_active_network_interface(configured_iface: str = "eth0") -> str:
    """
    Verifica si la interfaz configurada existe físicamente en el sistema (/sys/class/net/).
    Si está mal configurada o no existe, detecta y corrige automáticamente a la interfaz activa.
    """
    if configured_iface and Path(f"/sys/class/net/{configured_iface}").exists():
        return configured_iface

    # 1. Intentar detectar interfaz de ruta por defecto
    try:
        with open("/proc/net/route", "r") as f:
            for line in f:
                fields = line.strip().split()
                if len(fields) >= 2 and fields[1] == "00000000":
                    candidate = fields[0]
                    if Path(f"/sys/class/net/{candidate}").exists():
                        logger.warning(
                            f"[!] ADVERTENCIA: La interfaz '{configured_iface}' no existe. "
                            f"Corrigiendo automáticamente a la interfaz activa detectada: '{candidate}'"
                        )
                        return candidate
    except Exception:
        pass

    # 2. Buscar primera interfaz activa no-loopback/no-virtual en /sys/class/net/
    try:
        net_dir = Path("/sys/class/net")
        if net_dir.exists():
            for iface_p in net_dir.iterdir():
                iname = iface_p.name
                if iname not in ("lo", "virbr0", "docker0") and not iname.startswith("veth"):
                    logger.warning(
                        f"[!] ADVERTENCIA: La interfaz '{configured_iface}' no existe. "
                        f"Corrigiendo automáticamente a la interfaz detectada: '{iname}'"
                    )
                    return iname
    except Exception:
        pass

    return configured_iface or "eth0"


async def check_web(url: str, timeout: float = 4.0) -> Tuple[bool, str, str]:
    """
    Verifica disponibilidad de una aplicación web vía HTTP/HTTPS utilizando
    curl -k --ciphers 'DEFAULT:@SECLEVEL=0' -s -o /dev/null -w '%{http_code}'
    para soportar servidores heredados y certificados SSL antiguos/corporativos.
    """
    if not url or url.startswith("0.0.0.0"):
        return False, "0", "URL inválida o no configurada"

    target_url = url if url.startswith(("http://", "https://")) else f"http://{url}"

    try:
        proc = await asyncio.create_subprocess_exec(
            "curl",
            "-k",
            "--ciphers", "DEFAULT:@SECLEVEL=0",
            "-s",
            "-o", "/dev/null",
            "-w", "%{http_code}",
            "--connect-timeout", "3",
            "--max-time", str(int(timeout)),
            target_url,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout + 1.0)
        code_str = stdout.decode("utf-8", errors="ignore").strip()

        if code_str.isdigit():
            code = int(code_str)
            if code in (200, 301, 302, 307, 308, 401, 403):
                return True, code_str, f"HTTP {code_str} OK"
            elif code > 0:
                return False, code_str, f"HTTP {code_str} Error"

        return False, code_str or "0", "Sin respuesta (Timeout / Caído)"
    except asyncio.TimeoutError:
        return False, "0", "Timeout al conectar al sitio web"
    except Exception as e:
        return False, "0", f"Error de conexión: {e}"


async def check_dns(dns_server: str, test_host: str, timeout: float = 3.5) -> Tuple[bool, str, str]:
    """Verifica resolución DNS usando dig @dns_server test_host."""
    if not dns_server or dns_server.startswith("0.0.0.0"):
        return False, "0", "Servidor DNS no configurado"

    target_host = test_host if test_host else "1.1.1.1"
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
    """Verifica navegación a través de un proxy corporativo (Squid/pfSense) usando curl con SECLEVEL=0 y caché anti-colisión."""
    if not proxy_url:
        return False, "0", "Proxy no configurado"

    target = test_url if test_url else "https://core.telegram.org/bots"

    # 1. Comprobar caché de corto plazo (60s) en memoria RAM compartida (/dev/shm)
    cached = get_cached_proxy_result(proxy_url, target, max_age_seconds=60.0)
    if cached:
        return cached["is_ok"], cached["code_str"], cached["detail"]

    # 2. Candado In-Flight: Si otro proceso ya está chequeando este proxy en este instante, esperar su resultado
    guard = ProxyInflightGuard(proxy_url, target)
    waited_cached = await guard.acquire_or_wait(timeout=timeout)
    if waited_cached:
        return waited_cached["is_ok"], waited_cached["code_str"], waited_cached["detail"]

    # 3. Realizar chequeo físico con curl
    t0 = time.perf_counter()
    try:
        proc = await asyncio.create_subprocess_exec(
            "curl",
            "-x", proxy_url,
            "-k",
            "--ciphers", "DEFAULT:@SECLEVEL=0",
            "-s",
            "-o", "/dev/null",
            "-w", "%{http_code}",
            "--connect-timeout", "3",
            "--max-time", str(int(timeout)),
            target,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout + 1.0)
        elapsed_ms = (time.perf_counter() - t0) * 1000.0
        code_str = stdout.decode("utf-8", errors="ignore").strip()
        is_ok = False
        detail = "Proxy no responde (Timeout / Caído)"
        if code_str.isdigit():
            code = int(code_str)
            if code in (200, 301, 302):
                is_ok = True
                detail = f"Proxy operativo (HTTP {code_str})"
            elif code > 0:
                detail = f"Proxy respondió HTTP {code_str}"

        # Guardar en memoria compartida para reutilización inmediata por otros procesos
        save_cached_proxy_result(proxy_url, target, is_ok, code_str, detail, elapsed_ms, source="curl")
        return is_ok, code_str, detail
    except asyncio.TimeoutError:
        elapsed_ms = (time.perf_counter() - t0) * 1000.0
        save_cached_proxy_result(proxy_url, target, False, "0", "Timeout al probar proxy", elapsed_ms, source="curl")
        return False, "0", "Timeout al probar proxy"
    except Exception as e:
        elapsed_ms = (time.perf_counter() - t0) * 1000.0
        return False, "0", f"Fallo al probar proxy: {e}"
    finally:
        guard.release()


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
        writer.write(b"HELO mail.empresa.local\r\n")
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


async def check_dhcp(interface: str = "eth0", timeout: float = 13.0) -> Tuple[bool, str, str]:
    """Verifica si un servidor DHCP ofrece concesiones en la red local usando broadcast con nmap."""
    active_iface = get_active_network_interface(interface)
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "nmap", "--script", "broadcast-dhcp-discover", "-e", active_iface,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")
        if "DHCP Message Type: DHCPOFFER" in out_str or "IP Offered:" in out_str:
            return True, "200", f"Servidor DHCP activo (DHCPOFFER recibido en {active_iface})"
        return False, "0", f"Servidor DHCP no responde en {active_iface}"
    except asyncio.TimeoutError:
        return False, "0", f"Timeout en broadcast DHCP ({active_iface})"
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


def markdown_to_telegram_html(text: str) -> str:
    """Convierte Markdown estándar (títulos, negritas, código de consola) a HTML compatible con Telegram."""
    if not text:
        return ""

    import html

    # 1. Proteger bloques de código multilínea ```lang ... ```
    code_blocks = []
    def _save_code_block(match):
        lang = (match.group(1) or "").strip()
        code_content = match.group(2)
        idx = len(code_blocks)
        code_blocks.append((lang, code_content))
        return f"QQQBLOCKCODE{idx}ZZZ"

    text = re.sub(r'```([a-zA-Z0-9_-]*)\n?(.*?)```', _save_code_block, text, flags=re.DOTALL)

    # 2. Proteger código inline `code`
    inline_codes = []
    def _save_inline_code(match):
        inline_content = match.group(1)
        idx = len(inline_codes)
        inline_codes.append(inline_content)
        return f"QQQINLINECODE{idx}ZZZ"

    text = re.sub(r'`([^`\n]+)`', _save_inline_code, text)

    # 3. Escapar caracteres HTML básicos en el texto general
    text = html.escape(text)

    # 4. Normalizar viñetas de listas (* item o - item -> • item)
    text = re.sub(r'^[ \t]*[\*\-][ \t]+', r'• ', text, flags=re.MULTILINE)

    # 5. Títulos y subtítulos (# Título -> <b>Título</b>)
    text = re.sub(r'^(#{1,6})\s+(.+)$', r'<b>\2</b>', text, flags=re.MULTILINE)

    # 6. Negrita + Cursiva (***texto*** o ___texto___)
    text = re.sub(r'\*\*\*(.+?)\*\*\*', r'<b><i>\1</i></b>', text, flags=re.DOTALL)
    text = re.sub(r'___(.+?)___', r'<b><i>\1</i></b>', text, flags=re.DOTALL)

    # 7. Negrita (**texto** o __texto__)
    text = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', text, flags=re.DOTALL)
    text = re.sub(r'(?<![a-zA-Z0-9])__(.+?)__(?![a-zA-Z0-9])', r'<b>\1</b>', text, flags=re.DOTALL)

    # 8. Cursiva (*texto* o _texto_)
    text = re.sub(r'(?<!\*)\*([^\*\n]+)\*(?!\*)', r'<i>\1</i>', text)
    text = re.sub(r'(?<![a-zA-Z0-9_])_([^_\n]+)_(?![a-zA-Z0-9_])', r'<i>\1</i>', text)

    # 9. Restaurar bloques de código multilínea <pre><code>...</code></pre>
    for idx, (lang, code_content) in enumerate(code_blocks):
        escaped_code = html.escape(code_content.strip('\r\n'))
        if lang:
            replacement = f'<pre><code class="language-{html.escape(lang)}">{escaped_code}</code></pre>'
        else:
            replacement = f'<pre><code>{escaped_code}</code></pre>'
        text = text.replace(f"QQQBLOCKCODE{idx}ZZZ", replacement)

    # 10. Restaurar código inline <code>...</code>
    for idx, inline_content in enumerate(inline_codes):
        escaped_inline = html.escape(inline_content)
        replacement = f'<code>{escaped_inline}</code>'
        text = text.replace(f"QQQINLINECODE{idx}ZZZ", replacement)

    return text

