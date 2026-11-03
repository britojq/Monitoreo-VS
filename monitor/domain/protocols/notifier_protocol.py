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
        """Despacha un mensaje de texto respetando los filtros de seguridad de la Regla de Oro #1.

        Args:
            notification: DTO con el texto formateado, nivel de severidad y metadatos de alerta.

        Returns:
            bool: True si el mensaje fue aceptado por la API de Telegram, False en caso de fallo.

        Raises:
            NotificationDispatchException: Si ocurre un error no recuperable de red o autenticación.
        """
        ...

    async def send_photo(
        self,
        recipient: Union[int, str],
        photo_path: str,
        caption: str = "",
        parse_mode: str = "HTML",
    ) -> bool:
        """Despacha una imagen con pie de foto al destinatario especificado.

        Args:
            recipient: ID numérico del chat o identificador de usuario Telegram.
            photo_path: Ruta del sistema de archivos donde reside la imagen a enviar.
            caption: Texto explicativo o pie de foto en formato HTML.
            parse_mode: Modo de parseo de texto ('HTML' o 'Markdown').

        Returns:
            bool: True si la imagen se transmitió exitosamente, False en caso de error.

        Raises:
            NotificationDispatchException: Si el archivo no existe o Telegram rechaza la carga.
        """
        ...
