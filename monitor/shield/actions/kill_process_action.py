"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD KILL PROCESS ACTION: kill_process_action.py
# Terminación de procesos de shell/emulador, conteo de infracciones y diálogo visual
# Ubicación: /opt/sonar/monitor/shield/actions/kill_process_action.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
import signal
from monitor.shield.domain.proc_inspector import get_process_name
from monitor.shield.infrastructure.audit_logger import log_event_to_database
from monitor.shield.ui.infraction_counter import record_infraction
from monitor.shield.ui.pyqt_dialog_adapter import launch_shield_dialog

logger = logging.getLogger("monitor.terminal_shield")


def execute_kill(pid: int, ppid: int = 0, dry_run: bool = False) -> dict:
    """Mata el proceso de la terminal, registra la infracción y lanza advertencia visual o reinicio."""
    killed = []
    failed = []

    if not dry_run:
        # 1. Matar proceso de la shell
        if pid > 1:
            try:
                os.kill(pid, signal.SIGKILL)
                killed.append(f"Shell (PID: {pid})")
            except ProcessLookupError:
                killed.append(f"Shell (PID: {pid} ya terminada)")
            except Exception as e:
                failed.append(f"PID {pid}: {e}")

        # 2. Si el padre es un emulador de terminal gráfico o shell, matarlo también
        if ppid > 1:
            p_name = get_process_name(ppid).lower()
            if p_name in ("konsole", "xterm", "gnome-terminal", "terminator", "tilix", "alacritty", "kitty", "bash", "zsh"):
                try:
                    os.kill(ppid, signal.SIGKILL)
                    killed.append(f"{p_name} (PID: {ppid})")
                except ProcessLookupError:
                    pass
                except Exception as e:
                    failed.append(f"PPID {ppid}: {e}")
    else:
        killed.append(f"Simulación (PID: {pid}, PPID: {ppid})")

    count = record_infraction(reason="terminal_killed")
    reboot_triggered = count >= 3

    if reboot_triggered:
        launch_shield_dialog(mode="reboot_limit", attempt=count, seconds=10, dry_run=dry_run)
        desc = (
            f"Terminal neutralizada por el Administrador. 3er INTENTO NO AUTORIZADO ALCANZADO ({count}/3). "
            f"Protocolo de reinicio de seguridad activado en pantalla (10s): {', '.join(killed) if killed else 'Shell'}"
        )
        event_tag = "reboot_limit_triggered"
    else:
        launch_shield_dialog(mode="warning", attempt=count, seconds=10, dry_run=dry_run)
        desc = (
            f"Terminal neutralizada por el Administrador ({', '.join(killed) if killed else 'Ninguna shell'}). "
            f"Advertencia {count} de 3 notificada en pantalla (10s)."
        )
        event_tag = "terminal_killed"

    log_event_to_database(
        user_name="admin",
        event=event_tag,
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {
        "success": len(killed) > 0,
        "killed": killed,
        "failed": failed,
        "infractions": count,
        "reboot_triggered": reboot_triggered,
    }
