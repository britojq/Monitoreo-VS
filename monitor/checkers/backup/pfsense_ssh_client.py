"""
Cliente SSH para extracción de configuración de firewalls pfSense (config.xml).
"""
import asyncio
import logging
from typing import Tuple

logger = logging.getLogger("monitor.checkers.backup.pfsense_ssh_client")


async def fetch_pfsense_config_ssh(
    ip: str,
    username: str,
    password: str,
    port: int = 22,
    timeout: float = 20.0
) -> Tuple[bool, str, str]:
    """Obtiene el archivo config.xml de un firewall pfSense vía SSH."""
    def _exec():
        try:
            import paramiko
            ssh = paramiko.SSHClient()
            ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            ssh.connect(ip, port=port, username=username, password=password, timeout=timeout)
            _, stdout, stderr = ssh.exec_command("cat /cf/conf/config.xml")
            cfg_data = stdout.read().decode("utf-8", errors="ignore")
            err_data = stderr.read().decode("utf-8", errors="ignore")
            ssh.close()
            if cfg_data and "<pfsense>" in cfg_data:
                return True, cfg_data, "Captura exitosa config.xml pfSense"
            return False, "", f"Salida inválida de pfSense: {err_data}"
        except Exception as e:
            return False, "", f"Fallo SSH pfSense ({ip}): {e}"

    return await asyncio.to_thread(_exec)
