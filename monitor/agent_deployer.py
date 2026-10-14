"""
# ==============================================================================
# 🚀 ORQUESTADOR DE DESPLIEGUE REMOTO DE AGENTE GLPI: agent_deployer.py
# Despliegue desatendido e inventario en PCs Windows (32/64 bits) y Linux (Xubuntu)
# Ubicación: /scripts/telegram-admin-bot/monitor/agent_deployer.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

import argparse
import json
import logging
import os
import shlex
import socket
import sys
import time
from pathlib import Path
from typing import Any, Dict, Optional, Tuple

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)
logger = logging.getLogger("monitor.agent_deployer")


def is_port_open(host: str, port: int, timeout: float = 2.0) -> bool:
    """Comprueba rápidamente si un puerto TCP está abierto antes de conectar."""
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True
    except (socket.timeout, ConnectionRefusedError, OSError):
        return False

DEV_SERVER_URL = "http://10.20.23.221/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure"
PROD_SERVER_URL = "http://10.20.23.252/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure"
DEV_DEPLOY_BASE = "http://10.20.23.221"
PROD_DEPLOY_BASE = "http://10.20.23.252"


def get_target_url(target: str = "dev") -> Tuple[str, str]:
    """Retorna (server_url, deploy_base_url) según el entorno seleccionado."""
    if target.lower() in ("prod", "production", "produccion"):
        return PROD_SERVER_URL, PROD_DEPLOY_BASE
    return DEV_SERVER_URL, DEV_DEPLOY_BASE


def deploy_linux_ssh(
    host: str,
    user: str,
    password: Optional[str] = None,
    key_filename: Optional[str] = None,
    port: int = 22,
    target: str = "dev",
    action: str = "install",
    timeout: int = 120,
) -> Dict[str, Any]:
    """
    Ejecuta el despliegue o conmutación remota en Linux/Xubuntu vía SSH.
    """
    try:
        import paramiko
    except ImportError:
        return {"success": False, "error": "Librería paramiko no disponible en el entorno Python."}

    server_url, base_url = get_target_url(target)
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
                inner_cmd = f"{dl_switch} | bash -s {target}"
                cmd = f"echo {shlex.quote(password)} | sudo -S bash -c {shlex.quote(inner_cmd)}"
            else:
                cmd = f"{dl_switch} | sudo bash -s {target}"
        elif action == "status":
            cmd = "systemctl is-active glpi-agent 2>/dev/null || echo 'inactive'; glpi-agent --version 2>/dev/null || echo 'not_installed'"
        else:
            # install
            if password:
                inner_cmd = (
                    f"export SERVER_URL='{server_url}'; "
                    f"export BASE_URL='{base_url}/instaladores'; "
                    f"{dl_linux} | bash"
                )
                cmd = f"echo {shlex.quote(password)} | sudo -S -E bash -c {shlex.quote(inner_cmd)}"
            else:
                cmd = (
                    f"export SERVER_URL='{server_url}'; "
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


def deploy_windows_winrm(
    host: str,
    user: str,
    password: str,
    domain: Optional[str] = None,
    port: int = 5985,
    target: str = "dev",
    action: str = "install",
    timeout: int = 90,
) -> Dict[str, Any]:
    """
    Ejecuta el despliegue o conmutación remota en Windows vía WinRM (soporta Dominio).
    """
    try:
        import winrm
    except ImportError:
        return {"success": False, "error": "Librería pywinrm no disponible en el entorno Python."}

    server_url, base_url = get_target_url(target)

    # Formateo de usuario de dominio si aplica
    full_username = f"{domain}\\{user}" if domain and "\\" not in user else user
    endpoint = f"http://{host}:{port}/wsman"

    logger.info(f"Conectando por WinRM a Windows {full_username}@{endpoint}...")
    try:
        session = winrm.Session(
            endpoint,
            auth=(full_username, password),
            transport="ntlm",
            server_cert_validation="ignore",
            read_timeout_sec=timeout,
            operation_timeout_sec=timeout - 5,
        )

        if action == "switch":
            ps_script = f"(New-Object System.Net.WebClient).DownloadString('{base_url}/deploy/switch/win') | iex"
        elif action == "status":
            ps_script = "(Get-Service glpi-agent -ErrorAction SilentlyContinue).Status; if (Test-Path 'HKLM:\\SOFTWARE\\GLPI-Agent') { (Get-ItemProperty 'HKLM:\\SOFTWARE\\GLPI-Agent').server }"
        else:
            # install
            ps_script = f"(New-Object System.Net.WebClient).DownloadString('{base_url}/deploy/win') | iex"

        logger.info(f"Ejecutando PowerShell remoto en {host}: {ps_script}")
        res = session.run_ps(ps_script)

        out_text = res.std_out.decode("utf-8", errors="replace").strip()
        err_text = res.std_err.decode("utf-8", errors="replace").strip()
        success = (res.status_code == 0)

        return {
            "success": success,
            "host": host,
            "os": "windows",
            "action": action,
            "target": target,
            "exit_code": res.status_code,
            "stdout": out_text,
            "stderr": err_text,
        }

    except Exception as e:
        logger.error(f"Fallo WinRM contra {host}: {e}")
        return {"success": False, "host": host, "os": "windows", "error": str(e)}


def deploy_windows_rpc(
    host: str,
    user: str,
    password: Optional[str] = None,
    domain: Optional[str] = None,
    target: str = "dev",
    action: str = "install",
    timeout: int = 150,
) -> Dict[str, Any]:
    """
    Ejecuta el despliegue o comando remoto en Windows vía WMI / SMB (Impacket).
    Compatible con Windows 7 (PowerShell 2.0) y sistemas modernos sin WinRM habilitado.
    """
    server_url, base_url = get_target_url(target)

    if action == "switch":
        win_cmd = f'powershell -ExecutionPolicy Bypass -NoProfile -Command "(New-Object System.Net.WebClient).DownloadString(\'{base_url}/deploy/switch/win\') | iex"'
    elif action == "status":
        win_cmd = "sc query glpi-agent"
    else:
        # install
        win_cmd = f'powershell -ExecutionPolicy Bypass -NoProfile -Command "(New-Object System.Net.WebClient).DownloadString(\'{base_url}/deploy/win\') | iex"'

    pwd = password or ""
    dom_prefix = f"{domain}/" if domain else ""
    target_str = f"{dom_prefix}{user}:{pwd}@{host}" if pwd else f"{dom_prefix}{user}@{host}"

    venv_bin = Path(sys.executable).parent
    wmiexec_path = venv_bin / "wmiexec.py"

    cmd_exec = [sys.executable, str(wmiexec_path), "-codec", "latin1"]
    if not pwd:
        cmd_exec.append("-no-pass")
    cmd_exec.extend([target_str, win_cmd])

    logger.info(f"Intentando fallback RPC/WMI contra {host}...")
    try:
        import subprocess
        proc = subprocess.run(cmd_exec, capture_output=True, text=True, timeout=timeout)
        out_text = proc.stdout.strip()
        err_text = proc.stderr.strip()

        has_error = False
        for line in out_text.splitlines():
            sline = line.strip()
            if sline.startswith("[-] ") and "Decoding error" not in sline:
                has_error = True
                break

        if proc.returncode == 0 and not has_error:
            return {
                "success": True,
                "host": host,
                "os": "windows",
                "method": "rpc/wmi",
                "action": action,
                "target": target,
                "exit_code": 0,
                "stdout": out_text,
                "stderr": err_text,
            }
        else:
            combined_err = err_text or out_text
            # Si WMI falló (ej. DCOM 0x800706ba por firewall), probar fallback vía Task Scheduler (atexec)
            logger.info(f"WMI falló en {host}. Probando fallback Task Scheduler (atexec)...")
            atexec_path = venv_bin / "atexec.py"
            at_cmd = [sys.executable, str(atexec_path), "-silentcommand", "-codec", "latin1"]
            if not pwd:
                at_cmd.append("-no-pass")
            at_cmd.extend([target_str, win_cmd])

            try:
                proc_at = subprocess.run(at_cmd, capture_output=True, text=True, timeout=timeout)
                at_out = proc_at.stdout.strip()
                at_err = proc_at.stderr.strip()
                if "Running task" in at_out or "Deleting task" in at_out:
                    return {
                        "success": True,
                        "host": host,
                        "os": "windows",
                        "method": "rpc/task_scheduler",
                        "action": action,
                        "target": target,
                        "exit_code": 0,
                        "stdout": at_out,
                        "stderr": at_err,
                    }
            except Exception as e_at:
                logger.warning(f"Fallback atexec en {host} falló: {e_at}")

            return {
                "success": False,
                "host": host,
                "os": "windows",
                "method": "rpc/wmi",
                "error": combined_err,
            }
    except Exception as e:
        return {"success": False, "host": host, "os": "windows", "method": "rpc/wmi", "error": str(e)}


def deploy_windows(
    host: str,
    user: str,
    password: str,
    domain: Optional[str] = None,
    port: int = 5985,
    target: str = "dev",
    action: str = "install",
    timeout: int = 90,
) -> Dict[str, Any]:
    """
    Intenta despliegue en Windows: primero WinRM (si puerto está abierto), con fallback automático a RPC/WMI.
    """
    winrm_res = {"success": False, "error": f"Puerto WinRM {port} no accesible"}
    if is_port_open(host, port, timeout=2.0):
        winrm_res = deploy_windows_winrm(
            host=host,
            user=user,
            password=password,
            domain=domain,
            port=port,
            target=target,
            action=action,
            timeout=timeout,
        )
        if winrm_res.get("success"):
            winrm_res["method"] = "winrm"
            return winrm_res
        logger.info(f"WinRM falló en {host}. Probando fallback RPC/WMI...")
    else:
        logger.info(f"Puerto WinRM {port} no accesible en {host}. Probando fallback RPC/WMI directo...")

    rpc_res = deploy_windows_rpc(
        host=host,
        user=user,
        password=password,
        domain=domain,
        target=target,
        action=action,
        timeout=150,
    )
    if rpc_res.get("success"):
        return rpc_res

    winrm_err = winrm_res.get("error", "Error desconocido")
    rpc_err = rpc_res.get("error", "Error desconocido")

    diagnostic = (
        f"Conexión WinRM (puerto 5985) y RPC/SMB denegados en {host}.\n"
        f"• WinRM: {winrm_err}\n"
        f"• RPC/SMB: {rpc_err}\n\n"
        f"💡 Nota para Windows 7 / Standalone: Las cuentas locales sin contraseña o directivas UAC impiden ejecución remota. "
        f"Recomendación rápida 1-clic: Abra PowerShell como Administrador en la máquina y ejecute:\n"
        f"(New-Object System.Net.WebClient).DownloadString('http://10.20.23.221/deploy/win') | iex"
    )

    return {
        "success": False,
        "host": host,
        "os": "windows",
        "action": action,
        "error": diagnostic,
        "winrm_error": winrm_err,
        "rpc_error": rpc_err,
    }


def verify_asset_in_db(host_ip_or_name: str, since_minutes: int = 15) -> Dict[str, Any]:
    """
    Verifica en MariaDB (SSOT) si el equipo reportó telemetría recientemente al receptor GLPI.
    """
    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        with conn.cursor() as cur:
            sql = """
                SELECT id, name, ip_address, operating_system, model, serial_number, 
                       last_glpi_sync_at, status
                FROM inventory_assets
                WHERE (ip_address = %s OR name = %s)
                  AND (last_glpi_sync_at >= NOW() - INTERVAL %s MINUTE OR updated_at >= NOW() - INTERVAL %s MINUTE)
                ORDER BY updated_at DESC
                LIMIT 1
            """
            cur.execute(sql, (host_ip_or_name, host_ip_or_name, since_minutes, since_minutes))
            row = cur.fetchone()

        conn.close()
        if row:
            return {"verified": True, "asset": row}
        return {"verified": False, "message": "No se encontró registro de inventario reciente"}
    except Exception as e:
        return {"verified": False, "error": str(e)}


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
        )
    else:
        result = deploy_linux_ssh(
            host=host,
            user=user,
            password=password,
            key_filename=key_filename,
            target=target,
            action=action,
        )

    if wait_verify and action in ("install", "switch") and result.get("success"):
        logger.info(f"Esperando 8 segundos para verificar ingesta de inventario de {host} en MariaDB...")
        time.sleep(8)
        verification = verify_asset_in_db(host, since_minutes=5)
        result["db_verification"] = verification

    return result


def main():
    parser = argparse.ArgumentParser(description="Desplegador Remoto de Agente GLPI - Valle Seco ITAM")
    parser.add_argument("--host", required=True, help="IP o hostname del equipo objetivo")
    parser.add_argument("--os", choices=["windows", "linux"], required=True, help="Sistema operativo del equipo")
    parser.add_argument("--user", required=True, help="Usuario administrativo (Local o de Dominio)")
    parser.add_argument("--password", help="Contraseña del usuario administrativo")
    parser.add_argument("--domain", help="Nombre del dominio Active Directory (para Windows)")
    parser.add_argument("--key", help="Ruta a clave SSH privada (para Linux)")
    parser.add_argument("--target", choices=["dev", "prod"], default="dev", help="Servidor destino: dev (10.20.23.221) o prod (10.20.23.252)")
    parser.add_argument("--action", choices=["install", "switch", "status"], default="install", help="Acción a ejecutar")
    parser.add_argument("--no-verify", action="store_true", help="No esperar verificación en BD")
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
        key_filename=args.key,
        wait_verify=not args.no_verify,
    )

    if args.json:
        print(json.dumps(res, indent=2, default=str))
    else:
        if res.get("success"):
            print(f"\n✅ Operación '{args.action}' exitosa en {args.host} ({args.os})")
            if "stdout" in res and res["stdout"]:
                print(f"Salida:\n{res['stdout']}")
            if res.get("db_verification", {}).get("verified"):
                asset = res["db_verification"]["asset"]
                print(f"\n📦 Confirmado en Base de Datos GLPI: ID={asset.get('id')} - {asset.get('name')} ({asset.get('operating_system')})")
        else:
            print(f"\n❌ Error en operación '{args.action}' en {args.host}: {res.get('error') or res.get('stderr')}")
            sys.exit(1)


if __name__ == "__main__":
    main()
