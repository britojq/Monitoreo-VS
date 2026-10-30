"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD ENFORCE SUDO ACTION: enforce_sudo_action.py
# Contención inmediata en 0 segundos ante elevaciones interactivas no autorizadas
# Ubicación: /opt/sonar/monitor/shield/actions/enforce_sudo_action.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import logging
import os
import signal
import socket
import subprocess
import threading
from datetime import datetime

from monitor.shield.domain.proc_inspector import (
    get_process_name,
    is_protected_process,
)
from monitor.shield.domain.tty_resolver import (
    clean_tty_name,
    is_grace_period_active,
)
from monitor.shield.infrastructure.audit_logger import log_event_to_database
from monitor.shield.infrastructure.master_override import is_master_override_active
from monitor.shield.infrastructure.notifier import send_telegram_alert
from monitor.shield.ui.infraction_counter import record_infraction
from monitor.shield.ui.pyqt_dialog_adapter import launch_shield_dialog

logger = logging.getLogger("monitor.terminal_shield")


def execute_enforce_sudo(
    user: str,
    sudo_user: str,
    sudo_cmd: str,
    tty: str,
    ppid: int = 0,
    dry_run: bool = False,
) -> dict:
    """Aplica contención autónoma en 0 segundos ante intentos interactivos de sudo no autorizados."""
    if is_master_override_active():
        return {"allowed": True, "reason": "master_override_active"}

    grace_active, _ = is_grace_period_active(tty=tty)
    if grace_active:
        return {"allowed": True, "reason": "grace_period_active"}

    target_pid = ppid
    terminal_emulator_pid = 0
    if tty and tty not in ("none", "?", "consola"):
        c_tty = tty.replace("/dev/", "")
        try:
            out = subprocess.check_output(
                ["ps", "-t", c_tty, "-o", "pid,ppid,comm", "--no-headers"],
                text=True,
                timeout=2.0,
                stderr=subprocess.DEVNULL,
            )
            for line in out.strip().splitlines():
                parts = line.split()
                if len(parts) >= 3 and parts[2].lower() in ("bash", "zsh", "sh", "dash", "fish"):
                    target_pid = int(parts[0])
                    terminal_emulator_pid = int(parts[1])
                    break
        except Exception:
            pass

    if (target_pid > 1 and is_protected_process(target_pid)) or (ppid > 1 and is_protected_process(ppid)):
        logger.info(f"Sudo interactivo permitido para entorno de desarrollo/asistente (PID {target_pid}).")
        return {"allowed": True, "reason": "protected_agent_process"}

    count = record_infraction(reason="unauthorized_sudo")
    reboot_triggered = (count >= 3)

    mode = "reboot_limit" if reboot_triggered else "warning"
    launch_shield_dialog(mode=mode, attempt=count, seconds=10, dry_run=dry_run)

    action_type = "su / su -" if "su" in sudo_cmd.lower() else "sudo"
    event_tag = "reboot_limit_triggered" if reboot_triggered else "unauthorized_sudo_blocked"
    desc = (
        f"Intento no autorizado de {action_type} ({user} -> {sudo_user}): '{sudo_cmd}' en {tty}. "
        f"Infracción {count}/3 registrada. Shell (PID {ppid}) neutralizada."
    )
    log_event_to_database(
        user_name=user or "desconocido",
        event=event_tag,
        description=desc,
        ip_address="127.0.0.1",
        user_agent=f"PAM/{action_type} ({tty})",
        user_role="admin",
    )

    def _dispatch_alert():
        try:
            hostname = socket.gethostname()
            now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            if reboot_triggered:
                msg = (
                    "🛑 <b>REINCIDENCIA CRÍTICA: LÍMITE DE FALTAS ALCANZADO</b>\n\n"
                    f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                    f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
                    f"👑 <b>Destino:</b> <code>{html.escape(sudo_user)}</code>\n"
                    f"🛠️ <b>Comando:</b> <code>{html.escape(sudo_cmd)}</code>\n"
                    f"📟 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
                    f"⏰ <b>Hora:</b> <code>{now_str}</code>\n"
                    f"🚨 <b>Infracciones:</b> <code>{count} de 3 (LÍMITE ALCANZADO)</code>\n\n"
                    "⚡ <i>Protocolo de reinicio de contención activado en pantalla (10s).</i>"
                )
            else:
                msg = (
                    f"⚠️ <b>INTENTO NO AUTORIZADO DE {action_type.upper()} NEUTRALIZADO</b>\n\n"
                    f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                    f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
                    f"👑 <b>Destino:</b> <code>{html.escape(sudo_user)}</code>\n"
                    f"🛠️ <b>Comando:</b> <code>{html.escape(sudo_cmd)}</code>\n"
                    f"📟 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
                    f"⏰ <b>Hora:</b> <code>{now_str}</code>\n"
                    f"⚠️ <b>Infracción:</b> <code>{count} de 3</code>\n"
                    "🛡️ <b>Acción:</b> <code>Terminal clausurada en 0s</code>\n\n"
                    "<i>Al registrarse el 3er intento no autorizado, el equipo se reiniciará automáticamente.</i>"
                )
            clean_t = clean_tty_name(tty) or "pts"
            keyboard = {
                "inline_keyboard": [
                    [
                        {"text": "🔒 REINICIAR (LOCKDOWN)", "callback_data": f"shield_lockdown:{hostname}:{ppid}:0"},
                        {"text": "✅ SOY YO (AUTORIZAR 30m)", "callback_data": f"shield_auth:{hostname}:{ppid}:{clean_t}"},
                    ]
                ]
            }
            asyncio.run(send_telegram_alert(msg, reply_markup=keyboard))
        except Exception:
            pass

    t = threading.Thread(target=_dispatch_alert, daemon=True)
    t.start()

    killed_pids = []
    if not dry_run:
        pids_to_kill = set()
        if target_pid > 1 and not is_protected_process(target_pid):
            pids_to_kill.add(target_pid)
        if ppid > 1 and not is_protected_process(ppid):
            pids_to_kill.add(ppid)
        if terminal_emulator_pid > 1 and not is_protected_process(terminal_emulator_pid):
            p_name = get_process_name(terminal_emulator_pid).lower()
            if p_name in ("konsole", "xterm", "gnome-terminal", "terminator", "tilix", "alacritty", "kitty"):
                pids_to_kill.add(terminal_emulator_pid)
        for p in pids_to_kill:
            try:
                os.kill(p, signal.SIGKILL)
                killed_pids.append(p)
            except Exception:
                pass

    return {
        "allowed": False,
        "infractions": count,
        "reboot_triggered": reboot_triggered,
        "killed_pids": killed_pids,
    }
