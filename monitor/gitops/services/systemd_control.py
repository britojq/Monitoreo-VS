# monitor/gitops/services/systemd_control.py
import asyncio
import logging
import os
from typing import Dict

logger = logging.getLogger("monitor.gitops.systemd")

class SystemdController:
    """Controlador desacoplado para servicios y demonios systemd."""

    @staticmethod
    async def restart_service_delayed(delay_seconds: float = 5.0) -> None:
        """Espera delay_seconds para asegurar el envío del mensaje y reinicia los servicios del bot."""
        await asyncio.sleep(delay_seconds)
        try:
            # F-04: Ejecución estructurada mediante argv-list sin subshell shell=True ni os.system
            cmd_args = (["sudo"] if os.geteuid() != 0 else []) + [
                "systemd-run",
                "--no-block",
                "systemctl",
                "restart",
                "tg-admin-bot.service",
            ]
            proc = await asyncio.create_subprocess_exec(
                *cmd_args,
                stdout=asyncio.subprocess.DEVNULL,
                stderr=asyncio.subprocess.DEVNULL,
            )
            await proc.wait()
            logger.info("Comando de reinicio desacoplado de tg-admin-bot.service despachado con éxito.")
        except Exception as e:
            logger.error(f"Error al reiniciar tg-admin-bot.service: {e}")

    @staticmethod
    async def get_system_services_status() -> Dict[str, str]:
        """Consulta el estado en vivo de los demonios de systemd."""
        services: Dict[str, str] = {}
        for svc in ["tg-admin-bot", "apache2", "mariadb", "php8.4-fpm"]:
            try:
                proc = await asyncio.create_subprocess_exec(
                    "systemctl", "is-active", svc,
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                out, _ = await proc.communicate()
                state = out.decode("utf-8", errors="ignore").strip() or "unknown"
                services[svc] = state
            except Exception:
                services[svc] = "unknown"
        return services
