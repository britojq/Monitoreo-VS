"""
Generador de reportes Markdown y resúmenes para Telegram desde la base de datos de NET Radar.
"""
from datetime import datetime
import html
import logging
from pathlib import Path
from typing import Tuple

from monitor.checkers.radar.constants import format_bytes_human
from monitor.config_parser import get_formatted_datetime
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.radar.reporter")


def generate_markdown_report_from_db() -> Tuple[str, Path, str]:
    """
    Consulta la base de datos de NET Radar y genera:
    1. Texto de resumen conciso para Telegram (HTML seguro)
    2. Archivo .md estructurado completo para descarga
    3. Texto completo en formato Markdown
    """
    fecha_str, hora_str = get_formatted_datetime()
    timestamp_file = datetime.now().strftime("%Y%m%d_%H%M%S")
    md_path = Path("/tmp") / f"reporte_red_{timestamp_file}.md"

    with db_cursor() as cur:
        cur.execute("SELECT COUNT(*) AS total FROM net_radar_hosts")
        total_hosts = cur.fetchone()["total"]

        cur.execute("SELECT COUNT(*) AS active FROM net_radar_hosts WHERE last_seen_at >= NOW() - INTERVAL 15 MINUTE")
        active_hosts = cur.fetchone()["active"]

        cur.execute("""
            SELECT * FROM net_radar_hosts 
            WHERE update_status IN ('checking', 'downloading')
            ORDER BY last_update_at DESC LIMIT 20
        """)
        updating_hosts = cur.fetchall()

        cur.execute("SELECT COUNT(*) AS win FROM net_radar_hosts WHERE last_update_type = 'windows_update' AND update_status IN ('checking', 'downloading')")
        win_updating = cur.fetchone()["win"]

        cur.execute("SELECT COUNT(*) AS lin FROM net_radar_hosts WHERE last_update_type = 'linux_repo' AND update_status IN ('checking', 'downloading')")
        lin_updating = cur.fetchone()["lin"]

        cur.execute("SELECT SUM(bytes_in) AS total_rx, SUM(bytes_out) AS total_tx FROM net_radar_hosts")
        row_bytes = cur.fetchone()
        total_rx = format_bytes_human(row_bytes["total_rx"] or 0)
        total_tx = format_bytes_human(row_bytes["total_tx"] or 0)

        cur.execute("SELECT * FROM net_radar_hosts ORDER BY total_bytes DESC LIMIT 15")
        top_talkers = cur.fetchall()

        cur.execute("SELECT * FROM net_radar_hosts WHERE is_local = 1 ORDER BY ip ASC")
        local_hosts = cur.fetchall()

    sum_lines = [
        "🛰️ <b>REPORTE DE TELEMETRÍA DE RED (NET Radar)</b>",
        f"<b>Fecha:</b> {html.escape(fecha_str)} {html.escape(hora_str)}",
        f"<b>Sede Central:</b> Valle Seco",
        f"<b>Total Hosts Monitoreados:</b> <code>{total_hosts}</code>",
        f"<b>Hosts Activos en LAN:</b> <code>{active_hosts}</code>",
        f"<b>Tráfico Total:</b> RX (Bajada): <code>{total_rx}</code> | TX (Subida): <code>{total_tx}</code>",
        "",
        "<b>ESTADO DE ACTUALIZACIONES:</b>",
        f"• 🪟 <b>Windows Update:</b> <code>{win_updating}</code> equipo(s)",
        f"• 🐧 <b>Repositorios Linux:</b> <code>{lin_updating}</code> equipo(s)",
    ]

    if win_updating > 0 or lin_updating > 0:
        sum_lines.append("")
        sum_lines.append("⚠️ <b>ALERTA DE TRÁFICO:</b> Se detectaron equipos descargando actualizaciones.")

    sum_lines.append("")
    sum_lines.append("<i>Reporte consolidado desde base de datos. Para escaneo en vivo: /red vivo</i>")
    telegram_summary = "\n".join(sum_lines)

    md_content = [
        "# 🛰️ REPORTE DE ANÁLISIS Y TELEMETRÍA DE RED - NET RADAR\n\n",
        f"**Generado el:** {fecha_str} a las {hora_str}  \n",
        f"**Módulo:** NET Radar & Monitoreo de Flujos  \n",
        f"**Sede:** Central Valle Seco  \n\n",
        "---\n\n",
        "## 📊 1. Resumen Consolidado de Tráfico\n\n",
        "| Métrica | Valor |\n",
        "| :--- | :--- |\n",
        f"| **Total Dispositivos Registrados** | `{total_hosts}` |\n",
        f"| **Dispositivos Activos (&lt; 15 min)** | `{active_hosts}` |\n",
        f"| **Equipos con Windows Update Activo** | `{win_updating}` |\n",
        f"| **Equipos con Repositorios Linux** | `{lin_updating}` |\n",
        f"| **Tráfico Total RX (Descarga)** | `{total_rx}` |\n",
        f"| **Tráfico Total TX (Subida)** | `{total_tx}` |\n\n",
        "## 🚨 2. Detección Especializada de Actualizaciones en Curso\n\n",
    ]

    if not updating_hosts:
        md_content.append("_No se registran equipos buscando o descargando actualizaciones en este momento._\n\n")
    else:
        md_content.append("| IP | MAC | Fabricante | Tipo de Actualización | Servidor / Dominio Destino | Tráfico Detectado | Última Actividad |\n")
        md_content.append("| :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n")
        for uh in updating_hosts:
            u_icon = "🪟 Windows Update" if uh["last_update_type"] == "windows_update" else "🐧 Linux Repo"
            stat = f"**{u_icon}** ({uh['update_status']})"
            target = uh["last_update_target"] or "Dominio Oficial"
            u_bytes = format_bytes_human(uh["update_bytes"])
            last_at = str(uh["last_update_at"]) if uh["last_update_at"] else "N/A"
            vendor = uh["vendor"] or "Desconocido"
            md_content.append(f"| `{uh['ip']}` | `{uh['mac']}` | {vendor} | {stat} | `{target}` | {u_bytes} | {last_at} |\n")
        md_content.append("\n")

    md_content.append("## 🏆 3. Top Hosts por Consumo de Ancho de Banda (Top Talkers)\n\n")
    md_content.append("| # | IP | MAC | Nombre / Fabricante | Sistema Operativo | RX (In) | TX (Out) | Total |\n")
    md_content.append("| :-: | :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n")
    for idx, tt in enumerate(top_talkers, 1):
        name = tt["hostname"] or tt["vendor"] or "Dispositivo"
        os_det = tt["os_detected"] or "Desconocido"
        rx = format_bytes_human(tt["bytes_in"])
        tx = format_bytes_human(tt["bytes_out"])
        tot = format_bytes_human(tt["total_bytes"])
        md_content.append(f"| {idx} | `{tt['ip']}` | `{tt['mac']}` | {name} | {os_det} | {rx} | {tx} | **{tot}** |\n")
    md_content.append("\n")

    md_content.append("## 💻 4. Inventario de Dispositivos en Subred Local\n\n")
    md_content.append("| IP | Dirección MAC | Fabricante | Hostname / Alias | Estado |\n")
    md_content.append("| :--- | :--- | :--- | :--- | :--- |\n")
    for lh in local_hosts:
        hname = lh["hostname"] or "-"
        vend = lh["vendor"] or "Desconocido"
        act = "🟢 Activo" if lh["last_seen_at"] and (datetime.now() - lh["last_seen_at"]).total_seconds() < 900 else "⚪ Inactivo"
        md_content.append(f"| `{lh['ip']}` | `{lh['mac']}` | {vend} | {hname} | {act} |\n")
    md_content.append("\n")

    md_content.append("---\n*Reporte generado automáticamente por NET Radar.*\n")

    full_md_text = "".join(md_content)
    md_path.write_text(full_md_text, encoding="utf-8")

    try:
        from monitor.checkers.analyzer.analyzer_orchestrator import (
            purge_old_audit_files,
            save_network_analysis_report_to_db,
        )
        save_network_analysis_report_to_db(
            report_type="net_radar",
            interface="local",
            duration_seconds=15,
            total_packets=0,
            local_hosts_count=len(local_hosts),
            external_hosts_count=0,
            suspicious_packets=0,
            summary_text=telegram_summary,
            report_markdown=full_md_text,
            report_data_json={
                "total_hosts": total_hosts,
                "active_hosts": active_hosts,
                "win_updating": win_updating,
                "lin_updating": lin_updating,
                "total_rx": total_rx,
                "total_tx": total_tx,
            }
        )
        purge_old_audit_files(max_days=30)
    except Exception as e_db:
        logger.warning(f"Aviso en guardado o purgado de auditoría: {e_db}")

    return telegram_summary, md_path, full_md_text
