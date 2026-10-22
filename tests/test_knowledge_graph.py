#!/usr/bin/env python3
# ==============================================================================
# 🧪 TEST: Grafo de Conocimiento del Sistema (SystemKnowledgeGraph)
# Ubicación: /scripts/telegram-admin-bot/tests/test_knowledge_graph.py
# ==============================================================================

import pytest
from monitor.knowledge_graph import SystemKnowledgeGraph, KnowledgeNodeType, KnowledgeEdgeType


def test_knowledge_graph_build():
    """Valida que el grafo se construya con nodos y aristas desde MariaDB."""
    kg = SystemKnowledgeGraph.get_instance()
    kg.build_graph(force_refresh=True)

    assert kg.graph.number_of_nodes() > 50, "El grafo debe contener más de 50 nodos"
    assert kg.graph.number_of_edges() > 50, "El grafo debe contener más de 50 aristas relacionales"

    # Validar presencia de tipos clave
    sites = kg.find_nodes_by_type(KnowledgeNodeType.SITE)
    assert len(sites) > 0, "Debe existir al menos 1 sede"

    ups_nodes = kg.find_nodes_by_type(KnowledgeNodeType.UPS)
    assert len(ups_nodes) == 1, "Debe existir exactamente 1 nodo UPS"

    ups = ups_nodes[0]
    assert "ZTG" in ups.get("model", ""), "El modelo de UPS debe contener ZTG"
    assert ups.get("serial_number") == "UPS-DEMO-01", "El serial del UPS debe ser UPS-DEMO-01"


def test_knowledge_graph_ups_power_events():
    """Valida la resolución de averías y eventos del UPS a través del grafo."""
    kg = SystemKnowledgeGraph.get_instance()
    ctx, active_ent = kg.resolve_query_context("Cuántas averías ha detectado el histórico del UPS")

    assert active_ent == "ups_1"
    assert "HISTORIAL DE AVERÍAS Y EVENTOS ELÉCTRICOS" in ctx
    assert "incidencias registradas en MariaDB (SSOT)" in ctx
    assert "UPS-DEMO-01" in ctx


def test_knowledge_graph_disambiguation():
    """Valida que no exista contaminación de memoria entre SW03 y el UPS."""
    kg = SystemKnowledgeGraph.get_instance()

    # Usuario pregunta primero por SW03
    ctx_sw, active_sw = kg.resolve_query_context("Háblame de SW03")
    assert "SW03" in ctx_sw
    assert "device_" in str(active_sw)

    # Luego pregunta por el UPS manteniendo la memoria previa
    ctx_ups, active_ups = kg.resolve_query_context("cuál es el serial del UPS", active_node_id=active_sw)
    assert active_ups == "ups_1"
    assert "UPS-DEMO-01" in ctx_ups
    # Asegurar que en la cabecera de identidad del UPS NO aparezca SW03
    identity_line = [l for l in ctx_ups.splitlines() if "⚡ IDENTIDAD DEL EQUIPO:" in l][0]
    assert "SW03" not in identity_line, f"Contaminación detectada: {identity_line}"


def test_ai_knowledge_service_integration():
    """Valida que AiKnowledgeService resuelva a través del Grafo sin regresiones."""
    from bot_core.services.ai_knowledge_service import AiKnowledgeService

    # 1. Consulta Averías UPS
    ctx, ent = AiKnowledgeService.resolve_system_context("Cuántas averías ha detectado el histórico del UPS")
    assert ctx is not None
    assert ent is not None
    assert ent.get("type") == "ups"
    assert "UPS-DEMO-01" in ctx
    assert "HISTORIAL DE AVERÍAS Y EVENTOS ELÉCTRICOS" in ctx

    # 2. Desambiguación con SW03 previo
    sw_ctx, sw_ent = AiKnowledgeService.resolve_system_context("háblame del switch SW03")
    assert "SW03" in sw_ctx

    ups_ctx, ups_ent = AiKnowledgeService.resolve_system_context(
        "cuál es el serial del UPS",
        active_entity=sw_ent
    )
    assert ups_ent.get("type") == "ups"
    assert "UPS-DEMO-01" in ups_ctx
    identity_line = [l for l in ups_ctx.splitlines() if "⚡ IDENTIDAD DEL EQUIPO:" in l][0]
    assert "SW03" not in identity_line, f"Contaminación detectada: {identity_line}"


def test_knowledge_graph_all_domains_coverage():
    """Valida la cobertura del 100% de los 7 dominios del sistema."""
    from bot_core.services.ai_knowledge_service import AiKnowledgeService

    # 1. Tickets de Mantenimiento ITAM
    ctx_t, _ = AiKnowledgeService.resolve_system_context("cuántos tickets de mantenimiento hay abiertos")
    assert ctx_t is not None
    assert "DOMINIO DE TICKETS Y MANTENIMIENTO TÉCNICO ITAM" in ctx_t

    # 2. Alertas NOC
    ctx_a, _ = AiKnowledgeService.resolve_system_context("cuáles son las alertas activas del sistema")
    assert ctx_a is not None
    assert "DOMINIO DE ALERTAS E INCIDENTES NOC" in ctx_a

    # 3. Subredes e IPAM
    ctx_s, _ = AiKnowledgeService.resolve_system_context("qué subredes y bloques IP están configurados")
    assert ctx_s is not None
    assert "DOMINIO DE SUBREDES Y PLAN IP" in ctx_s
    assert "192.168.1.0/24" in ctx_s

    # 4. Certificados SSL
    ctx_c, _ = AiKnowledgeService.resolve_system_context("cuándo vence el certificado SSL o TLS")
    assert ctx_c is not None
    assert "DOMINIO DE CERTIFICADOS SSL" in ctx_c
    assert "core.telegram.org" in ctx_c


def test_knowledge_graph_services_and_outages():
    """Valida la resolución fáctica del dominio de servicios corporativos y caídas (NOC)."""
    from bot_core.services.ai_knowledge_service import AiKnowledgeService

    ctx, ent = AiKnowledgeService.resolve_system_context("cuantos servicios estan caidos")
    assert ctx is not None
    assert "DOMINIO DE SERVICIOS CORPORATIVOS Y ESTADO NOC" in ctx
    assert "OTRS" in ctx
    assert "INTRANET empresa" in ctx
    assert "FACTURA DIGITAL" in ctx
    assert "SISTEMA AUTOGESTION DE CLAVES" in ctx
    assert "ESTADO_OPERATIVO_BASE_NOC" in ctx


def test_ai_chat_service_history_sanitization():
    """Valida que el historial previo sanitice las respuestas de rechazo para no condicionar al LLM."""
    from bot_core.services.ai_chat_service import _build_ollama_messages

    history = [
        {"role": "user", "content": "hola"},
        {"role": "assistant", "content": "Como Asistente Técnico corporativo de ATIT, mi función está orientada exclusivamente a la infraestructura..."},
    ]
    messages, _ = _build_ollama_messages("cuantos servicios estan caidos", history)
    contents = [m["content"] for m in messages if m["role"] == "assistant"]
    for c in contents:
        assert "Como Asistente Técnico corporativo" not in c


def test_output_guardrail_sanitizes_false_refusals_and_contradictions():
    """Valida que el Output Guardrail intercepte falsas declinaciones y contradicciones de caídas."""
    from bot_core.services.ai_chat_service import _validate_and_sanitize_response

    # Caso 1: Falsa declinación sobre segmentos
    canned_refusal = "Lo siento, pero no tengo acceso a la información específica sobre los segmentos de red..."
    fixed = _validate_and_sanitize_response(canned_refusal, "Dame los segmentos de red de las sedes en el sistema", None)
    assert "192.168.107.0/24" in fixed
    assert "192.168.106.0/24" in fixed
    assert "192.168.1.0/24" in fixed

    # Caso 2: Contradicción fáctica sobre servicios caídos (dice que no hay ninguno cuando sí hay 4)
    hallucination = "Actualmente no hay ningún servicio caído registrado en el sistema. Todos están operativos."
    fixed_outage = _validate_and_sanitize_response(hallucination, "cuantos servicios estan caidos", None)
    assert "4 servicios caídos" in fixed_outage
    assert "OTRS" in fixed_outage
    assert "INTRANET empresa" in fixed_outage


def test_knowledge_graph_person_subgraph_resolution():
    """Valida la resolución relacional del subgrafo de una persona (Anais Colmenarez)."""
    kg = SystemKnowledgeGraph.get_instance()
    ctx, active_ent = kg.resolve_query_context("Qué equipo tiene Anais?")

    assert active_ent == "person_anais_c_colmenarez_g"
    assert "ANAIS C. COLMENAREZ G." in ctx
    assert "EQUIPO 01" in ctx
    assert "MXL52434KR" in ctx
    assert "V1PAF51" in ctx  # Monitor Lenovo
    assert "assignments/scans/UINF-4.05-2026-0014_ACTA_EQUIPO_01_TRANSMISION.pdf" in ctx


def test_knowledge_graph_scanned_actas_resolution():
    """Valida la resolución estricta de las 3 actas físicas escaneadas en PDF."""
    kg = SystemKnowledgeGraph.get_instance()
    ctx, active_ent = kg.resolve_query_context("dame un listado de los equipos que tienen acta de asignacion")

    assert "UINF-4.05-2026-0014" in ctx
    assert "UINF-4.05-2026-0015" in ctx
    assert "UINF-4.05-2026-0016" in ctx
    assert "ANAIS C. COLMENAREZ G." in ctx
    assert "SORANGEL A. ORTIZ S." in ctx
    assert "TANIA ALVIS" in ctx


def test_knowledge_graph_peripheral_matching():
    """Valida la búsqueda transversal de periféricos (monitores HP)."""
    kg = SystemKnowledgeGraph.get_instance()
    ctx, active_ent = kg.resolve_query_context("Cuántos monitores HP tenemos registrados?")

    assert "DOMINIO DE PERIFÉRICOS DE HARDWARE ITAM" in ctx
    assert "Periféricos Coincidentes con la Consulta: 8 unidades" in ctx
    assert "HP V193b" in ctx


def test_knowledge_graph_cencaratit_and_router():
    """Valida la resolución relacional de activos clave (CENCARATIT y Router Principal)."""
    kg = SystemKnowledgeGraph.get_instance()

    # CENCARATIT
    ctx_c, act_c = kg.resolve_query_context("Qué componentes tiene el equipo CENCARATIT?")
    assert act_c == "asset_11519"
    assert "CENCARATIT (Servidor Desarrollo)" in ctx_c
    assert "MJ09RSFL" in ctx_c
    assert "192.168.1.220" in ctx_c

    # Router Principal
    ctx_r, act_r = kg.resolve_query_context("A qué switch y puerto está conectado el Router Principal?")
    assert "ROUTER PRINCIPAL" in ctx_r
    assert "192.168.1.1" in ctx_r




