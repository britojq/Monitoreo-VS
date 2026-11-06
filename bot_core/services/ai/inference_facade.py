"""
# ==============================================================================
# 🧠 FACHADA DE INFERENCIA Y DIP: bot_core/services/ai/inference_facade.py
# Instancias compartidas y fachada de comunicación con el motor de inferencia.
# ==============================================================================
"""
from __future__ import annotations

import logging
from typing import Any, Dict, List, Optional, Tuple

from bot_core.contracts.ai_contracts import InferenceRequestDTO
from bot_core.services.ai_group_config_service import MariaDbAiGroupConfigService
from bot_core.services.ai_guardrails_service import AiGuardrailsService
from bot_core.services.ai_inference_client import AiInferenceClient
from bot_core.services.ai_telemetry_provider import MariaDbAiTelemetryProvider

logger = logging.getLogger("bot.services.ai.inference_facade")

# Instancias compartidas de servicios especializados (DIP)
telemetry_provider = MariaDbAiTelemetryProvider()
guardrails_service = AiGuardrailsService(telemetry_provider=telemetry_provider)
inference_client = AiInferenceClient(guardrails_service=guardrails_service)
group_config_service = MariaDbAiGroupConfigService()


def is_telegram_group_ai_enabled() -> bool:
    """Consulta si la IA está activa en el grupo corporativo."""
    return group_config_service.is_group_ai_enabled()


def set_telegram_group_ai_enabled(enabled: bool, updated_by: str = "Owner [Telegram]") -> bool:
    """Activa o desactiva la IA en el grupo corporativo en MariaDB SSOT."""
    return group_config_service.set_group_ai_enabled(enabled, updated_by)


def load_system_prompt(config: Optional[Dict[str, Any]] = None) -> str:
    """Carga el System Prompt corporativo."""
    return inference_client.load_system_prompt()


def is_negative_or_refusal(text: str) -> bool:
    """Detecta si la respuesta contiene patrones de negativa o rechazo."""
    return guardrails_service.is_negative_or_refusal(text)


def build_ollama_messages(
    user_text: str,
    history: List[Dict[str, str]],
    active_entity: Optional[Dict[str, Any]] = None,
) -> Tuple[List[Dict[str, str]], Optional[Dict[str, Any]]]:
    """Construye el payload de mensajes para inferencia."""
    return inference_client.build_messages(user_text, history, active_entity)


def validate_and_sanitize_response(
    content: str,
    user_text: str,
    dynamic_context: Optional[str] = None,
    is_group_context: bool = False,
) -> str:
    """Aplica guardrails y validaciones de veracidad fáctica."""
    return guardrails_service.validate_and_sanitize(content, user_text, dynamic_context, is_group_context)


def get_live_outage_summary() -> Tuple[int, int, List[str]]:
    """Obtiene el resumen de caídas activas en tiempo real."""
    dto = telemetry_provider.get_live_outage_summary()
    return dto.serv_total, dto.serv_up, dto.down_services


def get_live_inventory_summary() -> Tuple[int, int]:
    """Obtiene el resumen de inventario ITAM en tiempo real."""
    dto = telemetry_provider.get_live_inventory_summary()
    return dto.total_assets, dto.with_serial


def get_live_actas_summary() -> Tuple[int, int, List[str]]:
    """Obtiene el resumen de actas de entrega/salida en tiempo real."""
    dto = telemetry_provider.get_live_actas_summary()
    return dto.acta_count, dto.total_assets, dto.items


def ask_ollama_sync(
    user_text: str,
    history: List[Dict[str, str]],
    active_entity: Optional[Dict[str, Any]] = None,
    is_group_context: bool = False,
) -> Tuple[str, Optional[Dict[str, Any]]]:
    """Inferencia síncrona con el Asistente Corporativo."""
    req = InferenceRequestDTO(
        user_text=user_text,
        history=history,
        user_name="Grupo Corporativo" if is_group_context else "Administrador Telegram",
        user_role="Grupo Corporativo" if is_group_context else "Administrador",
        is_group_context=is_group_context,
        active_entity=active_entity,
    )
    res = inference_client.ask_sync(req)
    return res.content, res.updated_entity


async def ask_ollama_async(
    user_text: str,
    history: List[Dict[str, str]],
    active_entity: Optional[Dict[str, Any]] = None,
    is_group_context: bool = False,
) -> Tuple[str, Optional[Dict[str, Any]]]:
    """Inferencia asíncrona con el Asistente Corporativo."""
    req = InferenceRequestDTO(
        user_text=user_text,
        history=history,
        user_name="Grupo Corporativo" if is_group_context else "Administrador Telegram",
        user_role="Grupo Corporativo" if is_group_context else "Administrador",
        is_group_context=is_group_context,
        active_entity=active_entity,
    )
    res = await inference_client.ask_async(req)
    return res.content, res.updated_entity
