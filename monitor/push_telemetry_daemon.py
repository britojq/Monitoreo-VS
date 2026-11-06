#!/usr/bin/env python3
"""
# ==============================================================================
# 🛰️ FACHADA DE COMPATIBILIDAD: push_telemetry_daemon.py
# Redirige la ejecución canónica a monitor/daemons/push_telemetry_daemon.py
# ==============================================================================
"""
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.daemons.push_telemetry_daemon import *  # noqa: F401, F403
from monitor.daemons.push_telemetry_daemon import main

if __name__ == "__main__":
    main()
