"""
# ==============================================================================
# 🔋 EVALUADOR PURO DE TELEMETRÍA UPS: ups_evaluator.py
# Principio de Responsabilidad Única (S) - Dominio Puro sin I/O ni dependencias.
# ==============================================================================
"""

from __future__ import annotations


def calculate_battery_percentage(v_cell: float) -> int:
    """
    Calcula el porcentaje de batería en base al voltaje por celda (plomo-ácido AGM/GEL).
    Rango por celda: 1.75V (0%) a 2.25V (100% flotación).
    """
    if v_cell >= 2.23:
        return 100
    elif v_cell <= 1.75:
        return 0
    pct = (v_cell - 1.75) / (2.25 - 1.75) * 100.0
    return min(100, max(0, int(round(pct))))


def format_duration(seconds: float | int) -> str:
    """Convierte una duración en segundos a una cadena legible en español."""
    total_sec = max(0, int(seconds))
    if total_sec < 60:
        return f"{total_sec} seg"
    minutes = total_sec // 60
    rem_sec = total_sec % 60
    if minutes < 60:
        return f"{minutes} min {rem_sec} seg" if rem_sec > 0 else f"{minutes} min"
    hours = minutes // 60
    rem_min = minutes % 60
    return f"{hours} h {rem_min} min"


def is_spurious_transient(duration_seconds: float, min_duration: float = 8.0) -> bool:
    """
    Determina si un evento de batería fue una micro-fluctuación transitoria
    menor al umbral mínimo de confirmación de corte (por defecto 8s).
    """
    return duration_seconds < min_duration
