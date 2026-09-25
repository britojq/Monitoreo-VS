#!/usr/bin/env python3
"""
==============================================================================
🧪 SUITE MAESTRA DE INTEGRACIÓN Y CERTIFICACIÓN: tests/integration_test.py
Valida de extremo a extremo las 8 Fases del Proyecto de Monitoreo Valle Seco:
- FASE 1: Auto-Discovery de Red y Anti-Rogue
- FASE 2: Monitoreo Avanzado SNMP y Telemetría de Interfaces
- FASE 3: Auditoría y Verificación de Certificados SSL/TLS
- FASE 4: Alertas Inteligentes, Correlación, Escalación y Supresión
- FASE 5: Calidad de Enlace WAN (Jitter/Loss) y Auditoría GitOps de Configs
- FASE 6: Telemetría Push en Tiempo Real (NetFlow v5, Syslog RFC, Traps SNMP)
- FASE 7: Topología Dinámica, Wake-on-LAN, IA Predictiva y Ciclo de Vida
- FASE 8: Retención, Agregaciones Horarias (Rollups), Cluster Gzip y Hardening
==============================================================================
"""

import io
import os
import sys
import time
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# Colores ANSI para reporte en terminal
GREEN = "\033[0;32m"
RED = "\033[0;31m"
YELLOW = "\033[1;33m"
CYAN = "\033[0;36m"
BOLD = "\033[1m"
RESET = "\033[0m"

PHASE_MODULES = [
    ("FASE 1", "Auto-Discovery y Anti-Rogue", "tests.integration.test_discovery"),
    ("FASE 2", "Monitoreo SNMP e Interfaces", "tests.integration.test_snmp"),
    ("FASE 3", "Auditoría Certificados SSL/TLS", "tests.integration.test_ssl"),
    ("FASE 4", "Alertas, Correlación y Escalación", "tests.integration.test_alerts"),
    ("FASE 5", "Calidad WAN y Auditoría de Configs", "tests.integration.test_wan_configs"),
    ("FASE 6", "Push Telemetría (NetFlow/Syslog/Traps)", "tests.integration.test_telemetry_push"),
    ("FASE 7", "Topología, WoL e IA Predictiva", "tests.integration.test_topology_ia"),
    ("FASE 8", "Housekeeping, Rollups y Replicación", "tests.integration.test_housekeeping_cluster"),
]


def run_phase_suite(phase_id: str, phase_name: str, module_path: str):
    suite = unittest.defaultTestLoader.loadTestsFromName(module_path)
    runner = unittest.TextTestRunner(stream=io.StringIO(), verbosity=0)
    start_time = time.time()
    result = runner.run(suite)
    duration = time.time() - start_time
    return {
        "phase_id": phase_id,
        "phase_name": phase_name,
        "module": module_path,
        "tests_run": result.testsRun,
        "errors": len(result.errors),
        "failures": len(result.failures),
        "duration": duration,
        "passed": result.wasSuccessful(),
        "details": result.errors + result.failures
    }


def main():
    print(f"\n{CYAN}{BOLD}{'=' * 78}{RESET}")
    print(f"{CYAN}{BOLD}🛰️  SUITE MAESTRA DE INTEGRACIÓN - MONITOREO Y ADMINISTRACIÓN VALLE SECO{RESET}")
    print(f"{CYAN}{BOLD}{'=' * 78}{RESET}\n")

    overall_start = time.time()
    total_tests = 0
    total_errors = 0
    total_failures = 0
    all_passed = True

    results = []
    for p_id, p_name, mod in PHASE_MODULES:
        sys.stdout.write(f"⏳ Evaluando {p_id}: {p_name} ({mod})... ")
        sys.stdout.flush()
        res = run_phase_suite(p_id, p_name, mod)
        results.append(res)
        total_tests += res["tests_run"]
        total_errors += res["errors"]
        total_failures += res["failures"]
        if not res["passed"]:
            all_passed = False
            sys.stdout.write(f"{RED}[FALLÓ]{RESET}\n")
        else:
            sys.stdout.write(f"{GREEN}[OK]{RESET} ({res['tests_run']} pruebas en {res['duration']:.2f}s)\n")

    overall_duration = time.time() - overall_start

    print(f"\n{BOLD}📋 TABLA DE CERTIFICACIÓN POR FASES:{RESET}")
    print(f"{'-' * 78}")
    print(f"{'Fase':<8} | {'Módulo / Funcionalidad':<36} | {'Pruebas':<8} | {'Tiempo':<8} | {'Estado':<10}")
    print(f"{'-' * 78}")

    for r in results:
        status_str = f"{GREEN}✅ APROBADO{RESET}" if r["passed"] else f"{RED}❌ FALLIDO{RESET}"
        print(f"{r['phase_id']:<8} | {r['phase_name']:<36} | {r['tests_run']:<8} | {r['duration']:>6.2f}s | {status_str}")

    print(f"{'-' * 78}")
    print(f"Total de Pruebas Ejecutadas: {BOLD}{total_tests}{RESET}")
    print(f"Tiempo Total de Suite:      {BOLD}{overall_duration:.2f}s{RESET}")

    if all_passed:
        print(f"\n{GREEN}{BOLD}🎉 CERTIFICACIÓN EXITOSA: TODAS LAS 8 FASES CUMPLEN 100% LOS REQUISITOS.{RESET}")
        print(f"{GREEN}   • Inmunidad Git y Auto-curación: Certificada{RESET}")
        print(f"{GREEN}   • Reglas de Oro (Seguridad & Neutralidad): Certificadas{RESET}")
        print(f"{GREEN}   • Paridad de Telemetría e Integración en Base de Datos: Certificada{RESET}\n")
        return 0
    else:
        print(f"\n{RED}{BOLD}🚨 LA CERTIFICACIÓN FALLÓ. Revise los errores detallados:{RESET}\n")
        for r in results:
            if not r["passed"]:
                print(f"{RED}--- Detalle de fallas en {r['phase_id']} ({r['phase_name']}) ---{RESET}")
                for test_case, err_text in r["details"]:
                    print(f"• {test_case}:\n{err_text}")
        return 1


if __name__ == '__main__':
    sys.exit(main())
