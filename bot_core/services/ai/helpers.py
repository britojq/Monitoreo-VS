"""
# ==============================================================================
# 🧠 HELPERS DE IA Y COMANDOS: bot_core/services/ai/helpers.py
# Utilidades de configuración, comandos MariaDB SSOT e historial conversacional.
# ==============================================================================
"""
from __future__ import annotations

import logging
import sys
from typing import Any, Dict, List, Optional, Tuple

from telegram.ext import ContextTypes

from bot_core.services.config_manager import load_config
from bot_core.utils.telegram_helpers import safe_reply_html
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.services.ai.helpers")


def get_active_config() -> Dict[str, Any]:
    """Obtiene la configuración activa del bot."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


def get_commands_dict() -> Dict[str, Any]:
    """Obtiene el diccionario de comandos dinámicos."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "COMMANDS"):
        return getattr(bot_mod, "COMMANDS")
    _, cmds = load_commands_data()
    return cmds


def get_messages_dict() -> Dict[str, Any]:
    """Obtiene los mensajes de cabecera/pie dinámicos."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "MESSAGES"):
        return getattr(bot_mod, "MESSAGES")
    msgs, _ = load_commands_data()
    return msgs


async def reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Invoca safe_reply_html dinámicamente respetando cualquier patch en bot.safe_reply_html."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


def load_commands_data() -> Tuple[Dict[str, str], Dict[str, Any]]:
    """Carga comandos y mensajes informativos desde MariaDB (SSOT)."""
    default_messages: Dict[str, str] = {
        "start_header": "🤖 <b>Bot de Administración de Servidores</b>\n\nComandos disponibles:",
        "unknown_command": "⚠️ Comando no reconocido. Usa <code>/start</code> para ver las opciones disponibles.",
        "help_general": "ℹ️ Usa <code>/start</code> o <code>/help [comando]</code> para información específica.",
    }

    try:
        with db_cursor() as cur:
            cur.execute(
                "SELECT command, title, description, help_text, category, access_level, is_active "
                "FROM bot_commands WHERE is_active = 1 ORDER BY sort_order, command"
            )
            cmd_rows = cur.fetchall()

            cur.execute("SELECT setting_key, setting_value FROM bot_settings WHERE setting_group = 'messages'")
            msg_rows = cur.fetchall()

            if cmd_rows:
                commands: Dict[str, Any] = {}
                for r in cmd_rows:
                    cmd_name = r["command"].strip().lower()
                    commands[cmd_name] = {
                        "description": r.get("description") or "",
                        "help_text": r.get("help_text") or "",
                        "title": r.get("title") or "",
                        "category": r.get("category") or "General",
                        "access_level": r.get("access_level") or "all",
                    }

                messages = dict(default_messages)
                for m in msg_rows:
                    k = m["setting_key"]
                    v = m["setting_value"]
                    if v:
                        messages[k] = v

                logger.info("Comandos (%d) y mensajes cargados desde MariaDB (SSOT)", len(commands))
                return messages, commands
    except Exception as exc:
        logger.warning("No se pudieron cargar comandos desde MariaDB (%s). Usando fallback en memoria.", exc)

    fallback_commands: Dict[str, Any] = {
        "start": {"description": "Inicia el bot y muestra el menú principal", "category": "General", "access_level": "all"},
        "help": {"description": "Muestra la ayuda de comandos disponibles", "category": "General", "access_level": "all"},
        "status": {"description": "Estado general del monitoreo y servicios", "category": "Monitoreo", "access_level": "all"},
        "ping": {"description": "Comprueba latencia y conectividad del bot", "category": "Diagnóstico", "access_level": "all"},
        "id": {"description": "Muestra su ID de Telegram e información de chat", "category": "General", "access_level": "all"},
        "emergencia": {"description": "Panel de control y emergencias operativas", "category": "Administración", "access_level": "owner"},
    }
    return default_messages, fallback_commands


def append_to_history(context: ContextTypes.DEFAULT_TYPE, role: str, content: str) -> None:
    """Anexa un mensaje al historial de contexto conversacional en memoria."""
    cfg = get_active_config()
    max_history = int(cfg.get("ollama_max_history", 12))
    if max_history <= 0:
        return

    clean_content = (content or "").strip()
    if not clean_content:
        return
    if len(clean_content) > 8000:
        clean_content = clean_content[:8000]

    history = context.chat_data.setdefault("history", [])
    history.append({"role": role, "content": clean_content})
    if len(history) > max_history:
        context.chat_data["history"] = history[-max_history:]
