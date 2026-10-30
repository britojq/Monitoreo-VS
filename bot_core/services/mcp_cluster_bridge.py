"""
==============================================================================
🌐 PUENTE DE INTEGRACIÓN MCP CON EL CLÚSTER: mcp_cluster_bridge.py
Principio de Responsabilidad Única (SRP) y Extensión Limpia (OCP).
Comunica el Bot de Telegram con el Servidor MCP / Tool Calling de Laravel.
==============================================================================
"""

from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Any, Dict, List, Optional

import requests

logger = logging.getLogger("bot.services.mcp_bridge")


@dataclass(frozen=True)
class McpBridgeResult:
    """DTO inmutable para el resultado de una consulta al Servidor MCP."""
    success: bool
    reply: str
    tool_executed: Optional[str] = None
    tool_result: Optional[Dict[str, Any]] = None
    error: Optional[str] = None


class McpClusterBridge:
    """
    Cliente desacoplado para orquestación de Tool Calling con el backend de Laravel.
    Protegido mediante cabecera X-Cluster-Token (SSOT).
    """

    def __init__(
        self,
        api_url: str = "http://127.0.0.1/api/v1/ai/chat",
        cluster_token: str = "",
        timeout: int = 120,
    ) -> None:
        self._api_url = api_url.rstrip("/")
        self._cluster_token = cluster_token.strip()
        self._timeout = timeout

    @property
    def is_configured(self) -> bool:
        """Determina si el puente cuenta con credenciales para comunicarse con el MCP."""
        return bool(self._cluster_token and self._api_url)

    def ask(
        self,
        message: str,
        history: Optional[List[Dict[str, str]]] = None,
        user_name: str = "Administrador Telegram",
        user_role: str = "Administrador",
    ) -> McpBridgeResult:
        """
        Envía una consulta al endpoint MCP de Laravel y retorna el DTO del resultado.
        """
        if not self.is_configured:
            return McpBridgeResult(
                success=False,
                reply="",
                error="McpClusterBridge no está configurado (falta cluster_token o api_url).",
            )

        clean_message = (message or "").strip()
        if not clean_message:
            return McpBridgeResult(
                success=False,
                reply="",
                error="El mensaje de consulta no puede estar vacío.",
            )

        clean_history: List[Dict[str, str]] = []
        if history:
            for item in history:
                role = str(item.get("role", "user"))
                content = str(item.get("content", ""))
                if role in ("user", "assistant") and content:
                    clean_history.append({"role": role, "content": content})

        payload = {
            "message": clean_message,
            "history": clean_history[-10:],
            "user_name": user_name,
            "user_role": user_role,
        }

        headers = {
            "X-Cluster-Token": self._cluster_token,
            "Accept": "application/json",
            "Content-Type": "application/json",
        }

        try:
            logger.info("Enviando consulta al Orquestador MCP: %s", self._api_url)
            response = requests.post(
                self._api_url,
                json=payload,
                headers=headers,
                timeout=self._timeout,
            )

            if response.status_code == 200:
                data = response.json()
                if data.get("success"):
                    reply = str(data.get("reply", "")).strip()
                    tool_executed = data.get("tool_executed")
                    tool_result = data.get("tool_result")
                    logger.info("Respuesta MCP exitosa. Herramienta: %s", tool_executed)
                    return McpBridgeResult(
                        success=True,
                        reply=reply,
                        tool_executed=tool_executed,
                        tool_result=tool_result,
                    )

                err_msg = str(data.get("error", "Error desconocido devuelto por el servidor MCP."))
                logger.warning("Servidor MCP devolvió error en respuesta JSON: %s", err_msg)
                return McpBridgeResult(success=False, reply="", error=err_msg)

            logger.warning(
                "Servidor MCP retornó código HTTP no exitoso: %d (%s)",
                response.status_code,
                response.text[:200],
            )
            return McpBridgeResult(
                success=False,
                reply="",
                error=f"Error HTTP {response.status_code} desde el servidor MCP.",
            )

        except requests.exceptions.Timeout:
            logger.error("Timeout al comunicar con el Servidor MCP (%s)", self._api_url)
            return McpBridgeResult(
                success=False,
                reply="",
                error="Tiempo de espera agotado al comunicar con el Orquestador MCP.",
            )
        except Exception as exc:
            logger.error("Excepción inesperada al invocar McpClusterBridge: %s", exc)
            return McpBridgeResult(
                success=False,
                reply="",
                error=f"Fallo de conexión con el Orquestador MCP: {exc}",
            )
