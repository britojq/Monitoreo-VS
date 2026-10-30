#!/usr/bin/env python3
"""
# ==============================================================================
# 🚨 MOTOR DE ALERTAS, ESCALACIÓN Y CORRELACIÓN: alert_engine.py (@IA_ValleSeco_bot)
# Fachada Delegadora SOLID y de Retrocompatibilidad Total.
# Deconstruido en paquetes especializados bajo monitor/alerts/.
# Ubicación: /opt/sonar/monitor/alert_engine.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# Re-exports desde monitor.alerts
from monitor.alerts import (
    compute_fingerprint,
    AlertEngine,
    format_table,
    main_cli,
)

__all__ = [
    "compute_fingerprint",
    "AlertEngine",
    "format_table",
    "main_cli",
]

if __name__ == "__main__":
    asyncio.run(main_cli())
