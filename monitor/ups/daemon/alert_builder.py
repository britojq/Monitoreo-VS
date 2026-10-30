"""
# ==============================================================================
# 🔋 UPS ALERT BUILDER: alert_builder.py
# Construcción de plantillas HTML corporativas para alertas de contingencia eléctrica
# Ubicación: /opt/sonar/monitor/ups/daemon/alert_builder.py
# ==============================================================================
"""

from __future__ import annotations

import html
from datetime import datetime


def build_ups_outage_alert(device: dict, telemetry: dict, elapsed_str: str = "45 seg") -> str:
    """Construye mensaje corporativo de falla de servicio eléctrico confirmada (>45 seg en batería)."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    model = html.escape(str(device.get("model") or "6kVA"))
    bat_pct = telemetry.get("battery_percent", 100)
    load_pct = telemetry.get("load_percent", 0)
    temp_c = telemetry.get("temperature_c", 40.0)
    raw_out = float(telemetry.get("output_voltage") or 208.0)
    out_equipos = round(raw_out * (110.0 / 208.0), 1) if raw_out > 150 else raw_out
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "⚡ <b>ALERTA CRÍTICA: FALLA EN SERVICIO ELÉCTRICO CONFIRMADA</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code> ({model})\n"
        f"⚠️ <b>Condición:</b> <b>FALLA CONFIRMADA (MÁS DE 45 SEG EN MODO BATERÍA)</b>\n"
        "🔌 <b>Entrada Comercial (220V):</b> 0.0 VAC (Interrumpida)\n"
        f"⚡ <b>Salida a Equipos (110V):</b> {out_equipos:.1f} VAC (Activa)\n"
        f"⏱️ <b>Tiempo en Batería:</b> {elapsed_str} acumulados\n"
        f"🔋 <b>Nivel de Batería:</b> {bat_pct}% ({telemetry.get('battery_voltage', 2.25):.2f} V/celda)\n"
        f"📊 <b>Carga de Consumo:</b> {load_pct}% (~{int(load_pct * 60)} Watts)\n"
        f"🌡️ <b>Temperatura Inversor:</b> {temp_c:.1f} °C\n"
        f"🕒 <b>Confirmado:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "⚠️ <i>Conteo de autonomía de respaldo corporativo en progreso.</i>"
    )


def build_ups_fluctuation_alert(device: dict, telemetry: dict, duration_str: str) -> str:
    """Construye mensaje técnico de micro-fluctuación de voltaje (≤45 seg) para el Administrador."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    in_v = float(telemetry.get("input_voltage") or 220.0)
    raw_out = float(telemetry.get("output_voltage") or 208.0)
    out_equipos = round(raw_out * (110.0 / 208.0), 1) if raw_out > 150 else raw_out
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "⚠️ <b>AVISO TÉCNICO: MICRO-FLUCTUACIÓN DE VOLTAJE ABSORBIDA</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code>\n"
        f"⏱️ <b>Duración en Batería:</b> <b>{duration_str}</b> (≤ 45 seg)\n"
        f"🔌 <b>Entrada Red Comercial:</b> {in_v:.1f} VAC (Normalizada)\n"
        f"⚡ <b>Salida a Servidores:</b> {out_equipos:.1f} VAC (Sin interrupción)\n"
        f"🕒 <b>Fecha y Hora:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "🛡️ <i>Evento transitorio amortiguado por el UPS. Servidores protegidos.</i>"
    )


def build_ups_recovery_alert(device: dict, telemetry: dict, duration_str: str) -> str:
    """Construye mensaje corporativo de restablecimiento de energía eléctrica."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    in_v = float(telemetry.get("input_voltage") or 220.0)
    raw_out = float(telemetry.get("output_voltage") or 208.0)
    out_equipos = round(raw_out * (110.0 / 208.0), 1) if raw_out > 150 else raw_out
    freq = float(telemetry.get("frequency") or 60.0)
    bat_pct = telemetry.get("battery_percent", 100)
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "✅ <b>RECUPERACIÓN: FLUIDO ELÉCTRICO RESTABLECIDO</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code>\n"
        f"🔌 <b>Entrada Comercial (220V):</b> {in_v:.1f} VAC ({freq:.1f} Hz)\n"
        f"⚡ <b>Salida a Equipos (110V):</b> {out_equipos:.1f} VAC (Inversor L-L: {raw_out:.1f} VAC)\n"
        f"⏱️ <b>Tiempo en Batería:</b> {duration_str}\n"
        f"🔋 <b>Estado Batería:</b> {bat_pct}% (Recarga en curso)\n"
        f"🕒 <b>Restablecido:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "🛡️ <i>Alimentación eléctrica comercial operando normalmente.</i>"
    )


def build_ups_battery_low_alert(device: dict, telemetry: dict) -> str:
    """Construye mensaje de alerta cuando el banco de batería está por agotarse."""
    name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
    bat_pct = telemetry.get("battery_percent", 15)
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    return (
        "🚨 <b>ALERTA EXTREMA: BATERÍA CRÍTICA EN UPS</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
        f"🔋 <b>Equipo:</b> <code>{name}</code>\n"
        f"⚠️ <b>Nivel Restante:</b> <b>{bat_pct}% (Agotamiento inminente)</b>\n"
        "⚡ <b>Acción recomendada:</b> Preparar apagado controlado de servidores.\n"
        f"🕒 <b>Reportado:</b> {now_str}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )
