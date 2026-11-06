"""
# ==============================================================================
# ⚙️ HELPERS DEL SISTEMA Y SUBPROCESOS: system_helpers.py
# Ejecución asíncrona segura de comandos de consola del sistema con timeout.
# Principio de Responsabilidad Única (S) y Mitigación CWE-78 (SEC-003).
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import subprocess
from typing import Any, Dict, List

from bot_core.security.command_registry import validate_command

logger = logging.getLogger("bot.utils.system")


def _exec_system_command(command: List[str], timeout: int = 300) -> Dict[str, Any]:
    """
    Ejecuta subprocesos del sistema con validación previa de lista blanca (SEC-003).

    Args:
        command: Lista de strings con el binario y sus argumentos.
        timeout: Límite de tiempo máximo en segundos para la ejecución.

    Returns:
        Dict[str, Any]: Diccionario con 'success', 'returncode' y 'output'.
    """
    # 1. Validación de Lista Blanca y Sanitización de Metacaracteres (SEC-003)
    is_valid, reason = validate_command(command)
    if not is_valid:
        logger.warning(
            "Seguridad [SEC-003]: Bloqueado intento de ejecutar comando no autorizado %r. Razón: %s",
            command,
            reason,
        )
        return {
            "success": False,
            "returncode": -1,
            "output": f"Seguridad: Comando no autorizado o argumentos inválidos ({reason}).",
        }

    try:
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            timeout=timeout,
            shell=False,
        )
        output = result.stdout if result.stdout else result.stderr

        if output and len(output) > 3800:
            output = output[:3700] + "\n... [Salida truncada por límite de Telegram] ..."

        success = (result.returncode == 0)
        return {
            "success": success,
            "returncode": result.returncode,
            "output": output if output else "Comando ejecutado sin salida.",
        }

    except subprocess.TimeoutExpired:
        return {
            "success": False,
            "returncode": -1,
            "output": f"Error: El comando tardó demasiado en responder (Timeout > {timeout}s).",
        }
    except Exception as e:
        return {
            "success": False,
            "returncode": -1,
            "output": f"Error al ejecutar el comando: {str(e)}",
        }


async def run_command_async(command: List[str], timeout: int = 300) -> Dict[str, Any]:
    """
    Ejecuta comandos de sistema de forma asíncrona en un hilo separado sin bloquear el loop.

    Args:
        command: Lista de strings con el comando validado y sus argumentos.
        timeout: Límite de tiempo máximo en segundos.

    Returns:
        Dict[str, Any]: Diccionario con 'success', 'returncode' y 'output'.
    """
    return await asyncio.to_thread(_exec_system_command, command, timeout)
