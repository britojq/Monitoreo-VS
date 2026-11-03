#!/usr/bin/env python3
"""
Fachada delegadora hacia monitor.daemons.push_telemetry_daemon.
Preserva retrocompatibilidad absoluta con el servicio systemd tg-push-telemetry.service
y ejecuciones CLI.
"""

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.daemons.push_telemetry_daemon import *
from monitor.daemons.push_telemetry_daemon import main

if __name__ == "__main__":
    main()
