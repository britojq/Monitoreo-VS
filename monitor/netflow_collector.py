#!/usr/bin/env python3
"""
Fachada delegadora hacia monitor.daemons.netflow_collector.
Preserva retrocompatibilidad con importaciones y ejecuciones CLI.
"""

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.daemons.netflow_collector import *
from monitor.daemons.netflow_collector import main

if __name__ == "__main__":
    main()
