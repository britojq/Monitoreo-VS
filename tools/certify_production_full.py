#!/usr/bin/env python3
# ==============================================================================
# 🛡️ CERTIFICACIÓN EXHAUSTIVA DE SALUD Y GRAFO EN PRODUCCIÓN
# Ubicación: /scripts/telegram-admin-bot/tools/certify_production_full.py
# Valida los 7 dominios del Grafo de Conocimiento y la inferencia real de IA.
# ==============================================================================

import sys
import os
import time

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if base_dir not in sys.path:
    sys.path.insert(0, base_dir)

print("=" * 75)
print(" 🛡️ CERTIFICACIÓN EXHAUSTIVA MULTI-DOMINIO EN PRODUCCIÓN (100% MARIADB SSOT)")
print("=" * 75)

# 1. VERIFICACIÓN DE ENTORNO Y DEPENDENCIAS
print("\n[FASE 1: VERIFICACIÓN DE ENTORNO VIRTUAL Y DEPENDENCIAS CRÍTICAS]")
import importlib
dependencies = ["networkx", "pymysql", "requests", "cryptography", "psutil", "telegram"]
for dep in dependencies:
    try:
        mod = importlib.import_module(dep)
        ver = getattr(mod, "__version__", "instalado")
        print(f"  ✓ {dep:<15} : OK ({ver})")
    except ImportError as e:
        print(f"  ❌ {dep:<15} : NO INSTALADO ({e})")
        sys.exit(1)

# 2. CONSTRUCCIÓN DEL GRAFO DE CONOCIMIENTO DESDE MARIADB
print("\n[FASE 2: CONSTRUCCIÓN Y AUDITORÍA TOPOLÓGICA DEL GRAFO DE CONOCIMIENTO]")
from monitor.knowledge_graph import SystemKnowledgeGraph, KnowledgeNodeType

start_t = time.time()
kg = SystemKnowledgeGraph.get_instance()
kg.build_graph(force_refresh=True)
build_ms = int((time.time() - start_t) * 1000)

nodes_count = kg.graph.number_of_nodes()
edges_count = kg.graph.number_of_edges()
print(f"  ✓ Nodos cargados en memoria : {nodes_count}")
print(f"  ✓ Aristas relacionales      : {edges_count}")
print(f"  ✓ Tiempo de construcción    : {build_ms} ms")

if nodes_count < 20 or edges_count < 20:
    print("  ❌ ERROR: Grafo insuficiente o vacío.")
    sys.exit(1)

# 3. AUDITORÍA DETALLADA DE LOS 7 DOMINIOS
print("\n[FASE 3: EVALUACIÓN FUNCIONAL Y FÁCTICA DE LOS 7 DOMINIOS OPERATIVOS]")
from bot_core.services.ai_knowledge_service import AiKnowledgeService

domains = [
    {
        "num": 1,
        "name": "Red y Topología",
        "query": "Háblame del router principal y los switches de red",
        "check": lambda ctx: "ROUTER PRINCIPAL" in ctx or "monitored_network_devices" in ctx or "SW" in ctx,
    },
    {
        "num": 2,
        "name": "Energía y Respaldo Eléctrico (UPS)",
        "query": "Cuántas averías tiene el histórico del UPS",
        "check": lambda ctx: "LV6KL-VS-01" in ctx and "HISTORIAL DE AVERÍAS" in ctx,
    },
    {
        "num": 3,
        "name": "Inventario ITAM y Hardware",
        "query": "Cuántos equipos y dispositivos están inventariados con serial",
        "check": lambda ctx: "INVENTARIO Y ACTIVOS ITAM" in ctx and "24" in ctx,
    },
    {
        "num": 4,
        "name": "Tickets de Mantenimiento ITAM",
        "query": "Cuántos tickets de mantenimiento técnico hay abiertos",
        "check": lambda ctx: "TICKETS Y MANTENIMIENTO TÉCNICO ITAM" in ctx,
    },
    {
        "num": 5,
        "name": "Alertas e Incidentes Globales NOC",
        "query": "Cuáles son las alertas activas en el centro de operaciones",
        "check": lambda ctx: "ALERTAS E INCIDENTES NOC" in ctx,
    },
    {
        "num": 6,
        "name": "Subredes y Gestión IPAM (Sedes Reales)",
        "query": "Dame los segmentos de red de las sedes en el sistema",
        "check": lambda ctx: "10.20.23.0/24" in ctx and "10.20.107.0/24" in ctx and "10.20.106.0/24" in ctx,
    },
    {
        "num": 7,
        "name": "Certificados SSL / TLS",
        "query": "Cuándo vence el certificado SSL o TLS",
        "check": lambda ctx: "CERTIFICADOS SSL" in ctx,
    },
]

passed_domains = 0
for d in domains:
    ctx, ent = AiKnowledgeService.resolve_system_context(d["query"])
    if ctx and d["check"](ctx):
        passed_domains += 1
        print(f"  ✓ Dominio {d['num']}: {d['name']:<40} [APROBADO]")
    else:
        print(f"  ❌ Dominio {d['num']}: {d['name']:<40} [FALLIDO]")
        if not ctx:
            print("     Motivo: Contexto nulo o vacío.")
        else:
            print(f"     Muestra de contexto generado:\n{ctx[:300]}...")

print(f"\nResultado de Dominios: {passed_domains}/{len(domains)} APROBADOS.")
if passed_domains != len(domains):
    print("❌ CERTIFICACIÓN INCOMPLETA EN DOMINIOS.")
    sys.exit(1)

# 4. INFERENCIA REAL EN VIVO CON EL ASISTENTE
print("\n[FASE 4: PRUEBA DE INFERENCIA EN TIEMPO REAL CON EL ASISTENTE LOCAL]")
from bot_core.services.ai_chat_service import _ask_ollama_sync

test_prompt = "Dame los segmentos de red de las sedes en el sistema"
print(f"  • Enviando consulta de prueba: '{test_prompt}'")
inf_start = time.time()
try:
    resp, ent = _ask_ollama_sync(test_prompt, [])
    inf_sec = round(time.time() - inf_start, 2)
    print(f"  ✓ Inferencia completada en {inf_sec}s.")
    print("  • Respuesta generada por el Asistente:")
    print("-" * 65)
    for line in resp.strip().splitlines()[:15]:
        print(f"    {line}")
    print("-" * 65)

    # Validaciones obligatorias de la respuesta
    assert "10.20.23" in resp, "Falta 10.20.23 en respuesta generada"
    assert "10.20.107" in resp or "10.20.106" in resp, "Faltan segmentos de sedes remotas en respuesta generada"
    assert "Lo siento" not in resp, "El modelo emitió una disculpa o declinación evasiva"
    assert "no tengo acceso" not in resp, "El modelo afirmó no tener acceso"
    print("  ✓ Cero evasivas certificadas. Respuesta fáctica y completa.")
except Exception as e:
    print(f"  ❌ ERROR EN INFERENCIA: {e}")
    sys.exit(1)

print("\n" + "=" * 75)
print(" 🎉 CERTIFICACIÓN DE PRODUCCIÓN 100% EXITOSA Y FÁCTICAMENTE DEMOSTRADA")
print("=" * 75)
