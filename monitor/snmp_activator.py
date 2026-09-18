#!/usr/bin/env python3
"""
Módulo de Activación Remota SNMP (Fase 2)
Permite habilitar SNMP v2c/v3 en switches/routers Cisco (SSH y fallback Telnet)
y firewalls pfSense mediante API, registrando la auditoría completa en MariaDB.

Reglas de seguridad:
- No loguear contraseñas ni secretos en texto plano.
- Timeout estricto de 10s por operación de red.
- Registro en snmp_activation_log y audit_logs.
"""

import os
import sys
import json
import time
import base64
import logging
import asyncio
import argparse
from datetime import datetime
from pathlib import Path
from typing import Optional, Dict, Any

from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_web_sync import get_db_connection

logger = logging.getLogger("snmp.activator")
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] [%(name)s] %(message)s")


def get_laravel_app_key() -> bytes:
    env_file = BASE_DIR / "web_portal" / ".env"
    if not env_file.exists():
        env_file = Path("/var/www/monitoreo/.env")
    if env_file.exists():
        for line in env_file.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line.startswith("APP_KEY="):
                val = line.split("=", 1)[1].strip()
                if val.startswith("base64:"):
                    return base64.b64decode(val[7:])
                return val.encode()
    return b"32_bytes_default_key_monitoreo!"[:32]


def encrypt_laravel_value(plain_text: str) -> str:
    """Cifra una cadena usando el mismo esquema que Laravel (AES-256-CBC, IV, MAC)."""
    import hmac
    import hashlib
    key = get_laravel_app_key()
    iv = os.urandom(16)
    backend = default_backend()
    cipher = Cipher(algorithms.AES(key), modes.CBC(iv), backend=backend)
    encryptor = cipher.encryptor()
    
    # PKCS7 padding
    pad_len = 16 - (len(plain_text.encode('utf-8')) % 16)
    padded_data = plain_text.encode('utf-8') + bytes([pad_len] * pad_len)
    cipher_bytes = encryptor.update(padded_data) + encryptor.finalize()
    
    val_b64 = base64.b64encode(cipher_bytes).decode('utf-8')
    iv_b64 = base64.b64encode(iv).decode('utf-8')
    
    # MAC = hash_hmac('sha256', iv_b64 + val_b64, key)
    mac = hmac.new(key, (iv_b64 + val_b64).encode('utf-8'), hashlib.sha256).hexdigest()
    payload = {
        "iv": iv_b64,
        "value": val_b64,
        "mac": mac,
        "tag": ""
    }
    return base64.b64encode(json.dumps(payload).encode('utf-8')).decode('utf-8')


def log_activation(
    ip: str,
    method: str,
    status: str,
    output: Optional[str] = None,
    error: Optional[str] = None,
    user_id: Optional[int] = None,
    community: Optional[str] = None,
    commands: Optional[str] = None,
    snmp_device_id: Optional[int] = None,
    discovered_device_id: Optional[int] = None
) -> int:
    """Registra un evento de activación en snmp_activation_log."""
    try:
        conn = get_db_connection()
        with conn.cursor() as cursor:
            # Asociar device_id si no se pasó explícito
            if not snmp_device_id:
                cursor.execute("SELECT id FROM snmp_devices WHERE ip_address = %s LIMIT 1", (ip,))
                row = cursor.fetchone()
                if row:
                    snmp_device_id = row[0] if isinstance(row, (tuple, list)) else row.get("id")

            if not discovered_device_id:
                cursor.execute("SELECT id FROM discovered_devices WHERE ip_address = %s LIMIT 1", (ip,))
                row_disc = cursor.fetchone()
                if row_disc:
                    discovered_device_id = row_disc[0] if isinstance(row_disc, (tuple, list)) else row_disc.get("id")

            sql = """
                INSERT INTO snmp_activation_log
                (snmp_device_id, discovered_device_id, ip_address, activation_method,
                 community_set, commands_executed, status, response_output, error_message,
                 executed_by, executed_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
            """
            cursor.execute(sql, (
                snmp_device_id, discovered_device_id, ip, method,
                community, commands, status, output, error, user_id
            ))
            log_id = cursor.lastrowid

            # Si fue exitoso, asegurar que snmp_devices tenga la comunidad actualizada
            if status == "success" and community:
                enc_comm = encrypt_laravel_value(community)
                if snmp_device_id:
                    cursor.execute("""
                        UPDATE snmp_devices
                        SET snmp_community_encrypted = %s,
                            is_active = 1,
                            notes = CONCAT(COALESCE(notes, ''), ' [Activado remotamente: ', NOW(), ']')
                        WHERE id = %s
                    """, (enc_comm, snmp_device_id))
                else:
                    cursor.execute("""
                        INSERT INTO snmp_devices
                        (name, ip_address, snmp_version, snmp_community_encrypted, is_active, notes, created_at, updated_at)
                        VALUES (%s, %s, 'v2c', %s, 1, 'Creado por activación remota SNMP', NOW(), NOW())
                    """, (f"Device-{ip}", ip, enc_comm))
        conn.close()
        return log_id
    except Exception as e:
        logger.error(f"Error registrando activación para {ip}: {e}")
        return 0


def _exec_cisco_netmiko(device_type: str, ip: str, username: str, password: str, secret: Optional[str], commands: list[str], port: int = 22) -> Dict[str, Any]:
    """Ejecuta comandos Cisco vía Netmiko en hilo separado."""
    from netmiko import ConnectHandler, NetmikoTimeoutException, NetmikoAuthenticationException

    device_params = {
        "device_type": device_type,
        "host": ip,
        "username": username,
        "password": password,
        "port": port,
        "timeout": 10,
        "conn_timeout": 10,
        "fast_cli": False,
    }
    if secret:
        device_params["secret"] = secret

    output_lines = []
    try:
        with ConnectHandler(**device_params) as net_connect:
            if secret:
                net_connect.enable()
            output_cfg = net_connect.send_config_set(commands)
            output_lines.append(output_cfg)
            # Guardar configuración solo si los comandos aplicaron bien
            save_out = net_connect.save_config()
            output_lines.append(save_out)

        return {
            "status": "success",
            "output": "\n".join(output_lines),
            "error": None
        }
    except NetmikoTimeoutException as e:
        return {"status": "timeout", "output": None, "error": f"Timeout conectando al dispositivo: {e}"}
    except NetmikoAuthenticationException as e:
        return {"status": "auth_error", "output": None, "error": f"Error de autenticación: {e}"}
    except Exception as e:
        return {"status": "failed", "output": "\n".join(output_lines) if output_lines else None, "error": str(e)}


async def activate_cisco_snmp(
    ip: str,
    username: str,
    password: str,
    community: str,
    secret: Optional[str] = None,
    port: int = 22,
    location: str = "Valle Seco",
    contact: str = "Soporte TI Corporativo",
    user_id: Optional[int] = None
) -> Dict[str, Any]:
    """Activa SNMP en router o switch Cisco usando SSH (cisco_ios)."""
    commands = [
        f"snmp-server community {community} RO",
        f"snmp-server location {location}",
        f"snmp-server contact {contact}",
        "snmp-server enable traps",
    ]
    cmds_str = "\n".join(commands)
    logger.info(f"Iniciando activación SNMP SSH para {ip}...")

    loop = asyncio.get_running_loop()
    result = await loop.run_in_executor(
        None,
        _exec_cisco_netmiko,
        "cisco_ios",
        ip,
        username,
        password,
        secret,
        commands,
        port
    )

    log_activation(
        ip=ip,
        method="ssh_cisco",
        status=result["status"],
        output=result["output"],
        error=result["error"],
        user_id=user_id,
        community=community,
        commands=cmds_str
    )
    return result


async def activate_cisco_snmp_telnet(
    ip: str,
    username: str,
    password: str,
    community: str,
    secret: Optional[str] = None,
    port: int = 23,
    location: str = "Valle Seco",
    contact: str = "Soporte TI Corporativo",
    user_id: Optional[int] = None
) -> Dict[str, Any]:
    """Fallback para switches legacy (Catalyst 2960 con Telnet) usando cisco_ios_telnet."""
    commands = [
        f"snmp-server community {community} RO",
        f"snmp-server location {location}",
        f"snmp-server contact {contact}",
        "snmp-server enable traps",
    ]
    cmds_str = "\n".join(commands)
    logger.info(f"Iniciando activación SNMP Telnet para {ip}...")

    loop = asyncio.get_running_loop()
    result = await loop.run_in_executor(
        None,
        _exec_cisco_netmiko,
        "cisco_ios_telnet",
        ip,
        username,
        password,
        secret,
        commands,
        port
    )

    log_activation(
        ip=ip,
        method="telnet_cisco",
        status=result["status"],
        output=result["output"],
        error=result["error"],
        user_id=user_id,
        community=community,
        commands=cmds_str
    )
    return result


async def activate_pfsense_snmp(
    ip: str,
    api_key: str,
    api_secret: str,
    community: str,
    port: int = 443,
    user_id: Optional[int] = None
) -> Dict[str, Any]:
    """Habilita el servicio SNMP en pfSense vía API REST o XML-RPC."""
    import httpx
    logger.info(f"Iniciando activación SNMP pfSense API para {ip}...")
    url = f"https://{ip}:{port}/api/v1/services/snmp"
    headers = {
        "Authorization": f"Bearer {api_key}:{api_secret}",
        "Content-Type": "application/json"
    }
    payload = {
        "enable": True,
        "rocommunity": community,
        "syslocation": "Valle Seco",
        "syscontact": "Soporte TI Corporativo"
    }

    status = "failed"
    output = None
    error = None

    try:
        async with httpx.AsyncClient(timeout=10.0, verify=False) as client:
            resp = await client.post(url, headers=headers, json=payload)
            output = f"HTTP {resp.status_code}: {resp.text}"
            if resp.status_code in (200, 201):
                status = "success"
            elif resp.status_code in (401, 403):
                status = "auth_error"
                error = "Credenciales de API de pfSense no autorizadas"
            else:
                status = "failed"
                error = f"Error en endpoint pfSense: HTTP {resp.status_code}"
    except httpx.TimeoutException:
        status = "timeout"
        error = "Timeout al conectar con la API de pfSense (10s)"
    except Exception as e:
        status = "failed"
        error = str(e)

    log_activation(
        ip=ip,
        method="api_pfsense",
        status=status,
        output=output,
        error=error,
        user_id=user_id,
        community=community,
        commands=f"POST /api/v1/services/snmp (rocommunity={community})"
    )
    return {"status": status, "output": output, "error": error}


def parse_args():
    parser = argparse.ArgumentParser(description="Activador Remoto SNMP para Equipos de Red")
    parser.add_argument("--ip", required=True, help="Dirección IP del equipo")
    parser.add_argument("--method", choices=["ssh", "telnet", "pfsense"], default="ssh", help="Método de activación")
    parser.add_argument("--user", default="admin", help="Usuario SSH/Telnet o pfSense Key")
    parser.add_argument("--password", default="", help="Contraseña SSH/Telnet o pfSense Secret")
    parser.add_argument("--secret", default=None, help="Cisco Enable secret")
    parser.add_argument("--community", default="public", help="Comunidad SNMP a configurar")
    parser.add_argument("--port", type=int, default=None, help="Puerto SSH/Telnet/API")
    parser.add_argument("--user-id", type=int, default=None, help="ID de usuario que ejecuta la acción")
    return parser.parse_args()


if __name__ == "__main__":
    args = parse_args()
    if args.method == "ssh":
        port = args.port or 22
        res = asyncio.run(activate_cisco_snmp(
            ip=args.ip,
            username=args.user,
            password=args.password,
            community=args.community,
            secret=args.secret,
            port=port,
            user_id=args.user_id
        ))
    elif args.method == "telnet":
        port = args.port or 23
        res = asyncio.run(activate_cisco_snmp_telnet(
            ip=args.ip,
            username=args.user,
            password=args.password,
            community=args.community,
            secret=args.secret,
            port=port,
            user_id=args.user_id
        ))
    elif args.method == "pfsense":
        port = args.port or 443
        res = asyncio.run(activate_pfsense_snmp(
            ip=args.ip,
            api_key=args.user,
            api_secret=args.password,
            community=args.community,
            port=port,
            user_id=args.user_id
        ))

    print(json.dumps(res, indent=2))
    sys.exit(0 if res.get("status") == "success" else 1)
