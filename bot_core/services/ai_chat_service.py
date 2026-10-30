"""
==============================================================================
🧠 CONTROLADOR Y FACHADA DE CHAT CONVERSACIONAL: ai_chat_service.py
Arquitectura SOLID (SRP, OCP, DIP) - Controlador delgado para Telegram.
Delega inferencia, guardrails, telemetría y persistencia a servicios dedicados.
==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import logging
import re
import sys
from typing import Any, Dict, List, Optional, Tuple

from telegram import Update
from telegram.constants import ChatAction
from telegram.ext import Application, CommandHandler, ContextTypes

from bot_core.contracts.ai_contracts import (
    InferenceRequestDTO,
)
from bot_core.security.auth import check_authorization
from bot_core.services.ai_group_config_service import MariaDbAiGroupConfigService
from bot_core.services.ai_guardrails_service import AiGuardrailsService
from bot_core.services.ai_inference_client import AiInferenceClient
from bot_core.services.ai_telemetry_provider import MariaDbAiTelemetryProvider
from bot_core.services.config_manager import load_config
from bot_core.utils.system_helpers import run_command_async
from bot_core.utils.telegram_helpers import (
    check_ai_rate_limit,
    markdown_to_telegram_html,
    safe_reply_html,
    sanitizar_pii,
    split_message,
)

logger = logging.getLogger("bot.services.ai_chat")

# Instancias compartidas de servicios especializados (DIP)
_telemetry_provider = MariaDbAiTelemetryProvider()
_guardrails_service = AiGuardrailsService(telemetry_provider=_telemetry_provider)
_inference_client = AiInferenceClient(guardrails_service=_guardrails_service)
_group_config_service = MariaDbAiGroupConfigService()


# ============================================================================
# FUNCIONES AUXILIARES Y FACHADAS DE COMPATIBILIDAD
# ============================================================================

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
    """Carga comandos y mensajes informativos desde MariaDB (SSOT)."""
    default_messages: Dict[str, str] = {
        "start_header": "🤖 <b>Bot de Administración de Servidores</b>\n\nComandos disponibles:",
        "unknown_command": "⚠️ Comando no reconocido. Usa <code>/start</code> para ver las opciones disponibles.",
        "help_general": "ℹ️ Usa <code>/start</code> o <code>/help [comando]</code> para información específica.",
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
        finally:
            conn.close()
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


# Delegaciones canónicas hacia servicios especializados (DIP)
def is_telegram_group_ai_enabled() -> bool:
    return _group_config_service.is_group_ai_enabled()


def set_telegram_group_ai_enabled(enabled: bool, updated_by: str = "Owner [Telegram]") -> bool:
    return _group_config_service.set_group_ai_enabled(enabled, updated_by)


def load_system_prompt(config: Optional[Dict[str, Any]] = None) -> str:
    return _inference_client.load_system_prompt()


def append_to_history(context: ContextTypes.DEFAULT_TYPE, role: str, content: str) -> None:
    cfg = _get_active_config()
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


def _is_negative_or_refusal(text: str) -> bool:
    return _guardrails_service.is_negative_or_refusal(text)


def _build_ollama_messages(
    user_text: str,
    history: List[Dict[str, str]],
    active_entity: Optional[Dict[str, Any]] = None,
) -> Tuple[List[Dict[str, str]], Optional[Dict[str, Any]]]:
    return _inference_client.build_messages(user_text, history, active_entity)


def _validate_and_sanitize_response(
    content: str,
    user_text: str,
    dynamic_context: Optional[str] = None,
    is_group_context: bool = False,
) -> str:
    return _guardrails_service.validate_and_sanitize(content, user_text, dynamic_context, is_group_context)


def _get_live_outage_summary() -> Tuple[int, int, List[str]]:
    dto = _telemetry_provider.get_live_outage_summary()
    return dto.serv_total, dto.serv_up, dto.down_services


def _get_live_inventory_summary() -> Tuple[int, int]:
    dto = _telemetry_provider.get_live_inventory_summary()
    return dto.total_assets, dto.with_serial


def _get_live_actas_summary() -> Tuple[int, int, List[str]]:
    dto = _telemetry_provider.get_live_actas_summary()
    return dto.acta_count, dto.total_assets, dto.items


def _ask_ollama_sync(
    user_text: str,
    history: List[Dict[str, str]],
    active_entity: Optional[Dict[str, Any]] = None,
    is_group_context: bool = False,
) -> Tuple[str, Optional[Dict[str, Any]]]:
    req = InferenceRequestDTO(
        user_text=user_text,
        history=history,
        user_name="Grupo Corporativo" if is_group_context else "Administrador Telegram",
        user_role="Grupo Corporativo" if is_group_context else "Administrador",
        is_group_context=is_group_context,
        active_entity=active_entity,
    )
    res = _inference_client.ask_sync(req)
    return res.content, res.updated_entity


async def ask_ollama_async(
    user_text: str,
    history: List[Dict[str, str]],
    active_entity: Optional[Dict[str, Any]] = None,
    is_group_context: bool = False,
) -> Tuple[str, Optional[Dict[str, Any]]]:
    req = InferenceRequestDTO(
        user_text=user_text,
        history=history,
        user_name="Grupo Corporativo" if is_group_context else "Administrador Telegram",
        user_role="Grupo Corporativo" if is_group_context else "Administrador",
        is_group_context=is_group_context,
        active_entity=active_entity,
    )
    res = await _inference_client.ask_async(req)
    return res.content, res.updated_entity


# ============================================================================
# PIPELINE Y ANIMACIÓN DE INTERFAZ DE USUARIO (TELEGRAM UI)
# ============================================================================

async def _animate_waiting_message(bot: Any, chat_id: int, message_obj: Any, stop_event: asyncio.Event) -> None:
    """Anima el mensaje de espera con reloj rotativo e indicador de escritura."""
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
            await message_obj.edit_text(f"{clock_emoji} {base_text}", parse_mode="HTML")
        except Exception:
            pass


async def _execute_chat_pipeline(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    raw_user_text: str,
    is_group_context: bool = False,
) -> None:
    """Ejecuta el ciclo de consulta, inferencia y respuesta formateada al usuario."""
    if not update.message or not raw_user_text:
        return

    user = update.effective_user
    chat = update.effective_chat
    rate_key = user.id if user else (chat.id if chat else 0)

    max_reqs = 3 if is_group_context else 5
    if not check_ai_rate_limit(rate_key, max_requests=max_reqs, window_seconds=60.0):
        await update.message.reply_text("⏳ Límite de consultas a la IA excedido. Por favor espere 60 segundos.")
        return

    user_text = sanitizar_pii(raw_user_text)

    waiting_msg = None
    stop_event = asyncio.Event()
    anim_task = None

    try:
        waiting_msg = await update.message.reply_text(
            "⏳ <i>Estoy haciendo un análisis de lo que indicas, espere por favor puedo tardar unos segundos...</i>",
            parse_mode="HTML",
        )
        anim_task = asyncio.create_task(
            _animate_waiting_message(context.bot, update.effective_chat.id, waiting_msg, stop_event)
        )
    except Exception as exc:
        logger.warning("No se pudo enviar el mensaje inicial de espera: %s", exc)

    if is_group_context:
        history: List[Dict[str, str]] = []
        active_entity: Optional[Dict[str, Any]] = None
    else:
        history = context.chat_data.get("history", [])
        active_entity = context.chat_data.get("active_entity")

    success = False
    answer = ""

    try:
        answer, updated_entity = await ask_ollama_async(
            user_text, history, active_entity, is_group_context=is_group_context
        )
        if not is_group_context and updated_entity:
            context.chat_data["active_entity"] = updated_entity
        success = True
    except Exception as exc:
        logger.exception("Error consultando servicio de IA: %s", exc)
        answer = "⚠️ Ocurrió un error inesperado consultando al asistente local. Intenta nuevamente."
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

    if success and not is_group_context:
        if not _is_negative_or_refusal(answer):
            append_to_history(context, "user", user_text)
            append_to_history(context, "assistant", answer)

    formatted_answer = markdown_to_telegram_html(answer)

    if success and not is_group_context:
        formatted_answer += (
            "\n\n<i>💡 <b>Nota:</b> El bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento "
            "hasta que uses <code>/reset_ia</code> para iniciar una nueva consulta referente a otro tema.</i>"
        )

    for chunk in split_message(formatted_answer):
        await _reply(update.message, chunk, disable_web_page_preview=True)


# ============================================================================
# MANEJADORES DE COMANDOS Y MENSAJES (HANDLERS)
# ============================================================================

async def handle_dynamic_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos dinámicos configurados en MariaDB (SSOT)."""
    if not await check_authorization(update, context):
        return

    cfg = _get_active_config()
    if cfg.get("commands_locked_for_users", False) and update.effective_user.id != cfg.get("owner_id", 0):
        await _reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.",
        )
        return

    if not cfg.get("commands_enabled", True):
        await _reply(
            update.message,
            "⚠️ Los comandos del sistema están desactivados. El bot se encuentra en <b>modo interactivo</b>.\n"
            "Escríbeme directamente en texto para interactuar con el motor local de IA.",
        )
        return

    full_text = (update.message.text or "") if update.message else ""
    parts = full_text.split()
    cmd_name = parts[0].lstrip("/").split("@")[0].lower()
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
                response_text += f"❌ <b>{html.escape(title)} (Fallo en ejecución)</b>\n" if title else "❌ <b>Error al ejecutar el comando:</b>\n"
            elif title:
                response_text += f"<b>{html.escape(title)}</b>\n"
            response_text += f"<pre>{safe_output}</pre>"
            await _reply(update.message, response_text)
        else:
            if not success:
                logger.warning("Comando '%s' falló con retorno %s (salida oculta)", command_list, exec_res.get("returncode"))
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
    context.chat_data.pop("active_entity", None)
    if update.message:
        await update.message.reply_text("✅ Historial de conversación y memoria activa reiniciados.")


async def cmd_ia_grupo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando de control exclusivo del Owner para activar/desactivar la IA en el Grupo Corporativo."""
    if not update.message:
        return

    user = update.effective_user
    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))

    if not user or user.id != owner_id:
        await update.message.reply_text("⛔ Acceso restringido: Este comando es exclusivo del Administrador Principal.")
        return

    args = context.args or []
    if not args:
        current_status = is_telegram_group_ai_enabled()
        status_str = "🟢 <b>ACTIVADO</b>" if current_status else "🔴 <b>DESACTIVADO (Pausado)</b>"
        await update.message.reply_text(
            f"🤖 <b>Control de Asistente IA en Grupo Corporativo</b>\n\n"
            f"• Estado de la Línea 2 (Grupo): {status_str}\n\n"
            f"<b>Comandos de gestión:</b>\n"
            f"• <code>/ia_grupo on</code> ➔ Activa la atención en el grupo (responde a menciones).\n"
            f"• <code>/ia_grupo off</code> ➔ Desactiva la IA en el grupo (silencio total).\n"
            f"• <code>/ia_grupo status</code> ➔ Muestra el estado actual.\n\n"
            f"<i>Nota: También puede conmutarlo desde el Portal Web en Configuración Avanzada.</i>",
            parse_mode="HTML",
        )
        return

    subcmd = args[0].strip().lower()
    if subcmd in ("on", "activar", "habilitar", "1"):
        success = set_telegram_group_ai_enabled(True, updated_by=f"{user.first_name or 'Owner'} [Telegram]")
        if success:
            await update.message.reply_text(
                "✅ <b>Línea 2 de IA Activada para el Grupo Corporativo</b>\n\n"
                "El Asistente inteligente responderá cuando los miembros lo mencionen o respondan a sus mensajes en el grupo corporativo.\n"
                "<i>La Línea 1 (su chat privado) se mantiene permanente e irrestricta.</i>",
                parse_mode="HTML",
            )
        else:
            await update.message.reply_text("❌ Error al persistir la activación en MariaDB SSOT.")

    elif subcmd in ("off", "desactivar", "pausar", "0"):
        success = set_telegram_group_ai_enabled(False, updated_by=f"{user.first_name or 'Owner'} [Telegram]")
        if success:
            await update.message.reply_text(
                "⛔ <b>Línea 2 de IA Desactivada para el Grupo Corporativo</b>\n\n"
                "El Asistente permanecerá en silencio absoluto en el grupo corporativo (cero consumo de CPU).\n"
                "<i>Los 2 hilos de inferencia quedan dedicados a su chat privado.</i>",
                parse_mode="HTML",
            )
        else:
            await update.message.reply_text("❌ Error al persistir la desactivación en MariaDB SSOT.")

    elif subcmd in ("status", "estado", "check"):
        current_status = is_telegram_group_ai_enabled()
        status_str = "🟢 <b>ACTIVADO</b>" if current_status else "🔴 <b>DESACTIVADO (Pausado)</b>"
        await update.message.reply_text(f"📊 <b>Estado de la Línea 2 de IA (Grupo):</b> {status_str}", parse_mode="HTML")
    else:
        await update.message.reply_text(
            "⚠️ Parámetro no reconocido. Use <code>/ia_grupo on</code>, <code>/ia_grupo off</code> o <code>/ia_grupo status</code>.",
            parse_mode="HTML",
        )


async def handle_chat_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja mensajes de texto normales y los envía al motor local de IA respetando las 2 líneas autorizadas."""
    if not update.message or not update.message.text:
        return

    chat = update.effective_chat
    user = update.effective_user
    if not chat or not user:
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))

    # LÍNEA 1: CHAT PRIVADO DEL OWNER
    if chat.type == "private":
        if user.id != owner_id or chat.id != owner_id:
            logger.warning("Intento de interacción no autorizada en chat privado: chat_id=%s, user_id=%s", chat.id, user.id)
            await check_authorization(update, context)
            return

        raw_user_text = update.message.text.strip()
        if not raw_user_text:
            return

        await _execute_chat_pipeline(update, context, raw_user_text)
        return

    # LÍNEA 2: GRUPO CORPORATIVO (CONDICIONAL)
    if chat.type in ("group", "supergroup") or chat.id < 0:
        if not is_telegram_group_ai_enabled():
            return

        message_text = update.message.text.strip()
        bot_username = (context.bot.username or "").lower()
        bot_id = context.bot.id

        is_targeted = False
        if bot_username and f"@{bot_username}" in message_text.lower():
            is_targeted = True
        elif update.message.reply_to_message:
            reply_from = update.message.reply_to_message.from_user
            if reply_from and reply_from.id == bot_id:
                is_targeted = True

        if not is_targeted:
            return

        clean_text = message_text
        if bot_username:
            clean_text = re.sub(rf"@{re.escape(bot_username)}", "", clean_text, flags=re.IGNORECASE).strip()

        if not clean_text:
            clean_text = "Hola, soy el asistente virtual del centro de operaciones. ¿En qué puedo apoyarte?"

        await _execute_chat_pipeline(update, context, clean_text, is_group_context=True)
        return


async def handle_chat_query_direct(update: Update, context: ContextTypes.DEFAULT_TYPE, user_text: str) -> None:
    """Procesa una consulta directa (ej. /ia <pregunta>) respetando el filtrado exclusivo del Owner o Grupo."""
    if not update.message or not user_text:
        return

    chat = update.effective_chat
    user = update.effective_user
    if not chat or not user:
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", 38914901))

    if chat.type == "private":
        if user.id != owner_id or chat.id != owner_id:
            await update.message.reply_text(
                "⛔ Acceso restringido: El asistente de IA está disponible exclusivamente para el chat privado del Administrador del Centro de Operaciones ATIT."
            )
            return
        await _execute_chat_pipeline(update, context, user_text.strip())
        return

    if chat.type in ("group", "supergroup") or chat.id < 0:
        if not is_telegram_group_ai_enabled():
            return
        await _execute_chat_pipeline(update, context, user_text.strip(), is_group_context=True)
        return


def register_ai_routes(application: Application) -> None:
    """Registra los comandos de IA e interactividad conversacional."""
    application.add_handler(CommandHandler(["reset_ia", "reset_chat", "olvidar"], reset_chat))
    application.add_handler(CommandHandler(["ia_grupo", "ia_group"], cmd_ia_grupo))
