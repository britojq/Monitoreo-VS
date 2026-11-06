"""
# ==============================================================================
# 🏢 COMPILADOR DE REPORTE DE SEDES Y ENLACES: site_report_compiler.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Generación y formateo de reportes de sedes físicas, routers y enlaces WAN.
# Ubicación: /opt/sonar/monitor/formatters/site_report_compiler.py
# ==============================================================================
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional, Tuple

from monitor.config_parser import (
    MonitorConfigLoader,
    get_formatted_datetime,
    render_template,
)


def compile_sedes_from_snapshot(
    snapshot: Dict[str, Any],
    snapshot_dt: Optional[str] = None,
    debug_mode: bool = False,
) -> Tuple[str, Dict[str, str], List[str]]:
    """
    Compila el reporte oficial de Sedes y Equipos a partir del snapshot consolidado en MariaDB.
    Retorna: (reporte_texto, variables_template, bitácora_logs).
    """
    loader = MonitorConfigLoader()
    sites_data: List[Dict[str, Any]] = snapshot.get("sites", [])

    fecha_str, hora_str = get_formatted_datetime()
    dt_display = snapshot_dt or f"{fecha_str} {hora_str}"

    variables: Dict[str, str] = {
        "fecha": fecha_str,
        "hora": hora_str,
    }
    logs: List[str] = []

    # Inicializar sedes A..H y equipos 1..8 según plantilla histórica
    for code in range(ord("A"), ord("H") + 1):
        letter = chr(code)
        variables[f"NAMESITE{letter}"] = loader.raw_monitoreo.get(f"NAMESITE{letter}", "")
        variables[f"STSITE{letter}"] = ""
        variables[f"ESTATSITE{letter}"] = "NO_CONFIGURADO"
        for num in range(1, 9):
            variables[f"NAMESITE{letter}EQUIPO{num}"] = loader.raw_monitoreo.get(
                f"NAMESITE{letter}EQUIPO{num}", ""
            )
            variables[f"STSITE{letter}EQUIPO{num}"] = ""
            variables[f"ESTATSITE{letter}EQUIPO{num}"] = "NO_CONFIGURADO"

    site_blocks: List[str] = []

    for st in sites_data:
        letter = st.get("letter", "")
        name = st.get("name", "Sede")
        is_up = bool(st.get("is_up", False))
        site_line = f"✅ - {name}" if is_up else f"❌ - {name}"

        variables[f"NAMESITE{letter}"] = name
        variables[f"STSITE{letter}"] = site_line
        variables[f"ESTATSITE{letter}"] = "ACTIVO" if is_up else "APAGADO"

        block_lines = [
            "━━━━━━━━━━━━",
            f"**{name}**",
            "━━━━━━━━━━━━",
            site_line,
        ]

        devices: List[Dict[str, Any]] = st.get("devices", [])
        for num, dev in enumerate(devices, start=1):
            dev_name = dev.get("name", f"Equipo {num}")
            dev_up = bool(dev.get("is_up", False))
            dev_line = f"✅ - {dev_name}" if dev_up else f"❌ - {dev_name}"

            variables[f"NAMESITE{letter}EQUIPO{num}"] = dev_name
            variables[f"STSITE{letter}EQUIPO{num}"] = dev_line
            variables[f"ESTATSITE{letter}EQUIPO{num}"] = "ACTIVO" if dev_up else "APAGADO"
            block_lines.append(dev_line)

        site_blocks.append("\n".join(block_lines))

    variables["SEDES_Y_ENLACES"] = "\n\n".join(site_blocks)

    template_key = "MENSAJEDEBUGB" if debug_mode else "MENSAJEC"
    template_str = loader.templates.get(template_key, "")

    if not template_str:
        lines = [
            "🏢 *REPORTE DE SEDES Y ENLACES*",
            f"Fecha: {fecha_str} {hora_str}",
            f"🕒 Snapshot: {dt_display}",
            "",
            variables["SEDES_Y_ENLACES"],
        ]
        report = "\n".join(lines)
    else:
        report = render_template(template_str, variables)

    return report.strip(), variables, logs
