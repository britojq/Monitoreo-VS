# monitor/gitops/actions/auto_update_worker.py
import asyncio
import html
import logging
from typing import Any, Callable, Optional

from monitor.shield.secure_core import IMMUTABLE_OWNER_ID, trip_deadman_switch
from monitor.gitops.actions.check_updates_action import CheckUpdatesAction
from monitor.gitops.actions.execute_update_action import ExecuteUpdateAction
from monitor.gitops.services.git_client import GitClient
from monitor.gitops.services.systemd_control import SystemdController

logger = logging.getLogger("monitor.gitops.worker")

class AutoUpdateWorker:
    """Worker periódico en segundo plano que supervisa y aplica actualizaciones de Git."""

    @staticmethod
    async def run(
        bot_instance: Any = None,
        get_owner_id_func: Optional[Callable[[], int]] = None,
        get_config_func: Optional[Callable[[], dict]] = None,
    ) -> None:
        """Ejecuta la función run conforme a la especificación técnica.

        Args:
            bot_instance: Parámetro operativo de entrada.
            get_owner_id_func: Parámetro operativo de entrada.
            get_config_func: Parámetro operativo de entrada.
        """
        logger.info("Servicio inmutable de auto-actualización Git iniciado en segundo plano (Revisión cada 48h).")
        await asyncio.sleep(180)

        consecutive_git_failures = 0
        MAX_CONSECUTIVE_GIT_FAILURES = 3

        while True:
            try:
                config = get_config_func() if get_config_func else {}
                interval_hours = int(config.get("auto_update_interval_hours", 48))
                interval_seconds = max(300, interval_hours * 3600)

                logger.info("Ejecutando comprobación autónoma periódica de actualizaciones en GitHub...")
                check_res = await CheckUpdatesAction.check_updates(bot_instance=bot_instance)

                if not check_res.get("success"):
                    if not check_res.get("is_locked"):
                        consecutive_git_failures += 1
                        err_msg = check_res.get("error", "Error de red con GitHub")
                        logger.error(f"Auto-actualización: Fallo #{consecutive_git_failures} en comprobación Git: {err_msg}")
                        await GitClient.notify_owner_failure(
                            bot_instance,
                            err_msg,
                            operation=f"comprobación periódica (intento {consecutive_git_failures}/{MAX_CONSECUTIVE_GIT_FAILURES})"
                        )
                        if consecutive_git_failures >= MAX_CONSECUTIVE_GIT_FAILURES:
                            logger.critical("🔒 PROTOCOLO DE CONTENCIÓN: 3 fallos consecutivos con Git.")
                            trip_deadman_switch(reason="3 fallos consecutivos de sincronización con Git")

                elif check_res.get("has_update"):
                    consecutive_git_failures = 0
                    old_hash = check_res.get("local_hash", "N/A")
                    new_hash = check_res.get("remote_hash", "N/A")
                    commits = check_res.get("commits", [])
                    commits_summary = "\n".join(commits[:6])

                    if bot_instance:
                        alert_text = (
                            "🔄 <b>Actualización Automática Detectada</b>\n\n"
                            f"🏷️ <b>Versión Actual:</b> <code>{old_hash}</code>\n"
                            f"🚀 <b>Nueva Versión:</b> <code>{new_hash}</code>\n"
                            f"🌐 <b>Ruta de Red:</b> <code>{html.escape(check_res.get('route_label', 'Directa'))}</code>\n\n"
                            f"📦 <b>Novedades:</b>\n{commits_summary}\n\n"
                            "⚙️ <i>Aplicando despliegue seguro con respaldo de BD...</i>"
                        )
                        try:
                            await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=alert_text, parse_mode='HTML')
                        except Exception as e:
                            logger.error(f"Error notificando al Owner: {e}")

                    res_text = await ExecuteUpdateAction.execute(bot_instance=bot_instance, auto_restart=False)
                    if bot_instance:
                        if len(res_text) > 4000:
                            res_text = res_text[:3950] + "\n\n<i>[Resumen recortado por límite de Telegram...]</i>"
                        try:
                            await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=res_text, parse_mode='HTML')
                        except Exception as e:
                            logger.error(f"Error enviando resultado: {e}")
                    asyncio.create_task(SystemdController.restart_service_delayed(delay_seconds=4.0))
                else:
                    consecutive_git_failures = 0

                await asyncio.sleep(interval_seconds)

            except asyncio.CancelledError:
                break
            except Exception as e:
                logger.error(f"Error en auto-actualización: {e}", exc_info=True)
                await asyncio.sleep(1800)
