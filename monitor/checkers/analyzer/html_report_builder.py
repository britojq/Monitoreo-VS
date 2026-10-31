"""
Generador modular de reportes HTML para el análisis profundo de red.
"""
import html
from typing import List, Tuple

from monitor.checkers.analyzer.dtos import NetworkHost


def generate_html_network_report(
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
    """Construye el documento HTML interactivo con estilos integrados (autosuficiente, sin CDNs)."""
    html_sections: List[str] = []

    if total_suspicious > 50:
        susp_rows = "".join([
            f"<tr><td><code>{html.escape(s[0])}</code></td><td><code>{html.escape(s[1])}</code></td><td><b>{s[2]}</b></td></tr>"
            for s in suspicious_hosts
        ])
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

    if hosts_local_con_mac:
        rows = "".join([
            f"<tr><td><code>{html.escape(h.ip)}</code></td><td>{html.escape(h.hostname)}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.rx}</td><td>{h.tx}</td><td><b>{h.total_traffic}</b></td><td>{html.escape(fecha_str)}</td></tr>"
            for h in hosts_local_con_mac
        ])
        html_sections.append(f"""
        <div class="section local">
            <h3>🔹 Hosts Locales (en la misma red)</h3>
            <table>
                <tr><th>IP</th><th>Hostname</th><th>MAC</th><th>RX</th><th>TX</th><th>Total</th><th>Fecha</th></tr>
                {rows}
            </table>
        </div>
        """)

    if hosts_local_sin_mac:
        rows = "".join([
            f"<tr><td><code>{html.escape(h.ip)}</code></td><td>{html.escape(h.hostname)}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.rx}</td><td>{h.tx}</td><td><b>{h.total_traffic}</b></td><td>{html.escape(fecha_str)}</td></tr>"
            for h in hosts_local_sin_mac
        ])
        html_sections.append(f"""
        <div class="section local-unknown">
            <h3>🔸 Hosts Locales (sin MAC detectada)</h3>
            <table>
                <tr><th>IP</th><th>Hostname</th><th>MAC</th><th>RX</th><th>TX</th><th>Total</th><th>Fecha</th></tr>
                {rows}
            </table>
        </div>
        """)

    if hosts_externos:
        rows = "".join([
            f"<tr><td><code>{html.escape(h.ip)}</code></td><td>{html.escape(h.hostname)}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.rx}</td><td>{h.tx}</td><td><b>{h.total_traffic}</b></td><td>{html.escape(fecha_str)}</td></tr>"
            for h in hosts_externos
        ])
        html_sections.append(f"""
        <div class="section external">
            <h3>🌐 Hosts Externos (Internet / otras redes)</h3>
            <table>
                <tr><th>IP</th><th>Hostname</th><th>MAC</th><th>RX</th><th>TX</th><th>Total</th><th>Fecha</th></tr>
                {rows}
            </table>
        </div>
        """)

    lat_rows: List[str] = []
    for h in latencias_ordenadas:
        lat_class = "high-latency" if h.latency_ms > 150.0 else ""
        lat_str = f"{h.latency_ms:.2f}" if h.latency_ms > 0 else "N/A"
        lat_rows.append(
            f"<tr><td><code>{html.escape(h.ip)}</code></td><td class='{lat_class}'>{lat_str}</td><td><code>{html.escape(h.mac)}</code></td><td>{h.tx}</td><td>{h.rx}</td><td>{h.total_traffic}</td></tr>"
        )

    return f"""<!DOCTYPE html>
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
