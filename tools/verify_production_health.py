#!/usr/bin/env python3
# ==============================================================================
# 🩺 SCRIPT DE CERTIFICACIÓN FUNCIONAL DE SALUD (SMOKE TEST REAL)
# Ubicación: /scripts/telegram-admin-bot/tools/verify_production_health.py
# Valida dependencias, conectividad a MariaDB y extractores del Grafo de Conocimiento.
# ==============================================================================

import sys
import os

# Asegurar path base
base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if base_dir not in sys.path:
    sys.path.insert(0, base_dir)

try:
    from monitor.knowledge_graph import SystemKnowledgeGraph
    from bot_core.services.ai_knowledge_service import AiKnowledgeService
except Exception as e:
    print(f"❌ FALLO CRÍTICO DE IMPORTACIÓN: {e}", file=sys.stderr)
    sys.exit(1)


def test_knowledge_graph_health():
    print("--> 1. Instanciando SystemKnowledgeGraph...")
    try:
        kg = SystemKnowledgeGraph.get_instance()
        kg.build_graph(force_refresh=True)
    except Exception as e:
        print(f"❌ FALLO AL CONSTRUIR EL GRAFO: {e}", file=sys.stderr)
        return False

    print("--> 2. Verificando extractor de subredes e IPAM...")
    ctx_subnets, _ = AiKnowledgeService.resolve_system_context("Dame los segmentos de red de las sedes en el sistema")
    if not ctx_subnets or len(ctx_subnets) < 50:
        print("❌ FALLO: Contexto de subredes vacío o nulo.", file=sys.stderr)
        return False

    required_subnets = ["10.20.23.0/24", "10.20.107.0/24", "10.20.106.0/24", "10.20.27.0/24"]
    for sn in required_subnets:
        if sn not in ctx_subnets:
            print(f"❌ FALLO: Subred requerida {sn} no encontrada en el contexto.", file=sys.stderr)
            return False
    print("    ✓ Subredes operativas reales certificadas.")

    print("--> 3. Verificando extractor de energía y respaldo eléctrico...")
    ctx_ups, _ = AiKnowledgeService.resolve_system_context("Cuántas averías tiene el UPS")
    if not ctx_ups or "LV6KL-VS-01" not in ctx_ups:
        print("❌ FALLO: Fallo en extractor de respaldo eléctrico y averías.", file=sys.stderr)
        return False
    print("    ✓ Identidad de UPS y eventos certificados.")

    print("--> 4. Verificando extractor de inventario ITAM...")
    ctx_inv, _ = AiKnowledgeService.resolve_system_context("Cuántos equipos tienen serial")
    if not ctx_inv or "INVENTARIO Y ACTIVOS ITAM" not in ctx_inv:
        print("❌ FALLO: Contexto de inventario ITAM vacío o nulo.", file=sys.stderr)
        return False
    print("    ✓ Inventario y analítica ITAM certificados.")

    print("--> 5. Verificando extractor de servicios corporativos y caídas NOC...")
    ctx_serv, _ = AiKnowledgeService.resolve_system_context("cuantos servicios estan caidos")
    if not ctx_serv or "DOMINIO DE SERVICIOS CORPORATIVOS Y ESTADO NOC" not in ctx_serv or "OTRS" not in ctx_serv:
        print("❌ FALLO: Contexto de servicios caídos vacío o no detectó OTRS.", file=sys.stderr)
        return False
    print("    ✓ Extractor de servicios y caídas NOC certificado.")

    return True


if __name__ == "__main__":
    success = test_knowledge_graph_health()
    if not success:
        print("❌ CERTIFICACIÓN DE SALUD REPROBADA.", file=sys.stderr)
        sys.exit(1)
    print("🎉 CERTIFICACIÓN FUNCIONAL 100% EXITOSA.")
    sys.exit(0)
