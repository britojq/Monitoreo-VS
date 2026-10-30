"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD KDIALOG ADAPTER: kdialog_adapter.py
# Despliegue de notificaciones pasivas nativas en KDE Plasma mediante kdialog
# Ubicación: /opt/sonar/monitor/shield/ui/kdialog_adapter.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
import subprocess
from pathlib import Path

logger = logging.getLogger("monitor.terminal_shield")


def launch_kdialog_fallback(mode: str, attempt: int = 1, seconds: int = 10) -> bool:
    """Despliega notificación nativa en KDE Plasma usando kdialog como respaldo determinista."""
    kdialog_bin = "/usr/bin/kdialog"
    if not os.path.exists(kdialog_bin):
        logger.warning("kdialog no está instalado en el sistema.")
        return False

    title = f"ADVERTENCIA DE SEGURIDAD (INFRACCIÓN {attempt} DE 3)"
    if mode == "warning":
        msg = (
            f"🛑 ACCESO ADMINISTRATIVO DENEGADO\n\n"
            f"Infracción {attempt} de 3 registrada por intento de sudo no autorizado.\n\n"
            f"👉 Para autorizar este comando, presione el botón 'SOY YO' en Telegram."
        )
    else:
        msg = (
            f"🚨 PROTOCOLO CRÍTICO DE CONTENCIÓN\n\n"
            f"Límite de infracciones alcanzado ({attempt}/3).\n"
            f"El equipo se reiniciará en {seconds} segundos a menos que autorice en Telegram."
        )

    kdialog_cmd = [
        kdialog_bin,
        "--title", title,
        "--passivepopup", msg, str(seconds),
    ]

    k_env = {
        **os.environ,
        "DISPLAY": ":0",
        "WAYLAND_DISPLAY": "wayland-0",
        "QT_QPA_PLATFORM": "wayland;xcb",
        "XDG_RUNTIME_DIR": "/run/user/1000",
    }
    try:
        xauth_candidates = list(Path("/run/user/1000").glob("xauth_*"))
        if xauth_candidates:
            k_env["XAUTHORITY"] = str(xauth_candidates[0])
    except Exception:
        pass

    if os.geteuid() == 0:
        env_args = [
            "DISPLAY=:0",
            "WAYLAND_DISPLAY=wayland-0",
            "QT_QPA_PLATFORM=wayland;xcb",
            "XDG_RUNTIME_DIR=/run/user/1000",
        ]
        if "XAUTHORITY" in k_env:
            env_args.append(f"XAUTHORITY={k_env['XAUTHORITY']}")
        full_cmd = ["sudo", "-u", "britojab", "env"] + env_args + kdialog_cmd
    else:
        full_cmd = kdialog_cmd

    try:
        log_file = Path("/tmp/monitor/shield_dialog.log")
        log_file.parent.mkdir(parents=True, exist_ok=True)
        with open(log_file, "a", encoding="utf-8") as lf:
            subprocess.Popen(
                full_cmd,
                env=k_env,
                stdout=lf,
                stderr=lf,
                start_new_session=True,
            )
        logger.info(f"Fallback nativo kdialog ({mode}, infracción {attempt}) desplegado exitosamente.")
        return True
    except Exception as e_kd:
        logger.error(f"Fallo al desplegar fallback kdialog: {e_kd}")
        return False
