#!/usr/bin/env python3
"""
tools/verify_cluster_parity.py
==============================
Herramienta de Auditoría y Verificación Remota de Clúster (Zero-SSH).
Consulta la API de Clúster con autenticación criptográfica X-Cluster-Token,
evalúa la salud, instaladores, agentes GLPI y paridad sin requerir SSH.
Todo llamado queda registrado automáticamente en audit_logs de MariaDB.
"""

import sys
import json
import argparse
from pathlib import Path

try:
    import httpx
except ImportError:
    print("Error: Se requiere httpx. Instale con: pip install httpx")
    sys.exit(1)

BASE_DIR = Path(__file__).resolve().parent.parent

def load_cluster_config() -> dict:
    cfg_file = BASE_DIR / "config" / "config.json"
    if not cfg_file.exists():
        return {}
    try:
        return json.loads(cfg_file.read_text(encoding="utf-8"))
    except Exception:
        return {}

def format_bytes(b: int) -> str:
    if b < 1024:
        return f"{b} B"
    elif b < 1024 * 1024:
        return f"{b / 1024:.1f} KB"
    elif b < 1024 * 1024 * 1024:
        return f"{b / (1024 * 1024):.2f} MB"
    return f"{b / (1024 * 1024 * 1024):.2f} GB"

def query_node_verify(base_url: str, token: str, scope: str = "all") -> dict:
    url = f"{base_url.rstrip('/')}/api/cluster/verify?scope={scope}"
    headers = {
        "X-Cluster-Token": token,
        "Accept": "application/json",
        "User-Agent": "ATIT-ClusterParityChecker/1.0"
    }
    with httpx.Client(timeout=15.0, verify=False) as client:
        resp = client.get(url, headers=headers)
        if resp.status_code == 404:
            raise ValueError("HTTP 404: Token inválido, endpoint oculto o URL errónea.")
        resp.raise_for_status()
        return resp.json()

def print_audit_report(data: dict, target_label: str):
    print("=" * 80)
    print(f"📊 INFORME DE VERIFICACIÓN REMOTA DE CLÚSTER - {target_label.upper()}")
    print("=" * 80)

    node_role = data.get("node_role", "unknown").upper()
    hostname = data.get("hostname", "unknown")
    status = data.get("status", "unknown").upper()
    ts = data.get("timestamp", "N/A")

    status_icon = "🟢" if status == "HEALTHY" else "🟡"
    print(f"• Nodo: {hostname} | Rol: {node_role} | Estado Global: {status_icon} {status} | Fecha: {ts}")
    print("-" * 80)

    # 1. INSTALADORES
    inst = data.get("installers", {})
    files = inst.get("files", {})
    print(f"📦 [1] REPOSITORIO DE INSTALADORES FÍSICOS ({inst.get('present_count', 0)}/{inst.get('total_tracked', 0)} Presentes):")
    for fname, finfo in files.items():
        if finfo.get("exists"):
            size_str = finfo.get("size_formatted", format_bytes(finfo.get("size_bytes", 0)))
            sha = finfo.get("sha256", "")[:12] + "..." if finfo.get("sha256") else "N/A"
            mtime = finfo.get("modified_at", "N/A")
            print(f"   ✓ {fname:<22} | {size_str:>9} | SHA256: {sha:<15} | Mod: {mtime}")
        else:
            print(f"   ✗ {fname:<22} | [FALTANTE EN DISCO]")
    print("-" * 80)

    # 2. AGENTES GLPI
    agents = data.get("glpi_agents", {})
    total_assets = agents.get("total_inventory_assets", 0)
    installed = agents.get("glpi_installed_total", 0)
    active24h = agents.get("glpi_reporting_24h", 0)
    offline = agents.get("glpi_offline_count", 0)
    adopt = agents.get("adoption_percentage", 0.0)
    os_break = agents.get("breakdown_by_os", {})

    print(f"🖥️ [2] CENSO Y SALUD DE AGENTES GLPI:")
    print(f"   • Total Activos Inventario: {total_assets} | Agentes Instalados: {installed} ({adopt}% Adopción)")
    print(f"   • Reportando en 24h: {active24h} | Sin reporte (>24h): {offline}")
    print(f"   • Desglose SO: Windows 32-bit: {os_break.get('windows_x86', 0)} | Windows 64-bit: {os_break.get('windows_x64', 0)} | Linux: {os_break.get('linux_debian_ubuntu', 0)}")
    
    recents = agents.get("recent_agents", [])[:4]
    if recents:
        print("   • Últimos reportes recibidos:")
        for r in recents:
            print(f"     - {r.get('name', 'N/A')} ({r.get('ip_address', 'N/A')}) | {r.get('operating_system', 'N/A')[:35]} | {r.get('last_sync_at', 'N/A')}")
    print("-" * 80)

    # 3. PARIDAD DE CÓDIGO Y RUNTIME
    parity = data.get("runtime_parity", {})
    plat = parity.get("platform", {})
    git = parity.get("git", {})
    mig = parity.get("migrations", {})
    srv = parity.get("system_services", {}).get("services", {})
    ports = parity.get("port_listeners", {})

    print(f"⚙️ [3] RUNTIME, GIT Y BASE DE DATOS:")
    print(f"   • Versión Sistema: {plat.get('system_version')} | PHP: {plat.get('php_version')} | Laravel: {plat.get('laravel_version')}")
    print(f"   • Git Commit: {git.get('commit_short')} ({git.get('branch')}) | Árbol Limpio: {'SÍ' if git.get('is_clean') else 'NO (' + str(git.get('uncommitted_files_count', 0)) + ' archivos modificados)'}")
    print(f"   • Migraciones DB: {mig.get('applied_count')}/{mig.get('files_count')} | Sincronizado: {'SÍ' if mig.get('is_synchronized') else 'NO'}")
    
    srv_line = " | ".join([f"{k}: {v}" for k, v in srv.items()])
    print(f"   • Servicios: {srv_line}")

    port_line = " | ".join([f"{k}: {'UP' if v.get('is_listening') else 'DOWN'}" for k, v in ports.items()])
    print(f"   • Puertos Críticos: {port_line}")
    print("=" * 80)

def main():
    parser = argparse.ArgumentParser(description="Verificador de Salud y Paridad de Clúster (Zero-SSH)")
    parser.add_argument("--url", default="http://127.0.0.1", help="URL base del nodo a consultar (ej. http://10.20.23.252)")
    parser.add_argument("--token", default=None, help="Token del clúster (si se omite, se lee de config/config.json)")
    parser.add_argument("--scope", default="all", choices=["all", "installers", "agents", "parity"], help="Ámbito de inspección")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON crudo")
    args = parser.parse_args()

    cfg = load_cluster_config()
    token = args.token or cfg.get("cluster_token", "")
    if not token:
        print("❌ Error: No se proporcionó ni localizó el token de clúster.")
        sys.exit(1)

    try:
        data = query_node_verify(args.url, token, args.scope)
        if args.json:
            print(json.dumps(data, indent=2, ensure_ascii=False))
        else:
            print_audit_report(data, args.url)
    except Exception as e:
        print(f"❌ Fallo al consultar el nodo ({args.url}): {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
