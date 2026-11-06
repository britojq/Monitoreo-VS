#!/usr/bin/env python3
"""
# ==============================================================================
# 🔄 FACHADA FÍSICA CANÓNICA DE COMPATIBILIDAD: shield_dialog.py
# Delegación y preservación de punto de entrada histórico hacia: monitor.shield.ui.shield_dialog
# Arquitectura Limpia SOLID - Principio de Responsabilidad Única y Robustez en CI
# ==============================================================================
"""
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.shield.ui.shield_dialog import *  # noqa: F401, F403
