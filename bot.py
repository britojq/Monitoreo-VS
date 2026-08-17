import os
import re
import json
import html
import logging
import asyncio
import subprocess
import requests
from pathlib import Path

from telegram import Update
from telegram.constants import ChatAction
from telegram.ext import Application, CommandHandler, ContextTypes, MessageHandler, filters


async def safe_reply_html(message_obj, text: str, **kwargs) -> None:
    """Envía un mensaje con parse_mode='HTML'. Si Telegram rechaza la sintaxis HTML, reintenta enviarlo sin parse_mode."""
    try:
        await message_obj.reply_text(text, parse_mode='HTML', **kwargs)
    except Exception as e:
        logger.warning(f"Error al enviar mensaje con parse_mode='HTML': {e}. Reintentando sin formato HTML.")
        clean_text = re.sub(r'<[^>]+>', '', text)
        await message_obj.reply_text(clean_text, **kwargs)



# Directorio base del script para cargar archivos de configuración externos
BASE_DIR = Path(__file__).resolve().parent
CONFIG_PATH = BASE_DIR / "config.json"
COMMANDS_PATH = BASE_DIR / "commands.json"
SYSTEM_PROMPT_PATH = BASE_DIR / "system_prompt.txt"


# --- LOGGING ---
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)


def env_int(name: str, default: int) -> int:
    """Lee una variable de entorno como entero de forma segura."""
    value = os.getenv(name)
    if value is None or value.strip() == "":
        return default
    try:
        return int(value.strip())
    except ValueError:
        logger.warning(f"No se pudo convertir la variable de entorno {name} a entero. Usando default: {default}")
        return default


def load_config() -> dict:
    """Carga los parámetros del bot desde config.json o variables de entorno."""
    default_config = {
        # Telegram
        "bot_token": os.getenv("BOT_TOKEN", ""),
        "owner_id": env_int("OWNER_ID", 0),
        "allowed_user_ids": [],
        "allowed_group_ids": [],
        "commands_enabled": True,

        # Ollama
        "ollama_enabled": True,
        "ollama_allow_all": False,
        "ollama_base_url": os.getenv("OLLAMA_BASE_URL", "http://localhost:11434"),
        "ollama_model": os.getenv("OLLAMA_MODEL", "qwen-empresa"),
        "ollama_timeout": 180,
        "ollama_temperature": 0.3,
        "ollama_num_ctx": 4096,
        "ollama_max_history": 12,
        "ollama_include_system_prompt": True,
        "ollama_system_prompt": ""
    }

    if CONFIG_PATH.exists():
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                file_config = json.load(f)
                default_config.update(file_config)
                logger.info(f"Configuración cargada desde {CONFIG_PATH}")
        except Exception as e:
            logger.error(f"Error al leer {CONFIG_PATH}: {e}")

    # Overrides por variables de entorno
    if os.getenv("BOT_TOKEN"):
        default_config["bot_token"] = os.getenv("BOT_TOKEN")

    if os.getenv("OLLAMA_BASE_URL"):
        default_config["ollama_base_url"] = os.getenv("OLLAMA_BASE_URL")

    if os.getenv("OLLAMA_MODEL"):
        default_config["ollama_model"] = os.getenv("OLLAMA_MODEL")

    if os.getenv("COMMANDS_ENABLED"):
        default_config["commands_enabled"] = os.getenv("COMMANDS_ENABLED").strip().lower() in ("true", "1", "yes")

    return default_config


def load_commands_data() -> tuple[dict, dict]:
    """Carga los comandos y mensajes informativos/ayuda desde commands.json."""
    default_messages = {
        "start_header": "🤖 <b>Bot de Administración de Servidores</b>\n\nComandos disponibles:",
        "unknown_command": "⚠️ Comando no reconocido. Usa <code>/start</code> para ver las opciones disponibles.",
        "help_general": "ℹ️ Usa <code>/start</code> o <code>/help [comando]</code> para información específica."
    }

    if COMMANDS_PATH.exists():
        try:
            with open(COMMANDS_PATH, "r", encoding="utf-8") as f:
                data = json.load(f)

                if "commands" in data or "messages" in data:
                    messages = data.get("messages", default_messages)
                    commands = data.get("commands", {})
                else:
                    messages = default_messages
                    commands = data

                logger.info(f"Comandos y mensajes cargados exitosamente desde {COMMANDS_PATH}")
                return messages, commands
        except Exception as e:
            logger.error(f"Error al leer {COMMANDS_PATH}: {e}")
            return default_messages, {}
    else:
        logger.warning(f"No se encontró el archivo {COMMANDS_PATH}")
        return default_messages, {}


def load_system_prompt(config: dict) -> str:
    """
    Carga la identidad corporativa / personalidad.

    Prioridad:
    1. system_prompt.txt
    2. campo ollama_system_prompt dentro de config.json
    """
    if SYSTEM_PROMPT_PATH.exists():
        try:
            content = SYSTEM_PROMPT_PATH.read_text(encoding="utf-8").strip()
            if content:
                logger.info(f"System prompt cargado desde {SYSTEM_PROMPT_PATH}")
                return content
        except Exception as e:
            logger.error(f"Error al leer {SYSTEM_PROMPT_PATH}: {e}")

    return str(config.get("ollama_system_prompt", "")).strip()


# Cargar datos de configuración
CONFIG = load_config()
MESSAGES, COMMANDS = load_commands_data()
SYSTEM_PROMPT = load_system_prompt(CONFIG)


# --- SEGURIDAD Y AUTORIZACIÓN ---
def is_authorized(update: Update) -> bool:
    """Verifica la autorización del usuario y del chat."""
    if not update.effective_user or not update.effective_chat:
        return False

    user_id = update.effective_user.id
    chat_id = update.effective_chat.id
    chat_type = update.effective_chat.type

    owner_id = CONFIG.get("owner_id", 0)
    allowed_users = CONFIG.get("allowed_user_ids", [])
    allowed_groups = CONFIG.get("allowed_group_ids", [])

    user_authorized = (user_id == owner_id) or (user_id in allowed_users)

    if not user_authorized:
        logger.warning(f"Acceso DENEGADO para el usuario: {user_id} en chat: {chat_id}")
        return False

    if chat_type in ['group', 'supergroup']:
        if not allowed_groups:
            logger.warning(f"Acceso DENEGADO en grupo {chat_id}: no hay grupos autorizados configurados.")
            return False

        if chat_id not in allowed_groups:
            logger.warning(f"Acceso DENEGADO en grupo no autorizado: {chat_id}")
            return False

    return True


# --- EJECUCIÓN ASÍNCRONA DE COMANDOS DEL SISTEMA ---
def _exec_system_command(command: list, timeout: int = 300) -> dict:
    """Ejecuta subprocesos del sistema con un tiempo límite (timeout) configurable en segundos."""
    try:
        result = subprocess.run(command, capture_output=True, text=True, timeout=timeout)
        output = result.stdout if result.stdout else result.stderr

        if output and len(output) > 3800:
            output = output[:3700] + "\n... [Salida truncada por límite de Telegram] ..."

        success = (result.returncode == 0)
        return {
            "success": success,
            "returncode": result.returncode,
            "output": output if output else "Comando ejecutado sin salida."
        }

    except subprocess.TimeoutExpired:
        return {
            "success": False,
            "returncode": -1,
            "output": f"Error: El comando tardó demasiado en responder (Timeout > {timeout}s)."
        }
    except Exception as e:
        return {
            "success": False,
            "returncode": -1,
            "output": f"Error al ejecutar el comando: {str(e)}"
        }


async def run_command_async(command: list, timeout: int = 300) -> dict:
    """Ejecuta el subproceso en un hilo secundario sin bloquear asyncio."""
    return await asyncio.to_thread(_exec_system_command, command, timeout)


# --- FUNCIONES PARA OLLAMA ---
def markdown_to_telegram_html(text: str) -> str:
    """Convierte Markdown estándar (títulos, negritas, código de consola) a HTML compatible con Telegram."""
    if not text:
        return ""

    # 1. Proteger bloques de código multilínea ```lang ... ```
    code_blocks = []
    def _save_code_block(match):
        lang = (match.group(1) or "").strip()
        code_content = match.group(2)
        idx = len(code_blocks)
        code_blocks.append((lang, code_content))
        return f"QQQBLOCKCODE{idx}ZZZ"

    text = re.sub(r'```([a-zA-Z0-9_-]*)\n?(.*?)```', _save_code_block, text, flags=re.DOTALL)

    # 2. Proteger código inline `code`
    inline_codes = []
    def _save_inline_code(match):
        inline_content = match.group(1)
        idx = len(inline_codes)
        inline_codes.append(inline_content)
        return f"QQQINLINECODE{idx}ZZZ"

    text = re.sub(r'`([^`\n]+)`', _save_inline_code, text)

    # 3. Escapar caracteres HTML básicos en el texto general
    text = html.escape(text)

    # 4. Títulos y subtítulos (# Título -> <b>Título</b>)
    text = re.sub(r'^(#{1,6})\s+(.+)$', r'<b>\2</b>', text, flags=re.MULTILINE)

    # 5. Negrita (**texto** o __texto__)
    text = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', text, flags=re.DOTALL)
    text = re.sub(r'(?<![a-zA-Z0-9])__(.+?)__(?![a-zA-Z0-9])', r'<b>\1</b>', text, flags=re.DOTALL)

    # 6. Cursiva (*texto* o _texto_)
    text = re.sub(r'(?<!\*)\*([^\*\n]+)\*(?!\*)', r'<i>\1</i>', text)
    text = re.sub(r'(?<![a-zA-Z0-9_])_([^_\n]+)_(?![a-zA-Z0-9_])', r'<i>\1</i>', text)

    # 7. Restaurar bloques de código multilínea <pre><code>...</code></pre>
    for idx, (lang, code_content) in enumerate(code_blocks):
        escaped_code = html.escape(code_content.strip('\r\n'))
        if lang:
            replacement = f'<pre><code class="language-{html.escape(lang)}">{escaped_code}</code></pre>'
        else:
            replacement = f'<pre><code>{escaped_code}</code></pre>'
        text = text.replace(f"QQQBLOCKCODE{idx}ZZZ", replacement)

    # 8. Restaurar código inline <code>...</code>
    for idx, inline_content in enumerate(inline_codes):
        escaped_inline = html.escape(inline_content)
        replacement = f'<code>{escaped_inline}</code>'
        text = text.replace(f"QQQINLINECODE{idx}ZZZ", replacement)

    return text


def split_message(text: str, limit: int = 4096) -> list:
    """Divide un texto largo en fragmentos compatibles con Telegram."""
    text = (text or "").strip()

    if not text:
        return ["(Sin respuesta)"]

    chunks = []

    while len(text) > limit:
        cut = text.rfind("\n", 0, limit)

        # Si no hay un buen salto de línea, cortamos duro.
        if cut <= limit // 2:
            cut = limit

        chunks.append(text[:cut].rstrip())
        text = text[cut:].lstrip()

    if text:
        chunks.append(text)

    return chunks


def append_to_history(context: ContextTypes.DEFAULT_TYPE, role: str, content: str) -> None:
    """Guarda un mensaje en el historial del chat, con límite configurable."""
    max_history = int(CONFIG.get("ollama_max_history", 12))

    # Si max_history es 0 o negativo, desactivamos historial.
    if max_history <= 0:
        return

    content = (content or "").strip()

    if not content:
        return

    # Evitamos que un mensaje gigante reviente el contexto.
    if len(content) > 8000:
        content = content[:8000]

    history = context.chat_data.setdefault("history", [])
    history.append({
        "role": role,
        "content": content
    })

    if len(history) > max_history:
        context.chat_data["history"] = history[-max_history:]


def _build_ollama_messages(user_text: str, history: list) -> list:
    """Construye la lista de mensajes para enviar a Ollama."""
    messages = []

    system_prompt = SYSTEM_PROMPT.strip()

    if CONFIG.get("ollama_include_system_prompt", True) and system_prompt:
        messages.append({
            "role": "system",
            "content": system_prompt
        })

    if history:
        messages.extend(history)

    messages.append({
        "role": "user",
        "content": user_text
    })

    return messages


def _ask_ollama_sync(user_text: str, history: list) -> str:
    """Consulta sincrona a Ollama. Se ejecuta en un hilo para no bloquear el bot."""
    base_url = str(CONFIG.get("ollama_base_url", "http://localhost:11434")).rstrip("/")
    model = CONFIG.get("ollama_model", "qwen-empresa")
    timeout = int(CONFIG.get("ollama_timeout", 180))

    payload = {
        "model": model,
        "stream": False,
        "messages": _build_ollama_messages(user_text, history),
        "options": {
            "temperature": float(CONFIG.get("ollama_temperature", 0.3)),
            "num_ctx": int(CONFIG.get("ollama_num_ctx", 4096))
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
        raise RuntimeError("Ollama devolvió una respuesta inválida.")

    if data.get("error"):
        raise RuntimeError(str(data.get("error")))

    content = data.get("message", {}).get("content", "")
    content = (content or "").strip()

    if not content:
        return "Lo siento, el modelo no devolvió respuesta."

    return content


async def ask_ollama_async(user_text: str, history: list) -> str:
    """Consulta asíncrona a Ollama."""
    return await asyncio.to_thread(_ask_ollama_sync, user_text, history)


# --- HANDLERS DE COMANDOS Y AYUDA ---
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el mensaje inicial y la lista de comandos disponibles (o bienvenida interactiva)."""
    if not is_authorized(update):
        return

    commands_enabled = bool(CONFIG.get("commands_enabled", True))

    if not commands_enabled:
        interactive_msg = (
            "🤖 <b>Monitor Valle Seco (Modo Interactivo)</b>\n\n"
            "¡Hola! Los comandos del sistema se encuentran desactivados.\n\n"
            "💬 <i>Escríbeme directamente cualquier consulta técnica o administrativa para interactuar con el asistente IA.</i>\n\n"
            "🧹 <code>/reset_ia</code> - Reinicia el contexto de la conversación."
        )
        await safe_reply_html(update.message, interactive_msg)
        return

    if context.args:
        target_cmd = context.args[0].lstrip('/').lower()

        if target_cmd in COMMANDS:
            help_msg = COMMANDS[target_cmd].get(
                "help_text",
                f"Sin información detallada para /{target_cmd}."
            )
            await safe_reply_html(update.message, help_msg)
            return

    start_header = MESSAGES.get("start_header", "🤖 <b>Bot de Administración</b>")
    help_lines = [start_header, ""]

    for cmd_name, cmd_info in COMMANDS.items():
        desc = cmd_info.get("description", "Sin descripción")
        help_lines.append(f"/{cmd_name} - {html.escape(desc)}")

    help_lines.append("/reset_ia - Reinicia la conversación con el asistente")

    await safe_reply_html(update.message, "\n".join(help_lines))


async def handle_dynamic_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos dinámicos y soporta timeouts personalizados para tareas extensas."""
    if not is_authorized(update):
        return

    if not CONFIG.get("commands_enabled", True):
        await safe_reply_html(
            update.message,
            "⚠️ Los comandos del sistema están desactivados. El bot se encuentra en <b>modo interactivo</b>.\n"
            "Escríbeme directamente en texto para interactuar con la IA."
        )
        return

    full_text = update.message.text or ""
    parts = full_text.split()

    cmd_name = parts[0].lstrip('/').split('@')[0].lower()
    cmd_config = COMMANDS.get(cmd_name)

    if not cmd_config:
        await safe_reply_html(update.message, MESSAGES.get("unknown_command", "Comando no configurado."))
        return

    # Solicitar ayuda explícita (/comando help)
    if len(parts) > 1 and parts[1].lower() in ["help", "ayuda", "-h", "--help"]:
        help_text = cmd_config.get("help_text")

        if help_text:
            await safe_reply_html(update.message, help_text)
        else:
            desc = cmd_config.get("description", "Sin descripción")
            await safe_reply_html(
                update.message,
                f"ℹ️ <b>/{cmd_name}:</b> {html.escape(desc)}"
            )
        return

    # Determinar si se oculta/muestra la salida a nivel de comando (Default: True)
    cmd_show_output = True
    if "show_output" in cmd_config:
        cmd_show_output = bool(cmd_config.get("show_output"))
    elif "hide_output" in cmd_config:
        cmd_show_output = not bool(cmd_config.get("hide_output"))
    elif "silent" in cmd_config:
        cmd_show_output = not bool(cmd_config.get("silent"))

    # Mensaje informativo previo
    reply_header = cmd_config.get("reply_header")
    if reply_header:
        await safe_reply_html(update.message, reply_header)

    # Ejecución de los pasos del comando
    steps = cmd_config.get("steps", [])

    for step in steps:
        title = step.get("title")
        command_list = step.get("command")
        step_timeout = step.get("timeout", 300)

        # Determinar si se oculta/muestra la salida en este paso específico
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
            await safe_reply_html(update.message, response_text)
        else:
            # Salida oculta: NO enviamos el cuadro de código <pre>
            if not success:
                logger.warning(f"El comando '{command_list}' finalizó con código {exec_res.get('returncode')}, pero la salida está configurada como oculta.")
            if title:
                if success:
                    await safe_reply_html(update.message, f"✅ <b>{html.escape(title)}</b> ejecutado con éxito.")
                else:
                    await safe_reply_html(update.message, f"⚠️ <b>{html.escape(title)}</b> finalizado.")

    # Mensaje informativo posterior
    reply_footer = cmd_config.get("reply_footer")
    if reply_footer:
        await safe_reply_html(update.message, reply_footer)


async def reset_chat(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reinicia el historial de conversación con Ollama."""
    allow_all = bool(CONFIG.get("ollama_allow_all", False))

    if not allow_all and not is_authorized(update):
        return

    context.chat_data.pop("history", None)

    await update.message.reply_text(
        "✅ Historial de conversación reiniciado."
    )


async def _animate_waiting_message(bot, chat_id: int, message_obj, stop_event: asyncio.Event) -> None:
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


async def handle_chat_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """
    Maneja mensajes de texto normales y los envía a Ollama.
    Los comandos /... no pasan por aquí.
    """
    if not update.message or not update.message.text:
        return

    if not CONFIG.get("ollama_enabled", True):
        await update.message.reply_text("El asistente conversacional está deshabilitado.")
        return

    allow_all = bool(CONFIG.get("ollama_allow_all", False))

    if not allow_all and not is_authorized(update):
        return

    user_text = update.message.text.strip()

    if not user_text:
        return

    # Mensaje temporal de espera y animación de reloj giratorio
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
            "⏱️ El modelo tardó demasiado en responder.\n"
            "Intenta nuevamente en unos segundos."
        )

    except requests.exceptions.ConnectionError:
        answer = (
            "🔌 No pude conectar con Ollama.\n"
            "Verifica que el servicio esté activo en este equipo."
        )

    except requests.exceptions.RequestException as e:
        logger.exception("Error HTTP/Request consultando Ollama")
        answer = (
            "⚠️ Ocurrió un error de red o del servicio Ollama.\n"
            "Revisa los logs del bot."
        )

    except Exception as e:
        logger.exception("Error inesperado consultando Ollama")
        answer = (
            "⚠️ Ocurrió un error inesperado consultando al asistente.\n"
            "Intenta nuevamente."
        )

    finally:
        # Detener la animación y borrar el mensaje temporal de espera
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

    for chunk in split_message(formatted_answer):
        await safe_reply_html(
            update.message,
            chunk,
            disable_web_page_preview=True
        )


async def unknown_cmd(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos no registrados."""
    if not is_authorized(update):
        return

    commands_enabled = bool(CONFIG.get("commands_enabled", True))

    if not commands_enabled:
        unk_msg = (
            "⚠️ Los comandos del sistema están desactivados. El bot se encuentra en <b>modo interactivo</b>.\n"
            "Escribe directamente tu mensaje o usa <code>/reset_ia</code>."
        )
    else:
        unk_msg = MESSAGES.get(
            "unknown_command",
            "Comando no reconocido. Usa /start para ver las opciones."
        )

    await safe_reply_html(update.message, unk_msg)


def main() -> None:
    bot_token = CONFIG.get("bot_token")

    if not bot_token or bot_token in ("TU_TOKEN_AQUI", "TU_TOKEN_DE_TELEGRAM"):
        logger.error("Error: BOT_TOKEN no configurado en config.json o variables de entorno.")
        return

    application = Application.builder().token(bot_token).build()

    reserved_commands = {
        "start",
        "help",
        "ayuda",
        "reset_ia",
        "reset_chat",
        "borrar_chat"
    }

    # Comandos base
    application.add_handler(CommandHandler(["start", "help", "ayuda"], start))

    # Comando para reiniciar conversación IA
    application.add_handler(CommandHandler(["reset_ia", "reset_chat", "borrar_chat"], reset_chat))

    commands_enabled = bool(CONFIG.get("commands_enabled", True))

    # Comandos dinámicos existentes desde commands.json (solo si están habilitados)
    if commands_enabled:
        for cmd_name in COMMANDS.keys():
            if cmd_name.lower() not in reserved_commands:
                application.add_handler(CommandHandler(cmd_name, handle_dynamic_command))
        logger.info("Bot iniciado con comandos dinámicos + asistente Ollama.")
    else:
        logger.info("Bot iniciado en MODO INTERACTIVO (comandos dinámicos deshabilitados).")

    # Texto normal -> Ollama
    application.add_handler(
        MessageHandler(filters.TEXT & ~filters.COMMAND, handle_chat_message)
    )

    # Comandos desconocidos
    application.add_handler(MessageHandler(filters.COMMAND, unknown_cmd))

    application.run_polling(allowed_updates=Update.ALL_TYPES)


if __name__ == "__main__":
    main()
