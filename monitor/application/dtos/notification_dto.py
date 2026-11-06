"""
# ==============================================================================
# 📢 DTO DE NOTIFICACIÓN Y DESPACHO: notification_dto.py
# Transporte inmutable para mensajes de alertas hacia Telegram y canales de salida.
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Optional, Union


@dataclass(frozen=True)
class AlertNotificationDTO:
    """Notificación inmutable destinada a un canal o chat de Telegram."""
    recipient: Union[int, str]
    message: str
    parse_mode: str = "HTML"
    disable_web_page_preview: bool = True
    is_security_alert: bool = False
    priority: str = "NORMAL"  # LOW, NORMAL, HIGH, CRITICAL

    def is_safe_for_groups(self) -> bool:
        """
        Asegura el cumplimiento estricto de la Regla de Oro #1:
        Los mensajes de seguridad NUNCA se permiten hacia chats grupales (IDs negativos).
        """
        if self.is_security_alert:
            try:
                chat_id_num = int(self.recipient)
                if chat_id_num < 0:
                    return False
            except (ValueError, TypeError):
                pass
        return True
