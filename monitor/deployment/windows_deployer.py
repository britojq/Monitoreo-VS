"""
Despliegue y conmutación remota en estaciones y servidores Windows (WinRM y RPC/WMI/atexec).
"""

import logging
from pathlib import Path
import subprocess
import sys
from typing import Any, Dict, Optional

from monitor.deployment.target_resolver import get_target_url, is_port_open

logger = logging.getLogger("monitor.agent_deployer.windows")


def deploy_windows_winrm(
    host: str,
    user: str,
    password: str,
    domain: Optional[str] = None,
    port: int = 5985,
    target: str = "dev",
    action: str = "install",
    timeout: int = 90,
    server_url: Optional[str] = None,
    deploy_base: Optional[str] = None,
) -> Dict[str, Any]:
    """
    Ejecuta el despliegue o conmutación remota en Windows vía WinRM (soporta Dominio).
    """
    try:
        import winrm
    except ImportError:
        return {"success": False, "error": "Librería pywinrm no disponible en el entorno Python."}

    resolved_server_url, base_url = get_target_url(target, server_url, deploy_base)

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
            ps_script = (
                f"$s='{resolved_server_url}'; "
                f"$c=(New-Object System.Net.WebClient).DownloadString('{base_url}/deploy/switch/win'); "
                f"& ([scriptblock]::Create($c)) -CustomUrl $s"
            )
        elif action == "status":
            ps_script = "(Get-Service glpi-agent -ErrorAction SilentlyContinue).Status; if (Test-Path 'HKLM:\\SOFTWARE\\GLPI-Agent') { (Get-ItemProperty 'HKLM:\\SOFTWARE\\GLPI-Agent').server }"
        else:
            # install
            ps_script = (
                f"$s='{resolved_server_url}'; $b='{base_url}/instaladores'; "
                f"$c=(New-Object System.Net.WebClient).DownloadString('{base_url}/deploy/win'); "
                f"& ([scriptblock]::Create($c)) -ServerUrl $s -BaseUrl $b"
            )

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
    server_url: Optional[str] = None,
    deploy_base: Optional[str] = None,
) -> Dict[str, Any]:
    """
    Ejecuta el despliegue o comando remoto en Windows vía WMI / SMB (Impacket).
    Compatible con Windows 7 (PowerShell 2.0) y sistemas modernos sin WinRM habilitado.
    """
    resolved_server_url, base_url = get_target_url(target, server_url, deploy_base)

    if action == "switch":
        win_cmd = f'powershell -ExecutionPolicy Bypass -NoProfile -Command "$s=\'{resolved_server_url}\'; $c=(New-Object System.Net.WebClient).DownloadString(\'{base_url}/deploy/switch/win\'); & ([scriptblock]::Create($c)) -CustomUrl $s"'
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
    server_url: Optional[str] = None,
    deploy_base: Optional[str] = None,
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
            server_url=server_url,
            deploy_base=deploy_base,
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
        server_url=server_url,
        deploy_base=deploy_base,
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
        f"Recomendación rápida 1-clic: Abra PowerShell como Administrador en la máquina y ejecute el comando descargable desde la consola web."
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
