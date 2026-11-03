"""
Fachada delegadora hacia monitor.tools.web_screenshot.
Preserva retrocompatibilidad con importaciones y llamadas Playwright previas.
"""

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.tools.web_screenshot import *
