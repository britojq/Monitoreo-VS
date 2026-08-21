import os
import re
import time
import json
import html
import logging
import asyncio
import subprocess
import requests
import httpx
import urllib.parse
from datetime import datetime
from pathlib import Path

import telegram
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.constants import ChatAction
from telegram.request import HTTPXRequest
from telegram.ext import Application, CallbackQueryHandler, CommandHandler, ContextTypes, MessageHandler, filters


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

        # Seguridad y Auditoría
        "notify_unauthorized_to_owner": True,
        "reply_unauthorized_user": True,
        "log_unauthorized_to_file": True,
        "audit_log_file": "intentos_acceso.log",
        "auto_proxy_failover": True,
        "proxies": [],

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


def save_config() -> bool:
    """Guarda la configuración actual en config.json asegurando persistencia de cambios."""
    try:
        with open(CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(CONFIG, f, indent=2, ensure_ascii=False)
        logger.info(f"Configuración guardada exitosamente en {CONFIG_PATH}")
        return True
    except Exception as e:
        logger.error(f"Error al guardar configuración en {CONFIG_PATH}: {e}")
        return False


def load_proxies_list() -> list[dict]:
    """Carga la lista de proxies desde config.json o desde /scripts/monitor/config/bot.conf."""
    custom_proxies = CONFIG.get("proxies")
    if custom_proxies and isinstance(custom_proxies, list) and len(custom_proxies) > 0:
        return custom_proxies

    bot_conf_path = Path("/scripts/monitor/config/bot.conf")
    proxies = []
    if bot_conf_path.exists():
        try:
            content = bot_conf_path.read_text(encoding="utf-8")
            data = {}
            for line in content.splitlines():
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    data[k.strip()] = v.strip().strip("'\"")

            for letter in ("A", "B", "C", "D"):
                ip = data.get(f"IPADDRPORTPROXY{letter}")
                auth = data.get(f"USERPASSWDPROXY{letter}")
                name = data.get(f"NAMEPROXY{letter}", f"Proxy {letter}")
                if ip:
                    if auth and ":" in auth:
                        user, pwd = auth.split(":", 1)
                        user_enc = urllib.parse.quote(user)
                        pwd_enc = urllib.parse.quote(pwd)
                        url = f"http://{user_enc}:{pwd_enc}@{ip}"
                    else:
                        url = f"http://{ip}"
                    proxies.append({
                        "name": name,
                        "url": url,
                        "enabled": True
                    })
        except Exception as e:
            logger.warning(f"No se pudieron leer proxies de {bot_conf_path}: {e}")

    return proxies


ACTIVE_CONNECTION_LABEL = "Conexión Directa"
ACTIVE_PROXY_URL = None


def select_working_connection(bot_token: str) -> str | None:
    """
    Evalúa la conectividad con Telegram Bot API:
    1. Prueba conexión directa con timeout corto (3.5s).
    2. Si falla directa, prueba en orden los proxies configurados en config.json o bot.conf.
    3. Retorna la URL del proxy funcional (o None si la conexión directa funciona).
    """
    global ACTIVE_CONNECTION_LABEL, ACTIVE_PROXY_URL
    url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Probar conexión directa
    try:
        r = httpx.get(url, timeout=3.5)
        if r.status_code == 200 and r.json().get("ok"):
            logger.info("🌐 Conexión DIRECTA a Telegram verificada exitosamente.")
            ACTIVE_CONNECTION_LABEL = "Conexión Directa"
            ACTIVE_PROXY_URL = None
            return None
    except Exception as e:
        logger.warning(f"Conexión directa a Telegram no disponible ({e}). Evaluando proxies corporativos...")

    # 2. Probar proxies
    proxies = load_proxies_list()
    for p in proxies:
        if not p.get("enabled", True):
            continue
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if not p_url:
            continue

        try:
            r = httpx.get(url, proxy=p_url, timeout=4.5)
            if r.status_code == 200 and r.json().get("ok"):
                logger.info(f"🔄 Conectividad exitosa con Telegram vía [{p_name}]: {p_url}")
                ACTIVE_CONNECTION_LABEL = f"Proxy: {p_name}"
                ACTIVE_PROXY_URL = p_url
                return p_url
        except Exception as e:
            logger.info(f"Proxy [{p_name}] no disponible: {e}")

    logger.warning("⚠️ No se pudo verificar ningún proxy ni conexión directa. Intentando conexión estándar...")
    ACTIVE_CONNECTION_LABEL = "Conexión Estándar (Sin verificar)"
    ACTIVE_PROXY_URL = None
    return None


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
    """Verifica si el usuario y el chat están autorizados."""
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
        return False

    if chat_type in ['group', 'supergroup']:
        if not allowed_groups:
            return False
        if chat_id not in allowed_groups:
            return False

    return True


async def check_authorization(update: Update, context: ContextTypes.DEFAULT_TYPE) -> bool:
    """
    Verifica la autorización del usuario y chat.
    Si NO está autorizado:
    1. Registra el evento en el archivo de auditoría (intentos_acceso.log).
    2. Notifica inmediatamente al administrador (owner_id).
    3. Responde al usuario informándole que no tiene acceso e indicando su ID.
    """
    if is_authorized(update):
        return True

    user = update.effective_user
    chat = update.effective_chat

    user_id = user.id if user else 0
    username = f"@{user.username}" if (user and user.username) else ""
    first_name = (user.first_name or "").strip() if user else ""
    last_name = (user.last_name or "").strip() if user else ""
    if last_name.lower() == "none":
        last_name = ""
    name_parts = [p for p in [first_name, last_name] if p]
    full_name = " ".join(name_parts) or "(sin nombre)"
    lang = user.language_code if user else "desconocido"

    # Guardar en caché de usuarios para el comando /permisos
    if user_id:
        CONFIG.setdefault("users_cache", {})[str(user_id)] = {
            "full_name": full_name,
            "username": username
        }

    chat_id = chat.id if chat else 0
    chat_type = chat.type if chat else "desconocido"
    chat_title = chat.title if (chat and chat.type in ['group', 'supergroup']) else "Chat Privado"

    if chat_id and chat.title:
        CONFIG.setdefault("groups_cache", {})[str(chat_id)] = chat.title

    msg_text = update.message.text if (update.message and update.message.text) else "(sin texto)"
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    user_label = f"{full_name} ({username})" if username else full_name
    logger.warning(
        f"Acceso DENEGADO | User: {user_label} (ID: {user_id}) | "
        f"Chat: {chat_title} (ID: {chat_id}) | Msg: {msg_text}"
    )

    # 1. Registrar en archivo de auditoría
    if CONFIG.get("log_unauthorized_to_file", True):
        audit_file_name = CONFIG.get("audit_log_file", "intentos_acceso.log")
        audit_path = BASE_DIR / audit_file_name
        log_line = (
            f"[{now_str}] NO AUTORIZADO | ID: {user_id} | Username: {username} | "
            f"Nombre: {full_name} | Idioma: {lang} | "
            f"Chat: {chat_title} (ID: {chat_id}, Tipo: {chat_type}) | "
            f"Mensaje: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

    # 2. Responder al usuario no autorizado
    if update.message and CONFIG.get("reply_unauthorized_user", True):
        user_reply = (
            "⛔ <b>Acceso Restringido</b>\n\n"
            "No tienes autorización para interactuar con este bot.\n\n"
            f"Para solicitar acceso al administrador, proporciona tu ID:\n"
            f"🆔 <code>{user_id}</code>"
        )
        await safe_reply_html(update.message, user_reply)

    # 3. Notificar en tiempo real al owner con botones interactivos de autorización
    owner_id = CONFIG.get("owner_id", 0)
    if owner_id and user_id != owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
        owner_alert = (
            "🚨 <b>Alerta: Intento de Acceso No Autorizado</b>\n\n"
            f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username)})\n"
            f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
            f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
            f"🌐 <b>Idioma:</b> <code>{html.escape(lang)}</code>\n"
            f"📝 <b>Mensaje enviado:</b>\n<pre>{html.escape(msg_text)}</pre>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>"
        )
        keyboard = [
            [
                InlineKeyboardButton("✅ Permitir / Autorizar", callback_data=f"auth_allow:{user_id}"),
                InlineKeyboardButton("❌ Denegar", callback_data=f"auth_deny:{user_id}")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)

        try:
            await context.bot.send_message(
                chat_id=owner_id,
                text=owner_alert,
                reply_markup=reply_markup,
                parse_mode='HTML'
            )
        except Exception as e:
            logger.warning(f"No se pudo enviar notificación de alerta al owner ({owner_id}): {e}")

    return False


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
    if not await check_authorization(update, context):
        return

    commands_enabled = bool(CONFIG.get("commands_enabled", True))

    if not commands_enabled:
        lines = [
            "🤖 <b>Monitor Valle Seco (Modo Interactivo)</b>",
            "",
            "¡Hola! Los comandos del sistema se encuentran desactivados.",
            "",
            "💬 <i>Escríbeme directamente cualquier consulta técnica o administrativa para interactuar con el asistente IA.</i>",
            "",
            "🧹 <code>/reset_ia</code> - Reinicia el contexto de la conversación."
        ]
        if update.effective_user and update.effective_user.id == CONFIG.get("owner_id", 0):
            lines.append("🔐 <code>/permisos</code> - Administrar usuarios y grupos permitidos.")
            lines.append("📊 <code>/botstatus</code> - Diagnóstico de red, proxies y estado de accesos.")

        await safe_reply_html(update.message, "\n".join(lines))
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

    if update.effective_user and update.effective_user.id == CONFIG.get("owner_id", 0):
        help_lines.append("/permisos - Administrar usuarios y grupos permitidos (Owner)")
        help_lines.append("/botstatus - Diagnóstico de red, proxies y accesos (Owner)")

    await safe_reply_html(update.message, "\n".join(help_lines))


async def handle_dynamic_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos dinámicos y soporta timeouts personalizados para tareas extensas."""
    if not await check_authorization(update, context):
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

    if not allow_all and not await check_authorization(update, context):
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

    if not allow_all and not await check_authorization(update, context):
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
    if not await check_authorization(update, context):
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


async def _resolve_user_info(bot, user_id: int) -> tuple[str, str]:
    """Obtiene el nombre completo y @username de un usuario vía Telegram API o caché local."""
    users_cache = CONFIG.get("users_cache", {})
    cached = users_cache.get(str(user_id), {})
    cached_name = cached.get("full_name", "")
    cached_user = cached.get("username", "")

    try:
        chat = await bot.get_chat(user_id)
        first_name = (chat.first_name or "").strip()
        last_name = (chat.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        name_parts = [p for p in [first_name, last_name] if p]
        full_name = " ".join(name_parts) or chat.title or cached_name or "(sin nombre)"
        username = f"@{chat.username}" if chat.username else cached_user

        CONFIG.setdefault("users_cache", {})[str(user_id)] = {
            "full_name": full_name,
            "username": username
        }
        return full_name, username
    except Exception:
        return cached_name or "(Nombre no disponible)", cached_user


async def _resolve_group_info(bot, group_id: int) -> str:
    """Obtiene el título de un grupo vía Telegram API o caché local."""
    groups_cache = CONFIG.get("groups_cache", {})
    cached_title = groups_cache.get(str(group_id), "")

    try:
        chat = await bot.get_chat(group_id)
        title = chat.title or cached_title or "Grupo"
        CONFIG.setdefault("groups_cache", {})[str(group_id)] = title
        return title
    except Exception:
        return cached_title or "Grupo"


async def _build_permissions_panel(bot) -> tuple[str, InlineKeyboardMarkup | None]:
    """Construye el texto y el teclado interactivo con datos completos de usuarios y grupos."""
    owner_id = CONFIG.get("owner_id", 0)
    allowed_users = CONFIG.get("allowed_user_ids", [])
    allowed_groups = CONFIG.get("allowed_group_ids", [])

    owner_name, owner_username = await _resolve_user_info(bot, owner_id)
    owner_tag = f" ({owner_username})" if owner_username else ""

    text_lines = [
        "🔐 <b>Panel de Control de Acceso y Permisos</b>",
        "",
        f"👑 <b>Creador / Owner:</b> {html.escape(owner_name)}{html.escape(owner_tag)}",
        f"🆔 <b>ID de Telegram:</b> <code>{owner_id}</code> <i>(Acceso Permanente)</i>",
        ""
    ]

    keyboard = []

    # Usuarios adicionales
    other_users = [u for u in allowed_users if u != owner_id]
    text_lines.append("👥 <b>Usuarios Permitidos:</b>")
    if not other_users:
        text_lines.append("<i>(No hay usuarios adicionales en la lista)</i>")
    else:
        for idx, uid in enumerate(other_users, 1):
            name, username = await _resolve_user_info(bot, uid)
            user_tag = f" ({username})" if username else ""
            text_lines.append(f"{idx}. 👤 <b>Usuario:</b> {html.escape(name)}{html.escape(user_tag)}")
            text_lines.append(f"   🆔 <b>ID de Telegram:</b> <code>{uid}</code>")

            btn_label = f"❌ Quitar: {name[:15]} ({uid})"
            keyboard.append([
                InlineKeyboardButton(btn_label, callback_data=f"auth_revoke_user:{uid}")
            ])

    text_lines.append("")
    # Grupos permitidos
    text_lines.append("🏢 <b>Grupos Permitidos:</b>")
    if not allowed_groups:
        text_lines.append("<i>(No hay grupos en la lista)</i>")
    else:
        for idx, gid in enumerate(allowed_groups, 1):
            gtitle = await _resolve_group_info(bot, gid)
            text_lines.append(f"{idx}. 👥 <b>Grupo:</b> {html.escape(gtitle)}")
            text_lines.append(f"   🆔 <b>ID de Chat:</b> <code>{gid}</code>")

            btn_label = f"❌ Quitar Grupo: {gtitle[:15]} ({gid})"
            keyboard.append([
                InlineKeyboardButton(btn_label, callback_data=f"auth_revoke_group:{gid}")
            ])

    if keyboard:
        text_lines.append("")
        text_lines.append("ℹ️ <i>Presiona un botón para revocar el acceso a un usuario o grupo.</i>")

    reply_markup = InlineKeyboardMarkup(keyboard) if keyboard else None
    return "\n".join(text_lines), reply_markup


async def manage_permissions(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el panel de gestión de permisos con datos detallados (exclusivo para el owner)."""
    if not update.effective_user:
        return

    owner_id = CONFIG.get("owner_id", 0)
    if update.effective_user.id != owner_id:
        await safe_reply_html(update.message, "⛔ Este comando es exclusivo para el creador y administrador del bot.")
        return

    panel_text, reply_markup = await _build_permissions_panel(context.bot)
    await safe_reply_html(update.message, panel_text, reply_markup=reply_markup)


def get_denied_users_summary() -> list[dict]:
    """Extrae la lista de usuarios no autorizados registrados en el log de auditoría."""
    audit_file_name = CONFIG.get("audit_log_file", "intentos_acceso.log")
    audit_path = BASE_DIR / audit_file_name
    if not audit_path.exists():
        return []

    denied_map = {}
    allowed_users = set(CONFIG.get("allowed_user_ids", []))
    owner_id = CONFIG.get("owner_id", 0)
    if owner_id:
        allowed_users.add(owner_id)

    try:
        lines = audit_path.read_text(encoding="utf-8").splitlines()
        for line in reversed(lines):
            line = line.strip()
            if not line or "NO AUTORIZADO" not in line:
                continue

            try:
                date_match = re.search(r"\[(.*?)\]", line)
                fecha = date_match.group(1) if date_match else "Desconocida"

                id_match = re.search(r"ID:\s*(-?\d+)", line)
                user_id = int(id_match.group(1)) if id_match else None

                user_match = re.search(r"Username:\s*([^|]+)", line)
                username = user_match.group(1).strip() if user_match else ""

                name_match = re.search(r"Nombre:\s*([^|]+)", line)
                nombre = name_match.group(1).strip() if name_match else "(sin nombre)"
                if nombre.lower() == "none" or nombre == "None":
                    nombre = "(sin nombre)"
                nombre = nombre.replace(" None", "").strip()

                if user_id and user_id not in allowed_users:
                    if user_id not in denied_map:
                        denied_map[user_id] = {
                            "user_id": user_id,
                            "username": username if username != "(sin username)" else "",
                            "name": nombre,
                            "last_seen": fecha
                        }
            except Exception:
                continue
    except Exception as e:
        logger.warning(f"Error leyendo {audit_path}: {e}")

    return list(denied_map.values())


async def _check_endpoint_health(name: str, tg_url: str, proxy_url: str | None = None, timeout: float = 3.5) -> dict:
    """Verifica la conectividad y latencia hacia Telegram (directa o vía proxy)."""
    t0 = time.perf_counter()
    try:
        async with httpx.AsyncClient(proxy=proxy_url, timeout=timeout) as client:
            r = await client.get(tg_url)
            elapsed_ms = int((time.perf_counter() - t0) * 1000)
            if r.status_code == 200 and r.json().get("ok"):
                return {
                    "name": name,
                    "ok": True,
                    "status_code": r.status_code,
                    "elapsed_ms": elapsed_ms,
                    "detail": f"Operativo (HTTP 200, {elapsed_ms} ms)"
                }
            return {
                "name": name,
                "ok": False,
                "status_code": r.status_code,
                "elapsed_ms": elapsed_ms,
                "detail": f"HTTP {r.status_code} ({elapsed_ms} ms)"
            }
    except Exception as e:
        elapsed_ms = int((time.perf_counter() - t0) * 1000)
        err_str = "Timeout" if "timed out" in str(e).lower() else "Inaccesible"
        return {
            "name": name,
            "ok": False,
            "status_code": 0,
            "elapsed_ms": elapsed_ms,
            "detail": f"{err_str} ({elapsed_ms} ms)"
        }


async def bot_status(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Ejecuta un diagnóstico completo de conectividad, proxies, usuarios permitidos y negados (Exclusivo Owner)."""
    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    if update.effective_user.id != owner_id:
        await safe_reply_html(update.message, "⛔ Este comando es exclusivo para el creador y administrador del bot.")
        return

    # Mensaje temporal de espera
    waiting_msg = None
    try:
        waiting_msg = await update.message.reply_text(
            "⏳ <i>Ejecutando diagnóstico en tiempo real de red, proxies y accesos...</i>",
            parse_mode='HTML'
        )
    except Exception:
        pass

    bot_token = CONFIG.get("bot_token", "")
    tg_url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Tareas concurrentes de diagnóstico de red
    network_tasks = [
        _check_endpoint_health("Conexión Directa a Internet", tg_url, None, timeout=3.5)
    ]

    proxies_list = load_proxies_list()
    for p in proxies_list:
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if p_url:
            network_tasks.append(_check_endpoint_health(p_name, tg_url, p_url, timeout=3.5))

    network_results = await asyncio.gather(*network_tasks)

    # 2. Resolución de datos de usuarios y grupos
    allowed_users = CONFIG.get("allowed_user_ids", [])
    allowed_groups = CONFIG.get("allowed_group_ids", [])

    owner_name, owner_username = await _resolve_user_info(context.bot, owner_id)
    owner_tag = f" ({owner_username})" if owner_username else ""

    # Usuarios permitidos adicionales
    other_users = [u for u in allowed_users if u != owner_id]
    user_info_tasks = [_resolve_user_info(context.bot, uid) for uid in other_users]
    group_info_tasks = [_resolve_group_info(context.bot, gid) for gid in allowed_groups]

    user_info_results = await asyncio.gather(*user_info_tasks) if user_info_tasks else []
    group_info_results = await asyncio.gather(*group_info_tasks) if group_info_tasks else []

    # 3. Usuarios negados del log de auditoría
    denied_users = get_denied_users_summary()

    # 4. Construir reporte estructurado
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    active_channel = ACTIVE_CONNECTION_LABEL or "Conexión Directa"

    lines = [
        "📊 <b>Informe de Estado y Diagnóstico del Bot</b>",
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>",
        "",
        "═══════════════════════════════",
        "🌐 <b>DIAGNÓSTICO DE RED E INTERNET</b>",
        "═══════════════════════════════"
    ]

    for res in network_results:
        icon = "🟢" if res["ok"] else "🔴"
        lines.append(f"• <b>{html.escape(res['name'])}:</b>\n   {icon} <code>{html.escape(res['detail'])}</code>")

    lines.append("")
    lines.append(f"🔄 <b>Canal Activo del Bot:</b> <code>{html.escape(active_channel)}</code>")

    lines.append("")
    lines.append("═══════════════════════════════",)
    lines.append("👥 <b>CONTROL DE ACCESO DE USUARIOS</b>")
    lines.append("═══════════════════════════════")
    lines.append(f"👑 <b>Creador / Owner:</b> {html.escape(owner_name)}{html.escape(owner_tag)} [<code>{owner_id}</code>]")
    lines.append("")

    lines.append("✅ <b>Usuarios Permitidos:</b>")
    if not other_users:
        lines.append("• <i>(No hay usuarios adicionales en la lista)</i>")
    else:
        for idx, (uid, (uname, uuser)) in enumerate(zip(other_users, user_info_results), 1):
            utag = f" ({uuser})" if uuser else ""
            lines.append(f"{idx}. 👤 <b>Usuario:</b> {html.escape(uname)}{html.escape(utag)}\n   🆔 <b>ID de Telegram:</b> <code>{uid}</code>")

    lines.append("")
    lines.append("🏢 <b>Grupos Permitidos:</b>")
    if not allowed_groups:
        lines.append("• <i>(No hay grupos en la lista)</i>")
    else:
        for idx, (gid, gtitle) in enumerate(zip(allowed_groups, group_info_results), 1):
            lines.append(f"{idx}. 👥 <b>Grupo:</b> {html.escape(gtitle)}\n   🆔 <b>ID de Chat:</b> <code>{gid}</code>")

    lines.append("")
    lines.append("⛔ <b>Usuarios Negados / No Autorizados:</b>")
    if not denied_users:
        lines.append("• <i>(No hay usuarios bloqueados o negados recientemente)</i>")
    else:
        for idx, duser in enumerate(denied_users, 1):
            dtag = f" ({duser['username']})" if duser['username'] else ""
            lines.append(
                f"{idx}. 🚫 <b>Usuario:</b> {html.escape(duser['name'])}{html.escape(dtag)}\n"
                f"   🆔 <b>ID de Telegram:</b> <code>{duser['user_id']}</code>\n"
                f"   ⏰ <b>Último Intento:</b> <code>{duser['last_seen']}</code>"
            )

    report_text = "\n".join(lines)

    if waiting_msg:
        try:
            await waiting_msg.edit_text(report_text, parse_mode='HTML')
            return
        except Exception:
            try:
                await waiting_msg.delete()
            except Exception:
                pass

    await safe_reply_html(update.message, report_text)


async def handle_auth_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja la acción de los botones inline de autorización y revocación presionados por el creador."""
    query = update.callback_query
    if not query or not query.data:
        return

    owner_id = CONFIG.get("owner_id", 0)
    clicker_id = query.from_user.id

    # Seguridad: Solo el owner puede interactuar con estos botones de autorización
    if clicker_id != owner_id:
        await query.answer("⛔ Solo el creador del bot tiene permiso para gestionar accesos.", show_alert=True)
        return

    try:
        action, target_id_str = query.data.split(":", 1)
        target_user_id = int(target_id_str)
    except (ValueError, IndexError):
        await query.answer("⚠️ Datos de solicitud inválidos.")
        return

    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = query.from_user.full_name or f"@{query.from_user.username or 'admin'}"

    # Recuperar texto original del mensaje
    original_text = query.message.text_html if query.message else ""
    if not original_text and query.message and query.message.text:
        original_text = html.escape(query.message.text)

    if action == "auth_allow":
        allowed_list = CONFIG.setdefault("allowed_user_ids", [])
        if target_user_id not in allowed_list:
            allowed_list.append(target_user_id)
            save_config()
            logger.info(f"Usuario {target_user_id} autorizado y guardado en config.json por el creador {clicker_id}")

        await query.answer(f"✅ Usuario {target_user_id} autorizado exitosamente.")

        # Actualizar mensaje del owner retirando los botones
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"✅ <b>ESTADO: AUTORIZADO Y AGREGADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(
                text=original_text + status_badge,
                parse_mode='HTML',
                reply_markup=None
            )
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass

        # Notificar al usuario aprobado
        try:
            await context.bot.send_message(
                chat_id=target_user_id,
                text="🎉 <b>¡Acceso Autorizado!</b>\n\nEl administrador ha aprobado tu acceso. Ya puedes interactuar con el bot libremente.",
                parse_mode='HTML'
            )
        except Exception as e:
            logger.info(f"No se pudo notificar directamente al usuario {target_user_id}: {e}")

    elif action == "auth_deny":
        allowed_list = CONFIG.setdefault("allowed_user_ids", [])
        if target_user_id in allowed_list:
            allowed_list.remove(target_user_id)
            save_config()

        await query.answer(f"❌ Acceso denegado para {target_user_id}.")

        # Actualizar mensaje del owner retirando los botones
        status_badge = (
            f"\n\n═══════════════════════════════\n"
            f"❌ <b>ESTADO: ACCESO DENEGADO</b>\n"
            f"👮 <b>Por:</b> {html.escape(admin_name)}\n"
            f"⏰ <b>Fecha:</b> <code>{now_str}</code>"
        )
        try:
            await query.edit_message_text(
                text=original_text + status_badge,
                parse_mode='HTML',
                reply_markup=None
            )
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass

        # Notificar al usuario rechazado
        try:
            await context.bot.send_message(
                chat_id=target_user_id,
                text="⛔ <b>Solicitud Denegada</b>\n\nEl administrador ha rechazado tu solicitud de acceso a este bot.",
                parse_mode='HTML'
            )
        except Exception as e:
            logger.info(f"No se pudo notificar al usuario {target_user_id}: {e}")

    elif action == "auth_revoke_user":
        allowed_list = CONFIG.setdefault("allowed_user_ids", [])
        if target_user_id in allowed_list:
            allowed_list.remove(target_user_id)
            save_config()
            logger.info(f"Usuario {target_user_id} revocado y guardado en config.json por el creador {clicker_id}")

        await query.answer(f"✅ Usuario {target_user_id} revocado de la lista.")

        # Re-renderizar panel de permisos actualizado con nombres reales
        panel_text, reply_markup = await _build_permissions_panel(context.bot)
        try:
            await query.edit_message_text(
                text=panel_text,
                parse_mode='HTML',
                reply_markup=reply_markup
            )
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass

        # Notificar al usuario revocado
        try:
            await context.bot.send_message(
                chat_id=target_user_id,
                text="⛔ <b>Acceso Revocado</b>\n\nEl administrador ha revocado tu autorización para interactuar con este bot.",
                parse_mode='HTML'
            )
        except Exception as e:
            logger.info(f"No se pudo notificar al usuario revocado {target_user_id}: {e}")

    elif action == "auth_revoke_group":
        allowed_groups = CONFIG.setdefault("allowed_group_ids", [])
        if target_user_id in allowed_groups:
            allowed_groups.remove(target_user_id)
            save_config()
            logger.info(f"Grupo {target_user_id} revocado y guardado en config.json por el creador {clicker_id}")

        await query.answer(f"✅ Grupo {target_user_id} revocado de la lista.")

        # Re-renderizar panel de permisos actualizado con títulos reales
        panel_text, reply_markup = await _build_permissions_panel(context.bot)
        try:
            await query.edit_message_text(
                text=panel_text,
                parse_mode='HTML',
                reply_markup=reply_markup
            )
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=reply_markup)
            except Exception:
                pass


async def bot_error_handler(update: object, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Registra y gestiona errores inesperados o fallos de red durante el polling."""
    err = context.error
    if isinstance(err, (telegram.error.NetworkError, httpx.NetworkError, httpx.TimeoutException)):
        logger.warning(f"Aviso de red en polling Telegram: {err}. Reintentando automáticamente...")
    else:
        logger.error(f"Excepción en bot handler: {err}", exc_info=err)


def main() -> None:
    bot_token = CONFIG.get("bot_token")

    if not bot_token or bot_token in ("TU_TOKEN_AQUI", "TU_TOKEN_DE_TELEGRAM"):
        logger.error("Error: BOT_TOKEN no configurado en config.json o variables de entorno.")
        return

    # Selección y conmutación automática de conexión (Directa vs Proxies corporativos)
    active_proxy = None
    if CONFIG.get("auto_proxy_failover", True):
        active_proxy = select_working_connection(bot_token)

    # Configurar cliente HTTP con timeouts optimizados y proxy si corresponde
    request = HTTPXRequest(
        connection_pool_size=256,
        connect_timeout=10.0,
        read_timeout=30.0,
        write_timeout=30.0,
        pool_timeout=5.0,
        proxy=active_proxy
    )

    application = Application.builder().token(bot_token).request(request).build()

    # Manejador global de errores de red y aplicación
    application.add_error_handler(bot_error_handler)

    reserved_commands = {
        "start",
        "help",
        "ayuda",
        "reset_ia",
        "reset_chat",
        "borrar_chat",
        "permisos",
        "autorizados",
        "whitelist",
        "usuarios",
        "botstatus",
        "statusbot",
        "estado_bot"
    }

    # Comandos base
    application.add_handler(CommandHandler(["start", "help", "ayuda"], start))

    # Comando para reiniciar conversación IA
    application.add_handler(CommandHandler(["reset_ia", "reset_chat", "borrar_chat"], reset_chat))

    # Comando exclusivo para que el Owner gestione permisos
    application.add_handler(CommandHandler(["permisos", "autorizados", "whitelist", "usuarios"], manage_permissions))

    # Comando exclusivo para que el Owner verifique estado de red, proxies y accesos
    application.add_handler(CommandHandler(["botstatus", "statusbot", "estado_bot"], bot_status))

    # Callback query handler para botones de autorización interactiva y revocación
    application.add_handler(CallbackQueryHandler(handle_auth_callback, pattern=r"^auth_(allow|deny|revoke_user|revoke_group):"))

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
