"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD AUTH TTY ACTION: auth_tty_action.py
# Concesión de periodos de gracia interactiva por TTY, reseteo de faltas y sync
# Ubicación: /opt/sonar/monitor/shield/actions/auth_tty_action.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
import platform
import subprocess
import threading
import time
from datetime import datetime
from pathlib import Path
from typing import Optional

from monitor.shield.domain.tty_resolver import resolve_tty
from monitor.shield.infrastructure.audit_logger import log_event_to_database
from monitor.shield.ui.infraction_counter import reset_infractions

logger = logging.getLogger("monitor.terminal_shield")
GRACE_PERIOD_FLAG = Path("/dev/shm/terminal_shield_grace_period")


def execute_auth(minutes: int = 30, tty: Optional[str] = None, pid: int = 0) -> dict:
    """Autoriza temporalmente el acceso interactivo para una terminal específica y resetea el contador de infracciones."""
    reset_infractions()
    exp = time.time() + (minutes * 60)
    exp_dt = datetime.fromtimestamp(exp).strftime("%H:%M:%S")

    c_tty = resolve_tty(tty=tty, pid=pid)
    if c_tty:
        flag = Path(f"/dev/shm/terminal_shield_grace_{c_tty}")
        flag.write_text(str(exp), encoding="utf-8")
        try:
            os.chmod(str(flag), 0o666)
        except Exception:
            pass
        tty_label = c_tty.replace("_", "/")
        target_desc = f"Terminal TTY '{tty_label}'"
    else:
        tty_label = "global"
        target_desc = "Todas las terminales (Global)"

    GRACE_PERIOD_FLAG.write_text(str(exp), encoding="utf-8")
    try:
        os.chmod(str(GRACE_PERIOD_FLAG), 0o666)
    except Exception:
        pass

    try:
        subprocess.run(["pkill", "-f", "shield_dialog.py"], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        subprocess.run(["pkill", "-f", "kdialog.*ADVERTENCIA"], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except Exception:
        pass

    def _sync_peer():
        try:
            from bot_core.services.config_manager import load_config
            cfg = load_config()
            if str(cfg.get("node_role", "master")).lower() in ("slave", "dev"):
                return
        except Exception:
            pass

        node_name = (platform.node() or "").upper()
        target_host = "produccion" if node_name == "MONITOREO-DEV" else "desarrollo"
        target_file = f"/dev/shm/terminal_shield_grace_{c_tty}" if c_tty else "/dev/shm/terminal_shield_grace_period"
        try:
            cmd = [
                "ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=5",
                target_host,
                f"echo {exp} | sudo tee {target_file} >/dev/null && sudo chmod 0666 {target_file}",
            ]
            subprocess.run(cmd, capture_output=True, timeout=8)
        except Exception:
            pass

    threading.Thread(target=_sync_peer, daemon=True).start()

    desc = f"Ventana de mantenimiento interactivo autorizada para {target_desc} por {minutes} min (hasta {exp_dt}). Infracciones reseteadas a 0."
    log_event_to_database(
        user_name="admin",
        event="terminal_authorized",
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {"success": True, "expiry": exp_dt, "minutes": minutes, "tty": tty_label}
