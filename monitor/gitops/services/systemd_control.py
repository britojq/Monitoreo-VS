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
            sudo_prefix = "sudo " if os.geteuid() != 0 else ""
            cmd = f"{sudo_prefix}systemd-run --no-block systemctl restart tg-admin-bot.service || {sudo_prefix}nohup sh -c 'sleep 1 && systemctl restart tg-admin-bot.service' >/dev/null 2>&1 &"
            os.system(cmd)
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
