"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD OPEN WORKER: terminal_open_worker.py
# Procesamiento reactivo ante inicio de sesión interactiva de shell en Linux
# Ubicación: /opt/sonar/monitor/shield/actions/terminal_open_worker.py
# ==============================================================================
"""

from __future__ import annotations

import html
import logging
import os
import signal
import socket
import sys
from datetime import datetime

from monitor.shield.domain.proc_inspector import get_process_name, is_recent_alert
from monitor.shield.domain.tty_resolver import clean_tty_name, is_grace_period_active, is_lockdown_active
from monitor.shield.infrastructure.audit_logger import log_event_to_database
from monitor.shield.infrastructure.master_override import is_master_override_active
from monitor.shield.infrastructure.notifier import send_telegram_alert

logger = logging.getLogger("monitor.terminal_shield")


async def process_terminal_open(
    pid: int,
    ppid: int,
    user: str,
    tty: str,
    display: str = "",
) -> None:
    """Procesa el evento de apertura de terminal interactiva."""
    if is_master_override_active():
        logger.info(f"Terminal PID {pid} abierta bajo Override Maestro de emergencia (Owner autorizado).")
        return

    if is_lockdown_active():
        logger.warning(f"Lockdown activo: eliminando proceso de terminal PID {pid} de forma inmediata.")
        if pid > 1:
            try:
                os.kill(pid, signal.SIGKILL)
            except Exception:
                pass
        sys.exit(1)

    grace_active, exp_ts = is_grace_period_active(tty=tty)
    if grace_active:
        logger.info(f"Terminal PID {pid} ({tty}) abierta bajo ventana autorizada (expira en {int(exp_ts - 0)}s).")
        return

    key = f"{user}:{tty}"
    if is_recent_alert(key, window_sec=30):
        logger.info(f"Alerta omitida por deduplicación reciente ({key}).")
        return

    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    proc_name = get_process_name(pid)
    parent_name = get_process_name(ppid)

    entorno = display or "Consola Local / TTY"
    if display:
        entorno = f"KDE Plasma / VNC ({display})"

    mensaje = (
        "🚨 <b>Alerta de Seguridad: Terminal Interactiva</b>\n\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
        f"📟 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
        f"🆔 <b>Proceso:</b> PID <code>{pid}</code> (<code>{html.escape(proc_name)}</code>)\n"
        f"🧬 <b>Padre:</b> PID <code>{ppid}</code> (<code>{html.escape(parent_name)}</code>)\n"
        f"🖥️ <b>Entorno:</b> <code>{html.escape(entorno)}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
        "⚠️ <i>Se ha abierto una shell interactiva que permite la ejecución de comandos en el sistema. Seleccione una acción de contención inmediata:</i>"
    )

    clean_t = clean_tty_name(tty) or "pts"
    keyboard = {
        "inline_keyboard": [
            [
                {
                    "text": "🔒 REINICIAR (LOCKDOWN)",
                    "callback_data": f"shield_lockdown:{hostname}:{pid}:{ppid}",
                },
                {
                    "text": "🚫 MATAR TERMINAL",
                    "callback_data": f"shield_kill:{hostname}:{pid}:{ppid}",
                },
            ],
            [
                {
                    "text": "✅ SOY YO (AUTORIZAR 30m)",
                    "callback_data": f"shield_auth:{hostname}:{pid}:{clean_t}",
                }
            ],
        ]
    }

    desc_bd = f"Apertura de terminal interactiva ({proc_name} / {parent_name}) por {user} en {tty} (PID: {pid})"
    log_event_to_database(
        user_name=user,
        event="terminal_opened",
        description=desc_bd,
        ip_address="127.0.0.1",
        user_agent=f"Terminal/{parent_name} ({tty})",
        user_role="admin",
    )

    await send_telegram_alert(mensaje, reply_markup=keyboard)
