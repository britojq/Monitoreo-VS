#!/usr/bin/env python3
"""
Fachada delegadora hacia monitor.tools.diagnostico_produccion.
Preserva retrocompatibilidad con invocaciones directas y CLI.
"""

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.tools.diagnostico_produccion import *
from monitor.tools.diagnostico_produccion import main

if __name__ == "__main__":
    main()
