"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD PYQT DIALOG ADAPTER: pyqt_dialog_adapter.py
# Despliegue de diálogo visual PyQt5 en la pantalla activa con fallback a kdialog
# Ubicación: /opt/sonar/monitor/shield/ui/pyqt_dialog_adapter.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
import subprocess
from pathlib import Path

from monitor.shield.ui.kdialog_adapter import launch_kdialog_fallback

logger = logging.getLogger("monitor.terminal_shield")
BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent


def launch_shield_dialog(mode: str, attempt: int = 1, seconds: int = 10, dry_run: bool = False) -> None:
    """Despliega el diálogo gráfico visual PyQt5 en la pantalla activa del usuario con fallback a kdialog."""
    dialog_script = BASE_DIR / "monitor" / "shield_dialog.py"
    if not dialog_script.exists():
        logger.warning("No se encontró monitor/shield_dialog.py. Activando fallback kdialog...")
        launch_kdialog_fallback(mode, attempt, seconds)
        return

    log_file = Path("/tmp/monitor/shield_dialog.log")
    log_file.parent.mkdir(parents=True, exist_ok=True)

    env_vars = {
        "DISPLAY": ":0",
        "WAYLAND_DISPLAY": "wayland-0",
        "QT_QPA_PLATFORM": "wayland;xcb",
        "XDG_RUNTIME_DIR": "/run/user/1000",
        "PATH": os.environ.get("PATH", "/usr/local/bin:/usr/bin:/bin"),
    }
    try:
        xauth_candidates = list(Path("/run/user/1000").glob("xauth_*"))
        if xauth_candidates:
            env_vars["XAUTHORITY"] = str(xauth_candidates[0])
    except Exception:
        pass

    cmd = [
        "/usr/bin/python3",
        str(dialog_script),
        "--mode", mode,
        "--attempt", str(attempt),
        "--seconds", str(seconds),
    ]
    if dry_run:
        cmd.append("--dry-run")

    launched_pyqt = False
    try:
        if os.geteuid() == 0:
            env_args = [
                "DISPLAY=:0",
                "WAYLAND_DISPLAY=wayland-0",
                "QT_QPA_PLATFORM=wayland;xcb",
                "XDG_RUNTIME_DIR=/run/user/1000",
            ]
            if "XAUTHORITY" in env_vars:
                env_args.append(f"XAUTHORITY={env_vars['XAUTHORITY']}")
            full_cmd = ["sudo", "-u", "britojab", "env"] + env_args + cmd
        else:
            full_cmd = cmd

        with open(log_file, "a", encoding="utf-8") as lf:
            proc = subprocess.Popen(
                full_cmd,
                env={**os.environ, **env_vars},
                stdout=lf,
                stderr=lf,
                start_new_session=True,
            )
            try:
                ret = proc.wait(timeout=0.25)
                if ret != 0:
                    logger.warning(f"shield_dialog.py finalizó prematuramente con código {ret}. Activando fallback kdialog...")
                else:
                    launched_pyqt = True
            except subprocess.TimeoutExpired:
                launched_pyqt = True
                logger.info(f"Diálogo Qt ({mode}, infracción {attempt}) activo en pantalla (PID: {proc.pid}).")
    except Exception as e:
        logger.warning(f"No se pudo desplegar diálogo visual Qt ({e}). Activando fallback kdialog...")

    if not launched_pyqt:
        launch_kdialog_fallback(mode, attempt, seconds)
