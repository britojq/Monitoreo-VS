"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD TTY RESOLVER: tty_resolver.py
# Normalización y resolución de TTY, ventanas de gracia y comprobación de lockdown
# Ubicación: /opt/sonar/monitor/shield/domain/tty_resolver.py
# ==============================================================================
"""

from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Optional, Tuple

from monitor.shield.infrastructure.audit_logger import safe_unlink
from monitor.shield.infrastructure.master_override import is_master_override_active

LOCKDOWN_FLAG = Path("/dev/shm/system_lockdown_active")
GRACE_PERIOD_FLAG = Path("/dev/shm/terminal_shield_grace_period")
RECENT_ALERTS_FLAG = Path("/dev/shm/terminal_shield_recent.json")


def is_lockdown_active() -> bool:
    """Comprueba si el modo lockdown está actualmente activo en el host."""
    try:
        return LOCKDOWN_FLAG.exists() and LOCKDOWN_FLAG.stat().st_size > 0
    except Exception:
        return False


def clean_tty_name(tty: Optional[str]) -> str:
    """Normaliza el identificador de la terminal para su uso en rutas y banderas volátiles."""
    if not tty:
        return ""
    t = str(tty).strip()
    if t in ("none", "?", "consola", "desconocida", "not a tty"):
        return ""
    clean = t.replace("/dev/", "")
    clean = clean.replace("/", "_")
    return clean


def is_grace_period_active(tty: Optional[str] = None) -> Tuple[bool, float]:
    """Comprueba si hay una ventana de mantenimiento autorizada vigente para la TTY o global."""
    now = time.time()
    if is_master_override_active():
        return True, now + 3600.0

    c_tty = clean_tty_name(tty)
    if c_tty:
        flag = Path(f"/dev/shm/terminal_shield_grace_{c_tty}")
        if flag.exists():
            try:
                exp = float(flag.read_text(encoding="utf-8").strip())
                if now < exp:
                    return True, exp
                safe_unlink(flag)
            except Exception:
                pass

    if not c_tty:
        for tty_file in Path("/dev/shm").glob("terminal_shield_grace_*"):
            try:
                exp = float(tty_file.read_text(encoding="utf-8").strip())
                if now < exp:
                    return True, exp
                safe_unlink(tty_file)
            except Exception:
                pass

    if GRACE_PERIOD_FLAG.exists():
        try:
            exp = float(GRACE_PERIOD_FLAG.read_text(encoding="utf-8").strip())
            if now < exp:
                return True, exp
            safe_unlink(GRACE_PERIOD_FLAG)
        except Exception:
            pass

    return False, 0.0


def resolve_tty(tty: Optional[str] = None, pid: int = 0) -> str:
    """Resuelve la TTY limpia a partir del parámetro tty, del PID o de la alerta más reciente."""
    c_tty = clean_tty_name(tty)
    if c_tty:
        return c_tty

    if pid > 1:
        try:
            import psutil
            p = psutil.Process(pid)
            term = p.terminal()
            if term and clean_tty_name(term):
                return clean_tty_name(term)
        except Exception:
            pass
        try:
            fd0 = os.readlink(f"/proc/{pid}/fd/0")
            if "pts" in fd0 or "tty" in fd0:
                return clean_tty_name(fd0)
        except Exception:
            pass

    if RECENT_ALERTS_FLAG.exists():
        try:
            data = json.loads(RECENT_ALERTS_FLAG.read_text(encoding="utf-8"))
            for k in reversed(list(data.keys())):
                if ":" in k:
                    candidate = k.split(":", 1)[1]
                    if candidate and clean_tty_name(candidate):
                        return clean_tty_name(candidate)
        except Exception:
            pass
    return ""
