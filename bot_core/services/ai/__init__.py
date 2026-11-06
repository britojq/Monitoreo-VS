"""
# ==============================================================================
# 🧠 PAQUETE MODULAR DE ASISTENTE IA: __init__.py
# Re-exporta controladores, fachada de inferencia, pipeline y enrutador.
# Ubicación: /opt/sonar/bot_core/services/ai/__init__.py
# ==============================================================================
"""
from bot_core.services.ai.chat_handlers import (
    cmd_ia_grupo,
    handle_chat_message,
    handle_chat_query_direct,
    reset_chat,
)
from bot_core.services.ai.chat_pipeline import execute_chat_pipeline as _execute_chat_pipeline
from bot_core.services.ai.dynamic_command_handler import handle_dynamic_command
from bot_core.services.ai.helpers import (
    append_to_history,
    get_active_config as _get_active_config,
    get_commands_dict as _get_commands_dict,
    get_messages_dict as _get_messages_dict,
    load_commands_data,
    reply as _reply,
)
from bot_core.services.ai.inference_facade import (
    ask_ollama_async,
    ask_ollama_sync as _ask_ollama_sync,
    build_ollama_messages as _build_ollama_messages,
    get_live_actas_summary as _get_live_actas_summary,
    get_live_inventory_summary as _get_live_inventory_summary,
    get_live_outage_summary as _get_live_outage_summary,
    is_negative_or_refusal as _is_negative_or_refusal,
    is_telegram_group_ai_enabled,
    load_system_prompt,
    set_telegram_group_ai_enabled,
    validate_and_sanitize_response as _validate_and_sanitize_response,
)
from bot_core.services.ai.router import register_ai_routes
from bot_core.services.ai.ui_animator import animate_waiting_message as _animate_waiting_message

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
