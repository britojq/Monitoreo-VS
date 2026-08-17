import os
import json
import html
import logging
import asyncio
import subprocess
from pathlib import Path
from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes, MessageHandler, filters

# Directorio base del script para cargar archivos de configuración externos
BASE_DIR = Path(__file__).resolve().parent
CONFIG_PATH = BASE_DIR / "config.json"
COMMANDS_PATH = BASE_DIR / "commands.json"

# --- LOGGING ---
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

def load_config() -> dict:
    """Carga los parámetros del bot desde config.json o variables de entorno."""
    default_config = {
        "bot_token": os.getenv("BOT_TOKEN", ""),
        "owner_id": int(os.getenv("OWNER_ID", "0")),
        "allowed_user_ids": [],
        "allowed_group_ids": []
    }

    if CONFIG_PATH.exists():
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                file_config = json.load(f)
                default_config.update(file_config)
                logger.info(f"Configuración cargada desde {CONFIG_PATH}")
        except Exception as e:
            logger.error(f"Error al leer {CONFIG_PATH}: {e}")

    if os.getenv("BOT_TOKEN"):
        default_config["bot_token"] = os.getenv("BOT_TOKEN")

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

# Cargar datos de configuración
CONFIG = load_config()
MESSAGES, COMMANDS = load_commands_data()

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
def _exec_system_command(command: list, timeout: int = 300) -> str:
    """Ejecuta subprocesos del sistema con un tiempo límite (timeout) configurable en segundos."""
    try:
        result = subprocess.run(command, capture_output=True, text=True, timeout=timeout)
        output = result.stdout if result.stdout else result.stderr
        if len(output) > 3800:
            output = output[:3700] + "\n... [Salida truncada por límite de Telegram] ..."
        return output if output else "Comando ejecutado sin salida."
    except subprocess.TimeoutExpired:
        return f"Error: El comando tardó demasiado en responder (Timeout > {timeout}s)."
    except Exception as e:
        return f"Error al ejecutar el comando: {str(e)}"

async def run_command_async(command: list, timeout: int = 300) -> str:
    """Ejecuta el subproceso en un hilo secundario sin bloquear asyncio."""
    return await asyncio.to_thread(_exec_system_command, command, timeout)

# --- HANDLERS DE COMANDOS Y AYUDA ---
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el mensaje inicial y la lista de comandos disponibles."""
    if not is_authorized(update):
        return

    if context.args:
        target_cmd = context.args[0].lstrip('/').lower()
        if target_cmd in COMMANDS:
            help_msg = COMMANDS[target_cmd].get("help_text", f"Sin información detallada para /{target_cmd}.")
            await update.message.reply_text(help_msg, parse_mode='HTML')
            return

    start_header = MESSAGES.get("start_header", "🤖 <b>Bot de Administración</b>")
    help_lines = [start_header, ""]
    for cmd_name, cmd_info in COMMANDS.items():
        desc = cmd_info.get("description", "Sin descripción")
        help_lines.append(f"/{cmd_name} - {html.escape(desc)}")

    await update.message.reply_text("\n".join(help_lines), parse_mode='HTML')

async def handle_dynamic_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos dinámicos y soporta timeouts personalizados para tareas extensas."""
    if not is_authorized(update):
        return

    full_text = update.message.text or ""
    parts = full_text.split()
    cmd_name = parts[0].lstrip('/').split('@')[0].lower()
    cmd_config = COMMANDS.get(cmd_name)

    if not cmd_config:
        await update.message.reply_text(MESSAGES.get("unknown_command", "Comando no configurado."), parse_mode='HTML')
        return

    # Solicitar ayuda explícita (/comando help)
    if len(parts) > 1 and parts[1].lower() in ["help", "ayuda", "-h", "--help"]:
        help_text = cmd_config.get("help_text")
        if help_text:
            await update.message.reply_text(help_text, parse_mode='HTML')
        else:
            desc = cmd_config.get("description", "Sin descripción")
            await update.message.reply_text(f"ℹ️ <b>/{cmd_name}:</b> {html.escape(desc)}", parse_mode='HTML')
        return

    # Mensaje informativo previo
    reply_header = cmd_config.get("reply_header")
    if reply_header:
        await update.message.reply_text(reply_header, parse_mode='HTML')

    # Ejecución de los pasos del comando con timeout configurable (por defecto 300 segundos / 5 minutos)
    steps = cmd_config.get("steps", [])
    for step in steps:
        title = step.get("title")
        command_list = step.get("command")
        step_timeout = step.get("timeout", 300) # 5 minutos por defecto
        if not command_list:
            continue

        output = await run_command_async(command_list, timeout=step_timeout)
        safe_output = html.escape(output)

        response_text = ""
        if title:
            response_text += f"<b>{html.escape(title)}</b>\n"
        response_text += f"<pre>{safe_output}</pre>"

        await update.message.reply_text(response_text, parse_mode='HTML')

    # Mensaje informativo posterior
    reply_footer = cmd_config.get("reply_footer")
    if reply_footer:
        await update.message.reply_text(reply_footer, parse_mode='HTML')

async def unknown_cmd(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos no registrados."""
    if not is_authorized(update):
        return
    unk_msg = MESSAGES.get("unknown_command", "Comando no reconocido. Usa /start para ver las opciones.")
    await update.message.reply_text(unk_msg, parse_mode='HTML')

def main() -> None:
    bot_token = CONFIG.get("bot_token")
    if not bot_token or bot_token == "TU_TOKEN_AQUI":
        logger.error("Error: BOT_TOKEN no configurado en config.json o variables de entorno.")
        return

    application = Application.builder().token(bot_token).build()

    application.add_handler(CommandHandler(["start", "help", "ayuda"], start))

    for cmd_name in COMMANDS.keys():
        if cmd_name not in ["start", "help", "ayuda"]:
            application.add_handler(CommandHandler(cmd_name, handle_dynamic_command))

    application.add_handler(MessageHandler(filters.COMMAND, unknown_cmd))

    logger.info("Bot de administración iniciado con soporte para comandos de larga duración (Timeout extendido a 300s).")
    application.run_polling(allowed_updates=Update.ALL_TYPES)

if __name__ == '__main__':
    main()
