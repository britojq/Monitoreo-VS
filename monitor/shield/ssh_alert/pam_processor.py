"""
Procesador de eventos PAM (inicio/cierre SSH, elevación sudo, lockdown interactivo).
"""
from datetime import datetime
import html
import logging
import os
from pathlib import Path
import socket
import sys

from monitor.shield.ssh_alert.pam_event_parser import (
    GRACE_PERIOD_FLAG,
    LOCKDOWN_FLAG,
    MASTER_OVERRIDE_FLAG,
    calculate_session_duration,
    find_interactive_terminal_pids,
    get_geolocation,
    get_network_context,
    record_session_start,
)
from monitor.shield.ssh_alert.ssh_alert_dispatcher import (
    log_event_to_database,
    send_telegram_alert,
)
from monitor.terminal_shield import is_protected_process

logger = logging.getLogger("monitor.shield.ssh_alert.processor")


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

    # 0. COMPROBACIÓN DE LOCKDOWN ACTIVO
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

    # 1. EVENTO SSH: Inicio de Sesión
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

    # 2. EVENTO SSH: Cierre de Sesión
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

    # 3. EVENTO SUDO / SU
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

        is_interactive = bool(
            tty
            and not tty.startswith("none")
            and tty != "?"
            and (tty.startswith("pts/") or tty.startswith("tty") or tty.startswith("/dev/"))
        )

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
            logger.info(f"Sudo ejecutado por Asistente IA ({origin_user}): {cmd[:50]}")
            return

        grace_active = MASTER_OVERRIDE_FLAG.exists()
        if not grace_active and GRACE_PERIOD_FLAG.exists():
            try:
                exp = float(GRACE_PERIOD_FLAG.read_text().strip())
                if time.time() < exp:
                    grace_active = True
            except Exception:
                pass

        if is_interactive and not grace_active:
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
