# monitor/gitops/services/systemd_control.py
import asyncio
import json
import logging
import os
from typing import Dict, Optional

logger = logging.getLogger("monitor.gitops.systemd")


class SystemdController:
    """Controlador desacoplado y auditable para servicios y demonios systemd."""

    @staticmethod
    def _record_lifecycle_audit(
        action: str,
        service: str,
        actor_id: Optional[int] = None,
        reason: str = "",
    ) -> None:
        """Asienta un evento inmutable en MariaDB audit_logs antes de despachar comandos a systemd."""
        try:
            from monitor.infrastructure.monitor_db import get_db_connection

            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    sql = """
                        INSERT INTO audit_logs 
                        (user_id, user_name, user_email, user_role, event, module, entity_name, entity_label, description, new_values, ip_address, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    """
                    actor_str = str(actor_id) if actor_id else "system"
                    payload = {
                        "action": action,
                        "service": service,
                        "actor_id": actor_id,
                        "reason": reason,
                    }
                    cur.execute(
                        sql,
                        (
                            actor_id if (actor_id and actor_id < 2147483647) else None,
                            f"telegram_user_{actor_str}",
                            f"{actor_str}@telegram.local",
                            "admin" if actor_id else "system",
                            f"service_{action}",
                            "systemd",
                            service,
                            f"Systemd Service: {service}",
                            f"Acción de ciclo de vida [{action}] ejecutada sobre {service}. Razón: {reason or 'no especificada'}",
                            json.dumps(payload, ensure_ascii=False),
                            "127.0.0.1",
                        ),
                    )
                conn.commit()
            finally:
                conn.close()
        except Exception as e:
            logger.warning(f"No se pudo registrar evento de systemd en MariaDB audit_logs: {e}")

    @classmethod
    async def restart_service_delayed(
        cls,
        delay_seconds: float = 5.0,
        service: str = "tg-admin-bot.service",
        actor_id: Optional[int] = None,
        reason: str = "",
    ) -> bool:
        """Espera delay_seconds para asegurar el envío del mensaje y reinicia los servicios del bot."""
        if delay_seconds > 0:
            await asyncio.sleep(delay_seconds)
        cls._record_lifecycle_audit("restart", service, actor_id=actor_id, reason=reason)
        try:
            # F-04 / SEC-002: Ejecución estructurada mediante argv-list sin subshell shell=True ni os.system
            cmd_args = (["sudo"] if os.geteuid() != 0 else []) + [
                "systemd-run",
                "--no-block",
                "systemctl",
                "restart",
                service,
            ]
            proc = await asyncio.create_subprocess_exec(
                *cmd_args,
                stdout=asyncio.subprocess.DEVNULL,
                stderr=asyncio.subprocess.DEVNULL,
            )
            await proc.wait()
            logger.info(f"Comando de reinicio desacoplado de {service} despachado con éxito.")
            return True
        except Exception as e:
            logger.error(f"Error al reiniciar {service}: {e}")
            return False

    @classmethod
    async def stop_service_delayed(
        cls,
        delay_seconds: float = 1.5,
        service: str = "tg-admin-bot.service",
        actor_id: Optional[int] = None,
        reason: str = "",
    ) -> bool:
        """Espera delay_seconds para asegurar el envío del mensaje y detiene el servicio."""
        if delay_seconds > 0:
            await asyncio.sleep(delay_seconds)
        cls._record_lifecycle_audit("stop", service, actor_id=actor_id, reason=reason)
        try:
            # SEC-006: Ejecución estructurada con lista argv y trazabilidad forense
            cmd_args = (["sudo"] if os.geteuid() != 0 else []) + [
                "systemctl",
                "stop",
                service,
            ]
            proc = await asyncio.create_subprocess_exec(
                *cmd_args,
                stdout=asyncio.subprocess.DEVNULL,
                stderr=asyncio.subprocess.DEVNULL,
            )
            await proc.wait()
            logger.info(f"Comando de parada desacoplada de {service} despachado con éxito.")
            return True
        except Exception as e:
            logger.error(f"Error al detener {service}: {e}")
            return False

    async def restart_service(
        self,
        service: str = "tg-admin-bot.service",
        *,
        delay_seconds: float = 0.0,
        actor_id: Optional[int] = None,
        reason: str = "",
    ) -> bool:
        """Implementación del contrato ServiceLifecyclePort para reinicio."""
        return await self.restart_service_delayed(
            delay_seconds=delay_seconds, service=service, actor_id=actor_id, reason=reason
        )

    async def stop_service(
        self,
        service: str = "tg-admin-bot.service",
        *,
        delay_seconds: float = 0.0,
        actor_id: Optional[int] = None,
        reason: str = "",
    ) -> bool:
        """Implementación del contrato ServiceLifecyclePort para parada."""
        return await self.stop_service_delayed(
            delay_seconds=delay_seconds, service=service, actor_id=actor_id, reason=reason
        )

    @staticmethod
    async def get_system_services_status() -> Dict[str, str]:
        """Consulta el estado en vivo de los demonios de systemd."""
        services: Dict[str, str] = {}
        for svc in ["tg-admin-bot", "apache2", "mariadb", "php8.4-fpm"]:
            try:
                proc = await asyncio.create_subprocess_exec(
                    "systemctl", "is-active", svc,
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE,
                )
                out, _ = await proc.communicate()
                state = out.decode("utf-8", errors="ignore").strip() or "unknown"
                services[svc] = state
            except Exception:
                services[svc] = "unknown"
        return services
