"""
Módulo de análisis y diagnóstico avanzado de red local (ARP / ICMP / Interfaces).
Refactorización moderna y asíncrona de analisis_red_completo.sh.
"""

from __future__ import annotations

import asyncio
import html
import logging
import re
import socket
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from monitor.checker_base import get_active_network_interface
from monitor.config_parser import get_formatted_datetime

logger = logging.getLogger("monitor.network_analyzer")

AUDIT_DIR = Path(__file__).resolve().parent.parent / "audit"


@dataclass
class NetworkDevice:
    ip: str
    mac: str = "desconocida"
    vendor: str = "Desconocido"
    hostname: str = ""
    latency_ms: float = 0.0
    is_up: bool = True
    issues: List[str] = field(default_factory=list)


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


async def run_arp_scan(interface: str, timeout: float = 8.0) -> List[NetworkDevice]:
    """Ejecuta arp-scan en la red local para descubrir dispositivos, MACs y fabricantes."""
    devices: List[NetworkDevice] = []
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "arp-scan", f"--interface={interface}", "--localnet", "--ignoredups",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")

        for line in out_str.splitlines():
            line = line.strip()
            # Formato típico: 10.20.23.1\t00:27:0d:8e:30:16\tCisco Systems, Inc
            parts = line.split("\t")
            if len(parts) >= 2 and re.match(r"^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", parts[0]):
                ip = parts[0].strip()
                mac = parts[1].strip()
                vendor = parts[2].strip() if len(parts) >= 3 else "Desconocido"
                devices.append(NetworkDevice(ip=ip, mac=mac, vendor=vendor))
    except Exception as e:
        logger.error(f"Error ejecutando arp-scan: {e}")

    # Fallback complementario: leer /proc/net/arp
    if not devices:
        try:
            arp_file = Path("/proc/net/arp")
            if arp_file.exists():
                lines = arp_file.read_text(encoding="utf-8").splitlines()[1:]
                for l in lines:
                    fields = l.split()
                    if len(fields) >= 6 and fields[5] == interface and fields[3] != "00:00:00:00:00:00":
                        devices.append(NetworkDevice(ip=fields[0], mac=fields[3]))
        except Exception:
            pass

    return devices


async def ping_device(device: NetworkDevice, timeout: float = 3.5) -> None:
    """Mide la latencia y verifica si el dispositivo responde a ICMP Ping."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "ping", "-c", "2", "-W", "1", device.ip,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        out_str = stdout.decode("utf-8", errors="ignore")

        if proc.returncode == 0:
            device.is_up = True
            # Extraer avg rtt: rtt min/avg/max/mdev = 0.658/0.742/0.826/0.084 ms
            match = re.search(r"= [0-9.]+/([0-9.]+)/", out_str)
            if match:
                device.latency_ms = round(float(match.group(1)), 2)
            else:
                device.latency_ms = 1.0

            if device.latency_ms > 150.0:
                device.issues.append(f"Alta latencia ({device.latency_ms} ms)")
        else:
            device.is_up = False
            device.latency_ms = 0.0
    except Exception:
        device.is_up = False


def generate_reports(
    devices: List[NetworkDevice],
    interface: str,
    my_ip: str,
    my_mask: str,
    elapsed_seconds: float
) -> Tuple[str, Path, Path]:
    """Genera el resumen para Telegram y los archivos detallados TXT y HTML."""
    AUDIT_DIR.mkdir(parents=True, exist_ok=True)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    fecha_str, hora_str = get_formatted_datetime()

    txt_path = AUDIT_DIR / f"reporte_red_{timestamp}.txt"
    html_path = AUDIT_DIR / f"reporte_red_{timestamp}.html"

    total_hosts = len(devices)
    up_hosts = sum(1 for d in devices if d.is_up)
    high_latency = [d for d in devices if d.latency_ms > 150.0]
    avg_latency = round(sum(d.latency_ms for d in devices if d.is_up) / max(up_hosts, 1), 2)

    # 1. Resumen para Telegram
    lines = [
        "🔍 *REPORTE DE ANÁLISIS DE RED LOCAL*",
        f"*Fecha:* {fecha_str}",
        f"*Hora:* {hora_str}",
        f"*Interfaz Activa:* `{interface}`",
        f"*Subred Local:* `{my_ip}/{my_mask}`" if my_ip else "*Subred:* `Detectada`",
        f"*Dispositivos Detectados:* {total_hosts} hosts ({up_hosts} activos)",
        f"*Latencia Promedio:* {avg_latency} ms",
    ]

    if high_latency:
        lines.append(f"⚠️ *Dispositivos con Alta Latencia (>150ms):* {len(high_latency)}")

    lines.append("")
    lines.append("*Principales Dispositivos en la Red:*")

    # Mostrar hasta 12 dispositivos clave en el mensaje de Telegram
    for d in sorted(devices, key=lambda x: x.ip)[:14]:
        icon = "🟢" if d.is_up else "🔴"
        lat_str = f"[{d.latency_ms} ms]" if d.is_up else "[Sin respuesta ICMP]"
        vendor_short = (d.vendor[:22] + "..") if len(d.vendor) > 22 else d.vendor
        lines.append(f"{icon} `{d.ip:<15}` • `{d.mac}`\n   🏷️ _{vendor_short}_ {lat_str}")

    if total_hosts > 14:
        lines.append(f"\n_... y {total_hosts - 14} dispositivos adicionales en el reporte adjunto._")

    lines.append(f"\n⏱️ *Tiempo de escaneo:* {elapsed_seconds}s")
    telegram_summary = "\n".join(lines)

    # 2. Archivo de Texto Detallado
    txt_content = [
        "================================================================================",
        "                       REPORTE COMPLETO DE ANÁLISIS DE RED                      ",
        "================================================================================",
        f"Fecha: {fecha_str} {hora_str}",
        f"Interfaz: {interface}",
        f"Subred: {my_ip}/{my_mask}",
        f"Total de dispositivos: {total_hosts} (Activos: {up_hosts})",
        f"Tiempo de ejecución: {elapsed_seconds} segundos",
        "--------------------------------------------------------------------------------",
        f"{'IP':<18} {'MAC':<20} {'LATENCIA':<12} {'ESTADO':<10} {'FABRICANTE / VENDOR'}",
        "--------------------------------------------------------------------------------"
    ]
    for d in sorted(devices, key=lambda x: x.ip):
        estado = "ACTIVO" if d.is_up else "INACTIVO"
        lat = f"{d.latency_ms} ms" if d.is_up else "N/A"
        txt_content.append(f"{d.ip:<18} {d.mac:<20} {lat:<12} {estado:<10} {d.vendor}")
    txt_content.append("================================================================================")
    txt_path.write_text("\n".join(txt_content), encoding="utf-8")

    # 3. Archivo HTML Detallado
    html_rows = []
    for d in sorted(devices, key=lambda x: x.ip):
        badge = '<span style="color:#2ecc71;font-weight:bold;">ACTIVO</span>' if d.is_up else '<span style="color:#e74c3c;font-weight:bold;">INACTIVO</span>'
        lat = f"{d.latency_ms} ms" if d.is_up else "N/A"
        html_rows.append(
            f"<tr><td><code>{html.escape(d.ip)}</code></td>"
            f"<td><code>{html.escape(d.mac)}</code></td>"
            f"<td>{html.escape(d.vendor)}</td>"
            f"<td>{lat}</td>"
            f"<td>{badge}</td></tr>"
        )

    html_content = f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de Análisis de Red - {html.escape(fecha_str)}</title>
<style>
body {{ font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; margin: 20px; color: #333; }}
.container {{ max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }}
h1 {{ color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }}
.meta {{ background: #ecf0f1; padding: 12px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }}
table {{ width: 100%; border-collapse: collapse; margin-top: 15px; }}
th, td {{ padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }}
th {{ background: #34495e; color: white; }}
tr:hover {{ background: #f1f2f6; }}
code {{ background: #edf2f7; padding: 2px 5px; border-radius: 4px; font-family: monospace; }}
</style>
</head>
<body>
<div class="container">
<h1>🔍 Reporte de Análisis de Red Local</h1>
<div class="meta">
<strong>Fecha:</strong> {html.escape(fecha_str)} {html.escape(hora_str)} &nbsp;|&nbsp;
<strong>Interfaz:</strong> <code>{html.escape(interface)}</code> &nbsp;|&nbsp;
<strong>Subred:</strong> <code>{html.escape(my_ip)}/{html.escape(my_mask)}</code> &nbsp;|&nbsp;
<strong>Hosts Detectados:</strong> {total_hosts}
</div>
<table>
<thead>
<tr><th>Dirección IP</th><th>Dirección MAC</th><th>Fabricante / Dispositivo</th><th>Latencia</th><th>Estado</th></tr>
</thead>
<tbody>
{''.join(html_rows)}
</tbody>
</table>
</div>
</body>
</html>"""
    html_path.write_text(html_content, encoding="utf-8")

    return telegram_summary, txt_path, html_path


async def execute_network_analysis(
    interface_name: Optional[str] = None
) -> Dict[str, any]:
    """
    Ejecuta el análisis completo de red de manera asíncrona y no bloqueante.
    """
    t0 = time.perf_counter()
    iface = get_active_network_interface(interface_name or "eth0")
    my_ip, my_mask = get_interface_ip_and_mask(iface)

    # 1. Escaneo ARP
    devices = await run_arp_scan(iface)

    # 2. Ping concurrente a todos los dispositivos descubiertos
    if devices:
        await asyncio.gather(*(ping_device(d) for d in devices))

    elapsed = round(time.perf_counter() - t0, 2)
    summary_text, txt_report, html_report = generate_reports(devices, iface, my_ip, my_mask, elapsed)

    return {
        "interface": iface,
        "my_ip": my_ip,
        "my_mask": my_mask,
        "total_devices": len(devices),
        "summary_text": summary_text,
        "txt_report": txt_report,
        "html_report": html_report,
        "elapsed_seconds": elapsed,
        "devices": devices
    }
