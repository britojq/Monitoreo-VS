"""
# ==============================================================================
# 🧠 SERVICIO DE IA Y CHAT CONVERSACIONAL: ai_chat_service.py
# Integración con motor local de inferencia, prompts, historial y comandos dinámicos.
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import logging
import os
import re
import sys
from pathlib import Path
from typing import Any, Dict, List, Tuple

import requests
from telegram import Update
from telegram.constants import ChatAction
from telegram.ext import Application, CommandHandler, ContextTypes, MessageHandler, filters

from bot_core.security.auth import check_authorization
from bot_core.services.config_manager import AI_DIR, BASE_DIR, load_config
from bot_core.utils.system_helpers import run_command_async
from bot_core.utils.telegram_helpers import (
    check_ai_rate_limit,
    markdown_to_telegram_html,
    safe_reply_html,
    sanitizar_pii,
    split_message,
)

logger = logging.getLogger("bot.services.ai")


def _get_active_config() -> Dict[str, Any]:
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


def _get_commands_dict() -> Dict[str, Any]:
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "COMMANDS"):
        return getattr(bot_mod, "COMMANDS")
    _, cmds = load_commands_data()
    return cmds


def _get_messages_dict() -> Dict[str, Any]:
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "MESSAGES"):
        return getattr(bot_mod, "MESSAGES")
    msgs, _ = load_commands_data()
    return msgs


async def _reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


def load_commands_data() -> Tuple[Dict[str, str], Dict[str, Any]]:
    """
    Carga los comandos y mensajes informativos/ayuda desde MariaDB (SSOT).
    Si MariaDB no está disponible o las tablas están vacías, recurre a un diccionario interno como fallback tolerante a fallos.
    """
    default_messages: Dict[str, str] = {
        "start_header": "🤖 <b>Bot de Administración de Servidores</b>\n\nComandos disponibles:",
        "unknown_command": "⚠️ Comando no reconocido. Usa <code>/start</code> para ver las opciones disponibles.",
        "help_general": "ℹ️ Usa <code>/start</code> o <code>/help [comando]</code> para información específica."
    }

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
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
                            "access_level": r.get("access_level") or "all"
                        }

                    messages = dict(default_messages)
                    for m in msg_rows:
                        k = m["setting_key"]
                        v = m["setting_value"]
                        if v:
                            messages[k] = v

                    logger.info(f"Comandos ({len(commands)}) y mensajes cargados exitosamente desde MariaDB (SSOT)")
                    return messages, commands
        finally:
            conn.close()
    except Exception as e:
        logger.warning(f"No se pudieron cargar comandos desde MariaDB ({e}). Usando fallback en memoria.")

    fallback_commands: Dict[str, Any] = {
        "start": {"description": "Inicia el bot y muestra el menú principal", "category": "General", "access_level": "all"},
        "help": {"description": "Muestra la ayuda de comandos disponibles", "category": "General", "access_level": "all"},
        "status": {"description": "Estado general del monitoreo y servicios", "category": "Monitoreo", "access_level": "all"},
        "ping": {"description": "Comprueba latencia y conectividad del bot", "category": "Diagnóstico", "access_level": "all"},
        "id": {"description": "Muestra su ID de Telegram e información de chat", "category": "General", "access_level": "all"},
        "emergencia": {"description": "Panel de control y emergencias operativas", "category": "Administración", "access_level": "owner"},
    }
    return default_messages, fallback_commands


def load_system_prompt(config: Dict[str, Any]) -> str:
    """Carga la identidad corporativa / personalidad del asistente virtual."""
    prompt_candidates = [
        AI_DIR / "system_prompt.txt",
        BASE_DIR / "system_prompt.txt"
    ]
    for p in prompt_candidates:
        if p.exists():
            try:
                content = p.read_text(encoding="utf-8").strip()
                if content:
                    logger.info(f"System prompt cargado desde {p}")
                    return content
            except Exception as e:
                logger.error(f"Error al leer {p}: {e}")

    modelfile_p = AI_DIR / "Modelfile.txt"
    if modelfile_p.exists():
        try:
            mcontent = modelfile_p.read_text(encoding="utf-8")
            match = re.search(r'SYSTEM\s+"""(.*?)"""', mcontent, re.DOTALL)
            if match:
                extracted = match.group(1).strip()
                if extracted:
                    logger.info(f"System prompt extraído desde {modelfile_p}")
                    return extracted
        except Exception as e:
            logger.error(f"Error al extraer system prompt de {modelfile_p}: {e}")

    return str(config.get("ollama_system_prompt", "")).strip()


def append_to_history(context: ContextTypes.DEFAULT_TYPE, role: str, content: str) -> None:
    """Guarda un mensaje en el historial del chat, con límite configurable."""
    cfg = _get_active_config()
    max_history = int(cfg.get("ollama_max_history", 12))

    if max_history <= 0:
        return

    content = (content or "").strip()
    if not content:
        return

    if len(content) > 8000:
        content = content[:8000]

    history = context.chat_data.setdefault("history", [])
    history.append({
        "role": role,
        "content": content
    })

    if len(history) > max_history:
        context.chat_data["history"] = history[-max_history:]


def _build_ollama_messages(user_text: str, history: List[Dict[str, str]]) -> List[Dict[str, str]]:
    """Construye la lista de mensajes para enviar al motor local de IA con System Prompt y MariaDB SSOT."""
    from bot_core.services.ai_knowledge_service import AiKnowledgeService

    messages = []
    cfg = _get_active_config()

    # 1. System Prompt oficial con delimitación de dominio técnico y guardrails
    system_prompt = AiKnowledgeService.get_system_prompt()
    messages.append({
        "role": "system",
        "content": system_prompt
    })

    # 2. Inyección de contexto fáctico en tiempo real desde MariaDB (SSOT)
    dynamic_context = AiKnowledgeService.resolve_system_context(user_text)
    if dynamic_context:
        messages.append({
            "role": "system",
            "content": dynamic_context
        })

    # 3. Historial de conversación previo
    if history:
        messages.extend(history)

    # 4. Consulta del usuario
    messages.append({
        "role": "user",
        "content": user_text
    })

    return messages


def _ask_ollama_sync(user_text: str, history: List[Dict[str, str]]) -> str:
    """Consulta sincrónica al motor local de inferencia. Se ejecuta en un hilo para no bloquear el bot."""
    cfg = _get_active_config()
    base_url = str(cfg.get("ollama_base_url", "http://localhost:11434")).rstrip("/")
    model = cfg.get("ollama_model", "qwen-empresa")
    timeout = int(cfg.get("ollama_timeout", 180))

    payload = {
        "model": model,
        "stream": False,
        "messages": _build_ollama_messages(user_text, history),
        "options": {
            "temperature": float(cfg.get("ollama_temperature", 0.3)),
            "num_ctx": int(cfg.get("ollama_num_ctx", 4096))
        }
    }

    response = requests.post(
        f"{base_url}/api/chat",
        json=payload,
        timeout=timeout
    )
    response.raise_for_status()

    try:
        data = response.json()
    except ValueError:
        raise RuntimeError("El servicio local de IA devolvió una respuesta inválida.")

    if data.get("error"):
        raise RuntimeError(str(data.get("error")))

    content = data.get("message", {}).get("content", "")
    content = (content or "").strip()

    if not content:
        return "Lo siento, el motor local de IA no devolvió respuesta."

    return content


async def ask_ollama_async(user_text: str, history: List[Dict[str, str]]) -> str:
    """Consulta asíncrona al motor local de IA."""
    return await asyncio.to_thread(_ask_ollama_sync, user_text, history)


async def handle_dynamic_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos dinámicos y soporta timeouts personalizados para tareas extensas."""
    if not await check_authorization(update, context):
        return

    cfg = _get_active_config()
    if cfg.get("commands_locked_for_users", False) and update.effective_user.id != cfg.get("owner_id", 0):
        await _reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos."
        )
        return

    if not cfg.get("commands_enabled", True):
        await _reply(
            update.message,
            "⚠️ Los comandos del sistema están desactivados. El bot se encuentra en <b>modo interactivo</b>.\n"
            "Escríbeme directamente en texto para interactuar con el motor local de IA."
        )
        return

    full_text = (update.message.text or "") if update.message else ""
    parts = full_text.split()

    cmd_name = parts[0].lstrip('/').split('@')[0].lower()
    commands_dict = _get_commands_dict()
    cmd_config = commands_dict.get(cmd_name)

    if not cmd_config:
        messages_dict = _get_messages_dict()
        await _reply(update.message, messages_dict.get("unknown_command", "Comando no configurado."))
        return

    if len(parts) > 1 and parts[1].lower() in ["help", "ayuda", "-h", "--help"]:
        help_text = cmd_config.get("help_text")
        if help_text:
            await _reply(update.message, help_text)
        else:
            desc = cmd_config.get("description", "Sin descripción")
            await _reply(update.message, f"ℹ️ <b>/{cmd_name}:</b> {html.escape(desc)}")
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
        await _reply(update.message, reply_header)

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
                if title:
                    response_text += f"❌ <b>{html.escape(title)} (Fallo en ejecución)</b>\n"
                else:
                    response_text += "❌ <b>Error al ejecutar el comando:</b>\n"
            else:
                if title:
                    response_text += f"<b>{html.escape(title)}</b>\n"
            response_text += f"<pre>{safe_output}</pre>"
            await _reply(update.message, response_text)
        else:
            if not success:
                logger.warning(f"El comando '{command_list}' finalizó con código {exec_res.get('returncode')}, pero la salida está configurada como oculta.")
            if title:
                if success:
                    await _reply(update.message, f"✅ <b>{html.escape(title)}</b> ejecutado con éxito.")
                else:
                    await _reply(update.message, f"⚠️ <b>{html.escape(title)}</b> finalizado.")

    reply_footer = cmd_config.get("reply_footer")
    if reply_footer:
        await _reply(update.message, reply_footer)


async def reset_chat(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reinicia el historial de conversación con el asistente virtual."""
    cfg = _get_active_config()
    allow_all = bool(cfg.get("ollama_allow_all", False))

    if not allow_all and not await check_authorization(update, context):
        return

    context.chat_data.pop("history", None)
    if update.message:
        await update.message.reply_text("✅ Historial de conversación reiniciado.")


async def _animate_waiting_message(bot: Any, chat_id: int, message_obj: Any, stop_event: asyncio.Event) -> None:
    """Anima el mensaje de espera con reloj giratorio e indicador de 'escribiendo...'"""
    clocks = ["🕐", "🕑", "🕒", "🕓", "🕔", "🕕", "🕖", "🕗", "🕘", "🕙", "🕚", "🕛"]
    base_text = "<i>Estoy haciendo un análisis de lo que indicas, espere por favor puedo tardar unos segundos...</i>"
    idx = 0

    while not stop_event.is_set():
        try:
            await bot.send_chat_action(chat_id=chat_id, action=ChatAction.TYPING)
        except Exception:
            pass

        try:
            await asyncio.wait_for(stop_event.wait(), timeout=2.5)
            break
        except asyncio.TimeoutError:
            pass

        idx = (idx + 1) % len(clocks)
        clock_emoji = clocks[idx]

        try:
            await message_obj.edit_text(f"{clock_emoji} {base_text}", parse_mode='HTML')
        except Exception:
            pass


async def _execute_chat_pipeline(update: Update, context: ContextTypes.DEFAULT_TYPE, raw_user_text: str) -> None:
    """Ejecuta el ciclo de consulta, inferencia y respuesta formateada al usuario."""
    if not update.message or not raw_user_text:
        return

    user = update.effective_user
    chat = update.effective_chat
    rate_key = user.id if user else (chat.id if chat else 0)

    if not check_ai_rate_limit(rate_key, max_requests=5, window_seconds=60.0):
        await update.message.reply_text("Límite de consultas a la IA excedido. Espere 60 segundos.")
        return

    user_text = sanitizar_pii(raw_user_text)

    waiting_msg = None
    stop_event = asyncio.Event()
    anim_task = None

    try:
        waiting_msg = await update.message.reply_text(
            "⏳ <i>Estoy haciendo un análisis de lo que indicas, espere por favor puedo tardar unos segundos...</i>",
            parse_mode='HTML'
        )
        anim_task = asyncio.create_task(
            _animate_waiting_message(context.bot, update.effective_chat.id, waiting_msg, stop_event)
        )
    except Exception as e:
        logger.warning(f"No se pudo enviar el mensaje inicial de espera: {e}")

    history = context.chat_data.get("history", [])
    success = False

    try:
        answer = await ask_ollama_async(user_text, history)
        success = True

    except requests.exceptions.Timeout:
        answer = (
            "⏱️ El motor local de IA tardó demasiado en responder.\n"
            "Intenta nuevamente en unos segundos."
        )

    except requests.exceptions.ConnectionError:
        answer = (
            "🔌 No pude conectar con el motor local de IA.\n"
            "Verifica que el servicio esté activo en este equipo."
        )

    except requests.exceptions.RequestException:
        logger.exception("Error HTTP/Request consultando servicio de IA")
        answer = (
            "⚠️ Ocurrió un error de red o del servicio local de IA.\n"
            "Intenta nuevamente en unos momentos."
        )

    except Exception:
        logger.exception("Error inesperado consultando servicio local de IA")
        answer = (
            "⚠️ Ocurrió un error inesperado consultando al asistente.\n"
            "Intenta nuevamente."
        )

    finally:
        stop_event.set()
        if anim_task:
            anim_task.cancel()
            try:
                await anim_task
            except (asyncio.CancelledError, Exception):
                pass

        if waiting_msg:
            try:
                await waiting_msg.delete()
            except Exception:
                pass

    if success:
        append_to_history(context, "user", user_text)
        append_to_history(context, "assistant", answer)

    formatted_answer = markdown_to_telegram_html(answer)

    if success:
        formatted_answer += (
            "\n\n<i>💡 <b>Nota:</b> El bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento "
            "hasta que uses <code>/reset_ia</code> para iniciar una nueva consulta referente a otro tema.</i>"
        )

    for chunk in split_message(formatted_answer):
        await _reply(
            update.message,
            chunk,
            disable_web_page_preview=True
        )


async def handle_chat_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja mensajes de texto normales y los envía al motor local de IA (exclusivo chat privado del Owner)."""
    if not update.message or not update.message.text:
        return

    chat = update.effective_chat
    user = update.effective_user

    # REGLA DE ORO #1: Jamás responder en grupos (identificadores negativos)
    if not chat or chat.type != "private" or chat.id < 0:
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))
    if not user or user.id != owner_id or chat.id != owner_id:
        logger.warning(f"Acceso a IA denegado para chat_id={getattr(chat, 'id', None)}, user_id={getattr(user, 'id', None)}")
        await update.message.reply_text("⛔ Acceso restringido: El asistente de IA está disponible exclusivamente para el chat privado del Administrador del Centro de Operaciones ATIT.")
        return

    if not cfg.get("ollama_enabled", True):
        await update.message.reply_text("El asistente conversacional está deshabilitado.")
        return

    raw_user_text = update.message.text.strip()
    if not raw_user_text:
        return

    await _execute_chat_pipeline(update, context, raw_user_text)


async def handle_chat_query_direct(update: Update, context: ContextTypes.DEFAULT_TYPE, user_text: str) -> None:
    """Procesa una consulta directa (ej. /ia <pregunta>) respetando el filtrado exclusivo del Owner."""
    if not update.message or not user_text:
        return

    chat = update.effective_chat
    user = update.effective_user

    # REGLA DE ORO #1: Jamás responder en grupos (identificadores negativos)
    if not chat or chat.type != "private" or chat.id < 0:
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))
    if not user or user.id != owner_id or chat.id != owner_id:
        logger.warning(f"Acceso a /ia denegado para chat_id={getattr(chat, 'id', None)}, user_id={getattr(user, 'id', None)}")
        await update.message.reply_text("⛔ Acceso restringido: El asistente de IA está disponible exclusivamente para el chat privado del Administrador del Centro de Operaciones ATIT.")
        return

    if not cfg.get("ollama_enabled", True):
        await update.message.reply_text("El asistente conversacional está deshabilitado.")
        return

    await _execute_chat_pipeline(update, context, user_text.strip())


def register_ai_routes(application: Application) -> None:
    """Registra los comandos de IA e interactividad conversacional."""
    application.add_handler(CommandHandler(["reset_ia", "reset_chat", "olvidar"], reset_chat))
    # El handler de texto general se agrega al final del router para no interceptar comandos
