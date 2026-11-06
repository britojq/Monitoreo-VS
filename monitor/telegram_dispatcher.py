"""
# ==============================================================================
# 📤 FACHADA DE COMPATIBILIDAD FÍSICA: telegram_dispatcher.py
# Re-exporta la implementación canónica de monitor/alerts/telegram_dispatcher.py
# ==============================================================================
"""
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.alerts.telegram_dispatcher import *  # noqa: F401, F403
