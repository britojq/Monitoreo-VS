"""
Submódulo de orquestación y despliegue remoto de agentes GLPI.
"""

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
]
