"""
# ==============================================================================
# 📊 COMPILADOR DE REPORTE DE SERVICIOS: service_report_compiler.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Generación y formateo de reportes de servicios corporativos y regionales.
# Ubicación: /opt/sonar/monitor/formatters/service_report_compiler.py
# ==============================================================================
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional, Tuple

from monitor.config_parser import (
    MonitorConfigLoader,
    get_formatted_datetime,
    render_template,
)


def compile_services_from_snapshot(
    snapshot: Dict[str, Any],
    snapshot_dt: Optional[str] = None,
    debug_mode: bool = False,
) -> Tuple[str, Dict[str, str], List[str]]:
    """
    Compila el reporte oficial de Servicios a partir del snapshot consolidado en MariaDB.
    Retorna: (reporte_texto, variables_template, bitácora_logs).
    """
    loader = MonitorConfigLoader()
    services_data: List[Dict[str, Any]] = snapshot.get("services", [])

    fecha_str, hora_str = get_formatted_datetime()
    dt_display = snapshot_dt or f"{fecha_str} {hora_str}"

    variables: Dict[str, str] = {
        "fecha": fecha_str,
        "hora": hora_str,
    }
    logs: List[str] = []

    # Inicializar servicios A..Z según plantilla histórica
    for code in range(ord("A"), ord("Z") + 1):
        letter = chr(code)
        variables[f"NAMESERVICE{letter}"] = loader.raw_monitoreo.get(f"NAMESERVICE{letter}", "")
        variables[f"STHOST{letter}"] = ""
        variables[f"STATESERVICE{letter}"] = "NO_CONFIGURADO"

    corp_lines: List[str] = []
    reg_lines: List[str] = []

    for s in services_data:
        letter = s.get("letter", "")
        name = s.get("name", "Servicio")
        is_up = bool(s.get("is_up", False))

        variables[f"NAMESERVICE{letter}"] = name
        line_item = f"✅ - {name}" if is_up else f"❌ - {name}"
        variables[f"STHOST{letter}"] = line_item
        variables[f"STATESERVICE{letter}"] = "ACTIVO" if is_up else "APAGADO"
        logs.append(f"SERVICIO: [{letter}] {name} | ESTADO: {'ACTIVO' if is_up else 'APAGADO'}")

        scope = s.get("scope", "corporativo")
        if scope == "regional":
            reg_lines.append(line_item)
        else:
            corp_lines.append(line_item)

    variables["SERVICIOS_CORPORATIVOS"] = "\n".join(corp_lines)
    variables["SERVICIOS_REGIONALES"] = "\n".join(reg_lines)

    template_key = "MENSAJEDEBUGA" if debug_mode else "MENSAJEA"
    template_str = loader.templates.get(template_key, "")

    if not template_str:
        lines = [
            "📊 *REPORTE DE SERVICIOS CORPORATIVOS*",
            f"Fecha: {fecha_str} {hora_str}",
            f"🕒 Snapshot: {dt_display}",
            "",
        ]
        if corp_lines:
            lines.append("━━━━━━━━━━━━\n**Servicios Corporativos Verificados:**\n━━━━━━━━━━━━")
            lines.extend(corp_lines)
            lines.append("")
        if reg_lines:
            lines.append("━━━━━━━━━━━━\n**Servicios Regionales – Carabobo Verificados:**\n━━━━━━━━━━━━")
            lines.extend(reg_lines)
        report = "\n".join(lines)
    else:
        report = render_template(template_str, variables)

    return report.strip(), variables, logs
