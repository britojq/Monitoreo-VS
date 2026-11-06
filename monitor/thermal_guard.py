#!/usr/bin/env python3
"""
# ==============================================================================
# 🌡️ FACHADA DE COMPATIBILIDAD: thermal_guard.py
# Redirige la ejecución canónica a monitor/checkers/thermal/
# ==============================================================================
"""
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.checkers.thermal import *  # noqa: F401, F403
from monitor.checkers.thermal.thermal_cli import main

if __name__ == "__main__":
    main()
