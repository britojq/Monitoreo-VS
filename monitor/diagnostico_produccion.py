#!/usr/bin/env python3
"""
==============================================================================
📊 DIAGNÓSTICO EN TIEMPO REAL VÍA API CLUSTER: diagnostico_produccion.py
Permite auditar el estado del servidor Master/Producción (10.20.23.252)
o local de forma instantánea (<50ms) vía HTTP REST API sin usar SSH
ni disparar alertas PAM al Telegram del Administrador.
Ubicación: /scripts/telegram-admin-bot/monitor/diagnostico_produccion.py
==============================================================================
"""

import json
import sys
import time
from pathlib import Path

try:
    import httpx
except ImportError:
    print("❌ Falta biblioteca 'httpx'. Instálela en el entorno virtual.")
    sys.exit(1)

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_FILE = BASE_DIR / "config" / "config.json"


def get_cluster_credentials():
    master_url = "http://10.20.23.252"
    cluster_token = ""
    if CONFIG_FILE.exists():
        try:
            cfg = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
            master_url = cfg.get("master_api_url", "http://10.20.23.252").rstrip("/")
            cluster_token = cfg.get("cluster_token", "")
        except Exception:
            pass
    return master_url, cluster_token


def main():
    target_url, cluster_token = get_cluster_credentials()

    # Permitir sobreescribir URL de destino por argumento
    if len(sys.argv) > 1 and sys.argv[1].startswith("http"):
        target_url = sys.argv[1].rstrip("/")

    endpoint = f"{target_url}/api/cluster/diagnostics"
    headers = {
        "X-Cluster-Token": cluster_token,
        "Accept": "application/json",
        "User-Agent": "ATIT-ValleSeco-DiagnosticsCLI/1.0"
    }

    print("=" * 72)
    print(f"🔍 CONSULTANDO DIAGNÓSTICO EN VIVO: {target_url}")
    print("=" * 72)

    start = time.perf_counter()
    try:
        with httpx.Client(timeout=6.0, verify=False) as client:
            resp = client.get(endpoint, headers=headers)
            elapsed_ms = round((time.perf_counter() - start) * 1000, 1)

            if resp.status_code == 404:
                print("❌ Error 404 Not Found: Token de clúster inválido o ruta no habilitada.")
                sys.exit(1)
            elif resp.status_code != 200:
                print(f"❌ Error HTTP {resp.status_code}: {resp.text[:200]}")
                sys.exit(1)

            data = resp.json()
    except Exception as e:
        print(f"❌ Error conectando a {endpoint}: {e}")
        sys.exit(1)

    health = data.get("overall_health", "unknown").upper()
    health_icon = "🟢" if health == "HEALTHY" else ("🟡" if health == "WARNING" else "🔴")

    git = data.get("git", {})
    census = data.get("database_census", {})
    services = data.get("services", {})
    last_dep = data.get("last_deployment")

    print(f"⚡ Respuesta API: HTTP 200 en {elapsed_ms} ms | Estado: {health_icon} {health}")
    print(f"🏛️  Host: {data.get('hostname')} | Rol de Clúster: {data.get('node_role', '').upper()}")
    print(f"⏰ Fecha/Hora del Nodo: {data.get('timestamp')}")
    print("-" * 72)

    print("🏷️  GITOPS & VERSIÓN:")
    print(f"   • Commit Activo : {git.get('commit')} ({git.get('branch')})")
    print(f"   • Mensaje       : {git.get('last_commit_msg')}")
    print(f"   • Fecha Commit  : {git.get('last_commit_date')}")
    print("-" * 72)

    print("📦 CENSO DE ENTIDADES & TELEMETRÍA:")
    snmp_icon = "✅" if census.get("snmp_status") == "ok" else "⚠️"
    topo_icon = "✅" if census.get("topology_status") == "ok" else "⚠️"
    print(f"   • Dispositivos SNMP       : {census.get('snmp_devices', 0)} {snmp_icon} (Estado: {census.get('snmp_status')})")
    print(f"   • Enlaces de Topología    : {census.get('network_topology_links', 0)} {topo_icon} (Estado: {census.get('topology_status')})")
    print(f"   • Servicios Monitoreados  : {census.get('monitored_services', 0)}")
    print(f"   • Equipos de Red          : {census.get('monitored_network_devices', 0)}")
    print(f"   • Sedes Monitoreadas      : {census.get('monitored_sites', 0)}")
    print(f"   • Proxies Monitoreados    : {census.get('monitored_proxies', 0)}")
    print(f"   • Usuarios Registrados    : {census.get('users', 0)}")
    print(f"   • Alertas Activas         : {census.get('active_alerts', 0)}")
    print("-" * 72)

    print("⚙️  ESTADO DE SERVICIOS (SYSTEMD):")
    for s_name, s_state in services.items():
        s_icon = "🟢" if s_state == "active" else "🔴"
        print(f"   • {s_name:<16}: {s_icon} {s_state}")
    print("-" * 72)

    if last_dep:
        print("📋 ÚLTIMO DESPLIEGUE REGISTRADO:")
        print(f"   • Estado         : {last_dep.get('status', '').upper()}")
        print(f"   • Fecha/Hora     : {last_dep.get('timestamp')}")
        print(f"   • Duración       : {last_dep.get('duration_seconds')}s")
        print(f"   • Commit Previo  : {last_dep.get('prev_commit')} -> {last_dep.get('new_commit')}")
        print(f"   • Respaldo BD    : {last_dep.get('backup_file')}")
    else:
        print("📋 ÚLTIMO DESPLIEGUE REGISTRADO:")
        print("   • Sin manifiesto previo estructurado (se generará en el próximo /actualizar).")

    print("=" * 72)
    print("🔒 Consulta realizada 100% vía API interna (Cero SSH, Cero alertas PAM).")
    print("=" * 72)


if __name__ == "__main__":
    main()
