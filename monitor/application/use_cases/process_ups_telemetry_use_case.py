"""
# ==============================================================================
# 🔋 CASO DE USO: PROCESAMIENTO DE TELEMETRÍA Y ALERTAS UPS
# process_ups_telemetry_use_case.py
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Optional

from monitor.application.dtos.notification_dto import AlertNotificationDTO
from monitor.domain.entities.ups import UpsStatusEntity
from monitor.domain.protocols.notifier_protocol import TelegramNotifierProtocol
from monitor.domain.services.ups_evaluator import (
    calculate_battery_percentage,
    format_duration,
    is_spurious_transient,
)

logger = logging.getLogger("application.use_case.process_ups")


class ProcessUpsTelemetryUseCase:
    """Caso de uso orquestador para procesar telemetría y eventos de la UPS."""

    def __init__(self, notifier: TelegramNotifierProtocol) -> None:
        self._notifier = notifier

    async def notify_outage(
        self,
        recipient: int | str,
        ups: UpsStatusEntity,
        duration_sec: float,
    ) -> bool:
        """Notifica un corte eléctrico confirmado si supera el umbral de transitorio."""
        if is_spurious_transient(duration_sec):
            logger.info(f"Micro-fluctuación transitoria ({duration_sec:.1f}s) suprimida.")
            return False

        dur_str = format_duration(duration_sec)
        msg = (
            "⚡ <b>ALERTA CRÍTICA: CORTE DE ENERGÍA DETECTADO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🔋 <b>Estado UPS:</b> Operando en Batería (OB)\n"
            f"📊 <b>Nivel Batería:</b> {ups.battery_percentage}%\n"
            f"🔌 <b>Voltaje Entrada:</b> {ups.input_voltage}V (Cero/Falla)\n"
            f"💡 <b>Voltaje Salida:</b> {ups.output_voltage}V (Inversor Activo)\n"
            f"⏱️ <b>Tiempo en Batería:</b> {dur_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
        notification = AlertNotificationDTO(
            recipient=recipient,
            message=msg,
            parse_mode="HTML",
            is_security_alert=False,
            priority="CRITICAL",
        )
        return await self._notifier.send_message(notification)

    async def notify_recovery(
        self,
        recipient: int | str,
        ups: UpsStatusEntity,
        total_outage_sec: float,
    ) -> bool:
        """Notifica el restablecimiento del suministro eléctrico comercial."""
        dur_str = format_duration(total_outage_sec)
        msg = (
            "✅ <b>RESTABLECIMIENTO: ENERGÍA COMERCIAL RESTAURADA</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🔌 <b>Estado UPS:</b> En Línea / Normal (OL)\n"
            f"⚡ <b>Voltaje Entrada:</b> {ups.input_voltage}V (Estable)\n"
            f"💡 <b>Voltaje Salida:</b> {ups.output_voltage}V\n"
            f"🔋 <b>Nivel Batería:</b> {ups.battery_percentage}%\n"
            f"⏱️ <b>Duración del Corte:</b> {dur_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
        notification = AlertNotificationDTO(
            recipient=recipient,
            message=msg,
            parse_mode="HTML",
            is_security_alert=False,
            priority="NORMAL",
        )
        return await self._notifier.send_message(notification)
