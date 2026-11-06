"""
Contratos formales para el subsistema de Terminal Shield.
Principio DIP: Los handlers dependen de abstracciones y protocolos.
"""

from __future__ import annotations
from typing import Protocol, Any, Tuple
from telegram import Update
from telegram.ext import ContextTypes
from bot_core.handlers.shield.dtos import ShieldCallbackPayload


class ShieldActionProtocol(Protocol):
    """Protocolo formal para una acción ejecutable de mitigación de Terminal Shield."""

    async def execute(self, payload: ShieldCallbackPayload, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        """Ejecuta la acción de mitigación de seguridad."""
        ...
