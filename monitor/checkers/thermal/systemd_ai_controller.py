"""
Controlador de servicio systemd para parada de emergencia de procesos de IA.
"""
import logging
import os
import subprocess

from monitor.checkers.thermal.constants import AI_SERVICE_UNIT

logger = logging.getLogger("monitor.checkers.thermal.ai_controller")


def is_ai_service_active() -> bool:
    """Verifica si el servicio de IA se encuentra actualmente activo en systemd."""
    try:
        res = subprocess.run(
            ["systemctl", "is-active", AI_SERVICE_UNIT],
            capture_output=True,
            text=True,
            timeout=3.0
        )
        return res.stdout.strip() == "active"
    except Exception:
        return False


def stop_ai_service() -> bool:
    """Detiene inmediatamente el servicio de IA para salvaguardar el procesador."""
    try:
        cmd = ["sudo", "systemctl", "stop", AI_SERVICE_UNIT] if os.geteuid() != 0 else ["systemctl", "stop", AI_SERVICE_UNIT]
        logger.critical(f"🛑 [CONTROL DE ASISTENTE] Ejecutando: {' '.join(cmd)}")
        res = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=10.0
        )
        return res.returncode == 0
    except Exception as e:
        logger.error(f"Error deteniendo {AI_SERVICE_UNIT}: {e}")
        return False


def start_ai_service() -> bool:
    """Reanuda el servicio de IA una vez alcanzada temperatura segura."""
    try:
        cmd = ["sudo", "systemctl", "start", AI_SERVICE_UNIT] if os.geteuid() != 0 else ["systemctl", "start", AI_SERVICE_UNIT]
        logger.info(f"🟢 [CONTROL DE ASISTENTE] Ejecutando: {' '.join(cmd)}")
        res = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=10.0
        )
        return res.returncode == 0
    except Exception as e:
        logger.error(f"Error iniciando {AI_SERVICE_UNIT}: {e}")
        return False
