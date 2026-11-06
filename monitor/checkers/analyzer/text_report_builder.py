"""
Generador modular de reportes en Markdown y resúmenes para Telegram.
"""
import html
from typing import List, Tuple

from monitor.checkers.analyzer.dtos import NetworkHost


def generate_telegram_summary(
    hosts_local_con_mac: List[NetworkHost],
    hosts_local_sin_mac: List[NetworkHost],
    hosts_externos: List[NetworkHost],
    total_suspicious: int,
    storm_alert: bool,
    interface: str,
    my_ip: str,
    my_mask: str,
    pkt_count: int,
    duration_seconds: int,
    fecha_str: str,
    hora_str: str,
) -> str:
    """Construye un resumen conciso en HTML seguro para despachar por Telegram."""
    total_externos = len(hosts_externos)
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

    sum_lines.append("\n📁 <i>Reportes detallados adjuntos (.md y .html)</i>")
    return "\n".join(sum_lines)


def generate_markdown_report(
    hosts_local_con_mac: List[NetworkHost],
    hosts_local_sin_mac: List[NetworkHost],
    hosts_externos: List[NetworkHost],
    latencias_ordenadas: List[NetworkHost],
    suspicious_hosts: List[Tuple[str, str, int]],
    total_suspicious: int,
    storm_alert: bool,
    interface: str,
    my_ip: str,
    my_mask: str,
    pkt_count: int,
    duration_seconds: int,
    fecha_str: str,
    hora_str: str,
) -> str:
    """Construye el reporte detallado en formato Markdown."""
    total_externos = len(hosts_externos)
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

    if hosts_local_con_mac:
        md_lines.append("### 🔹 Hosts Locales (en la misma red)")
        md_lines.append("")
        md_lines.append("| Dirección IP | Hostname / Fabricante | Dirección MAC | RX (recibidos) | TX (enviados) | Total Tráfico |")
        md_lines.append("| :--- | :--- | :--- | :--- | :--- | :--- |")
        for h in hosts_local_con_mac:
            md_lines.append(f"| `{h.ip}` | {h.hostname} | `{h.mac}` | {h.rx} | {h.tx} | **{h.total_traffic}** |")
        md_lines.append("")

    if hosts_local_sin_mac:
        md_lines.append("### 🔸 Hosts Locales (sin MAC detectada)")
        md_lines.append("")
        md_lines.append("| Dirección IP | Hostname / Fabricante | Dirección MAC | RX (recibidos) | TX (enviados) | Total Tráfico |")
        md_lines.append("| :--- | :--- | :--- | :--- | :--- | :--- |")
        for h in hosts_local_sin_mac:
            md_lines.append(f"| `{h.ip}` | {h.hostname} | `{h.mac}` | {h.rx} | {h.tx} | **{h.total_traffic}** |")
        md_lines.append("")

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
    return "\n".join(md_lines)
