"""
# ==============================================================================
# 📢 ADAPTADOR DE NOTIFICACIÓN TELEGRAM: telegram_notifier.py
# Implementación de TelegramNotifierProtocol bajo Clean Architecture.
# Cumple estrictamente con la Regla de Oro #1 (Filtro absoluto a grupos).
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Optional, Union

from monitor.application.dtos.notification_dto import AlertNotificationDTO
from monitor.domain.protocols.notifier_protocol import TelegramNotifierProtocol
from monitor.telegram_dispatcher import TelegramDispatcher

logger = logging.getLogger("infrastructure.telegram.notifier")


class TelegramNotifier(TelegramNotifierProtocol):
    """Adaptador de infraestructura para despacho de notificaciones a Telegram."""

    def __init__(self, dispatcher: Optional[TelegramDispatcher] = None) -> None:
        self._dispatcher = dispatcher or TelegramDispatcher()

    async def send_message(self, notification: AlertNotificationDTO) -> bool:
        """
        Envía un mensaje a Telegram validando la Regla de Oro #1:
        Los mensajes de seguridad NUNCA se despachan a grupos (IDs negativos).
        """
        if not notification.is_safe_for_groups():
            logger.warning(
                f"🚨 REGLA DE ORO #1: Alerta de seguridad bloqueada hacia chat grupal ({notification.recipient})."
            )
            return False

        ok, msg = await self._dispatcher.send_text(
            chat_id=notification.recipient,
            text=notification.message,
            parse_mode=notification.parse_mode,
        )
        if not ok:
            logger.error(f"Error enviando mensaje a {notification.recipient}: {msg}")
        return ok

    async def send_photo(
        self,
        recipient: Union[int, str],
        photo_path: str,
        caption: str = "",
        parse_mode: str = "HTML",
    ) -> bool:
        """Envía una foto con pie de foto al destinatario indicado."""
        ok, msg = await self._dispatcher.send_photo(
            chat_id=recipient,
            photo_path=photo_path,
            caption=caption,
            parse_mode=parse_mode,
        )
        if not ok:
            logger.error(f"Error enviando foto a {recipient}: {msg}")
        return ok
