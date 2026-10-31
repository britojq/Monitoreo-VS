"""
# ==============================================================================
# ⚡ MANEJADOR DE COMANDOS DINÁMICOS: dynamic_command_handler.py
# Ejecución controlada de comandos registrados dinámicamente en MariaDB SSOT.
# Ubicación: /opt/sonar/bot_core/services/ai/dynamic_command_handler.py
# ==============================================================================
"""
from __future__ import annotations

import html
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.security.auth import check_authorization
from bot_core.services.ai.helpers import (
    get_active_config,
    get_commands_dict,
    get_messages_dict,
    reply,
)
from bot_core.utils.system_helpers import run_command_async

logger = logging.getLogger("bot.services.ai.dynamic_cmd")


async def handle_dynamic_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos dinámicos configurados en MariaDB (SSOT)."""
    if not await check_authorization(update, context):
        return

    cfg = get_active_config()
    if cfg.get("commands_locked_for_users", False) and update.effective_user.id != cfg.get("owner_id", 0):
        await reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.",
        )
        return

    if not cfg.get("commands_enabled", True):
        await reply(
            update.message,
            "⚠️ Los comandos del sistema están desactivados. El bot se encuentra en <b>modo interactivo</b>.\n"
            "Escríbeme directamente en texto para interactuar con el motor local de IA.",
        )
        return

    full_text = (update.message.text or "") if update.message else ""
    parts = full_text.split()
    cmd_name = parts[0].lstrip("/").split("@")[0].lower()
    commands_dict = get_commands_dict()
    cmd_config = commands_dict.get(cmd_name)

    if not cmd_config:
        messages_dict = get_messages_dict()
        await reply(update.message, messages_dict.get("unknown_command", "Comando no configurado."))
        return

    if len(parts) > 1 and parts[1].lower() in ["help", "ayuda", "-h", "--help"]:
        help_text = cmd_config.get("help_text")
        if help_text:
            await reply(update.message, help_text)
        else:
            desc = cmd_config.get("description", "Sin descripción")
            await reply(update.message, f"ℹ️ <b>/{cmd_name}:</b> {html.escape(desc)}")
        return

    cmd_show_output = True
    if "show_output" in cmd_config:
        cmd_show_output = bool(cmd_config.get("show_output"))
    elif "hide_output" in cmd_config:
        cmd_show_output = not bool(cmd_config.get("hide_output"))
    elif "silent" in cmd_config:
        cmd_show_output = not bool(cmd_config.get("silent"))

    reply_header = cmd_config.get("reply_header")
    if reply_header:
        await reply(update.message, reply_header)

    steps = cmd_config.get("steps", [])
    for step in steps:
        title = step.get("title")
        command_list = step.get("command")
        step_timeout = step.get("timeout", 300)

        step_show_output = cmd_show_output
        if "show_output" in step:
            step_show_output = bool(step.get("show_output"))
        elif "hide_output" in step:
            step_show_output = not bool(step.get("hide_output"))
        elif "silent" in step:
            step_show_output = not bool(step.get("silent"))

        if not command_list:
            continue

        exec_res = await run_command_async(command_list, timeout=step_timeout)
        output = exec_res.get("output", "")
        success = exec_res.get("success", False)

        if step_show_output:
            safe_output = html.escape(output)
            response_text = ""
            if not success:
                response_text += f"❌ <b>{html.escape(title)} (Fallo en ejecución)</b>\n" if title else "❌ <b>Error al ejecutar el comando:</b>\n"
            elif title:
                response_text += f"<b>{html.escape(title)}</b>\n"
            response_text += f"<pre>{safe_output}</pre>"
            await reply(update.message, response_text)
        else:
            if not success:
                logger.warning("Comando '%s' falló con retorno %s (salida oculta)", command_list, exec_res.get("returncode"))
            if title:
                if success:
                    await reply(update.message, f"✅ <b>{html.escape(title)}</b> ejecutado con éxito.")
                else:
                    await reply(update.message, f"⚠️ <b>{html.escape(title)}</b> finalizado.")

    reply_footer = cmd_config.get("reply_footer")
    if reply_footer:
        await reply(update.message, reply_footer)
