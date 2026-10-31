# monitor/gitops/services/git_client.py
from __future__ import annotations

import asyncio
import html
import logging
from datetime import datetime
from pathlib import Path
from typing import Any, List, Optional, Tuple

from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    get_core_repo_url,
    get_core_branch,
)
from monitor.domain.exceptions import SubprocessSecurityException

logger = logging.getLogger("monitor.gitops.git")

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent


def validate_process_args(args: List[str]) -> bool:
    """
    Valida estrictamente los argumentos pasados a un subproceso del sistema.
    Previene inyección de metacaracteres, secuencias nulas y flags peligrosos de Git.

    Args:
        args: Lista de cadenas de argumentos a despachar.

    Returns:
        bool: True si todos los argumentos son seguros.

    Raises:
        SubprocessSecurityException: Si se detecta un patrón malicioso o flag prohibido.
    """
    if not isinstance(args, list):
        raise SubprocessSecurityException("La lista de argumentos debe ser de tipo List[str]")

    forbidden_chars = {"\x00", "\r", "\n", "`", ";", "|", "&"}
    dangerous_git_options = {
        "--upload-pack",
        "--receive-pack",
        "--exec",
    }

    for arg in args:
        if not isinstance(arg, str):
            raise SubprocessSecurityException(f"Argumento no es de tipo str: {type(arg)}")

        for char in forbidden_chars:
            if char in arg:
                raise SubprocessSecurityException(
                    f"Argumento contiene metacaracter prohibido '{repr(char)}': {arg!r}"
                )

        if "$(" in arg or "${" in arg:
            raise SubprocessSecurityException(
                f"Argumento contiene intento de interpolación o substitución: {arg!r}"
            )

        arg_lower = arg.lower()
        for dangerous_opt in dangerous_git_options:
            if arg_lower == dangerous_opt or arg_lower.startswith(dangerous_opt + "="):
                raise SubprocessSecurityException(
                    f"Flag de Git potencialmente inseguro denegado: {arg}"
                )

    return True


class GitClient:
    """Ejecutor desacoplado y seguro de comandos Git con soporte de proxies corporativos."""

    @staticmethod
    def validate_args(args: List[str]) -> bool:
        """Alias de validación de argumentos en la clase GitClient."""
        return validate_process_args(args)

    @staticmethod
    async def run_command(
        args: List[str],
        timeout: float = 30.0,
        proxy_args: Optional[List[str]] = None,
        cwd: Optional[Path] = None,
    ) -> Tuple[int, str, str]:
        """Ejecuta un comando git en el directorio del proyecto con argumentos opcionales de proxy."""
        all_args = (proxy_args or []) + args
        try:
            validate_process_args(all_args)
        except SubprocessSecurityException as sec_err:
            logger.error(f"Violación de seguridad al validar argumentos de Git: {sec_err}")
            return -1, "", f"Error de seguridad: {sec_err}"

        cmd = ["git"]
        if proxy_args:
            cmd.extend(proxy_args)
        cmd.extend(args)

        work_dir = cwd or BASE_DIR

        try:
            proc = await asyncio.create_subprocess_exec(
                *cmd,
                cwd=str(work_dir),
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
            return (
                proc.returncode or 0,
                stdout.decode("utf-8", errors="ignore").strip(),
                stderr.decode("utf-8", errors="ignore").strip(),
            )
        except asyncio.TimeoutExpired:
            try:
                proc.kill()
            except Exception:
                pass
            return -1, "", "Timeout: El comando Git tardó demasiado tiempo en responder."
        except Exception as e:
            return -1, "", str(e)

    @staticmethod
    async def notify_owner_failure(bot_instance: Any, error_message: str, operation: str = "comprobación") -> None:
        """Envía una alerta crítica inmediata al Owner si Git no puede actualizarse o sincronizarse."""
        if not bot_instance:
            return

        repo_url = get_core_repo_url()
        branch = get_core_branch()
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        alert_text = (
            "⚠️ <b>ALERTA CRÍTICA: Fallo en Actualización de Git</b>\n"
            f"El sistema no pudo completar la <b>{html.escape(operation)}</b> con el repositorio oficial.\n\n"
            f"🔗 <b>Repositorio:</b> <code>{repo_url}</code>\n"
            f"🌿 <b>Rama:</b> <code>{branch}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            f"❌ <b>Detalle del Error:</b>\n"
            f"<pre>{html.escape(error_message)}</pre>\n\n"
            "<i>⚠️ Se requiere verificar la conectividad del servidor, DNS corporativo o acceso a GitHub.</i>"
        )
        try:
            await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=alert_text, parse_mode='HTML')
            logger.info(f"Alerta de fallo en Git notificada exitosamente al Owner ({IMMUTABLE_OWNER_ID}).")
        except Exception as e:
            logger.error(f"Fallo al notificar error de Git al Owner: {e}")
