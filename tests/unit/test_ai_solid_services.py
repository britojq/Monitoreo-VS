"""
==============================================================================
🧪 PRUEBAS UNITARIAS DE ARQUITECTURA SOLID PARA IA: test_ai_solid_services.py
Verifica el desacoplamiento de DTOs, Telemetría, Guardrails, Inferencia y Configuración.
==============================================================================
"""

import pytest
from unittest.mock import MagicMock, patch

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
from bot_core.services.ai_guardrails_service import AiGuardrailsService
from bot_core.services.ai_inference_client import AiInferenceClient
from bot_core.services.ai_telemetry_provider import MariaDbAiTelemetryProvider
from bot_core.services.ai_group_config_service import MariaDbAiGroupConfigService
from bot_core.services.ai_chat_service import (
    is_telegram_group_ai_enabled,
    set_telegram_group_ai_enabled,
    _get_live_inventory_summary,
    _get_live_actas_summary,
    _get_live_outage_summary,
    _ask_ollama_sync,
)


def test_contracts_conformity():
    """Certifica que las implementaciones concretas cumplen los contratos formales (Protocol)."""
    assert isinstance(MariaDbAiTelemetryProvider(), AiTelemetryProviderProtocol)
    assert isinstance(AiGuardrailsService(), AiGuardrailsServiceProtocol)
    assert isinstance(AiInferenceClient(), AiInferenceClientProtocol)
    assert isinstance(MariaDbAiGroupConfigService(), AiGroupConfigProtocol)


def test_guardrails_group_isolation():
    """Valida que los guardrails bloqueen consultas de infraestructura en el grupo corporativo."""
    mock_telemetry = MagicMock(spec=AiTelemetryProviderProtocol)
    service = AiGuardrailsService(telemetry_provider=mock_telemetry)

    # Consulta de infraestructura en grupo
    res_group = service.validate_and_sanitize(
        content="Respuesta con datos de infraestructura",
        user_text="cuantos equipos hay en la sede?",
        is_group_context=True,
    )
    assert "Información Restringida" in res_group
    assert "acceso reservado exclusivo para el Administrador" in res_group
    mock_telemetry.get_live_inventory_summary.assert_not_called()

    # Consulta técnica general en grupo (permitida)
    res_tech = service.validate_and_sanitize(
        content="Para listar procesos en Linux usa `ps aux` o `htop`.",
        user_text="como veo los procesos en linux?",
        is_group_context=True,
    )
    assert "ps aux" in res_tech


def test_guardrails_live_telemetry_recovery():
    """Valida que un rechazo erróneo del modelo sea interceptado y sustituido por MariaDB SSOT."""
    mock_telemetry = MagicMock(spec=AiTelemetryProviderProtocol)
    mock_telemetry.get_live_inventory_summary.return_value = InventorySummaryDTO(
        total_assets=43, with_serial=22
    )

    service = AiGuardrailsService(telemetry_provider=mock_telemetry)

    # Si el modelo emite declinación errónea ante pregunta de seriales en privado
    corrected = service.validate_and_sanitize(
        content="Lo siento, no tengo acceso a la base de datos de equipos.",
        user_text="cuantos equipos tienen serial verificado?",
        is_group_context=False,
    )
    assert "43 activos" in corrected
    assert "22 equipos" in corrected
    mock_telemetry.get_live_inventory_summary.assert_called_once()


def test_guardrails_actas_recovery():
    """Valida la sustitución de alucinación en actas escaneadas con datos reales."""
    mock_telemetry = MagicMock(spec=AiTelemetryProviderProtocol)
    mock_telemetry.get_live_actas_summary.return_value = ActasSummaryDTO(
        acta_count=11,
        total_assets=43,
        items=["• UINF-4.05-001: Responsable: Coord. Sistemas | Archivo: actas/acta1.pdf"]
    )

    service = AiGuardrailsService(telemetry_provider=mock_telemetry)

    corrected = service.validate_and_sanitize(
        content="No dispongo de información sobre actas de entrega.",
        user_text="cuales equipos tienen acta de asignacion?",
        is_group_context=False,
    )
    assert "11 equipos con acta" in corrected
    assert "43 activos" in corrected
    assert "UINF-4.05-001" in corrected


def test_inference_client_mcp_bridge_success():
    """Valida que el cliente de inferencia despache a través del puente MCP de Laravel."""
    mock_guardrails = MagicMock(spec=AiGuardrailsServiceProtocol)
    mock_guardrails.validate_and_sanitize.side_effect = lambda content, **kwargs: content

    client = AiInferenceClient(
        guardrails_service=mock_guardrails,
        config={"cluster_token": "valid_token", "mcp_api_url": "http://127.0.0.1/api/v1/ai/chat"}
    )

    with patch("bot_core.services.ai_inference_client.McpClusterBridge") as mock_bridge_cls:
        mock_bridge = MagicMock()
        mock_bridge_cls.return_value = mock_bridge
        mock_bridge.ask.return_value = MagicMock(
            success=True,
            reply="Hay 43 equipos registrados en el inventario.",
            tool_executed="get_inventory_summary"
        )

        req = InferenceRequestDTO(
            user_text="cuantos equipos existen?",
            is_group_context=False
        )
        res = client.ask_sync(req)

        assert res.success is True
        assert "43 equipos" in res.content
        assert res.tool_executed == "get_inventory_summary"


def test_facades_backward_compatibility():
    """Valida que las fachadas públicas en ai_chat_service.py funcionen de forma idéntica."""
    with patch("bot_core.services.ai_chat_service._telemetry_provider") as mock_tel:
        mock_tel.get_live_inventory_summary.return_value = InventorySummaryDTO(total_assets=43, with_serial=22)
        total, with_ser = _get_live_inventory_summary()
        assert total == 43
        assert with_ser == 22

    with patch("bot_core.services.ai_chat_service._group_config_service") as mock_cfg:
        mock_cfg.is_group_ai_enabled.return_value = True
        assert is_telegram_group_ai_enabled() is True
        mock_cfg.set_group_ai_enabled.return_value = True
        assert set_telegram_group_ai_enabled(False) is True


def test_mcp_cluster_bridge_sends_hmac_and_timestamp():
    """Valida que McpClusterBridge firme la petición con HMAC-SHA256 y envíe timestamp (F-09)."""
    import hashlib
    import hmac
    from bot_core.services.mcp_cluster_bridge import McpClusterBridge

    token = "test_cluster_secret_key"
    bridge = McpClusterBridge(
        api_url="http://127.0.0.1/api/v1/ai/chat",
        cluster_token=token,
    )

    with patch("requests.post") as mock_post:
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "success": True,
            "reply": "Respuesta mock exitosa",
        }
        mock_post.return_value = mock_response

        result = bridge.ask(message="test message", user_name="Admin", user_role="Admin")

        assert result.success is True
        assert result.reply == "Respuesta mock exitosa"
        mock_post.assert_called_once()
        _args, kwargs = mock_post.call_args
        headers = kwargs["headers"]
        data = kwargs["data"].decode("utf-8")

        assert headers["X-Cluster-Token"] == token
        assert "X-Cluster-Timestamp" in headers
        assert "X-Cluster-Signature" in headers

        timestamp = headers["X-Cluster-Timestamp"]
        expected_sig = hmac.new(
            token.encode("utf-8"),
            f"{timestamp}:{data}".encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()
        assert headers["X-Cluster-Signature"] == expected_sig
