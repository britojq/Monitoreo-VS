"""
==============================================================================
📜 CONTRATOS Y DTOs DE INTELIGENCIA ARTIFICIAL: ai_contracts.py
Principio de Inversión de Dependencias (D) y Segregación de Interfaces (I).
Define las interfaces formales (Protocol) y los DTOs inmutables para IA.
==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional, Protocol, runtime_checkable


# ============================================================================
# DTOs INMUTABLES (TRANSFERENCIA DE DATOS ENTRE CAPAS)
# ============================================================================

@dataclass(frozen=True)
class OutageSummaryDTO:
    """Resumen cuantitativo de servicios corporativos caídos y operativos."""
    serv_total: int
    serv_up: int
    down_services: List[str] = field(default_factory=list)


@dataclass(frozen=True)
class InventorySummaryDTO:
    """Resumen cuantitativo de activos en MariaDB SSOT."""
    total_assets: int
    with_serial: int


@dataclass(frozen=True)
class ActasSummaryDTO:
    """Resumen cuantitativo de actas de asignación escaneadas en PDF."""
    acta_count: int
    total_assets: int
    items: List[str] = field(default_factory=list)


@dataclass(frozen=True)
class InferenceRequestDTO:
    """Parámetros de consulta hacia el motor de inferencia local."""
    user_text: str
    history: List[Dict[str, str]] = field(default_factory=list)
    user_name: str = "Administrador Telegram"
    user_role: str = "Administrador"
    is_group_context: bool = False
    active_entity: Optional[Dict[str, Any]] = None


@dataclass(frozen=True)
class InferenceResponseDTO:
    """Resultado formal de inferencia procesada."""
    success: bool
    content: str
    updated_entity: Optional[Dict[str, Any]] = None
    tool_executed: Optional[str] = None
    error: Optional[str] = None


# ============================================================================
# CONTRATOS FORMALES (PROTOCOLS)
# ============================================================================

@runtime_checkable
class AiTelemetryProviderProtocol(Protocol):
    """Contrato formal para suministro de telemetría e histórico desde MariaDB SSOT."""

    def get_live_outage_summary(self) -> OutageSummaryDTO:
        """Obtiene el consolidado en vivo de servicios caídos."""
        ...

    def get_live_inventory_summary(self) -> InventorySummaryDTO:
        """Consulta en tiempo real el total de activos y equipos con serial verificado."""
        ...

    def get_live_actas_summary(self) -> ActasSummaryDTO:
        """Consulta en tiempo real las actas físicas escaneadas en PDF."""
        ...

    def get_subnets_summary(self) -> List[str]:
        """Consulta los segmentos de red y subredes operativas."""
        ...


@runtime_checkable
class AiGuardrailsServiceProtocol(Protocol):
    """Contrato formal para filtrado, sanitización y censura de información."""

    def is_negative_or_refusal(self, text: str) -> bool:
        """Determina si una respuesta contiene declinaciones erróneas o negativas."""
        ...

    def validate_and_sanitize(
        self,
        content: str,
        user_text: str,
        dynamic_context: Optional[str] = None,
        is_group_context: bool = False
    ) -> str:
        """Aplica las reglas de salida y blindaje de confidencialidad."""
        ...


@runtime_checkable
class AiInferenceClientProtocol(Protocol):
    """Contrato formal para despacho de inferencias con backend MCP y fallback."""

    def ask_sync(self, request: InferenceRequestDTO) -> InferenceResponseDTO:
        """Ejecuta la inferencia de manera sincrónica."""
        ...

    async def ask_async(self, request: InferenceRequestDTO) -> InferenceResponseDTO:
        """Ejecuta la inferencia de manera asincrónica."""
        ...


@runtime_checkable
class AiGroupConfigProtocol(Protocol):
    """Contrato formal para gestión del estado de IA en grupos corporativos."""

    def is_group_ai_enabled(self) -> bool:
        """Consulta si la Línea 2 de IA está habilitada en MariaDB SSOT."""
        ...

    def set_group_ai_enabled(self, enabled: bool, updated_by: str = "Owner [Telegram]") -> bool:
        """Persiste la activación/desactivación de la IA en MariaDB SSOT."""
        ...
