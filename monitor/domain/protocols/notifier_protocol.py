"""
# ==============================================================================
# 📢 CONTRATO DE DESPACHO DE NOTIFICACIONES: notifier_protocol.py
# Inversión de Dependencias (D) para canales de notificación (Telegram / Bots).
# ==============================================================================
"""

from __future__ import annotations

from typing import Protocol, Union, runtime_checkable
from monitor.application.dtos.notification_dto import AlertNotificationDTO


@runtime_checkable
class TelegramNotifierProtocol(Protocol):
    """Protocolo formal para el despacho de alertas y mensajes hacia Telegram."""

    async def send_message(self, notification: AlertNotificationDTO) -> bool:
        """
        Despacha un mensaje de texto respetando los filtros de seguridad de la Regla de Oro #1.
        """
        ...

    async def send_photo(
        self,
        recipient: Union[int, str],
        photo_path: str,
        caption: str = "",
        parse_mode: str = "HTML",
    ) -> bool:
        """
        Despacha una imagen con pie de foto al destinatario especificado.
        """
        ...
