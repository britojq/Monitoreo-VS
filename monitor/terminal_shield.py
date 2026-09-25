#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ ESCUDO DE TERMINAL INTERACTIVA & LOCKDOWN: terminal_shield.py (@IA_ValleSeco_bot)
# Detección en tiempo real de consolas, Konsole, shells interactivas y mitigación activa
# Ubicación: /scripts/telegram-admin-bot/monitor/terminal_shield.py
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
import signal
import socket
import subprocess
import sys
import time
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import httpx

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("monitor.terminal_shield")

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.core_shield import IMMUTABLE_OWNER_ID, get_core_bot_token, verify_system_auth_token

LOCKDOWN_FLAG = Path("/dev/shm/system_lockdown_active")
GRACE_PERIOD_FLAG = Path("/dev/shm/terminal_shield_grace_period")
RECENT_ALERTS_FLAG = Path("/dev/shm/terminal_shield_recent.json")
INFRACTIONS_FLAG = Path("/dev/shm/terminal_shield_infractions.json")
MASTER_OVERRIDE_FLAG = Path("/dev/shm/shield_master_override_active")

from monitor.monitor_db import get_db_connection


def log_event_to_database(
    user_name: str,
    event: str,
    description: str,
    ip_address: str = "127.0.0.1",
    user_agent: str = "Sentinel Shield",
    user_role: str = "admin",
) -> bool:
    """Registra el evento de seguridad en la tabla audit_logs de MariaDB."""
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
                    ip_address,
                    user_agent,
                    now_ts,
                    now_ts,
                ),
            )
        conn.close()
        return True
    except Exception as e:
        logger.debug(f"Error registrando en MariaDB: {e}")
        return False


def load_configured_proxies() -> list:
    """Obtiene proxies configurados para redundancia en Telegram."""
    for cfg in [BASE_DIR / "config" / "config.json", BASE_DIR / "config.json"]:
        if cfg.exists():
            try:
                data = json.loads(cfg.read_text(encoding="utf-8"))
                return data.get("telegram_proxies", [])
            except Exception:
                pass
    return []


async def send_telegram_alert(mensaje: str, reply_markup: Optional[dict] = None) -> bool:
    """Despacha la alerta a Telegram exclusivamente al Administrador con botones interactivos."""
    bot_token = get_core_bot_token()
    if not bot_token:
        logger.error("No se pudo obtener el Bot Token para alertar.")
        return False

    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    proxies = load_configured_proxies()
    connection_targets = [None]
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
            async with httpx.AsyncClient(proxy=target, timeout=6.0) as client:
                resp = await client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info("Alerta de terminal enviada exitosamente a Telegram.")
                    return True
        except Exception as e:
            logger.debug(f"Fallo enviando alerta con proxy {target}: {e}")
            continue

    logger.error("No se pudo entregar la alerta a Telegram tras agotar conexiones.")
    return False


def safe_unlink(p: Path) -> None:
    """Elimina de forma segura un archivo flag, soportando permisos root en /dev/shm."""
    try:
        p.unlink(missing_ok=True)
    except PermissionError:
        try:
            p.write_text("", encoding="utf-8")
        except Exception:
            pass
        try:
            subprocess.run(["sudo", "rm", "-f", str(p)], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        except Exception:
            pass
    except Exception:
        pass


def is_master_override_active() -> bool:
    """Comprueba si el Override Maestro de emergencia del Owner está activo (hasta el próximo reinicio)."""
    try:
        return MASTER_OVERRIDE_FLAG.exists() and MASTER_OVERRIDE_FLAG.stat().st_size > 0
    except Exception:
        return False


def execute_master_override(key: str, source: str = "Terminal / CLI") -> dict:
    """Valida el token de autorización administrativa y desactiva el escudo hasta el próximo reinicio."""
    if not verify_system_auth_token(key):
        log_event_to_database(
            user_name="intruder",
            event="master_override_failed",
            description=f"Intento fallido de validación de token desde {source}.",
            ip_address="127.0.0.1",
            user_role="unknown",
        )
        return {"success": False, "message": "Token de validación inválido. Acceso denegado."}

    payload = {
        "activated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "activated_by": "Administrador (Token validado)",
        "source": source,
        "expires": "until_reboot",
    }
    MASTER_OVERRIDE_FLAG.write_text(json.dumps(payload), encoding="utf-8")
    try:
        os.chmod(str(MASTER_OVERRIDE_FLAG), 0o666)
    except Exception:
        pass

    safe_unlink(LOCKDOWN_FLAG)
    reset_infractions()

    # Cerrar cualquier diálogo de advertencia Qt abierto (excepto si fue invocado desde el propio diálogo)
    if "shield_dialog" not in sys.argv[0]:
        subprocess.run(["pkill", "-f", "shield_dialog.py"], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    desc = f"AUTORIZACIÓN ADMINISTRATIVA CONCEDIDA ({source}). Escudo desactivado hasta el próximo reinicio."
    log_event_to_database(
        user_name="Owner",
        event="master_override_activated",
        description=desc,
        ip_address="127.0.0.1",
        user_role="owner",
    )

    # Notificar por Telegram al Owner en segundo plano desacoplado (no bloquea si no hay internet)
    def _dispatch_override_alert():
        try:
            hostname = socket.gethostname()
            now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            msg = (
                "🔑 <b>AUTORIZACIÓN ADMINISTRATIVA ACTIVADA</b>\n\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                f"👤 <b>Identidad:</b> Validado como <b>Owner legítimo</b>\n"
                f"🚪 <b>Origen:</b> <code>{html.escape(source)}</code>\n"
                f"⏰ <b>Hora:</b> <code>{now_str}</code>\n"
                "🛡️ <b>Estado del Escudo:</b> <code>SUSPENDIDO HASTA PRÓXIMO REINICIO</code>\n\n"
                "<i>Todas las restricciones y alertas automáticas quedan desactivadas para mantenimiento. "
                "La protección perimetral se restablecerá automáticamente al reiniciar el equipo.</i>"
            )
            asyncio.run(send_telegram_alert(msg))
        except Exception:
            pass

    import threading
    t = threading.Thread(target=_dispatch_override_alert, daemon=True)
    t.start()

    return {"success": True, "message": "Token validado con éxito. Escudo desactivado hasta el próximo reinicio."}


def is_lockdown_active() -> bool:
    """Comprueba si el modo lockdown está actualmente activo en el host."""
    try:
        return LOCKDOWN_FLAG.exists() and LOCKDOWN_FLAG.stat().st_size > 0
    except Exception:
        return False


def is_grace_period_active() -> Tuple[bool, float]:
    """Comprueba si hay una ventana de mantenimiento autorizada vigente."""
    if not GRACE_PERIOD_FLAG.exists():
        return False, 0.0
    try:
        exp = float(GRACE_PERIOD_FLAG.read_text(encoding="utf-8").strip())
        if time.time() < exp:
            return True, exp
        safe_unlink(GRACE_PERIOD_FLAG)
    except Exception:
        pass
    return False, 0.0


def get_process_name(pid: int) -> str:
    """Obtiene el nombre del ejecutable a partir de su PID."""
    if pid <= 0:
        return "desconocido"
    comm_path = Path(f"/proc/{pid}/comm")
    if comm_path.exists():
        try:
            return comm_path.read_text(encoding="utf-8").strip()
        except Exception:
            pass
    return "desconocido"


def is_recent_alert(key: str, window_sec: int = 40) -> bool:
    """Verifica si ya se alertó recientemente para evitar saturación de mensajes."""
    try:
        data = {}
        if RECENT_ALERTS_FLAG.exists():
            data = json.loads(RECENT_ALERTS_FLAG.read_text(encoding="utf-8"))
        now = time.time()
        last_time = data.get(key, 0)
        if now - last_time < window_sec:
            return True
        data[key] = now
        # Limpiar entradas viejas
        data = {k: v for k, v in data.items() if now - v < 300}
        RECENT_ALERTS_FLAG.write_text(json.dumps(data), encoding="utf-8")
    except Exception:
        pass
    return False


def record_infraction(reason: str = "terminal_killed", window_sec: int = 1800) -> int:
    """Registra una infracción en memoria volátil y devuelve el conteo acumulado (ventana de 30 min)."""
    count = 1
    now = time.time()
    try:
        if INFRACTIONS_FLAG.exists():
            data = json.loads(INFRACTIONS_FLAG.read_text(encoding="utf-8"))
            last_ts = data.get("last_ts", 0)
            prev_count = data.get("count", 0)
            if now - last_ts < window_sec:
                count = prev_count + 1
            else:
                count = 1
        payload = {
            "count": count,
            "last_ts": now,
            "last_reason": reason,
            "updated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        }
        INFRACTIONS_FLAG.write_text(json.dumps(payload), encoding="utf-8")
        try:
            os.chmod(str(INFRACTIONS_FLAG), 0o666)
        except Exception:
            pass
    except Exception as e:
        logger.warning(f"Error gestionando contador de infracciones: {e}")
    return count


def reset_infractions() -> None:
    """Limpia el historial de infracciones acumuladas."""
    try:
        INFRACTIONS_FLAG.unlink(missing_ok=True)
    except PermissionError:
        try:
            INFRACTIONS_FLAG.write_text(json.dumps({"count": 0, "last_ts": 0, "updated_at": ""}), encoding="utf-8")
        except Exception:
            pass
        try:
            subprocess.run(["sudo", "rm", "-f", str(INFRACTIONS_FLAG)], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        except Exception:
            pass
    except Exception:
        pass


def launch_shield_dialog(mode: str, attempt: int = 1, seconds: int = 10, dry_run: bool = False) -> None:
    """Despliega el diálogo gráfico visual PyQt5 en la pantalla activa del usuario."""
    dialog_script = BASE_DIR / "monitor" / "shield_dialog.py"
    if not dialog_script.exists():
        logger.warning("No se encontró monitor/shield_dialog.py para mostrar la alerta visual.")
        return

    env_vars = {
        "DISPLAY": ":0",
        "WAYLAND_DISPLAY": "wayland-0",
        "XDG_RUNTIME_DIR": "/run/user/1000",
        "PATH": os.environ.get("PATH", "/usr/local/bin:/usr/bin:/bin"),
    }

    cmd = [
        "/usr/bin/python3",
        str(dialog_script),
        "--mode", mode,
        "--attempt", str(attempt),
        "--seconds", str(seconds),
    ]
    if dry_run:
        cmd.append("--dry-run")

    try:
        # Si corre bajo root (ej. PAM seteuid), lanzar como el usuario de la sesión gráfica britojab
        if os.geteuid() == 0:
            full_cmd = ["sudo", "-u", "britojab", "env", f"DISPLAY=:0", f"WAYLAND_DISPLAY=wayland-0", f"XDG_RUNTIME_DIR=/run/user/1000"] + cmd
        else:
            full_cmd = cmd

        subprocess.Popen(
            full_cmd,
            env={**os.environ, **env_vars},
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            start_new_session=True,
        )
        logger.info(f"Diálogo Qt ({mode}, infracción {attempt}) lanzado exitosamente.")
    except Exception as e:
        logger.warning(f"No se pudo desplegar diálogo visual Qt: {e}")


def execute_kill(pid: int, ppid: int = 0, dry_run: bool = False) -> dict:
    """Mata el proceso de la terminal, registra la infracción y lanza advertencia visual o reinicio."""
    killed = []
    failed = []

    if not dry_run:
        # 1. Matar proceso de la shell
        if pid > 1:
            try:
                os.kill(pid, signal.SIGKILL)
                killed.append(f"Shell (PID: {pid})")
            except ProcessLookupError:
                killed.append(f"Shell (PID: {pid} ya terminada)")
            except Exception as e:
                failed.append(f"PID {pid}: {e}")

        # 2. Si el padre es un emulador de terminal gráfico o shell, matarlo también
        if ppid > 1:
            p_name = get_process_name(ppid).lower()
            if p_name in ("konsole", "xterm", "gnome-terminal", "terminator", "tilix", "alacritty", "kitty", "bash", "zsh"):
                try:
                    os.kill(ppid, signal.SIGKILL)
                    killed.append(f"{p_name} (PID: {ppid})")
                except ProcessLookupError:
                    pass
                except Exception as e:
                    failed.append(f"PPID {ppid}: {e}")
    else:
        killed.append(f"Simulación (PID: {pid}, PPID: {ppid})")

    # 4. Registrar infracción acumulada y disparar el diálogo correspondiente
    count = record_infraction(reason="terminal_killed")
    reboot_triggered = count >= 3

    if reboot_triggered:
        launch_shield_dialog(mode="reboot_limit", attempt=count, seconds=10, dry_run=dry_run)
        desc = (
            f"Terminal neutralizada por el Administrador. 3er INTENTO NO AUTORIZADO ALCANZADO ({count}/3). "
            f"Protocolo de reinicio de seguridad activado en pantalla (10s): {', '.join(killed) if killed else 'Shell'}"
        )
        event_tag = "reboot_limit_triggered"
    else:
        launch_shield_dialog(mode="warning", attempt=count, seconds=10, dry_run=dry_run)
        desc = (
            f"Terminal neutralizada por el Administrador ({', '.join(killed) if killed else 'Ninguna shell'}). "
            f"Advertencia {count} de 3 notificada en pantalla (10s)."
        )
        event_tag = "terminal_killed"

    log_event_to_database(
        user_name="admin",
        event=event_tag,
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {
        "success": len(killed) > 0,
        "killed": killed,
        "failed": failed,
        "infractions": count,
        "reboot_triggered": reboot_triggered,
    }


def is_protected_process(pid: int) -> bool:
    """Verifica si el PID o sus ancestros/descendientes pertenecen al entorno del agente/asistente de desarrollo."""
    if pid <= 1:
        return False
    try:
        import psutil
        p = psutil.Process(pid)
        while p and p.pid > 1:
            name = p.name().lower()
            try:
                cmdline = " ".join(p.cmdline()).lower()
            except Exception:
                cmdline = ""
            try:
                environ = p.environ()
            except Exception:
                environ = {}

            if (
                any(k in name for k in ("agy", "antigravity"))
                or any(k in cmdline for k in ("agy", "antigravity-cli", "faadaf35-22d4-442e"))
                or "ANTIGRAVITY_AGENT" in environ
                or "ANTIGRAVITY_CONVERSATION_ID" in environ
            ):
                return True

            # Si el proceso tiene algún hijo directo que sea agy/antigravity (ej. bash que invocó a agy)
            try:
                for child in p.children(recursive=False):
                    cname = child.name().lower()
                    if any(k in cname for k in ("agy", "antigravity")):
                        return True
            except Exception:
                pass

            p = p.parent()
    except Exception:
        pass
    return False


def execute_enforce_sudo(
    user: str,
    sudo_user: str,
    sudo_cmd: str,
    tty: str,
    ppid: int = 0,
    dry_run: bool = False,
) -> dict:
    """Aplica contención autónoma en 0 segundos ante intentos interactivos de sudo no autorizados."""
    # 0. Si hay Override Maestro activo o ventana autorizada, permitir
    if is_master_override_active():
        return {"allowed": True, "reason": "master_override_active"}

    grace_active, _ = is_grace_period_active()
    if grace_active:
        return {"allowed": True, "reason": "grace_period_active"}

    # Determinar el PID de la shell interactiva en el TTY
    target_pid = ppid
    terminal_emulator_pid = 0
    if tty and tty not in ("none", "?", "consola"):
        clean_tty = tty.replace("/dev/", "")
        try:
            out = subprocess.check_output(
                ["ps", "-t", clean_tty, "-o", "pid,ppid,comm", "--no-headers"],
                text=True,
                timeout=2.0,
                stderr=subprocess.DEVNULL,
            )
            for line in out.strip().splitlines():
                parts = line.split()
                if len(parts) >= 3 and parts[2].lower() in ("bash", "zsh", "sh", "dash", "fish"):
                    target_pid = int(parts[0])
                    terminal_emulator_pid = int(parts[1])
                    break
        except Exception:
            pass

    # 1. Si el proceso que invoca pertenece al entorno del agente/asistente, permitir y no matar
    if (target_pid > 1 and is_protected_process(target_pid)) or (ppid > 1 and is_protected_process(ppid)):
        logger.info(f"Sudo interactivo permitido para entorno de desarrollo/asistente (PID {target_pid}).")
        return {"allowed": True, "reason": "protected_agent_process"}

    # 2. Intento no autorizado en terminal interactiva: CONTENCIÓN AUTÓNOMA INMEDIATA
    count = record_infraction(reason="unauthorized_sudo")
    reboot_triggered = (count >= 3)

    # Lanzar diálogo visual Qt en pantalla física
    mode = "reboot_limit" if reboot_triggered else "warning"
    launch_shield_dialog(mode=mode, attempt=count, seconds=10, dry_run=dry_run)

    # Mensaje de auditoría en base de datos
    action_type = "su / su -" if "su" in sudo_cmd.lower() else "sudo"
    event_tag = "reboot_limit_triggered" if reboot_triggered else "unauthorized_sudo_blocked"
    desc = (
        f"Intento no autorizado de {action_type} ({user} -> {sudo_user}): '{sudo_cmd}' en {tty}. "
        f"Infracción {count}/3 registrada. Shell (PID {ppid}) neutralizada."
    )
    log_event_to_database(
        user_name=user or "desconocido",
        event=event_tag,
        description=desc,
        ip_address="127.0.0.1",
        user_agent=f"PAM/{action_type} ({tty})",
        user_role="admin",
    )

    # Despachar alerta asíncrona a Telegram en segundo plano (si hay internet disponible)
    def _dispatch_alert():
        try:
            hostname = socket.gethostname()
            now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            if reboot_triggered:
                msg = (
                    "🛑 <b>REINCIDENCIA CRÍTICA: LÍMITE DE FALTAS ALCANZADO</b>\n\n"
                    f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                    f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
                    f"👑 <b>Destino:</b> <code>{html.escape(sudo_user)}</code>\n"
                    f"🛠️ <b>Comando:</b> <code>{html.escape(sudo_cmd)}</code>\n"
                    f"📟 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
                    f"⏰ <b>Hora:</b> <code>{now_str}</code>\n"
                    f"🚨 <b>Infracciones:</b> <code>{count} de 3 (LÍMITE ALCANZADO)</code>\n\n"
                    "⚡ <i>Protocolo de reinicio de contención activado en pantalla (10s).</i>"
                )
            else:
                msg = (
                    f"⚠️ <b>INTENTO NO AUTORIZADO DE {action_type.upper()} NEUTRALIZADO</b>\n\n"
                    f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                    f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
                    f"👑 <b>Destino:</b> <code>{html.escape(sudo_user)}</code>\n"
                    f"🛠️ <b>Comando:</b> <code>{html.escape(sudo_cmd)}</code>\n"
                    f"📟 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
                    f"⏰ <b>Hora:</b> <code>{now_str}</code>\n"
                    f"⚠️ <b>Infracción:</b> <code>{count} de 3</code>\n"
                    "🛡️ <b>Acción:</b> <code>Terminal clausurada en 0s</code>\n\n"
                    "<i>Al registrarse el 3er intento no autorizado, el equipo se reiniciará automáticamente.</i>"
                )
            keyboard = {
                "inline_keyboard": [
                    [
                        {"text": "🔒 REINICIAR (LOCKDOWN)", "callback_data": f"shield_lockdown:{hostname}:{ppid}:0"},
                        {"text": "✅ SOY YO (AUTORIZAR 30m)", "callback_data": f"shield_auth:{hostname}:{ppid}:0"}
                    ]
                ]
            }
            asyncio.run(send_telegram_alert(msg, reply_markup=keyboard))
        except Exception:
            pass

    import threading
    t = threading.Thread(target=_dispatch_alert, daemon=True)
    t.start()

    # 3. Matar el proceso de la shell interactiva en 0 segundos
    killed_pids = []
    if not dry_run:
        pids_to_kill = set()
        if target_pid > 1 and not is_protected_process(target_pid):
            pids_to_kill.add(target_pid)
        if ppid > 1 and not is_protected_process(ppid):
            pids_to_kill.add(ppid)
        for p in pids_to_kill:
            try:
                os.kill(p, signal.SIGKILL)
                killed_pids.append(p)
            except Exception:
                pass

    return {
        "allowed": False,
        "infractions": count,
        "reboot_triggered": reboot_triggered,
        "killed_pids": killed_pids,
    }


def execute_lockdown(target_pid: int = 0, target_ppid: int = 0, target_user: str = "", dry_run: bool = False) -> dict:
    """Protocolo de Lockdown activo: mata terminales, lanza diálogo Qt de 10s y reinicia el equipo."""
    if not dry_run:
        # 1. Matar terminal solicitada
        if target_pid > 1:
            try:
                os.kill(target_pid, signal.SIGKILL)
            except Exception:
                pass
        if target_ppid > 1:
            try:
                os.kill(target_ppid, signal.SIGKILL)
            except Exception:
                pass

        # 2. Matar instancias específicas de emuladores interactivos (excluyendo procesos protegidos)
        for proc in ("konsole", "xterm", "gnome-terminal"):
            try:
                import psutil
                for p in psutil.process_iter(['pid', 'name']):
                    if p.info['name'] and proc in p.info['name'].lower():
                        if not is_protected_process(p.info['pid']):
                            try:
                                p.kill()
                            except Exception:
                                pass
            except Exception:
                pass

    # 3. Lanzar diálogo visual de reinicio forzado (10s con countdown)
    launch_shield_dialog(mode="lockdown", attempt=3, seconds=10, dry_run=dry_run)

    desc = "REINICIO FORZADO POR EL ADMINISTRADOR (Lockdown). Terminales terminadas y diálogo de reinicio de 10s activado."
    log_event_to_database(
        user_name="admin",
        event="system_lockdown_reboot",
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {
        "success": True,
        "message": "Protocolo de reinicio de seguridad activado en pantalla (10s).",
        "reboot_triggered": True,
    }


def execute_unlock() -> dict:
    """Levanta el estado de emergencia, resetea infracciones y restaura servicios."""
    safe_unlink(LOCKDOWN_FLAG)
    safe_unlink(MASTER_OVERRIDE_FLAG)
    safe_unlink(GRACE_PERIOD_FLAG)
    reset_infractions()
    subprocess.run(["sudo", "systemctl", "start", "krfb"], check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    desc = "Estado de contención desactivado. Infracciones reseteadas y normalidad restaurada."
    log_event_to_database(
        user_name="admin",
        event="lockdown_lifted",
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {"success": True, "message": "Escudo restaurado a estado normal e infracciones reseteadas."}


def execute_auth(minutes: int = 30) -> dict:
    """Autoriza temporalmente el acceso interactivo y resetea el contador de infracciones."""
    reset_infractions()
    exp = time.time() + (minutes * 60)
    GRACE_PERIOD_FLAG.write_text(str(exp), encoding="utf-8")
    try:
        os.chmod(str(GRACE_PERIOD_FLAG), 0o666)
    except Exception:
        pass
    exp_dt = datetime.fromtimestamp(exp).strftime("%H:%M:%S")

    desc = f"Ventana de mantenimiento interactivo autorizada por {minutes} min (hasta {exp_dt}). Infracciones reseteadas a 0."
    log_event_to_database(
        user_name="admin",
        event="terminal_authorized",
        description=desc,
        ip_address="127.0.0.1",
        user_role="admin",
    )
    return {"success": True, "expiry": exp_dt, "minutes": minutes}


async def process_terminal_open(
    pid: int,
    ppid: int,
    user: str,
    tty: str,
    display: str = "",
) -> None:
    """Procesa el evento de apertura de terminal interactiva."""
    # 0. Si hay Override Maestro del Owner activo, permitir sin restricciones ni alertas
    if is_master_override_active():
        logger.info(f"Terminal PID {pid} abierta bajo Override Maestro de emergencia (Owner autorizado).")
        return

    # 1. Si hay Lockdown activo, matar fulminantemente el proceso
    if is_lockdown_active():
        logger.warning(f"Lockdown activo: eliminando proceso de terminal PID {pid} de forma inmediata.")
        if pid > 1:
            try:
                os.kill(pid, signal.SIGKILL)
            except Exception:
                pass
        sys.exit(1)

    # 2. Si la sesión está dentro del periodo de gracia autorizado, permitir sin alertar
    grace_active, exp_ts = is_grace_period_active()
    if grace_active:
        logger.info(f"Terminal PID {pid} abierta bajo ventana de mantenimiento autorizada (expira en {int(exp_ts - time.time())}s).")
        return

    # 3. Deduplicación anti-spam
    key = f"{user}:{tty}"
    if is_recent_alert(key, window_sec=30):
        logger.info(f"Alerta omitida por deduplicación reciente ({key}).")
        return

    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    proc_name = get_process_name(pid)
    parent_name = get_process_name(ppid)

    entorno = display or "Consola Local / TTY"
    if display:
        entorno = f"KDE Plasma / VNC ({display})"

    mensaje = (
        "🚨 <b>Alerta de Seguridad: Terminal Interactiva</b>\n\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"👤 <b>Usuario:</b> <code>{html.escape(user)}</code>\n"
        f"📟 <b>Terminal:</b> <code>{html.escape(tty)}</code>\n"
        f"🆔 <b>Proceso:</b> PID <code>{pid}</code> (<code>{html.escape(proc_name)}</code>)\n"
        f"🧬 <b>Padre:</b> PID <code>{ppid}</code> (<code>{html.escape(parent_name)}</code>)\n"
        f"🖥️ <b>Entorno:</b> <code>{html.escape(entorno)}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
        "⚠️ <i>Se ha abierto una shell interactiva que permite la ejecución de comandos en el sistema. Seleccione una acción de contención inmediata:</i>"
    )

    # Botones interactivos de contención
    keyboard = {
        "inline_keyboard": [
            [
                {
                    "text": "🔒 REINICIAR (LOCKDOWN)",
                    "callback_data": f"shield_lockdown:{hostname}:{pid}:{ppid}",
                },
                {
                    "text": "🚫 MATAR TERMINAL",
                    "callback_data": f"shield_kill:{hostname}:{pid}:{ppid}",
                },
            ],
            [
                {
                    "text": "✅ SOY YO (AUTORIZAR 30m)",
                    "callback_data": f"shield_auth:{hostname}:{pid}:{ppid}",
                }
            ],
        ]
    }

    desc_bd = f"Apertura de terminal interactiva ({proc_name} / {parent_name}) por {user} en {tty} (PID: {pid})"
    log_event_to_database(
        user_name=user,
        event="terminal_opened",
        description=desc_bd,
        ip_address="127.0.0.1",
        user_agent=f"Terminal/{parent_name} ({tty})",
        user_role="admin",
    )

    await send_telegram_alert(mensaje, reply_markup=keyboard)


def main():
    parser = argparse.ArgumentParser(description="Sentinel Terminal Shield Dispatcher")
    parser.add_argument("--action", required=True, choices=["alert_terminal", "kill", "lockdown", "unlock", "rearm", "auth", "override", "status", "enforce_sudo"])
    parser.add_argument("--pid", type=int, default=0)
    parser.add_argument("--ppid", type=int, default=0)
    parser.add_argument("--user", default="")
    parser.add_argument("--sudo-user", default="root")
    parser.add_argument("--sudo-cmd", default="")
    parser.add_argument("--tty", default="")
    parser.add_argument("--display", default="")
    parser.add_argument("--minutes", type=int, default=30)
    parser.add_argument("--dry-run", action="store_true", help="Simular sin reiniciar el equipo físicamente")
    parser.add_argument("--key", default="", help="Token de autorización administrativa")
    args = parser.parse_args()

    if args.action == "alert_terminal":
        asyncio.run(
            process_terminal_open(
                pid=args.pid,
                ppid=args.ppid,
                user=args.user or os.getenv("USER") or "desconocido",
                tty=args.tty or "desconocida",
                display=args.display or os.getenv("DISPLAY", ""),
            )
        )
    elif args.action == "kill":
        res = execute_kill(args.pid, args.ppid, dry_run=args.dry_run)
        print(json.dumps(res))
    elif args.action == "lockdown":
        res = execute_lockdown(args.pid, args.ppid, args.user, dry_run=args.dry_run)
        print(json.dumps(res))
    elif args.action in ("unlock", "rearm"):
        res = execute_unlock()
        print(json.dumps(res))
    elif args.action == "status":
        override_active = is_master_override_active()
        lockdown_active = is_lockdown_active()
        grace_active, grace_exp = is_grace_period_active()
        infrac_count = 0
        if INFRACTIONS_FLAG.exists():
            try:
                infrac_count = json.loads(INFRACTIONS_FLAG.read_text(encoding="utf-8")).get("count", 0)
            except Exception:
                pass
        status_info = {
            "master_override_active": override_active,
            "lockdown_active": lockdown_active,
            "grace_period_active": grace_active,
            "grace_expiry": datetime.fromtimestamp(grace_exp).strftime("%H:%M:%S") if grace_active else None,
            "infractions_count": infrac_count,
        }
        print(json.dumps(status_info, indent=2))
    elif args.action == "auth":
        res = execute_auth(args.minutes)
        print(json.dumps(res))
    elif args.action == "override":
        key = args.key
        if not key:
            import getpass
            print("\n🛡️ [SENTINEL SHIELD] Validación de Token de Autorización Administrativa")
            try:
                key = getpass.getpass("🔑 Ingrese Token de Acceso: ")
            except Exception:
                key = input("🔑 Ingrese Token de Acceso: ")
        res = execute_master_override(key, source="CLI / Terminal")
        print(json.dumps(res))
        if res.get("success"):
            print("\n✅ Token validado exitosamente. Escudo de seguridad desactivado hasta el próximo reinicio.\n")
        else:
            print("\n❌ Token de acceso inválido. Intento registrado en auditoría.\n")
            sys.exit(1)
    elif args.action == "enforce_sudo":
        res = execute_enforce_sudo(
            user=args.user or os.getenv("USER") or "desconocido",
            sudo_user=args.sudo_user or "root",
            sudo_cmd=args.sudo_cmd or "sudo",
            tty=args.tty or "consola",
            ppid=args.ppid,
            dry_run=args.dry_run,
        )
        print(json.dumps(res))
        if not res.get("allowed", True):
            sys.exit(1)
        sys.exit(0)


if __name__ == "__main__":
    main()
