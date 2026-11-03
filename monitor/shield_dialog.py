#!/usr/bin/env python3
"""
Fachada delegadora hacia monitor.shield.ui.shield_dialog.
Preserva retrocompatibilidad arquitectónica y paridad de módulos en memoria.
"""

from __future__ import annotations

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import monitor.shield.ui.shield_dialog as _target_module
sys.modules[__name__] = _target_module

from monitor.shield.ui.shield_dialog import *

if __name__ == "__main__":
    if "main" in globals() and callable(globals()["main"]):
        globals()["main"]()
