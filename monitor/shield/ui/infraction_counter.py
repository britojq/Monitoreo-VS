"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD INFRACTION COUNTER: infraction_counter.py
# Control atómico de reincidencias de seguridad en memoria volátil (/dev/shm)
# Ubicación: /opt/sonar/monitor/shield/ui/infraction_counter.py
# ==============================================================================
"""

from __future__ import annotations

import json
import logging
import os
import subprocess
import time
from datetime import datetime
from pathlib import Path

logger = logging.getLogger("monitor.terminal_shield")

INFRACTIONS_FLAG = Path("/dev/shm/terminal_shield_infractions.json")


def record_infraction(reason: str = "terminal_killed", window_sec: int = 1800) -> int:
    """Registra una infracción en memoria volátil y devuelve el conteo acumulado (ventana de 30 min)."""
    count = 1
    now = time.time()
    try:
        if INFRACTIONS_FLAG.exists():
            data = json.loads(INFRACTIONS_FLAG.read_text(encoding="utf-8"))
            last_ts = data.get("last_ts", 0)
            prev_count = data.get("count", 0)
            if now - last_ts < window_sec:
                count = prev_count + 1
            else:
                count = 1
        payload = {
            "count": count,
            "last_ts": now,
            "last_reason": reason,
            "updated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        }
        INFRACTIONS_FLAG.write_text(json.dumps(payload), encoding="utf-8")
        try:
            os.chmod(str(INFRACTIONS_FLAG), 0o666)
        except Exception:
            pass
    except Exception as e:
        logger.warning(f"Error gestionando contador de infracciones: {e}")
    return count


def reset_infractions() -> None:
    """Limpia el historial de infracciones acumuladas."""
    try:
        INFRACTIONS_FLAG.unlink(missing_ok=True)
    except PermissionError:
        try:
            INFRACTIONS_FLAG.write_text(json.dumps({"count": 0, "last_ts": 0, "updated_at": ""}), encoding="utf-8")
        except Exception:
            pass
        try:
            subprocess.run(
                ["sudo", "rm", "-f", str(INFRACTIONS_FLAG)],
                check=False,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )
        except Exception:
            pass
    except Exception:
        pass
