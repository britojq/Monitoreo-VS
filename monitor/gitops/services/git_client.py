# monitor/gitops/services/git_client.py
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

logger = logging.getLogger("monitor.gitops.git")

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent

class GitClient:
    """Ejecutor desacoplado y seguro de comandos Git con soporte de proxies corporativos."""

    @staticmethod
    async def run_command(
        args: List[str],
        timeout: float = 30.0,
        proxy_args: Optional[List[str]] = None,
        cwd: Optional[Path] = None,
    ) -> Tuple[int, str, str]:
        """Ejecuta un comando git en el directorio del proyecto con argumentos opcionales de proxy."""
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
