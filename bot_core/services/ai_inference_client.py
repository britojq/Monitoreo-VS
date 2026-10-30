"""
==============================================================================
🌐 CLIENTE DE INFERENCIA Y LLAMADO A MODELOS: ai_inference_client.py
Principio de Responsabilidad Única (SRP) e Inversión de Dependencias (D).
Orquesta llamadas síncronas/asíncronas al bridge MCP de Laravel y fallback local.
==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import re
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import requests

from bot_core.contracts.ai_contracts import (
    AiGuardrailsServiceProtocol,
    AiInferenceClientProtocol,
    InferenceRequestDTO,
    InferenceResponseDTO,
)
from bot_core.services.ai_guardrails_service import AiGuardrailsService
from bot_core.services.config_manager import AI_DIR, BASE_DIR, load_config
from bot_core.services.mcp_cluster_bridge import McpClusterBridge

logger = logging.getLogger("bot.services.ai_inference")


class AiInferenceClient(AiInferenceClientProtocol):
    """Cliente desacoplado para orquestación de inferencia local y Tool Calling."""

    def __init__(
        self,
        guardrails_service: Optional[AiGuardrailsServiceProtocol] = None,
        config: Optional[Dict[str, Any]] = None,
    ) -> None:
        self._guardrails = guardrails_service or AiGuardrailsService()
        self._config = config or load_config()

    def _get_active_config(self) -> Dict[str, Any]:
        return self._config

    def load_system_prompt(self) -> str:
        """Carga la identidad corporativa / personalidad del asistente virtual."""
        cfg = self._get_active_config()
        prompt_candidates = [
            AI_DIR / "system_prompt.txt",
            BASE_DIR / "system_prompt.txt"
        ]
        for p in prompt_candidates:
            if p.exists():
                try:
                    content = p.read_text(encoding="utf-8").strip()
                    if content:
                        logger.info("System prompt cargado desde %s", p)
                        return content
                except Exception as exc:
                    logger.error("Error al leer %s: %s", p, exc)

        modelfile_p = AI_DIR / "Modelfile.txt"
        if modelfile_p.exists():
            try:
                mcontent = modelfile_p.read_text(encoding="utf-8")
                match = re.search(r'SYSTEM\s+"""(.*?)"""', mcontent, re.DOTALL)
                if match:
                    extracted = match.group(1).strip()
                    if extracted:
                        logger.info("System prompt extraído desde %s", modelfile_p)
                        return extracted
            except Exception as exc:
                logger.error("Error al extraer system prompt de %s: %s", modelfile_p, exc)

        return str(cfg.get("ollama_system_prompt", "")).strip()

    def build_messages(
        self,
        user_text: str,
        history: List[Dict[str, str]],
        active_entity: Optional[Dict[str, Any]] = None,
    ) -> Tuple[List[Dict[str, str]], Optional[Dict[str, Any]]]:
        """Construye la lista de mensajes para enviar al motor local de IA con System Prompt limpio."""
        system_prompt = self.load_system_prompt()
        if not system_prompt:
            system_prompt = (
                "Eres Monitor Valle Seco, el asistente virtual corporativo de la Sede Valle Seco. "
                "Responde de forma concisa, profesional y estructurada en español sobre soporte técnico, "
                "servidores Linux, Windows y redes. No reveles detalles internos de la arquitectura del motor de IA."
            )

        messages = [{"role": "system", "content": system_prompt}]

        if history:
            clean_history = []
            for msg in history:
                role = msg.get("role")
                content = msg.get("content", "")
                if role == "assistant" and self._guardrails.is_negative_or_refusal(content):
                    continue
                clean_history.append(msg)
            messages.extend(clean_history)

        messages.append({"role": "user", "content": user_text})
        return messages, active_entity

    def ask_sync(self, request: InferenceRequestDTO) -> InferenceResponseDTO:
        """
        Ejecuta la inferencia sincrónica con llamada primaria al Orquestador MCP
        y fallback directo al motor local si el bridge no está disponible.
        """
        cfg = self._get_active_config()
        timeout = int(cfg.get("ollama_timeout", 300))
        token = str(cfg.get("cluster_token", "")).strip()

        user_name = "Grupo Corporativo" if request.is_group_context else request.user_name
        user_role = "Grupo Corporativo" if request.is_group_context else request.user_role

        # 1. Integración con el Orquestador MCP de Laravel (Tool Calling nativo)
        mcp_url = str(cfg.get("mcp_api_url", "http://127.0.0.1/api/v1/ai/chat"))
        if token:
            bridge = McpClusterBridge(api_url=mcp_url, cluster_token=token, timeout=min(timeout, 120))
            result = bridge.ask(
                message=request.user_text,
                history=request.history,
                user_name=user_name,
                user_role=user_role,
            )
            if result.success and result.reply:
                sanitized_reply = self._guardrails.validate_and_sanitize(
                    content=result.reply,
                    user_text=request.user_text,
                    dynamic_context=None,
                    is_group_context=request.is_group_context,
                )
                logger.info("Respuesta MCP exitosa. Rol: %s, Herramienta: %s", user_role, result.tool_executed)
                return InferenceResponseDTO(
                    success=True,
                    content=sanitized_reply,
                    updated_entity=request.active_entity,
                    tool_executed=result.tool_executed,
                )
            logger.warning("Orquestador MCP no devolvió respuesta exitosa (%s). Ejecutando inferencia de respaldo...", result.error)

        # 2. Inferencia directa de fallback con Ollama local
        base_url = str(cfg.get("ollama_base_url", "http://localhost:11434")).rstrip("/")
        model = cfg.get("ollama_model", "qwen-empresa")

        messages, updated_entity = self.build_messages(
            user_text=request.user_text,
            history=request.history,
            active_entity=request.active_entity,
        )

        dynamic_context = None
        for m in messages:
            if m.get("role") == "system" and "[CONTEXTO_DEL_SISTEMA" in m.get("content", ""):
                dynamic_context = m.get("content")
                break

        payload = {
            "model": model,
            "stream": False,
            "keep_alive": "24h",
            "messages": messages,
            "options": {
                "temperature": float(cfg.get("ollama_temperature", 0.3)),
                "num_ctx": int(cfg.get("ollama_num_ctx", 4096)),
            },
        }

        try:
            response = requests.post(f"{base_url}/api/chat", json=payload, timeout=timeout)
            response.raise_for_status()
            data = response.json()
        except requests.exceptions.Timeout as exc:
            logger.error("Timeout consultando motor local de IA: %s", exc)
            return InferenceResponseDTO(
                success=False,
                content="⏱️ El motor local de IA tardó demasiado en responder.\nIntenta nuevamente en unos segundos.",
                error="Timeout",
            )
        except requests.exceptions.RequestException as exc:
            logger.error("Error de conexión con motor local de IA: %s", exc)
            return InferenceResponseDTO(
                success=False,
                content="🔌 No pude conectar con el motor local de IA.\nVerifica que el servicio esté activo en este equipo.",
                error=str(exc),
            )
        except Exception as exc:
            logger.error("Excepción inesperada en inferencia directa: %s", exc)
            return InferenceResponseDTO(
                success=False,
                content="⚠️ Ocurrió un error inesperado consultando al asistente.\nIntenta nuevamente.",
                error=str(exc),
            )

        if data.get("error"):
            return InferenceResponseDTO(
                success=False,
                content="⚠️ El motor local de IA reportó un error.",
                error=str(data.get("error")),
            )

        content = str(data.get("message", {}).get("content", "")).strip()
        if not content:
            content = "Lo siento, el motor local de IA no devolvió respuesta."

        content = self._guardrails.validate_and_sanitize(
            content=content,
            user_text=request.user_text,
            dynamic_context=dynamic_context,
            is_group_context=request.is_group_context,
        )

        return InferenceResponseDTO(
            success=True,
            content=content,
            updated_entity=updated_entity,
        )

    async def ask_async(self, request: InferenceRequestDTO) -> InferenceResponseDTO:
        """Ejecuta la inferencia de manera asincrónica sin bloquear el event loop."""
        return await asyncio.to_thread(self.ask_sync, request)
