#!/usr/bin/env python3
"""
Fachada delegadora hacia monitor.tools.system_optimizer.
Preserva retrocompatibilidad con invocaciones directas y CLI.
"""

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.tools.system_optimizer import *
from monitor.tools.system_optimizer import execute_optimize_plasma, print_cli_report

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] in ["--optimize", "-o", "optimize", "optimizar"]:
        banner, stats = execute_optimize_plasma()
        print("\n" + banner.replace("<b>", "").replace("</b>", "").replace("<code>", "").replace("</code>", "").replace("<i>", "").replace("</i>", ""))
    else:
        print_cli_report()
