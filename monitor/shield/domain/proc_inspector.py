"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD PROCESS INSPECTOR: proc_inspector.py
# Inspección segura en /proc, detección de linaje y protección del agente de desarrollo
# Ubicación: /opt/sonar/monitor/shield/domain/proc_inspector.py
# ==============================================================================
"""

from __future__ import annotations

import json
import time
from pathlib import Path

RECENT_ALERTS_FLAG = Path("/dev/shm/terminal_shield_recent.json")


def get_process_name(pid: int) -> str:
    """Obtiene el nombre del ejecutable a partir de su PID en /proc/$PID/comm."""
    if pid <= 0:
        return "desconocido"
    comm_path = Path(f"/proc/{pid}/comm")
    if comm_path.exists():
        try:
            return comm_path.read_text(encoding="utf-8").strip()
        except Exception:
            pass
    return "desconocido"


def is_protected_process(pid: int) -> bool:
    """Verifica si el PID o sus ancestros/descendientes pertenecen al entorno del agente/asistente de desarrollo."""
    if pid <= 1:
        return False
    try:
        import psutil
        p = psutil.Process(pid)
        while p and p.pid > 1:
            name = p.name().lower()
            try:
                cmdline = " ".join(p.cmdline()).lower()
            except Exception:
                cmdline = ""
            try:
                environ = p.environ()
            except Exception:
                environ = {}

            if (
                any(k in name for k in ("agy", "antigravity"))
                or any(k in cmdline for k in ("agy", "antigravity-cli", "faadaf35-22d4-442e"))
                or "ANTIGRAVITY_AGENT" in environ
                or "ANTIGRAVITY_CONVERSATION_ID" in environ
            ):
                return True

            try:
                for child in p.children(recursive=False):
                    cname = child.name().lower()
                    if any(k in cname for k in ("agy", "antigravity")):
                        return True
            except Exception:
                pass

            p = p.parent()
    except Exception:
        pass
    return False


def is_recent_alert(key: str, window_sec: int = 40) -> bool:
    """Verifica si ya se alertó recientemente para evitar saturación de mensajes."""
    try:
        data = {}
        if RECENT_ALERTS_FLAG.exists():
            data = json.loads(RECENT_ALERTS_FLAG.read_text(encoding="utf-8"))
        now = time.time()
        last_time = data.get(key, 0)
        if now - last_time < window_sec:
            return True
        data[key] = now
        data = {k: v for k, v in data.items() if now - v < 300}
        RECENT_ALERTS_FLAG.write_text(json.dumps(data), encoding="utf-8")
    except Exception:
        pass
    return False
