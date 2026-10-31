"""
Despliegue y conmutación de agente GLPI en sistemas Linux vía SSH.
"""

import logging
import shlex
from typing import Any, Dict, Optional

from monitor.deployment.target_resolver import get_target_url

logger = logging.getLogger("monitor.agent_deployer.linux")


def deploy_linux_ssh(
    host: str,
    user: str,
    password: Optional[str] = None,
    key_filename: Optional[str] = None,
    port: int = 22,
    target: str = "dev",
    action: str = "install",
    timeout: int = 120,
    server_url: Optional[str] = None,
    deploy_base: Optional[str] = None,
) -> Dict[str, Any]:
    """
    Ejecuta el despliegue o conmutación remota en Linux/Xubuntu vía SSH.
    """
    try:
        import paramiko
    except ImportError:
        return {"success": False, "error": "Librería paramiko no disponible en el entorno Python."}

    resolved_server_url, base_url = get_target_url(target, server_url, deploy_base)
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    logger.info(f"Conectando por SSH a Linux {user}@{host}:{port}...")
    try:
        client.connect(
            hostname=host,
            port=port,
            username=user,
            password=password,
            key_filename=key_filename,
            timeout=15,
            banner_timeout=15,
        )

        dl_linux = f"(which curl >/dev/null 2>&1 && curl -sSL '{base_url}/deploy/linux' || wget -qO- '{base_url}/deploy/linux')"
        dl_switch = f"(which curl >/dev/null 2>&1 && curl -sSL '{base_url}/deploy/switch/linux' || wget -qO- '{base_url}/deploy/switch/linux')"

        if action == "switch":
            if password:
                inner_cmd = f"export SERVER_URL='{resolved_server_url}'; {dl_switch} | bash -s '{resolved_server_url}'"
                cmd = f"echo {shlex.quote(password)} | sudo -S -E bash -c {shlex.quote(inner_cmd)}"
            else:
                cmd = f"export SERVER_URL='{resolved_server_url}'; {dl_switch} | sudo -E bash -s '{resolved_server_url}'"
        elif action == "status":
            cmd = "systemctl is-active glpi-agent 2>/dev/null || echo 'inactive'; glpi-agent --version 2>/dev/null || echo 'not_installed'"
        else:
            # install
            if password:
                inner_cmd = (
                    f"export SERVER_URL='{resolved_server_url}'; "
                    f"export BASE_URL='{base_url}/instaladores'; "
                    f"{dl_linux} | bash"
                )
                cmd = f"echo {shlex.quote(password)} | sudo -S -E bash -c {shlex.quote(inner_cmd)}"
            else:
                cmd = (
                    f"export SERVER_URL='{resolved_server_url}'; "
                    f"export BASE_URL='{base_url}/instaladores'; "
                    f"{dl_linux} | sudo -E bash"
                )

        logger.info(f"Ejecutando comando remoto en {host}: {cmd}")
        stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)

        exit_status = stdout.channel.recv_exit_status()
        out_text = stdout.read().decode("utf-8", errors="replace").strip()
        err_text = stderr.read().decode("utf-8", errors="replace").strip()

        client.close()
        success = (exit_status == 0)
        return {
            "success": success,
            "host": host,
            "os": "linux",
            "action": action,
            "target": target,
            "exit_code": exit_status,
            "stdout": out_text,
            "stderr": err_text,
        }

    except Exception as e:
        logger.error(f"Fallo SSH contra {host}: {e}")
        return {"success": False, "host": host, "os": "linux", "error": str(e)}
