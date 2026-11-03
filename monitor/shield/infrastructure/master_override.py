"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD MASTER OVERRIDE: master_override.py
# Validación y gestión del token de desactivación temporal de emergencia (Owner)
# Ubicación: /opt/sonar/monitor/shield/infrastructure/master_override.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import os
import socket
import subprocess
import sys
import threading
from datetime import datetime
from pathlib import Path

from monitor.core_shield import verify_system_auth_token
from monitor.shield.infrastructure.audit_logger import log_event_to_database, safe_unlink
from monitor.shield.infrastructure.notifier import send_telegram_alert

logger = logging.getLogger("monitor.terminal_shield")

MASTER_OVERRIDE_FLAG = Path("/dev/shm/shield_master_override_active")
LOCKDOWN_FLAG = Path("/dev/shm/system_lockdown_active")
INFRACTIONS_FLAG = Path("/dev/shm/terminal_shield_infractions.json")


def is_master_override_active() -> bool:
    """Comprueba si el Override Maestro de emergencia del Owner está activo (hasta el próximo reinicio)."""
    try:
        return MASTER_OVERRIDE_FLAG.exists() and MASTER_OVERRIDE_FLAG.stat().st_size > 0
    except Exception:
        return False


def execute_master_override(key: str, source: str = "Terminal / CLI") -> dict:
    """Valida el token de autorización administrativa y desactiva el escudo hasta el próximo reinicio."""
    if not verify_system_auth_token(key):
        log_event_to_database(
            user_name="intruder",
            event="master_override_failed",
            description=f"Intento fallido de validación de token desde {source}.",
            ip_address="127.0.0.1",
            user_role="unknown",
        )
        return {"success": False, "message": "Token de validación inválido. Acceso denegado."}

    payload = {
        "activated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "activated_by": "Administrador (Token validado)",
        "source": source,
        "expires": "until_reboot",
    }
    MASTER_OVERRIDE_FLAG.write_text(json.dumps(payload), encoding="utf-8")
    try:
        os.chmod(str(MASTER_OVERRIDE_FLAG), 0o666)
    except Exception:
        pass

    safe_unlink(LOCKDOWN_FLAG)
    try:
        INFRACTIONS_FLAG.unlink(missing_ok=True)
    except Exception:
        pass

    # Cerrar cualquier diálogo de advertencia Qt o kdialog abierto
    if "shield_dialog" not in sys.argv[0]:
        subprocess.run(["pkill", "-f", "shield_dialog.py"], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        subprocess.run(["pkill", "-f", "kdialog.*ADVERTENCIA"], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    desc = f"AUTORIZACIÓN ADMINISTRATIVA CONCEDIDA ({source}). Escudo desactivado hasta el próximo reinicio."
    log_event_to_database(
        user_name="Owner",
        event="master_override_activated",
        description=desc,
        ip_address="127.0.0.1",
        user_role="owner",
    )

    def _dispatch_override_alert():
        """Ejecuta la función _dispatch_override_alert conforme a la especificación técnica."""
        try:
            hostname = socket.gethostname()
            now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            msg = (
                "🔑 <b>AUTORIZACIÓN ADMINISTRATIVA ACTIVADA</b>\n\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                f"👤 <b>Identidad:</b> Validado como <b>Owner legítimo</b>\n"
                f"🚪 <b>Origen:</b> <code>{html.escape(source)}</code>\n"
                f"⏰ <b>Hora:</b> <code>{now_str}</code>\n"
                "🛡️ <b>Estado del Escudo:</b> <code>SUSPENDIDO HASTA PRÓXIMO REINICIO</code>\n\n"
                "<i>Todas las restricciones y alertas automáticas quedan desactivadas para mantenimiento. "
                "La protección perimetral se restablecerá automáticamente al reiniciar el equipo.</i>"
            )
            asyncio.run(send_telegram_alert(msg))
        except Exception:
            pass

    t = threading.Thread(target=_dispatch_override_alert, daemon=True)
    t.start()

    return {"success": True, "message": "Token validado con éxito. Escudo desactivado hasta el próximo reinicio."}
