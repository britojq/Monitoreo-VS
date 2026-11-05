#!/usr/bin/env python3
"""
# ==============================================================================
# 🔄 FACHADA FÍSICA CANÓNICA DE COMPATIBILIDAD: self_heal_environment.py
# Delegación y preservación de punto de entrada histórico hacia: monitor.tools.self_heal_environment
# Arquitectura Limpia SOLID - Principio de Responsabilidad Única y Robustez en CI
# ==============================================================================
"""
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.tools.self_heal_environment import *  # noqa: F401, F403

if __name__ == "__main__":
    from monitor.tools.self_heal_environment import main
    main()
