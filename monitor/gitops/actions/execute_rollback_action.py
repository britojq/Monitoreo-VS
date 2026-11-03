# monitor/gitops/actions/execute_rollback_action.py
import asyncio
import html
import logging
import os
from pathlib import Path
from typing import Any, Awaitable, Callable, Optional

from monitor.database_backup import restore_db_snapshot
from monitor.gitops.services.update_lock_service import UpdateLockService
from monitor.gitops.services.git_client import GitClient
from monitor.gitops.services.systemd_control import SystemdController

logger = logging.getLogger("monitor.gitops.rollback")

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent
WEB_DIR = BASE_DIR / "web_portal"

class ExecuteRollbackAction:
    """Acción de rollback manual que revierte código Git y restaura base de datos MariaDB."""

    @staticmethod
    async def execute(
        bot_instance: Any = None,
        target_commit: Optional[str] = None,
        backup_path: Optional[str] = None,
        auto_restart: bool = False,
        restart_delay: float = 6.0,
        progress_callback: Optional[Callable[[str], Awaitable[None]]] = None,
    ) -> str:
        """Ejecuta la acción de negocio o tarea programada de forma asíncrona.

        Args:
            bot_instance: Parámetro operativo de entrada.
            target_commit: Parámetro operativo de entrada.
            backup_path: Parámetro operativo de entrada.
            auto_restart: Parámetro operativo de entrada.
            restart_delay: Parámetro operativo de entrada.
            progress_callback: Parámetro operativo de entrada.
        """
        logs = []
        logs.append("🔄 <b>Iniciando procedimiento manual de ROLLBACK...</b>")

        async def report_progress(msg: str):
            """Notifica el progreso de la acción hacia los canales de telemetría.

            Args:
                msg: Parámetro operativo de entrada.
            """
            if progress_callback:
                try:
                    await progress_callback(msg)
                except Exception as e_prog:
                    logger.debug(f"Aviso actualizando progreso rollback: {e_prog}")

        await report_progress("⏳ <b>[1/3] Revirtiendo repositorio Git a commit anterior...</b>")

        target = target_commit or "HEAD@{1}"
        logs.append(f"📦 <i>Revertiendo repositorio Git a: <code>{html.escape(target)}</code>...</i>")

        rc_git, _, err_git = await GitClient.run_command(["reset", "--hard", target])
        if rc_git != 0:
            logs.append(f"❌ <b>Fallo al revertir código en Git:</b>\n<pre>{html.escape(err_git)}</pre>")
            return "\n\n".join(logs)

        # Restaurar base de datos
        await report_progress("⏳ <b>[2/3] Restaurando snapshot de base de datos MariaDB...</b>")
        ok_db, msg_db = restore_db_snapshot(backup_path)
        if not ok_db:
            logs.append(f"⚠️ <b>Aviso en base de datos:</b> {html.escape(msg_db)}")
        else:
            logs.append(f"💾 <i>Base de datos: {html.escape(msg_db)}</i>")

        # Sincronizar Portal Web restaurado
        await report_progress("⏳ <b>[3/3] Sincronizando portal web y liberando Circuit Breaker...</b>")
        if WEB_DIR.exists():
            sudo_p = ["sudo"] if os.geteuid() != 0 else []
            await (await asyncio.create_subprocess_exec(*(sudo_p + ["rsync", "-a", f"{BASE_DIR}/web_portal/", f"{WEB_DIR}/"]))).communicate()
            await (await asyncio.create_subprocess_exec(*(sudo_p + ["chown", "-R", "www-data:www-data", str(WEB_DIR)]))).communicate()
            for acmd in ["view:clear", "config:clear", "route:clear"]:
                await (await asyncio.create_subprocess_exec(*(sudo_p + ["php", f"{WEB_DIR}/artisan", acmd]), cwd=str(WEB_DIR))).communicate()
            await (await asyncio.create_subprocess_exec(*(sudo_p + ["systemctl", "reload", "apache2"]))).communicate()
            logs.append("🌐 <i>Portal web sincronizado y cachés purgadas.</i>")

        # Liberar Circuit Breaker
        UpdateLockService.clear_lock()
        logs.append("🔓 <i>Circuit Breaker liberado.</i>")

        _, cur_h, _ = await GitClient.run_command(["rev-parse", "--short", "HEAD"])
        _, cur_m, _ = await GitClient.run_command(["log", "-1", "--format=%s", "HEAD"])

        logs.append(f"✅ <b>Rollback completado exitosamente a la versión</b> <code>{cur_h}</code> (<i>{html.escape(cur_m)}</i>).")
        logs.append("⚡ <i>Reiniciando servicio del bot en segundo plano...</i>")

        if auto_restart:
            asyncio.create_task(SystemdController.restart_service_delayed(delay_seconds=restart_delay))
        return "\n\n".join(logs)
