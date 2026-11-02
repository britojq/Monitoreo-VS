"""
Acciones atómicas para Terminal Shield y mitigación PAM.
Principio SOLID: Responsabilidad Única (SRP) en cada acción.
"""

from __future__ import annotations
import html
import logging
import socket
import subprocess
import threading
import time
from datetime import datetime
from typing import Any, Dict

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.shield.contracts import ShieldActionProtocol
from bot_core.handlers.shield.dtos import ShieldCallbackPayload

logger = logging.getLogger("bot_core.handlers.shield.actions")


class AuthorizeTerminalAction(ShieldActionProtocol):
    """Autoriza una sesión interactiva por 30 minutos y resetea infracciones."""

    async def execute(self, payload: ShieldCallbackPayload, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        query = update.callback_query
        if not query:
            return

        await query.answer("✅ Sesión autorizada por 30 minutos.")
        from monitor.terminal_shield import execute_auth

        target_pid = payload.target_pid
        target_ppid = payload.target_ppid
        target_server = payload.target_server or socket.gethostname()

        # Resuelve la autorización pasando el PID objetivo de forma determinista y tipada
        res: Dict[str, Any] = execute_auth(minutes=30, tty=None, pid=target_pid or target_ppid)
        tty_label = res.get("tty", "pts")
        expiry = res.get("expiry", "30 minutos")

        text = (
            "✅ <b>SESIÓN AUTORIZADA POR EL ADMINISTRADOR</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server)}</code>\n"
            f"📟 <b>Terminal:</b> <code>{html.escape(tty_label)}</code> (PID <code>{target_pid or 'activo'}</code>)\n"
            f"⏱️ <b>Ventana de Trabajo:</b> <code>30 minutos</code> (hasta {expiry})\n"
            "🛡️ <b>Infracciones:</b> Contador reseteado a 0.\n\n"
            "<i>Puede continuar utilizando la terminal sin interrupciones.</i>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
        try:
            await query.edit_message_text(text, parse_mode="HTML", reply_markup=None)
        except Exception:
            pass


class KillTerminalAction(ShieldActionProtocol):
    """Termina inmediatamente la terminal y procesos no autorizados."""

    async def execute(self, payload: ShieldCallbackPayload, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        query = update.callback_query
        if not query:
            return

        await query.answer("🚫 Neutralizando terminal...")
        from monitor.terminal_shield import execute_kill

        target_pid = payload.target_pid
        target_ppid = payload.target_ppid
        target_server = payload.target_server or socket.gethostname()

        res = execute_kill(target_pid, target_ppid)
        killed_str = ", ".join(res.get("killed", [])) or f"PID {target_pid}"
        infractions = res.get("infractions", 1)
        reboot_triggered = res.get("reboot_triggered", False)
        admin_user = query.from_user.username or "admin"

        if reboot_triggered:
            text = (
                "🛑 <b>REINCIDENCIA MÁXIMA ALCANZADA (3/3)</b>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server)}</code>\n"
                f"⚡ <b>Procesos Eliminados:</b> <code>{html.escape(killed_str)}</code>\n"
                f"⚠️ <b>Infracciones Acumuladas:</b> <code>3 de 3</code>\n"
                f"👤 <b>Acción ejecutada por:</b> Administrador (@{html.escape(admin_user)})\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                "🚨 <i>Se ha activado el protocolo de reinicio por reincidencia.</i>"
            )
        else:
            text = (
                "🚫 <b>TERMINAL NEUTRALIZADA EXITOSAMENTE</b>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server)}</code>\n"
                f"⚡ <b>Procesos Eliminados:</b> <code>{html.escape(killed_str)}</code>\n"
                f"⚠️ <b>Infracción Registrada:</b> <code>{infractions} de 3</code>\n"
                "📺 <b>Acción en Pantalla:</b> Advertencia de 10s desplegada al operador.\n"
                f"👤 <b>Acción ejecutada por:</b> Administrador (@{html.escape(admin_user)})\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                "<i>Al registrarse el 3er intento no autorizado, el equipo se reiniciará automáticamente.</i>"
            )

        try:
            await query.edit_message_text(text, parse_mode="HTML", reply_markup=None)
        except Exception:
            pass


class LockdownAction(ShieldActionProtocol):
    """Activa el reinicio preventivo de contención por intrusión."""

    async def execute(self, payload: ShieldCallbackPayload, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        query = update.callback_query
        if not query:
            return

        await query.answer("🔒 ACTIVANDO REINICIO DE SEGURIDAD...", show_alert=True)
        from monitor.terminal_shield import execute_lockdown

        target_pid = payload.target_pid
        target_ppid = payload.target_ppid
        target_server = payload.target_server or socket.gethostname()

        execute_lockdown(target_pid, target_ppid)
        text = (
            "🚨🚨 <b>REINICIO DE CONTENCIÓN ACTIVADO</b> 🚨🚨\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server)}</code>\n"
            f"⏰ <b>Hora:</b> <code>{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</code>\n\n"
            "🛡️ <b>Medidas de contención activadas:</b>\n"
            f" • Terminal interactiva terminada (PID: {target_pid}).\n"
            " • Todas las instancias de emuladores cerradas.\n"
            " • Reinicio automático del equipo en ejecución para neutralizar la intrusión.\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
        try:
            await query.edit_message_text(text, parse_mode="HTML", reply_markup=None)
        except Exception:
            pass


class UnlockAction(ShieldActionProtocol):
    """Restaura el sistema levantando el estado de emergencia."""

    async def execute(self, payload: ShieldCallbackPayload, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        query = update.callback_query
        if not query:
            return

        await query.answer("🔓 Desactivando estado de emergencia...")
        from monitor.terminal_shield import execute_unlock

        target_server = payload.target_server or socket.gethostname()
        execute_unlock()
        text = (
            "✅ <b>SISTEMA RESTAURADO A LA NORMALIDAD</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(target_server)}</code>\n\n"
            "El estado de emergencia ha sido levantado:\n"
            " • Infracciones acumuladas reseteadas a 0.\n"
            " • Permisos interactivos reactivados.\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
        try:
            await query.edit_message_text(text, parse_mode="HTML", reply_markup=None)
        except Exception:
            pass


class SoyYoAction:
    """Procesa respuestas de texto 'soy yo', 'autorizar' o 'override' exclusivas del Owner."""

    @staticmethod
    async def execute(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        if not update.message or not update.message.text:
            return

        chat = update.effective_chat
        user = update.effective_user

        # Regla de Oro #1: Jamás procesar en grupos (identificadores negativos)
        if not chat or chat.type != "private" or chat.id < 0:
            return

        from monitor.core_shield import IMMUTABLE_OWNER_ID
        from bot_core.services.config_manager import load_config

        cfg = load_config()
        owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
        allowed_admins = set(cfg.get("allowed_user_ids", [])) | {IMMUTABLE_OWNER_ID, owner_id}

        if not user or user.id not in allowed_admins or chat.id not in allowed_admins:
            logger.warning(f"Intento no autorizado de 'soy yo' desde chat_id={getattr(chat, 'id', None)}")
            return

        try:
            from monitor.terminal_shield import execute_auth
            res = execute_auth(30)
            hostname = socket.gethostname()
            expiry_time = res.get("expiry", "30 minutos")
            tty_label = res.get("tty", "terminal activa")

            # Propagación asíncrona no bloqueante al nodo par
            def _propagate_remote():
                try:
                    target_host = "produccion" if (hostname or "").upper() == "MONITOREO-DEV" else "desarrollo"
                    exp_ts = time.time() + 1800
                    c_tty = res.get("tty", "").replace("/", "_")
                    target_file = f"/dev/shm/terminal_shield_grace_{c_tty}" if c_tty and c_tty != "global" else "/dev/shm/terminal_shield_grace_period"
                    cmd = [
                        "ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=5",
                        target_host,
                        f"echo {exp_ts} | sudo tee {target_file} >/dev/null && sudo chmod 0666 {target_file}"
                    ]
                    subprocess.run(cmd, capture_output=True, timeout=8)
                except Exception:
                    pass

            threading.Thread(target=_propagate_remote, daemon=True).start()

            msg = (
                "✅ <b>SESIÓN INTERACTIVA AUTORIZADA POR EL ADMINISTRADOR</b>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
                f"📟 <b>Terminal:</b> <code>{html.escape(tty_label)}</code>\n"
                f"⏱️ <b>Ventana de Trabajo:</b> <code>30 minutos</code> (hasta {expiry_time})\n"
                "🛡️ <b>Infracciones:</b> Contador reseteado a <code>0 de 3</code>.\n\n"
                "🔓 <i>Acceso administrativo de superusuario ('sudo' / 'su') habilitado para esta terminal.</i>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━"
            )
            await update.message.reply_text(msg, parse_mode="HTML")
            logger.info(f"Autorización por texto 'soy yo' concedida a {user.id} para {hostname}.")

        except Exception as e:
            logger.error(f"Error procesando 'soy yo': {e}", exc_info=True)
            try:
                await update.message.reply_text(f"⚠️ Error al registrar autorización interactiva: {e}")
            except Exception:
                pass
