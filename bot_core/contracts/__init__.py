"""Contratos y protocolos formales para el ecosistema del Bot de Telegram."""
from bot_core.contracts.ai_contracts import (
    ActasSummaryDTO,
    AiGroupConfigProtocol,
    AiGuardrailsServiceProtocol,
    AiInferenceClientProtocol,
    AiTelemetryProviderProtocol,
    InferenceRequestDTO,
    InferenceResponseDTO,
    InventorySummaryDTO,
    OutageSummaryDTO,
)

__all__ = [
    "OutageSummaryDTO",
    "InventorySummaryDTO",
    "ActasSummaryDTO",
    "InferenceRequestDTO",
    "InferenceResponseDTO",
    "AiTelemetryProviderProtocol",
    "AiGuardrailsServiceProtocol",
    "AiInferenceClientProtocol",
    "AiGroupConfigProtocol",
]
