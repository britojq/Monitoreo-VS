"""
# ==============================================================================
# 🔑 NOTIFICACIÓN DE ACCESOS Y AUDITORÍA PAM: ssh_alert.py (@IA_ValleSeco_bot)
# Monitoreo de ciclo de vida completo (Login/Logout), elevación de privilegios
# persistencia en MariaDB (audit_logs) y despacho seguro a Telegram en tiempo real
# Ubicación: /scripts/telegram-admin-bot/monitor/ssh_alert.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import html
import json
import logging
import os
import re
import socket
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import httpx

# Configuración de logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("monitor.pam_alert")

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# Importar credenciales protegidas desde el núcleo de seguridad
from monitor.core_shield import IMMUTABLE_OWNER_ID, get_core_bot_token
from monitor.terminal_shield import is_protected_process
from monitor.monitor_db import get_db_connection

# Directorio para tracking de duración de sesiones PAM
SESSION_DIR = Path("/tmp/pam_sessions")


def log_event_to_database(
    user_name: str,
    event: str,
    description: str,
    ip_address: Optional[str] = None,
    user_agent: Optional[str] = None,
    user_role: str = "admin"
) -> bool:
    """Inserta de manera atómica el registro de auditoría en MariaDB audit_logs."""
    try:
        conn = get_db_connection(connect_timeout=3)
        with conn.cursor() as cur:
            now_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            cur.execute(
                """
                INSERT INTO audit_logs (
                    user_name, user_role, event, module, description,
                    ip_address, user_agent, created_at, updated_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    user_name,
                    user_role,
                    event,
                    "pam",
                    description,
                    ip_address or "127.0.0.1",
                    user_agent or "PAM Subsystem",
                    now_ts,
                    now_ts,
                ),
            )
        conn.close()
        logger.info(f"Auditoría PAM registrada en MariaDB: [{event}] {description[:60]}")
        return True
    except Exception as e:
        logger.debug(f"No se pudo registrar auditoría en MariaDB: {e}")
        return False


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
    if ip == "10.20.23.221":
        return "Servidor Desarrollo (Esclavo)"
    if ip == "10.20.23.252":
        return "Servidor Producción (Master)"
    if ip == "10.20.23.1":
        return "Router Core / Gateway Valle Seco"
    if ip.startswith("10.20.23."):
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


def load_configured_proxies() -> list:
    """Carga los proxies configurados en config/config.json."""
    config_file = BASE_DIR / "config" / "config.json"
    if config_file.exists():
        try:
            with open(config_file, "r", encoding="utf-8") as f:
                data = json.load(f)
                return data.get("proxies", [])
        except Exception:
            pass
    return []


LOCKDOWN_FLAG = Path("/dev/shm/system_lockdown_active")
GRACE_PERIOD_FLAG = Path("/dev/shm/terminal_shield_grace_period")
MASTER_OVERRIDE_FLAG = Path("/dev/shm/shield_master_override_active")


async def send_telegram_alert(mensaje: str, reply_markup: Optional[dict] = None) -> bool:
    """Despacha de forma asíncrona y segura la alerta a Telegram del Administrador."""
    bot_token = get_core_bot_token()
    if not bot_token:
        logger.error("No se pudo obtener el Bot Token para alertar.")
        return False

    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    proxies = load_configured_proxies()

    connection_targets = [None]  # Primero conexión directa
    for p in proxies:
        p_url = p.get("url") if isinstance(p, dict) else p
        if p_url:
            connection_targets.append(p_url)

    payload = {
        "chat_id": IMMUTABLE_OWNER_ID,
        "text": mensaje,
        "parse_mode": "HTML",
    }
    if reply_markup:
        payload["reply_markup"] = reply_markup

    for target in connection_targets:
        try:
            async with httpx.AsyncClient(proxy=target, timeout=7.0) as client:
                resp = await client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta PAM enviada exitosamente (Destino: {target or 'Directo'}).")
                    return True
        except Exception as e:
            logger.debug(f"Fallo al enviar alerta PAM con proxy {target}: {e}")
            continue

    logger.error("No se pudo entregar la alerta PAM tras probar conexión directa y todos los proxies.")
    return False



def sanitize_session_token(user: str, tty: str) -> str:
    """Crea un nombre de archivo seguro para registrar el inicio de sesión."""
    clean_user = re.sub(r"[^a-zA-Z0-9_-]", "_", user)
    clean_tty = re.sub(r"[^a-zA-Z0-9_-]", "_", tty)
    return f"{clean_user}_{clean_tty}.ts"


def record_session_start(user: str, tty: str) -> None:
    """Guarda el timestamp de inicio de sesión."""
    try:
        SESSION_DIR.mkdir(parents=True, exist_ok=True)
        token_file = SESSION_DIR / sanitize_session_token(user, tty)
        token_file.write_text(str(time.time()), encoding="utf-8")
    except Exception as e:
        logger.debug(f"No se pudo guardar timestamp de sesión: {e}")


def calculate_session_duration(user: str, tty: str) -> Optional[str]:
    """Calcula y retorna la duración de la sesión en formato legible."""
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
    """Busca el PID de la shell interactiva y de la ventana de terminal asociada."""
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


async def process_pam_event(
    user: str,
    rhost: str,
    tty: str,
    service: str,
    pam_type: str,
    sudo_user: str = "",
    sudo_cmd: str = "",
    is_agent: bool = False,
    caller_pid: int = 0,
) -> None:
    """Procesa el evento PAM, decide el tipo de alerta, guarda en BD y notifica a Telegram."""
    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    net_ctx = get_network_context(rhost)

    # --------------------------------------------------------------------------
    # 0. COMPROBACIÓN DE LOCKDOWN ACTIVO
    # --------------------------------------------------------------------------
    if LOCKDOWN_FLAG.exists() and not MASTER_OVERRIDE_FLAG.exists() and pam_type == "open_session":
        mensaje_block = (
            "🛑 <b>ACCESO BLOQUEADO POR MODO LOCKDOWN</b>\n\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
            f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
            f"🔒 <b>Servicio:</b> <code>{html.escape(service)}</code>\n"
            f"💻 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>Intento de sesión interactiva neutralizado de inmediato por confinamiento activo.</i>"
        )
        log_event_to_database(
            user_name=user,
            event="unauthorized_access",
            description=f"Intento de acceso ({service}) bloqueado por modo LOCKDOWN activo en {tty}",
            ip_address=rhost or "127.0.0.1",
            user_agent=f"PAM/{service} (LOCKDOWN)",
            user_role="admin",
        )
        kb_lock = {
            "inline_keyboard": [
                [{"text": "🔓 DESACTIVAR LOCKDOWN", "callback_data": f"shield_unlock:{hostname}"}]
            ]
        }
        await send_telegram_alert(mensaje_block, reply_markup=kb_lock)
        sys.exit(1)

    # --------------------------------------------------------------------------
    # 1. EVENTO SSH: Inicio de Sesión (open_session)
    # --------------------------------------------------------------------------
    if service == "sshd" and pam_type == "open_session":
        record_session_start(user, tty)

        geo = await get_geolocation(rhost)
        ubicacion_str = ""
        if geo and (geo["city"] or geo["country"]):
            ubicacion_str = f"\n📍 <b>Ubicación:</b> {html.escape(geo['city'])}, {html.escape(geo['country'])} ({html.escape(geo['isp'])})"

        mensaje = (
            "🟢 <b>Acceso SSH Detectado</b>\n\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
            f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
            f"🌐 <b>IP Origen:</b> <code>{html.escape(rhost)}</code> ({net_ctx}){ubicacion_str}\n"
            f"💻 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>Notificación de seguridad en tiempo real • PAM</i>"
        )

        desc_bd = f"Acceso SSH abierto desde {rhost} ({net_ctx}) en terminal {tty}"
        log_event_to_database(
            user_name=user,
            event="ssh_login",
            description=desc_bd,
            ip_address=rhost,
            user_agent=f"PAM/sshd ({tty})",
            user_role="admin",
        )
        await send_telegram_alert(mensaje)

    # --------------------------------------------------------------------------
    # 2. EVENTO SSH: Cierre de Sesión (close_session)
    # --------------------------------------------------------------------------
    elif service == "sshd" and pam_type == "close_session":
        duration = calculate_session_duration(user, tty)
        duracion_str = f"\n⏱️ <b>Duración:</b> <code>{duration}</code>" if duration else ""

        mensaje = (
            "🔴 <b>Desconexión SSH</b>\n\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
            f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
            f"🌐 <b>IP Origen:</b> <code>{html.escape(rhost)}</code> ({net_ctx}){duracion_str}\n"
            f"💻 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "<i>Auditoría de desconexión en tiempo real • PAM</i>"
        )

        desc_bd = f"Desconexión SSH finalizada ({rhost}, {net_ctx})"
        if duration:
            desc_bd += f" - Duración: {duration}"

        log_event_to_database(
            user_name=user,
            event="ssh_logout",
            description=desc_bd,
            ip_address=rhost,
            user_agent=f"PAM/sshd ({tty})",
            user_role="admin",
        )
        await send_telegram_alert(mensaje)

    # --------------------------------------------------------------------------
    # 3. EVENTO SUDO / SU: Elevación de Privilegios o Comandos Administrativos
    # --------------------------------------------------------------------------
    elif service in ("sudo", "su") and pam_type == "open_session":
        origin_user = sudo_user or os.getenv("SUDO_USER") or user
        cmd = sudo_cmd or os.getenv("SUDO_COMMAND") or f"Sesión interactiva {service}"

        cmd_clean = cmd.strip()
        cmd_lower = cmd_clean.lower()
        cmd_parts = cmd_clean.split()
        first_token = Path(cmd_parts[0]).name.lower() if cmd_parts else ""

        is_shell_elevation = (
            first_token in ("su", "bash", "sh", "zsh", "dash")
            or any(opt in cmd_parts for opt in ("-i", "-s", "--shell"))
            or cmd_lower in ("su", "su -", "/bin/su", "/usr/bin/su", "/bin/bash", "/usr/bin/bash")
        )

        # Determinar si proviene de una terminal interactiva (Konsole, TTY, etc.)
        is_interactive = bool(
            tty
            and not tty.startswith("none")
            and tty != "?"
            and (tty.startswith("pts/") or tty.startswith("tty") or tty.startswith("/dev/"))
        )

        # Determinar si proviene del entorno del Asistente Técnico (agy / Antigravity)
        shell_pid, terminal_pid = (0, 0)
        if is_interactive:
            shell_pid, terminal_pid = find_interactive_terminal_pids(tty)

        is_assistant = (
            is_agent
            or os.getenv("ANTIGRAVITY_AGENT") == "1"
            or (caller_pid > 1 and is_protected_process(caller_pid))
            or (shell_pid > 1 and is_protected_process(shell_pid))
            or is_protected_process(os.getpid())
            or (terminal_pid > 1 and is_protected_process(terminal_pid))
        )
        if is_assistant:
            desc_bd = f"Comando administrativo (Asistente Técnico Local) vía {service} ({origin_user} -> {user}): {cmd} en {tty}"
            log_event_to_database(
                user_name=f"{origin_user} [Asistente IA]",
                event="sudo_command",
                description=desc_bd,
                ip_address=rhost or "127.0.0.1",
                user_agent=f"PAM/{service} ({tty}) [Asistente IA]",
                user_role="admin",
            )
            logger.info(f"Sudo ejecutado por Asistente IA ({origin_user}): {cmd[:50]} (Alerta Telegram suprimida)")
            return

        # Comprobar si hay ventana de mantenimiento autorizada o Override Maestro
        grace_active = MASTER_OVERRIDE_FLAG.exists()
        if not grace_active and GRACE_PERIOD_FLAG.exists():
            try:
                exp = float(GRACE_PERIOD_FLAG.read_text().strip())
                if time.time() < exp:
                    grace_active = True
            except Exception:
                pass

        if is_interactive and not grace_active:

            # Comando sudo desde terminal interactiva de usuario sin autorización previa: ALERTA INTERACTIVA
            titulo = "⚡ <b>Elevación de Privilegios (SUDO)</b>" if (is_shell_elevation and origin_user != user) else "⚠️ <b>Comando SUDO Interactivo</b>"
            event_tag = "privilege_escalation" if (is_shell_elevation and origin_user != user) else "sudo_command"

            mensaje = (
                f"{titulo}\n\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                f"👤 <b>Usuario Origen:</b> <code>{html.escape(origin_user)}</code>\n"
                f"👑 <b>Usuario Destino:</b> <code>{html.escape(user)}</code>\n"
                f"🛠️ <b>Comando:</b> <code>{html.escape(cmd)}</code>\n"
                f"💻 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                "⚠️ <i>Ejecución interactiva con privilegios de superusuario. Seleccione una acción de contención:</i>"
            )

            keyboard = {
                "inline_keyboard": [
                    [
                        {"text": "🔒 REINICIAR (LOCKDOWN)", "callback_data": f"shield_lockdown:{hostname}:{shell_pid}:{terminal_pid}"},
                        {"text": "🚫 MATAR TERMINAL", "callback_data": f"shield_kill:{hostname}:{shell_pid}:{terminal_pid}"},
                    ],
                    [
                        {"text": "✅ SOY YO (AUTORIZAR 30m)", "callback_data": f"shield_auth:{hostname}:{shell_pid}:{terminal_pid}"}
                    ],
                ]
            }

            desc_bd = f"Comando administrativo interactivo ({origin_user} -> {user}): {cmd} en {tty}"
            log_event_to_database(
                user_name=origin_user,
                event=event_tag,
                description=desc_bd,
                ip_address=rhost or "127.0.0.1",
                user_agent=f"PAM/{service} ({tty})",
                user_role="admin",
            )
            await send_telegram_alert(mensaje, reply_markup=keyboard)
        else:
            # Comando administrativo no interactivo (ej. bots, tareas programadas, o ventana autorizada)
            desc_prefix = "Comando administrativo (Override Maestro Activo)" if MASTER_OVERRIDE_FLAG.exists() else "Comando administrativo"
            desc_bd = f"{desc_prefix} vía {service} ({origin_user} -> {user}): {cmd}"
            log_event_to_database(
                user_name=origin_user,
                event="sudo_command",
                description=desc_bd,
                ip_address=rhost or "127.0.0.1",
                user_agent=f"PAM/{service} ({tty})",
                user_role="admin",
            )


def main():
    parser = argparse.ArgumentParser(description="PAM Security Alert Dispatcher")
    parser.add_argument("pos_user", nargs="?", default="")
    parser.add_argument("pos_rhost", nargs="?", default="")
    parser.add_argument("pos_tty", nargs="?", default="")
    parser.add_argument("--user", default="")
    parser.add_argument("--rhost", default="")
    parser.add_argument("--tty", default="")
    parser.add_argument("--service", default="")
    parser.add_argument("--type", default="")
    parser.add_argument("--sudo-user", default="")
    parser.add_argument("--sudo-cmd", default="")
    parser.add_argument("--is-agent", action="store_true", default=False)
    parser.add_argument("--caller-pid", type=int, default=0)

    args = parser.parse_args()

    # Prioridad: flags CLI > variables PAM_ > posicionales CLI
    user = args.user or os.getenv("PAM_USER") or args.pos_user or "desconocido"
    rhost = args.rhost or os.getenv("PAM_RHOST") or args.pos_rhost or "desconocida/local"
    tty = args.tty or os.getenv("PAM_TTY") or args.pos_tty or "consola"
    service = args.service or os.getenv("PAM_SERVICE") or "sshd"
    pam_type = args.type or os.getenv("PAM_TYPE") or "open_session"
    sudo_user = args.sudo_user or os.getenv("SUDO_USER") or ""
    sudo_cmd = args.sudo_cmd or os.getenv("SUDO_COMMAND") or ""

    try:
        asyncio.run(
            process_pam_event(
                user=user,
                rhost=rhost,
                tty=tty,
                service=service,
                pam_type=pam_type,
                sudo_user=sudo_user,
                sudo_cmd=sudo_cmd,
                is_agent=args.is_agent,
                caller_pid=args.caller_pid,
            )
        )
    except Exception as e:
        logger.error(f"Excepción en monitor.pam_alert: {e}")
    sys.exit(0)


if __name__ == "__main__":
    main()
