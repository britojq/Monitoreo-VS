"""
Parser de contexto de red, geolocalización, duración de sesión y resolución TTY.
"""
import logging
import os
from pathlib import Path
import re
import subprocess
import time
from typing import Optional, Tuple

import httpx

logger = logging.getLogger("monitor.shield.ssh_alert.parser")

SESSION_DIR = Path("/dev/shm/.sonar_ssh_sessions")
LOCKDOWN_FLAG = Path("/dev/shm/system_lockdown_active")
GRACE_PERIOD_FLAG = Path("/dev/shm/terminal_shield_grace_period")
MASTER_OVERRIDE_FLAG = Path("/dev/shm/shield_master_override_active")


def is_private_or_local_ip(ip: str) -> bool:
    """Determina si una dirección IP es privada, loopback o desconocida."""
    if not ip or ip in ("desconocida/local", "localhost", "127.0.0.1", "::1"):
        return True
    if (
        ip.startswith("10.")
        or ip.startswith("192.168.")
        or ip.startswith("127.")
        or (ip.startswith("172.") and 16 <= int(ip.split(".")[1]) <= 31)
    ):
        return True
    return False


def get_network_context(ip: str) -> str:
    """Identifica el rol de red o la entidad asociada a la IP."""
    if not ip or ip in ("desconocida/local", "localhost", "127.0.0.1", "::1"):
        return "Consola Local / Loopback"
    if ip == "192.168.1.220":
        return "Servidor Desarrollo (Esclavo)"
    if ip == "192.168.1.250":
        return "Servidor Producción (Master)"
    if ip == "192.168.1.1":
        return "Router Core / Gateway Valle Seco"
    if ip.startswith("192.168.1."):
        return "Red Corporativa Valle Seco (LAN)"
    if ip.startswith("10.") or ip.startswith("192.168.") or (ip.startswith("172.") and 16 <= int(ip.split(".")[1]) <= 31):
        return "Red Privada Interna (VPN/VLAN)"
    return "IP Pública Externa"


async def get_geolocation(ip: str) -> Optional[dict]:
    """Obtiene información geográfica de IPs públicas."""
    if is_private_or_local_ip(ip):
        return None
    try:
        url = f"http://ip-api.com/json/{ip}?fields=status,country,city,isp"
        async with httpx.AsyncClient(timeout=4.0) as client:
            resp = await client.get(url)
            if resp.status_code == 200:
                data = resp.json()
                if data.get("status") == "success":
                    return {
                        "country": data.get("country", ""),
                        "city": data.get("city", ""),
                        "isp": data.get("isp", ""),
                    }
    except Exception as e:
        logger.debug(f"No se pudo obtener geolocalización para {ip}: {e}")
    return None


def sanitize_session_token(user: str, tty: str) -> str:
    clean_user = re.sub(r"[^a-zA-Z0-9_-]", "_", user)
    clean_tty = re.sub(r"[^a-zA-Z0-9_-]", "_", tty)
    return f"{clean_user}_{clean_tty}.ts"


def record_session_start(user: str, tty: str) -> None:
    try:
        SESSION_DIR.mkdir(parents=True, exist_ok=True)
        token_file = SESSION_DIR / sanitize_session_token(user, tty)
        token_file.write_text(str(time.time()), encoding="utf-8")
    except Exception as e:
        logger.debug(f"No se pudo guardar timestamp de sesión: {e}")


def calculate_session_duration(user: str, tty: str) -> Optional[str]:
    try:
        token_file = SESSION_DIR / sanitize_session_token(user, tty)
        if token_file.exists():
            start_ts = float(token_file.read_text(encoding="utf-8").strip())
            token_file.unlink(missing_ok=True)
            duration_sec = int(time.time() - start_ts)
            if duration_sec < 0:
                return "1 seg"
            if duration_sec < 60:
                return f"{duration_sec} seg"
            if duration_sec < 3600:
                m = duration_sec // 60
                s = duration_sec % 60
                return f"{m} min, {s} seg"
            h = duration_sec // 3600
            m = (duration_sec % 3600) // 60
            s = duration_sec % 60
            return f"{h} h, {m} min, {s} seg"
    except Exception as e:
        logger.debug(f"Error calculando duración de sesión: {e}")
    return None


def find_interactive_terminal_pids(tty: str = "") -> Tuple[int, int]:
    shell_pid = 0
    terminal_pid = 0
    try:
        import psutil
        p = psutil.Process(os.getpid())
        while p and p.pid > 1:
            name = p.name().lower()
            if name in ("bash", "zsh", "sh", "dash", "fish"):
                shell_pid = p.pid
                parent = p.parent()
                if parent and parent.name().lower() in ("konsole", "xterm", "gnome-terminal", "terminator", "tilix", "alacritty", "kitty"):
                    terminal_pid = parent.pid
                break
            p = p.parent()
    except Exception:
        pass

    if shell_pid == 0 and tty:
        clean_tty = tty.replace("/dev/", "")
        try:
            out = subprocess.check_output(["ps", "-t", clean_tty, "-o", "pid,ppid,comm", "--no-headers"], text=True, timeout=2.0)
            for line in out.strip().splitlines():
                parts = line.split()
                if len(parts) >= 3 and parts[2].lower() in ("bash", "zsh", "sh", "dash"):
                    shell_pid = int(parts[0])
                    terminal_pid = int(parts[1])
                    break
        except Exception:
            pass

    return shell_pid, terminal_pid
