"""
Contrato formal para el ciclo de vida de demonios y servicios (DIP - Dependency Inversion Principle).
Aísla la capa de presentación de Telegram de la ejecución privilegiada en el sistema operativo.
"""
from typing import Optional, Protocol, runtime_checkable


@runtime_checkable
class ServiceLifecyclePort(Protocol):
    """Protocolo desacoplado para gestión de ciclo de vida de servicios."""

    async def restart_service(
        self,
        service: str = "tg-admin-bot.service",
        *,
        delay_seconds: float = 0.0,
        actor_id: Optional[int] = None,
        reason: str = "",
    ) -> bool:
        """Reinicia un servicio de forma asíncrona y auditable."""
        ...

    async def stop_service(
        self,
        service: str = "tg-admin-bot.service",
        *,
        delay_seconds: float = 0.0,
        actor_id: Optional[int] = None,
        reason: str = "",
    ) -> bool:
        """Detiene un servicio de forma asíncrona y auditable."""
        ...
