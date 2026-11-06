"""
Cliente SSH para extracción y normalización de configuraciones de conmutadores y routers Cisco.
"""
import asyncio
import hashlib
import logging
import re
from typing import Optional, Tuple

logger = logging.getLogger("monitor.checkers.backup.cisco_ssh_client")


def normalize_config(raw_config: str, device_type: str = "cisco_switch") -> str:
    """Normaliza texto eliminando timestamps volátiles que alteran el hash SHA-256."""
    if not raw_config:
        return ""

    lines = raw_config.splitlines()
    filtered_lines = []
    for line in lines:
        if re.match(r"^!\s*(Last configuration change at|NVRAM config last updated at|Current configuration|Time:)", line, re.IGNORECASE):
            continue
        if re.match(r"^#\s*(Generated on|Backup date:)", line, re.IGNORECASE):
            continue
        filtered_lines.append(line.rstrip())

    return "\n".join(filtered_lines).strip() + "\n"


def calculate_hash(text: str) -> str:
    """Calcula el hash SHA-256 del texto normalizado."""
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


async def fetch_cisco_config_ssh(
    ip: str,
    username: str,
    password: str,
    port: int = 22,
    secret: Optional[str] = None,
    timeout: float = 25.0
) -> Tuple[bool, str, str]:
    """Obtiene la configuración activa (running-config) vía Netmiko (SSH / Telnet)."""
    def _exec():
        """Ejecuta la función _exec conforme a la especificación técnica."""
        driver_type = "cisco_ios_telnet" if int(port) == 23 else "cisco_ios"
        proto_name = "Telnet" if int(port) == 23 else "SSH"
        try:
            from netmiko import ConnectHandler

            device_params = {
                "device_type": driver_type,
                "host": ip,
                "username": username,
                "password": password,
                "port": int(port),
                "secret": secret or password,
                "conn_timeout": timeout,
                "auth_timeout": timeout,
                "fast_cli": False,
            }

            with ConnectHandler(**device_params) as net_connect:
                if secret or not net_connect.check_enable_mode():
                    try:
                        net_connect.enable()
                    except Exception as e:
                        logger.warning(f"Aviso de elevación enable en {ip}: {e}")

                output = net_connect.send_command("show running-config", read_timeout=90)
                if output and len(output.strip()) > 50 and ("version" in output.lower() or "hostname" in output.lower() or "interface" in output.lower()):
                    return True, output, f"Captura exitosa vía Netmiko ({proto_name})"
                elif output and len(output.strip()) > 20:
                    return True, output, f"Captura completada vía Netmiko ({proto_name})"
                else:
                    return False, "", f"Respuesta vacía o incompleta de {ip} vía {proto_name}."

        except Exception as e:
            err_str = str(e)
            if "Authentication" in err_str or "password" in err_str.lower():
                return False, "", f"Fallo de autenticación {proto_name} en {ip}:{port}."
            elif "timed out" in err_str.lower() or "timeout" in err_str.lower():
                return False, "", f"Tiempo de espera agotado al conectar por {proto_name} a {ip}:{port}."
            return False, "", f"Error {proto_name} ({ip}:{port}): {err_str}"

    return await asyncio.to_thread(_exec)
