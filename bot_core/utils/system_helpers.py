"""
# ==============================================================================
# ⚙️ HELPERS DEL SISTEMA Y SUBPROCESOS: system_helpers.py
# Ejecución asíncrona segura de comandos de consola del sistema con timeout.
# Principio de Responsabilidad Única (S).
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import subprocess
from typing import Any, Dict, List

logger = logging.getLogger("bot.utils.system")


def _exec_system_command(command: List[str], timeout: int = 300) -> Dict[str, Any]:
    """Ejecuta subprocesos del sistema con un tiempo límite (timeout) configurable en segundos."""
    try:
        result = subprocess.run(command, capture_output=True, text=True, timeout=timeout)
        output = result.stdout if result.stdout else result.stderr

        if output and len(output) > 3800:
            output = output[:3700] + "\n... [Salida truncada por límite de Telegram] ..."

        success = (result.returncode == 0)
        return {
            "success": success,
            "returncode": result.returncode,
            "output": output if output else "Comando ejecutado sin salida."
        }

    except subprocess.TimeoutExpired:
        return {
            "success": False,
            "returncode": -1,
            "output": f"Error: El comando tardó demasiado en responder (Timeout > {timeout}s)."
        }
    except Exception as e:
        return {
            "success": False,
            "returncode": -1,
            "output": f"Error al ejecutar el comando: {str(e)}"
        }


async def run_command_async(command: List[str], timeout: int = 300) -> Dict[str, Any]:
    """Ejecuta comandos de sistema de forma asíncrona en un hilo separado sin bloquear el loop."""
    return await asyncio.to_thread(_exec_system_command, command, timeout)
