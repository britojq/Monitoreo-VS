"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD LOCKDOWN ACTION: lockdown_action.py
# Aislamiento forzado del host, terminación global de sesiones y desbloqueo
# Ubicación: /opt/sonar/monitor/shield/actions/lockdown_action.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
import signal
import subprocess
from pathlib import Path

from monitor.shield.domain.proc_inspector import is_protected_process
from monitor.shield.infrastructure.audit_logger import log_event_to_database, safe_unlink
from monitor.shield.ui.infraction_counter import reset_infractions
from monitor.shield.ui.pyqt_dialog_adapter import launch_shield_dialog

logger = logging.getLogger("monitor.terminal_shield")

LOCKDOWN_FLAG = Path("/dev/shm/system_lockdown_active")
MASTER_OVERRIDE_FLAG = Path("/dev/shm/shield_master_override_active")
GRACE_PERIOD_FLAG = Path("/dev/shm/terminal_shield_grace_period")


def execute_lockdown(target_pid: int = 0, target_ppid: int = 0, target_user: str = "", dry_run: bool = False) -> dict:
    """Protocolo de Lockdown activo: mata terminales, lanza diálogo Qt de 10s y reinicia el equipo."""
    if not dry_run:
        if target_pid > 1:
            try:
                os.kill(target_pid, signal.SIGKILL)
            except Exception:
                pass
        if target_ppid > 1:
            try:
                os.kill(target_ppid, signal.SIGKILL)
            except Exception:
                pass

        for proc in ("konsole", "xterm", "gnome-terminal"):
            try:
                import psutil
                for p in psutil.process_iter(['pid', 'name']):
                    if p.info['name'] and proc in p.info['name'].lower():
                        if not is_protected_process(p.info['pid']):
                            try:
                                p.kill()
                            except Exception:
                                pass
            except Exception:
                pass

    launch_shield_dialog(mode="lockdown", attempt=3, seconds=10, dry_run=dry_run)

    desc = "REINICIO FORZADO POR EL ADMINISTRADOR (Lockdown). Terminales terminadas y diálogo de reinicio de 10s activado."
    log_event_to_database(
        user_name="admin",
        event="system_lockdown_reboot",
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {
        "success": True,
        "message": "Protocolo de reinicio de seguridad activado en pantalla (10s).",
        "reboot_triggered": True,
    }


def execute_unlock() -> dict:
    """Levanta el estado de emergencia, resetea infracciones y restaura servicios."""
    safe_unlink(LOCKDOWN_FLAG)
    safe_unlink(MASTER_OVERRIDE_FLAG)
    safe_unlink(GRACE_PERIOD_FLAG)
    for f in Path("/dev/shm").glob("terminal_shield_grace_*"):
        safe_unlink(f)
    reset_infractions()
    subprocess.run(["sudo", "systemctl", "start", "krfb"], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    desc = "Estado de contención desactivado. Infracciones reseteadas y normalidad restaurada."
    log_event_to_database(
        user_name="admin",
        event="lockdown_lifted",
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {"success": True, "message": "Escudo restaurado a estado normal e infracciones reseteadas."}
