"""
# ==============================================================================
# 🚀 ORQUESTADOR DE DESPLIEGUE REMOTO DE AGENTE GLPI: agent_deployer.py
# Despliegue desatendido e inventario en PCs Windows (32/64 bits) y Linux (Xubuntu)
# Ubicación: /opt/sonar/monitor/agent_deployer.py
# ==============================================================================
"""

import argparse
import json
import logging
import sys
import time
from pathlib import Path
from typing import Any, Dict, Optional

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.deployment.asset_verifier import verify_asset_in_db
from monitor.deployment.linux_deployer import deploy_linux_ssh
from monitor.deployment.target_resolver import (
    DEV_DEPLOY_BASE,
    DEV_SERVER_URL,
    PROD_DEPLOY_BASE,
    PROD_SERVER_URL,
    get_target_url,
    is_port_open,
)
from monitor.deployment.windows_deployer import (
    deploy_windows,
    deploy_windows_rpc,
    deploy_windows_winrm,
)

logger = logging.getLogger("monitor.agent_deployer")

__all__ = [
    "DEV_SERVER_URL",
    "PROD_SERVER_URL",
    "DEV_DEPLOY_BASE",
    "PROD_DEPLOY_BASE",
    "is_port_open",
    "get_target_url",
    "deploy_linux_ssh",
    "deploy_windows_winrm",
    "deploy_windows_rpc",
    "deploy_windows",
    "verify_asset_in_db",
    "run_deployment",
    "main",
]


def run_deployment(
    host: str,
    os_type: str,
    user: str,
    password: Optional[str] = None,
    domain: Optional[str] = None,
    target: str = "dev",
    action: str = "install",
    key_filename: Optional[str] = None,
    wait_verify: bool = True,
    server_url: Optional[str] = None,
    deploy_base: Optional[str] = None,
) -> Dict[str, Any]:
    """
    Orquesta el despliegue completo y la verificación posterior en base de datos.
    """
    os_clean = os_type.lower()
    if os_clean in ("win", "windows"):
        result = deploy_windows(
            host=host,
            user=user,
            password=password or "",
            domain=domain,
            target=target,
            action=action,
            server_url=server_url,
            deploy_base=deploy_base,
        )
    else:
        result = deploy_linux_ssh(
            host=host,
            user=user,
            password=password,
            key_filename=key_filename,
            target=target,
            action=action,
            server_url=server_url,
            deploy_base=deploy_base,
        )

    if wait_verify and action in ("install", "switch") and result.get("success"):
        logger.info(f"Esperando 8 segundos para verificar ingesta de inventario de {host} en MariaDB...")
        time.sleep(8)
        verification = verify_asset_in_db(host, since_minutes=5)
        result["db_verification"] = verification

    return result


def main():
    """Punto de entrada principal para ejecución por línea de comandos."""
    parser = argparse.ArgumentParser(description="Despliegue Remoto Desatendido de Agente GLPI")
    parser.add_argument("--host", required=True, help="IP o hostname del equipo remoto")
    parser.add_argument("--os", choices=["windows", "linux", "win"], default="linux", help="Sistema operativo del equipo")
    parser.add_argument("--user", required=True, help="Usuario administrativo para la conexión remota")
    parser.add_argument("--password", default=None, help="Contraseña del usuario")
    parser.add_argument("--domain", default=None, help="Dominio Active Directory (solo Windows)")
    parser.add_argument("--target", default="dev", help="Destino ('dev' o 'prod')")
    parser.add_argument("--server-url", default=None, help="URL explícita del receptor GLPI")
    parser.add_argument("--deploy-base", default=None, help="URL base explícita del servidor HTTP de despliegue")
    parser.add_argument("--action", choices=["install", "switch", "status"], default="install", help="Acción a ejecutar")
    parser.add_argument("--no-verify", action="store_true", help="No esperar verificación en MariaDB")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON estructurado")

    args = parser.parse_args()

    res = run_deployment(
        host=args.host,
        os_type=args.os,
        user=args.user,
        password=args.password,
        domain=args.domain,
        target=args.target,
        action=args.action,
        wait_verify=not args.no_verify,
        server_url=args.server_url,
        deploy_base=args.deploy_base,
    )

    if args.json:
        print(json.dumps(res, indent=2, ensure_ascii=False))
    else:
        status_str = "EXITOSO" if res.get("success") else "FALLIDO"
        print(f"[*] Resultado del despliegue en {args.host}: {status_str}")
        if res.get("error"):
            print(f"[-] Error: {res['error']}")
        if res.get("stdout"):
            print(f"[+] Stdout:\n{res['stdout']}")

    sys.exit(0 if res.get("success") else 1)


if __name__ == "__main__":
    main()
