#!/usr/bin/env python3
"""
Fachada delegadora hacia monitor.tools.wol_sender.
Preserva retrocompatibilidad con controladores PHP, llamadas CLI y scripts de herramientas.
"""

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.tools.wol_sender import *
from monitor.tools.wol_sender import main

if __name__ == "__main__":
    main()
