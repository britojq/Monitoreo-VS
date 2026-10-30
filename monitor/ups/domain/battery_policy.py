"""
# ==============================================================================
# 🔋 UPS BATTERY POLICY: battery_policy.py
# Constantes físicas y política de cálculo de porcentaje de batería por celda
# Ubicación: /opt/sonar/monitor/ups/domain/battery_policy.py
# ==============================================================================
"""

from __future__ import annotations

# Constantes del protocolo industrial Megatec
COMMAND_STATUS = b"Q1\r"
COMMAND_INFO = b"I\r"
COMMAND_RATING = b"F\r"

# Constantes eléctricas del banco de baterías de plomo-ácido AGM/GEL
V_CELL_MIN = 1.67
V_CELL_MAX = 2.25
CELLS_PER_12V_BATTERY = 6
TOTAL_BATTERIES = 16
TOTAL_CELLS = CELLS_PER_12V_BATTERY * TOTAL_BATTERIES  # 96 celdas
V_BANK_MIN = V_CELL_MIN * TOTAL_CELLS                 # 160.32 V
V_BANK_MAX = V_CELL_MAX * TOTAL_CELLS                 # 216.00 V


def calculate_battery_percentage(v_cell: float) -> int:
    """
    Calcula el porcentaje de batería en base al voltaje por celda (plomo-ácido AGM/GEL).
    Rango de operación por celda: 1.75V (0% agotamiento) a 2.25V (100% flotación plena).
    """
    if v_cell >= 2.23:
        return 100
    elif v_cell <= 1.75:
        return 0

    pct = (v_cell - 1.75) / (2.25 - 1.75) * 100.0
    return min(100, max(0, int(round(pct))))
