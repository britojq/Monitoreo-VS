"""
# ==============================================================================
# 🧠 FACHADA DE CHAT CONVERSACIONAL: ai_chat_service.py
# Fachada ultraligera compatible que delega al paquete bot_core.services.ai.
# Ubicación: /opt/sonar/bot_core/services/ai_chat_service.py
# ==============================================================================
"""
from __future__ import annotations

import sys
from typing import Any, Dict, List, Optional, Tuple

from telegram import Update
from telegram.ext import Application, ContextTypes

from bot_core.services.ai import (
    append_to_history,
    ask_ollama_async,
    cmd_ia_grupo,
    handle_chat_message,
    handle_chat_query_direct,
    handle_dynamic_command,
    load_commands_data,
    load_system_prompt,
    register_ai_routes,
    reset_chat,
    _animate_waiting_message,
    _ask_ollama_sync,
    _build_ollama_messages,
    _execute_chat_pipeline,
    _get_active_config,
    _get_commands_dict,
    _get_messages_dict,
    _is_negative_or_refusal,
    _reply,
    _validate_and_sanitize_response,
)
from bot_core.services.ai.inference_facade import (
    group_config_service as _group_config_service,
    guardrails_service as _guardrails_service,
    inference_client as _inference_client,
    telemetry_provider as _telemetry_provider,
)


def is_telegram_group_ai_enabled() -> bool:
    """Consulta si la IA en grupo está activa respetando posibles mocks en tests."""
    mod = sys.modules.get(__name__)
    svc = getattr(mod, "_group_config_service", _group_config_service)
    return svc.is_group_ai_enabled()


def set_telegram_group_ai_enabled(enabled: bool, updated_by: str = "Owner [Telegram]") -> bool:
    """Conmuta la IA en grupo respetando posibles mocks en tests."""
    mod = sys.modules.get(__name__)
    svc = getattr(mod, "_group_config_service", _group_config_service)
    return svc.set_group_ai_enabled(enabled, updated_by)


def _get_live_outage_summary() -> Tuple[int, int, List[str]]:
    """Resumen de caídas en vivo respetando posibles mocks en tests."""
    mod = sys.modules.get(__name__)
    provider = getattr(mod, "_telemetry_provider", _telemetry_provider)
    dto = provider.get_live_outage_summary()
    return dto.serv_total, dto.serv_up, dto.down_services


def _get_live_inventory_summary() -> Tuple[int, int]:
    """Resumen de inventario en vivo respetando posibles mocks en tests."""
    mod = sys.modules.get(__name__)
    provider = getattr(mod, "_telemetry_provider", _telemetry_provider)
    dto = provider.get_live_inventory_summary()
    return dto.total_assets, dto.with_serial


def _get_live_actas_summary() -> Tuple[int, int, List[str]]:
    """Resumen de actas en vivo respetando posibles mocks en tests."""
    mod = sys.modules.get(__name__)
    provider = getattr(mod, "_telemetry_provider", _telemetry_provider)
    dto = provider.get_live_actas_summary()
    return dto.acta_count, dto.total_assets, dto.items


__all__ = [
    "cmd_ia_grupo",
    "reset_chat",
    "handle_chat_message",
    "handle_chat_query_direct",
    "handle_dynamic_command",
    "register_ai_routes",
    "load_commands_data",
    "append_to_history",
    "is_telegram_group_ai_enabled",
    "set_telegram_group_ai_enabled",
    "load_system_prompt",
    "ask_ollama_async",
    "_telemetry_provider",
    "_guardrails_service",
    "_inference_client",
    "_group_config_service",
    "_get_active_config",
    "_get_commands_dict",
    "_get_messages_dict",
    "_reply",
    "_execute_chat_pipeline",
    "_ask_ollama_sync",
    "_build_ollama_messages",
    "_validate_and_sanitize_response",
    "_get_live_outage_summary",
    "_get_live_inventory_summary",
    "_get_live_actas_summary",
    "_is_negative_or_refusal",
    "_animate_waiting_message",
]
