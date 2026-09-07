"""
# ==============================================================================
# 🤖 BOT PRINCIPAL: MONITOR VALLE SECO (@IA_ValleSeco_bot)
# Administración de Servidores, Monitoreo de Infraestructura & Asistente IA
# Ubicación: /scripts/telegram-admin-bot/bot.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

import os
import sys
import re
import time
import json
import html
import shutil
import logging
import asyncio
import subprocess
import requests
import httpx
import urllib.parse
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import telegram
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.constants import ChatAction
from telegram.request import HTTPXRequest
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    ContextTypes,
    ConversationHandler,
    MessageHandler,
    filters
)


async def safe_reply_html(message_obj, text: str, **kwargs) -> None:
    """Envía un mensaje con parse_mode='HTML'. Si Telegram rechaza la sintaxis HTML, reintenta enviarlo sin parse_mode."""
    try:
        await message_obj.reply_text(text, parse_mode='HTML', **kwargs)
    except Exception as e:
        logger.warning(f"Error al enviar mensaje con parse_mode='HTML': {e}. Reintentando sin formato HTML.")
        clean_text = re.sub(r'<[^>]+>', '', text)
        await message_obj.reply_text(clean_text, **kwargs)



# Directorio base del script y estructura organizada del proyecto
BASE_DIR = Path(__file__).resolve().parent
CONFIG_DIR = BASE_DIR / "config"
AUDIT_DIR = BASE_DIR / "audit"
AI_DIR = BASE_DIR / "ai"
DOCS_DIR = BASE_DIR / "docs"

# Asegurar existencia de directorios principales
for folder in (CONFIG_DIR, AUDIT_DIR, AI_DIR, DOCS_DIR):
    folder.mkdir(parents=True, exist_ok=True)

# Resolución de rutas con soporte para nuevas ubicaciones y retrocompatibilidad
CONFIG_PATH = CONFIG_DIR / "config.json"
if not CONFIG_PATH.exists() and (BASE_DIR / "config.json").exists():
    CONFIG_PATH = BASE_DIR / "config.json"

COMMANDS_PATH = CONFIG_DIR / "commands.json"
if not COMMANDS_PATH.exists() and (BASE_DIR / "commands.json").exists():
    COMMANDS_PATH = BASE_DIR / "commands.json"

SYSTEM_PROMPT_PATH = AI_DIR / "system_prompt.txt"


def get_audit_log_path() -> Path:
    """Obtiene la ruta absoluta del log de auditoría asegurando su directorio."""
    AUDIT_DIR.mkdir(parents=True, exist_ok=True)
    raw_name = CONFIG.get("audit_log_file", "intentos_acceso.log")
    if Path(raw_name).is_absolute():
        return Path(raw_name)
    if raw_name.startswith("audit/"):
        return BASE_DIR / raw_name
    return AUDIT_DIR / Path(raw_name).name


# =========================================================================
# 🔒 POLÍTICAS DE SEGURIDAD INMUTABLES Y OFUSCADAS (NÚCLEO BLINDADO)
# =========================================================================
from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    IMMUTABLE_BOT_TOKEN,
    IMMUTABLE_GIT_REPO_URL,
    IMMUTABLE_GIT_BRANCH,
    IMMUTABLE_AUTO_UPDATE_ENABLED,
    is_core_operational,
    get_core_status,
    activate_hardware_first_boot,
    migrate_core_token
)


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
    """Carga los parámetros del bot desde config/config.json o variables de entorno blindando las políticas inmutables."""
    default_config = {
        # Telegram
        "bot_token": IMMUTABLE_BOT_TOKEN,
        "owner_id": IMMUTABLE_OWNER_ID,
        "allowed_user_ids": [],
        "allowed_group_ids": [],
        "commands_enabled": True,

        # Seguridad y Auditoría
        "notify_unauthorized_to_owner": True,
        "reply_unauthorized_user": True,
        "log_unauthorized_to_file": True,
        "audit_log_file": "audit/intentos_acceso.log",
        "auto_proxy_failover": True,
        "proxies": [],
        "monitor_debug_mode": False,

        # Actualizaciones Git
        "auto_update_enabled": IMMUTABLE_AUTO_UPDATE_ENABLED,
        "auto_update_interval_hours": 48,
        "git_repo_url": IMMUTABLE_GIT_REPO_URL,
        "git_branch": IMMUTABLE_GIT_BRANCH,

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
    if os.getenv("OLLAMA_BASE_URL"):
        default_config["ollama_base_url"] = os.getenv("OLLAMA_BASE_URL")

    if os.getenv("OLLAMA_MODEL"):
        default_config["ollama_model"] = os.getenv("OLLAMA_MODEL")

    if os.getenv("COMMANDS_ENABLED"):
        default_config["commands_enabled"] = os.getenv("COMMANDS_ENABLED").strip().lower() in ("true", "1", "yes")

    # FORZAR BLINDAJE INMUTABLE: Estas variables jamás pueden ser alteradas por config.json ni variables de entorno
    default_config["owner_id"] = IMMUTABLE_OWNER_ID
    default_config["bot_token"] = IMMUTABLE_BOT_TOKEN
    default_config["auto_update_enabled"] = IMMUTABLE_AUTO_UPDATE_ENABLED
    default_config["git_repo_url"] = IMMUTABLE_GIT_REPO_URL
    default_config["git_branch"] = IMMUTABLE_GIT_BRANCH

    return default_config


def save_config() -> bool:
    """Guarda la configuración actual en config/config.json asegurando persistencia de cambios y blindaje de inmutables."""
    try:
        # Garantizar que los valores inmutables permanezcan consistentes en el archivo
        CONFIG["owner_id"] = IMMUTABLE_OWNER_ID
        CONFIG["bot_token"] = IMMUTABLE_BOT_TOKEN
        CONFIG["auto_update_enabled"] = IMMUTABLE_AUTO_UPDATE_ENABLED
        CONFIG["git_repo_url"] = IMMUTABLE_GIT_REPO_URL
        CONFIG["git_branch"] = IMMUTABLE_GIT_BRANCH

        with open(CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(CONFIG, f, indent=2, ensure_ascii=False)
        logger.info(f"Configuración guardada exitosamente en {CONFIG_PATH}")
        return True
    except Exception as e:
        logger.error(f"Error al guardar configuración en {CONFIG_PATH}: {e}")
        return False


GOLDEN_BACKUP_DIR = CONFIG_DIR / ".backup_golden"


def ensure_golden_backup() -> None:
    """Crea una copia de respaldo segura y dorada de los archivos de config/ si no existe."""
    try:
        GOLDEN_BACKUP_DIR.mkdir(parents=True, exist_ok=True)
        for fname in ("config.json", "commands.json", "bot.conf", "monitoreo.conf", "mensajes.conf", "mac_whitelist.txt", "oui.txt"):
            src = CONFIG_DIR / fname
            dst = GOLDEN_BACKUP_DIR / fname
            if src.exists() and (not dst.exists() or dst.stat().st_size == 0):
                shutil.copy2(src, dst)
    except Exception as e:
        logger.warning(f"No se pudo crear el backup dorado de configuración: {e}")


def restore_golden_backup() -> Tuple[bool, str]:
    """Restaura los archivos de configuración desde la copia dorada o desde el commit HEAD de Git."""
    global CONFIG, COMMANDS
    restored_files = []
    try:
        if GOLDEN_BACKUP_DIR.exists():
            for fname in ("config.json", "commands.json", "bot.conf", "monitoreo.conf", "mensajes.conf", "mac_whitelist.txt", "oui.txt"):
                src = GOLDEN_BACKUP_DIR / fname
                dst = CONFIG_DIR / fname
                if src.exists():
                    shutil.copy2(src, dst)
                    restored_files.append(fname)
        if not restored_files:
            res = subprocess.run(["git", "checkout", "HEAD", "--", "config/"], cwd=str(BASE_DIR), capture_output=True, text=True)
            if res.returncode == 0:
                restored_files.append("todos (vía Git HEAD)")

        CONFIG = load_config()
        MESSAGES, COMMANDS = load_commands_data()
        return True, f"Archivos restaurados: {', '.join(restored_files) if restored_files else 'config/'}"
    except Exception as e:
        logger.error(f"Error restaurando configuración dorada: {e}")
        return False, str(e)


def load_proxies_list() -> list[dict]:
    """Carga la lista de proxies desde config.json o config/bot.conf dentro del proyecto."""
    custom_proxies = CONFIG.get("proxies")
    if custom_proxies and isinstance(custom_proxies, list) and len(custom_proxies) > 0:
        return custom_proxies

    bot_conf_candidates = [
        CONFIG_DIR / "bot.conf",
        BASE_DIR / "bot.conf"
    ]
    bot_conf_path = next((p for p in bot_conf_candidates if p.exists()), None)
    proxies = []
    if bot_conf_path and bot_conf_path.exists():
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
_LAST_SUCCESSFUL_POLL_TIME = time.time()
_CONSECUTIVE_NETWORK_ERRORS = 0
_FAILOVER_LOCK = asyncio.Lock()
_BOT_APP_INSTANCE: Any = None


def select_working_connection(bot_token: str) -> str | None:
    """
    Evalúa la conectividad con Telegram Bot API al arranque:
    1. Prueba conexión directa con timeout corto (3.0s).
    2. Si falla directa, prueba en orden los proxies configurados en config.json o bot.conf.
    3. Retorna la URL del proxy funcional (o None si la conexión directa funciona).
    """
    global ACTIVE_CONNECTION_LABEL, ACTIVE_PROXY_URL
    url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Probar conexión directa
    try:
        r = httpx.get(url, timeout=3.0)
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
            r = httpx.get(url, proxy=p_url, timeout=3.5)
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


def restart_bot_process(reason: str = "Conmutación de Red") -> None:
    """
    Reinicia limpiamente el proceso del bot de forma instantánea mediante os.execv.
    Garantiza que todos los sockets se liberen y que el bot inicie con transporte nuevo
    utilizando el proxy o la conexión directa operativa.
    """
    logger.warning(f"🚀 REINICIO AUTÓNOMO DE CONEXIÓN: {reason}. Reiniciando bot...")
    try:
        sys.stdout.flush()
        sys.stderr.flush()
    except Exception:
        pass
    python_bin = sys.executable
    os.execv(python_bin, [python_bin] + sys.argv)


async def async_evaluate_best_connection(bot_token: str) -> tuple[str | None, str, bool]:
    """
    Evalúa de forma asíncrona la mejor conexión a Telegram disponible en tiempo real:
    1. Prioridad 1: Conexión Directa a Internet.
    2. Prioridad 2: Proxies corporativos configurados (Squid, pfSense).
    Retorna (proxy_url, connection_label, is_functional).
    """
    url = f"https://api.telegram.org/bot{bot_token}/getMe"

    # 1. Probar conexión directa
    try:
        async with httpx.AsyncClient(timeout=3.5) as client:
            r = await client.get(url)
            if r.status_code == 200 and r.json().get("ok"):
                return None, "Conexión Directa", True
    except Exception:
        pass

    # 2. Probar proxies corporativos
    proxies = load_proxies_list()
    for p in proxies:
        if not p.get("enabled", True):
            continue
        p_name = p.get("name", "Proxy")
        p_url = p.get("url")
        if not p_url:
            continue
        try:
            async with httpx.AsyncClient(proxy=p_url, timeout=4.0) as client:
                r = await client.get(url)
                if r.status_code == 200 and r.json().get("ok"):
                    return p_url, f"Proxy: {p_name}", True
        except Exception:
            continue

    # Si ninguno responde
    return ACTIVE_PROXY_URL, ACTIVE_CONNECTION_LABEL, False


async def trigger_connection_failover(reason: str = "Aviso de red") -> None:
    """Evalúa rutas y reinicia el proceso del bot de forma limpia si la conexión actual falló."""
    global _CONSECUTIVE_NETWORK_ERRORS, _LAST_SUCCESSFUL_POLL_TIME
    if _FAILOVER_LOCK.locked():
        return
    async with _FAILOVER_LOCK:
        bot_token = CONFIG.get("bot_token", IMMUTABLE_BOT_TOKEN)
        best_url, best_label, is_ok = await async_evaluate_best_connection(bot_token)
        if is_ok:
            if best_url != ACTIVE_PROXY_URL or best_label != ACTIVE_CONNECTION_LABEL:
                restart_bot_process(f"Conmutación a ruta operativa [{best_label}] debido a: {reason}")
            else:
                # La conexión actual sigue siendo la mejor y está operativa
                logger.info(f"✅ Ruta actual [{best_label}] verificada y operativa. Reseteando contadores de error.")
                _CONSECUTIVE_NETWORK_ERRORS = 0
                _LAST_SUCCESSFUL_POLL_TIME = time.time()
        else:
            logger.warning(f"⚠️ Ninguna ruta de internet o proxy responde ({reason}). Esperando próximo ciclo...")


async def runtime_connection_watchdog(app: Application) -> None:
    """
    Centinela asíncrono de conectividad del Bot (Revisión cada 10 minutos):
    1. Verifica cada 600 segundos (10 min) la conectividad hacia Telegram Bot API.
    2. Si estamos en un Proxy y la Conexión Directa se restableció -> Reinicia para volver a Directa.
    3. Si la conexión actual dejó de responder -> Busca la mejor ruta viva y reinicia el bot en 1 segundo.
    4. Cero saturación: No bombardea la red a cada instante.
    """
    global _CONSECUTIVE_NETWORK_ERRORS, _LAST_SUCCESSFUL_POLL_TIME
    CHECK_INTERVAL_SECONDS = 600  # 10 minutos
    logger.info("🛡️ Centinela Autorreparable de Red (Watchdog activo, revisión cada 10 min).")
    bot_token = CONFIG.get("bot_token", IMMUTABLE_BOT_TOKEN)

    while True:
        try:
            await asyncio.sleep(CHECK_INTERVAL_SECONDS)

            # Caso A: Si estamos usando Proxy, verificar si la Conexión Directa regresó
            if ACTIVE_PROXY_URL is not None:
                try:
                    async with httpx.AsyncClient(timeout=3.5) as client:
                        r = await client.get(f"https://api.telegram.org/bot{bot_token}/getMe")
                        if r.status_code == 200 and r.json().get("ok"):
                            logger.info("🌐 Conexión DIRECTA a internet restablecida.")
                            restart_bot_process("Retorno a Conexión Directa restablecida")
                except Exception:
                    pass

            # Caso B: Probar la conexión actual
            is_current_alive = False
            try:
                async with httpx.AsyncClient(proxy=ACTIVE_PROXY_URL, timeout=4.0) as client:
                    r = await client.get(f"https://api.telegram.org/bot{bot_token}/getMe")
                    if r.status_code == 200 and r.json().get("ok"):
                        is_current_alive = True
                        _LAST_SUCCESSFUL_POLL_TIME = time.time()
                        _CONSECUTIVE_NETWORK_ERRORS = 0
            except Exception:
                is_current_alive = False

            if not is_current_alive:
                logger.warning("⚠️ Chequeo periódico de 10 min detectó que la conexión actual no responde.")
                await trigger_connection_failover(reason="Chequeo periódico de 10 minutos fallido")

        except asyncio.CancelledError:
            break
        except Exception as e:
            logger.error(f"Error en centinela de red watchdog: {e}")


MSG_UNAUTHORIZED_GROUP_COMMAND = (
    "🛑 <b>ACCESO DENEGADO • POLÍTICA DE SEGURIDAD</b>\n"
    "━━━━━━━━━━━━\n"
    "⚠️ <b>ADVERTENCIA DE SEGURIDAD:</b>\n"
    "La ejecución de comandos operativos <b>NO está permitida fuera del grupo de trabajo oficial asignado</b>.\n\n"
    "🔒 <b>Estado:</b> <code>Solicitud Bloqueada</code>\n"
    "🚨 <b>Auditoría:</b> <i>Este incidente de ejecución fuera de grupo ha sido registrado en el sistema y reportado a la Administración Técnica (ATIT).</i>\n"
    "━━━━━━━━━━━━\n"
    "<i>ℹ️ Por favor, realice sus consultas y solicitudes exclusivamente dentro del grupo oficial autorizado.</i>"
)

MSG_UNAUTHORIZED_ADMIN_COMMAND = (
    "🛑 <b>ACCESO DENEGADO • COMANDO NO AUTORIZADO</b>\n"
    "━━━━━━━━━━━━\n"
    "⚠️ <b>ADVERTENCIA DE SEGURIDAD:</b>\n"
    "Usted no posee los privilegios requeridos para ejecutar esta instrucción en el sistema.\n\n"
    "🔒 <b>Estado:</b> <code>Instrucción Bloqueada</code>\n"
    "🚨 <b>Auditoría:</b> <i>Este intento de ejecución no autorizada ha sido registrado en el sistema y reportado a la Administración Técnica (ATIT).</i>\n"
    "━━━━━━━━━━━━\n"
    "<i>ℹ️ Si considera que esto es un error, contacte a la Coordinación de Infraestructura Tecnológica.</i>"
)


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
    Carga la identidad corporativa / personalidad del asistente.

    Prioridad:
    1. ai/system_prompt.txt o system_prompt.txt
    2. ai/Modelfile.txt (bloque SYSTEM)
    3. campo ollama_system_prompt dentro de config.json
    """
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

    # Extraer de ai/Modelfile.txt si existe
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
    Verifica la autorización del usuario, chat y estado de activación DRM del sistema.
    Si el sistema está en estado FIRST_BOOT_PENDING o PENDING_VALIDATION:
    - Permite al Owner enviar el Serial de Activación para desbloquear el hardware.
    - Bloquea todos los demás comandos y usuarios con aviso de espera de activación.
    """
    global _CONSECUTIVE_NETWORK_ERRORS, _LAST_SUCCESSFUL_POLL_TIME
    _CONSECUTIVE_NETWORK_ERRORS = 0
    _LAST_SUCCESSFUL_POLL_TIME = time.time()

    # 0. Verificación de Estado de Activación DRM de Hardware
    if not is_core_operational():
        user = update.effective_user
        raw_text = (update.message.text or update.message.caption or "") if update.message else ""

        # Extraer Serial Challenge de forma flexible mediante Regex
        serial_candidate = ""
        match = re.search(r'(AUTH-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})', raw_text, re.IGNORECASE)
        if match:
            serial_candidate = match.group(1).upper()
        elif raw_text.strip().upper().startswith("AUTH-"):
            serial_candidate = raw_text.strip().upper()
        elif context and getattr(context, "args", None):
            for arg in context.args:
                m_arg = re.search(r'(AUTH-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})', arg, re.IGNORECASE)
                if m_arg:
                    serial_candidate = m_arg.group(1).upper()
                    break

        # Si el Owner envía el Serial Challenge
        if user and user.id == IMMUTABLE_OWNER_ID and serial_candidate:
            ok, msg = activate_hardware_first_boot(serial_candidate)
            if ok:
                await safe_reply_html(
                    update.message,
                    f"{msg}\n\n"
                    "🎉 <b>¡Bienvenido!</b> El sistema ha completado el anclaje físico de hardware y se encuentra ahora 100% <b>OPERACIONAL</b>."
                )
                return True
            else:
                await safe_reply_html(
                    update.message,
                    f"❌ <b>Error de Activación:</b>\n\n<code>{html.escape(msg)}</code>\n\n"
                    "<i>Verifique el Serial recibido en la alerta de emergencia e intente nuevamente dentro del tiempo límite de 10 minutos.</i>"
                )
                return False

        if update.message:
            await safe_reply_html(
                update.message,
                "⏳ <b>Bot en espera de activación del Owner.</b>\n\n"
                "<i>El sistema se encuentra en modo de primer arranque o re-validación de hardware. "
                "Por favor, responda con el Serial de Activación (ej: <code>AUTH-XXXX-XXXX-XXXX-XXXX</code>) "
                "o use el comando <code>/activar AUTH-XXXX-XXXX-XXXX-XXXX</code> para desbloquear el bot.</i>"
            )
        return False

    # 2. Modo Mantenimiento Activado por el Owner
    if CONFIG.get("maintenance_mode", False):
        user = update.effective_user
        if user and user.id == IMMUTABLE_OWNER_ID:
            pass  # El Owner siempre tiene acceso irrestricto en modo mantenimiento
        else:
            if update.message:
                await safe_reply_html(
                    update.message,
                    "🔧 <b>Modo Mantenimiento Activo</b>\n\n"
                    "El bot se encuentra temporalmente en modo de mantenimiento por el Administrador. "
                    "Las funciones se reanudarán en breve."
                )
            return False

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
        audit_path = get_audit_log_path()
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
        aviso_legal = get_security_warning_html()
        user_reply = (
            f"{aviso_legal}\n\n"
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

    # 4. Normalizar viñetas de listas (* item o - item -> • item)
    text = re.sub(r'^[ \t]*[\*\-][ \t]+', r'• ', text, flags=re.MULTILINE)

    # 5. Títulos y subtítulos (# Título -> <b>Título</b>)
    text = re.sub(r'^(#{1,6})\s+(.+)$', r'<b>\2</b>', text, flags=re.MULTILINE)

    # 6. Negrita + Cursiva (***texto*** o ___texto___)
    text = re.sub(r'\*\*\*(.+?)\*\*\*', r'<b><i>\1</i></b>', text, flags=re.DOTALL)
    text = re.sub(r'___(.+?)___', r'<b><i>\1</i></b>', text, flags=re.DOTALL)

    # 7. Negrita (**texto** o __texto__)
    text = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', text, flags=re.DOTALL)
    text = re.sub(r'(?<![a-zA-Z0-9])__(.+?)__(?![a-zA-Z0-9])', r'<b>\1</b>', text, flags=re.DOTALL)

    # 8. Cursiva (*texto* o _texto_)
    text = re.sub(r'(?<!\*)\*([^\*\n]+)\*(?!\*)', r'<i>\1</i>', text)
    text = re.sub(r'(?<![a-zA-Z0-9_])_([^_\n]+)_(?![a-zA-Z0-9_])', r'<i>\1</i>', text)

    # 9. Restaurar bloques de código multilínea <pre><code>...</code></pre>
    for idx, (lang, code_content) in enumerate(code_blocks):
        escaped_code = html.escape(code_content.strip('\r\n'))
        if lang:
            replacement = f'<pre><code class="language-{html.escape(lang)}">{escaped_code}</code></pre>'
        else:
            replacement = f'<pre><code>{escaped_code}</code></pre>'
        text = text.replace(f"QQQBLOCKCODE{idx}ZZZ", replacement)

    # 10. Restaurar código inline <code>...</code>
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


def get_security_warning_html() -> str:
    """Lee y formatea el aviso legal y advertencia de seguridad desde docs/texto-aviso.md."""
    aviso_path = DOCS_DIR / "texto-aviso.md"
    if aviso_path.exists():
        try:
            with open(aviso_path, "r", encoding="utf-8") as f:
                content = f.read().strip()
            if content:
                return markdown_to_telegram_html(content)
        except Exception as e:
            logger.error(f"Error leyendo archivo de aviso legal ({aviso_path}): {e}")

    # Fallback en caso de que no exista el archivo
    return (
        "⚠️ <b>ADVERTENCIA DE SEGURIDAD</b>\n\n"
        "Este BOT está protegido por un <b>Custodio de Registros</b>.\n\n"
        "Toda la información contenida y procesada por este bot es de carácter Confidencial y se encuentra amparada bajo estrictos protocolos de privacidad y protección de datos.\n\n"
        "⚖️ <b>AVISO LEGAL</b>\n\n"
        "Se registran y almacenan los datos <b>(ID:, Usuario, Dirección IP, Fecha, Hora y mensajes enviados)</b> en nuestros servidores en caso de utilizar el bot sin autorización esto con fines de auditoría y seguridad.\n\n"
        "Cualquier <b>ACCESO NO AUTORIZADO</b>, intento de intrusión o uso indebido de la información será sancionado conforme a lo establecido en la Ley Contra los Delitos Informáticos, <b>Capítulos I y II, artículos 6, 7, 8, 9, 10, 11 y 13</b>.\n\n"
        "<b>Si usted no cuenta con autorización para acceder a este bot o utilizar sus servicios, desconéctese y elimine inmediatamente.</b>\n\n"
        "<b>La permanencia en esta chat constituye la aceptación de los términos aquí expuestos.</b>"
    )


# =========================================================================
# 🛡️ SEGURIDAD DE IA: FILTRO PII Y RATE LIMITING ANTI-DOS
# =========================================================================
AI_RATE_LIMITS: Dict[int, List[float]] = {}


def sanitizar_pii(texto: str) -> str:
    """
    Filtro Anti-Fuga de Datos (PII):
    Sanitiza cédulas venezolanas (8 dígitos) y teléfonos locales (04XX-XXXXXXX)
    reemplazándolos por [C.I. OCULTA] y [TLF. OCULTO] antes de inyectar a Ollama.
    """
    if not texto:
        return ""

    # 1. Teléfonos locales venezolanos (04XX-XXXXXXX, 04XXXXXXXXX, +584XXXXXXXXX, 02XXXXXXXXX)
    patron_tlf = r'(?:\+?58[-\s]?)?0?(?:412|414|424|416|426|418|2\d{2})[-\s]?\d{3}[-\s]?\d{4}\b'
    texto = re.sub(patron_tlf, '[TLF. OCULTO]', texto)

    # 2. Cédulas venezolanas (7 u 8 dígitos consecutivos con o sin prefijo V/E/CI)
    patron_ci = r'\b(?:[VvEe][-\s]?|[Cc][Ii][:\.\s]*)?\d{7,8}\b'
    texto = re.sub(patron_ci, '[C.I. OCULTA]', texto)

    return texto


def check_ai_rate_limit(entity_id: int, max_requests: int = 5, window_seconds: float = 60.0) -> bool:
    """
    Limitador de tasa (Rate Limiting Anti-DoS) para consultas de texto plano a la IA.
    Regla: Máximo 5 mensajes por minuto por usuario/chat.
    Retorna True si está dentro del límite permitido, False si lo excede.
    """
    now = time.time()
    timestamps = AI_RATE_LIMITS.setdefault(entity_id, [])
    # Filtrar marcas de tiempo dentro de la ventana de tiempo (60s)
    timestamps = [t for t in timestamps if now - t < window_seconds]

    if len(timestamps) >= max_requests:
        AI_RATE_LIMITS[entity_id] = timestamps
        return False

    timestamps.append(now)
    AI_RATE_LIMITS[entity_id] = timestamps
    return True


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


async def start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el mensaje inicial y el panel de comandos personalizados según el rol del usuario (Owner vs Usuario Autorizado)."""
    if not await check_authorization(update, context):
        return

    owner_id = CONFIG.get("owner_id", 0)
    user = update.effective_user
    chat = update.effective_chat
    user_id = user.id if user else 0
    is_owner = (user_id == owner_id)
    is_private = (chat.type == "private") if chat else False

    # Si se solicitó ayuda específica de un comando (/help comando)
    if context.args:
        target_cmd = context.args[0].lstrip('/').lower()
        if target_cmd in COMMANDS:
            help_msg = COMMANDS[target_cmd].get(
                "help_text",
                f"Sin información detallada para /{target_cmd}."
            )
            await safe_reply_html(update.message, help_msg)
            return

    first_name = (user.first_name or "").strip() if user else ""
    last_name = (user.last_name or "").strip() if user else ""
    if last_name.lower() == "none":
        last_name = ""
    display_name = " ".join([p for p in [first_name, last_name] if p]) or "Administrador"

    commands_enabled = bool(CONFIG.get("commands_enabled", True))

    # =========================================================================
    # 👑 MENÚ PERSONALIZADO EXCLUSIVO PARA EL PROPIETARIO (SOLO EN CHAT PRIVADO)
    # =========================================================================
    if is_owner and is_private:
        owner_menu = [
            "👑 <b>PANEL DE CONTROL PRINCIPAL • ADMINISTRADOR</b>\n",
            f"¡Bienvenido, <b>{html.escape(display_name)}</b>!\n",
            "A continuación tienes el inventario completo de herramientas y comandos administrativos del sistema:\n",
            "🛡️ <b>Gestión de Seguridad y Accesos</b>",
            "• <code>/permisos</code> <i>(/autorizados, /whitelist)</i> - Gestión interactiva de usuarios y grupos autorizados.",
            "• <code>/bloqueo_comandos</code> <i>(/bloquear_comandos)</i> - Bloquear o reactivar el uso de comandos para usuarios y grupos.",
            "• <code>/botstatus</code> <i>(/estatus, /status, /estado_bot)</i> - Diagnóstico de conectividad, proxies corporativos y accesos denegados.",
            "• <code>/info</code> <i>(/aviso, /legal)</i> - Información legal, privacidad y advertencia de seguridad.\n",
            "🛠️ <b>Mantenimiento y Rendimiento del Sistema</b>",
            "• <code>/emergencia</code> <i>(/panico, /contingencia)</i> - Panel de emergencia (detener servicio, modo mantenimiento, restaurar config).",
            "• <code>/limpiador</code> <i>(/limpieza, /cleaner)</i> - Diagnóstico de almacenamiento, inodos y panel interactivo de limpieza.",
            "• <code>/actualizar</code> <i>(/update, /git_update)</i> - Comprobar y aplicar actualizaciones desde GitHub.",
            "📊 <b>Monitoreo e Infraestructura de Red</b>",
            "• <code>/servicios [web]</code> <i>(/reporte_servicios)</i> - Chequeo de Servicios Corporativos (con captura web).",
            "• <code>/sedes [web]</code> <i>(/reporte_sedes, /sitios)</i> - Chequeo de Sedes y Enlaces de Comunicación (con captura web).",
            "• <code>/caidas [web]</code> <i>(/incidentes, /fallas)</i> - Reporte enfocado en fallas, servicios caídos y sedes desconectadas.",
            "• <code>/web [modo]</code> <i>(/pantalla, /dashboard)</i> - Captura gráfica panorámica HD del portal en tiempo real.",
            "• <code>/monitoreo</code> <i>(/reporte_completo)</i> - Reporte unificado integral (Servicios + Sedes).",
            "• <code>/internet</code> <i>(/proxy, /proxies)</i> - Diagnóstico de conectividad a internet y proxies corporativos.",
            "• <code>/analisis_red [tiempo]</code> <i>(/red)</i> - Captura de tráfico en vivo (<code>tcpdump</code> 120s), análisis profundo (<code>tshark</code>) y entrega de reportes <code>.md</code> y <code>.html</code>.\n",
            "🧪 <b>Diagnóstico Exhaustivo y Depuración (Exclusivo Owner)</b>",
            "• <code>/debug_servicios</code> - Reporte exhaustivo de todos los servicios (A a Z) con plantilla <code>MENSAJEDEBUGA</code> + <code>servicelog.txt</code>.",
            "• <code>/debug_sedes</code> - Reporte exhaustivo de todas las sedes y equipos con plantilla <code>MENSAJEDEBUGB</code> + <code>servicelog.txt</code>.",
            "• <code>/debug_completo</code> <i>(/debug_monitoreo)</i> - Reporte técnico integral exhaustivo (Servicios + Sedes) + <code>servicelog.txt</code>.",
            "• <code>/debug_monitor</code> <i>(/monitordebug)</i> - Conmutar interruptor de modo depuración global para todos los reportes.\n",
            "🧠 <b>ASISTENTE (IA)</b>",
            "• <code>/reset_ia</code> <i>(/borrar_chat)</i> - Reiniciar el contexto de la conversación con el asistente.\n",
            "<i>Recuerda también que puedes escribir directamente en el chat para interactuar con la IA.</i>\n",
            "<i>📌 <b>Nota sobre la memoria:</b> Después de entregar la respuesta técnica detallada, el bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento hasta que se use el comando <code>/reset_ia</code> y reinicie para una nueva consulta referente a otro tema.</i>\n",
            "<i>💡 Todas las interacciones que se tengan con el asistente de IA sirven de retroalimentación, en caso de obtener una solución se le puede enviar para ampliar el conocimiento de la IA.</i>\n"
        ]

        if commands_enabled and COMMANDS:
            owner_menu.append("⚙️ <b>Comandos Adicionales del Sistema:</b>")
            for cmd_name, cmd_info in COMMANDS.items():
                desc = cmd_info.get("description", "Sin descripción")
                owner_menu.append(f"• <code>/{cmd_name}</code> - {html.escape(desc)}")

        owner_menu.append("\n<i>Sistema operando en Debian GNU/Linux • Python 3.11</i>")

        await safe_reply_html(update.message, "\n".join(owner_menu))
        return

    # =========================================================================
    # 👤 MENÚ PARA USUARIOS AUTORIZADOS EN CHAT PRIVADO (ASISTENTE IA)
    # =========================================================================
    if is_private:
        user_private_menu = [
            "🤖 <b>ASISTENTE VIRTUAL CON INTELIGENCIA ARTIFICIAL</b>\n",
            f"¡Hola, <b>{html.escape(display_name)}</b>!\n",
            "En este chat privado tienes acceso directo al Asistente Técnico con IA Local.\n",
            "🧠 <b>¿Cómo interactuar?</b>",
            "• Escribe directamente en este chat tu duda, consulta técnica o problema de infraestructura para recibir asistencia en tiempo real.",
            "• <code>/reset_ia</code> - Reiniciar la memoria de la conversación para comenzar una nueva consulta sobre otro tema.",
            "• <code>/info</code> - Términos de uso, privacidad y políticas de seguridad.\n",
            "📌 <i><b>Nota:</b> Los demás comandos solo se ejecutan directamente en los grupos de operaciones autorizados.</i>\n",
            "<i>Sistema operando en Debian GNU/Linux</i>"
        ]
        await safe_reply_html(update.message, "\n".join(user_private_menu))
        return

    # =========================================================================
    # 👥 MENÚ PARA GRUPOS AUTORIZADOS (COMANDOS FUNCIONALES DE MONITOREO)
    # =========================================================================
    user_menu = [
        "🤖 <b>COMANDOS FUNCIONALES DEL BOT</b>\n",
        f"¡Hola, <b>{html.escape(display_name)}</b>!\n",
        "Tienes acceso a las siguientes funciones del sistema en este grupo:\n",
        "📊 <b>Monitoreo de Infraestructura</b>",
        "• <code>/servicios</code> - Consultar estado de los Servicios Corporativos.",
        "• <code>/sedes</code> - Consultar estado de Sedes y Enlaces de Comunicación.",
        "• <code>/monitoreo</code> - Ejecutar reporte completo de infraestructura.",
        "• <code>/internet</code> - Diagnóstico de salidas a internet y proxies corporativos.",
        "• <code>/analisis_red</code> - Solicitar análisis y diagnóstico de la red local.",
        "• <code>/info</code> - Información legal, privacidad y advertencia de seguridad.\n",
        "🧠 <b>ASISTENTE (IA)</b>",
        "• <code>/reset_ia</code> - Reiniciar la memoria de la conversación.\n",
        "<i>Recuerda también que puedes escribir directamente en el chat para interactuar con la IA.</i>\n",
        "<i>📌 <b>Nota sobre la memoria:</b> Después de entregar la respuesta técnica detallada, el bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento hasta que se use el comando <code>/reset_ia</code> y reinicie para una nueva consulta referente a otro tema.</i>\n",
        "<i>💡 Todas las interacciones que se tengan con el asistente de IA sirven de retroalimentación, en caso de obtener una solución se le puede enviar para ampliar el conocimiento de la IA.</i>\n"
    ]

    if commands_enabled and COMMANDS:
        user_menu.append("⚙️ <b>Comandos del Sistema:</b>")
        for cmd_name, cmd_info in COMMANDS.items():
            desc = cmd_info.get("description", "Sin descripción")
            user_menu.append(f"• <code>/{cmd_name}</code> - {html.escape(desc)}")

    user_menu.append("\n<i>Sistema de Monitoreo operando en Debian GNU/Linux</i>")

    await safe_reply_html(update.message, "\n".join(user_menu))


async def handle_dynamic_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos dinámicos y soporta timeouts personalizados para tareas extensas."""
    if not await check_authorization(update, context):
        return

    # Regla de bloqueo global de comandos para usuarios/grupos activada por el Owner
    if CONFIG.get("commands_locked_for_users", False) and update.effective_user.id != CONFIG.get("owner_id", 0):
        await safe_reply_html(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos."
        )
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

    # 3. Rate Limiting (Anti-DoS para IA: Máximo 5 consultas por minuto por usuario)
    user = update.effective_user
    chat = update.effective_chat
    rate_key = user.id if user else (chat.id if chat else 0)

    if not check_ai_rate_limit(rate_key, max_requests=5, window_seconds=60.0):
        await update.message.reply_text("Limite de consultas a la IA excedido. Espere 60 segundos.")
        return

    raw_user_text = update.message.text.strip()

    if not raw_user_text:
        return

    # 2. Filtro de PII (Anti-Fuga de Datos): Sanitizar Cédulas y Teléfonos
    user_text = sanitizar_pii(raw_user_text)

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
            "🔌 No pude conectar con el motor local de IA.\n"
            "Verifica que el servicio esté activo en este equipo."
        )

    except requests.exceptions.RequestException as e:
        logger.exception("Error HTTP/Request consultando servicio de IA")
        answer = (
            "⚠️ Ocurrió un error de red o del servicio local de IA.\n"
            "Intenta nuevamente en unos momentos."
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

    if success:
        # Recordatorio explícito al usuario sobre la memoria activa y el comando /reset_ia (en HTML puro)
        formatted_answer += (
            "\n\n<i>💡 <b>Nota:</b> El bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento "
            "hasta que uses <code>/reset_ia</code> para iniciar una nueva consulta referente a otro tema.</i>"
        )

    for chunk in split_message(formatted_answer):
        await safe_reply_html(
            update.message,
            chunk,
            disable_web_page_preview=True
        )


async def unknown_cmd(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos no registrados informando al usuario y sugiriendo opciones."""
    if not await check_authorization(update, context):
        return

    cmd_text = update.message.text.split()[0] if (update.message and update.message.text) else "/comando"
    unk_msg = (
        f"⚠️ <b>Comando no reconocido (<code>{html.escape(cmd_text)}</code>).</b>\n\n"
        "• Usa <code>/start</code> o <code>/ayuda</code> para consultar los comandos disponibles.\n"
        "• O escribe tu mensaje directamente en texto plano para consultar al asistente de IA."
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


async def require_private_chat(update: Update, context: ContextTypes.DEFAULT_TYPE) -> bool:
    """
    Garantiza que un comando administrativo exclusivo del Administrador se ejecute únicamente en chat privado.
    Si se intenta ejecutar en un grupo o supergrupo:
    1. Borra de inmediato el mensaje con el comando en el grupo.
    2. Envía un aviso informativo temporal en el grupo indicando que el comando solo es válido en privado.
    3. Si quien lo escribió fue el Administrador, le envía una notificación en chat privado para que pueda ejecutarlo allí.
    """
    chat = update.effective_chat
    user = update.effective_user
    msg = update.message

    if not chat or chat.type == "private":
        return True

    # Es un grupo o supergrupo: borrar el mensaje inmediatamente
    if msg:
        cmd_name = msg.text.split()[0] if (msg.text and msg.text.strip()) else "administrativo"
        try:
            await msg.delete()
        except Exception as e:
            logger.debug(f"No se pudo borrar mensaje de comando restringido en grupo: {e}")

        # Mensaje temporal en el grupo
        warning_text = (
            "⚠️ <b>Comando Restringido:</b> Esta instrucción no está permitida en este grupo "
            "y ha sido eliminada por políticas de seguridad."
        )
        try:
            temp_msg = await context.bot.send_message(
                chat_id=chat.id,
                text=warning_text,
                parse_mode='HTML'
            )
            async def _auto_delete_notice(sent_msg):
                await asyncio.sleep(8.0)
                try:
                    await sent_msg.delete()
                except Exception:
                    pass
            asyncio.create_task(_auto_delete_notice(temp_msg))
        except Exception as e:
            logger.debug(f"No se pudo enviar aviso de comando restringido en grupo: {e}")

        # Si el Owner fue quien lo escribió por error, notificarle en privado
        if user and user.id == IMMUTABLE_OWNER_ID:
            chat_title = chat.title or "Grupo"
            owner_notice = (
                "🔒 <b>Aviso de Seguridad en Grupo</b>\n\n"
                f"Has intentado ejecutar el comando <code>{html.escape(cmd_name)}</code> en el grupo <b>{html.escape(chat_title)}</b>.\n\n"
                "Por políticas de seguridad y privacidad, el mensaje fue eliminado automáticamente del grupo.\n"
                f"Puedes ejecutar <code>{html.escape(cmd_name)}</code> de forma segura directamente aquí en este chat privado."
            )
            try:
                await context.bot.send_message(
                    chat_id=IMMUTABLE_OWNER_ID,
                    text=owner_notice,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.debug(f"No se pudo enviar aviso privado al owner: {e}")

    return False


async def manage_permissions(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el panel de gestión de permisos con datos detallados (exclusivo para el owner en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user:
        return

    owner_id = CONFIG.get("owner_id", 0)
    if update.effective_user.id != owner_id:
        await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    panel_text, reply_markup = await _build_permissions_panel(context.bot)
    await safe_reply_html(update.message, panel_text, reply_markup=reply_markup)


def get_denied_users_summary() -> list[dict]:
    """Extrae la lista de usuarios no autorizados registrados en el log de auditoría."""
    audit_path = get_audit_log_path()
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
    """Ejecuta un diagnóstico completo de conectividad, proxies, usuarios permitidos y negados (Exclusivo Owner en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    if update.effective_user.id != owner_id:
        await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
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
        "🌐 <b>DIAGNÓSTICO DE RED E INTERNET</b>"
    ]

    for res in network_results:
        icon = "🟢" if res["ok"] else "🔴"
        lines.append(f"• <b>{html.escape(res['name'])}:</b>\n   {icon} <code>{html.escape(res['detail'])}</code>")

    lines.append("")
    lines.append(f"🔄 <b>Canal Activo del Bot:</b> <code>{html.escape(active_channel)}</code>")

    lines.append("")
    lines.append("👥 <b>CONTROL DE ACCESO DE USUARIOS</b>")
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


async def toggle_debug_monitor(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner activar, desactivar o consultar el Modo Depuración del Monitor (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    user_id = update.effective_user.id
    if user_id != owner_id:
        user = update.effective_user
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/debug_monitor"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] DEBUG DENEGADO | ID: {user.id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {update.effective_chat.id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception:
            pass

        if owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "🚨 <b>Alerta: Intento No Autorizado de Control de Depuración</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user.id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{update.effective_chat.id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>Acción bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"Error notificando al owner sobre intento de depuración: {e}")

        await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    current_status = bool(CONFIG.get("monitor_debug_mode", False))

    if context.args:
        arg = context.args[0].lower()
        if arg in ("on", "activar", "activado", "true", "1", "si"):
            CONFIG["monitor_debug_mode"] = True
            save_config()
            await safe_reply_html(
                update.message,
                "🧪 <b>Modo Depuración del Monitor: ACTIVADO</b>\n\n"
                "Los próximos reportes incluirán información técnica detallada y el archivo consolidado <code>servicelog.txt</code> enviado exclusivamente a tu chat privado."
            )
            return
        elif arg in ("off", "desactivar", "desactivado", "false", "0", "no"):
            CONFIG["monitor_debug_mode"] = False
            save_config()
            await safe_reply_html(
                update.message,
                "📊 <b>Modo Depuración del Monitor: DESACTIVADO</b>\n\n"
                "El monitor operará en modo producción estándar."
            )
            return
        elif arg in ("status", "estado", "ver"):
            estado_txt = "🟢 <b>ACTIVADO</b>" if current_status else "🔴 <b>DESACTIVADO</b>"
            await safe_reply_html(
                update.message,
                f"🧪 <b>Estado actual del Modo Depuración:</b> {estado_txt}"
            )
            return

    # Si no se pasó argumento, mostrar panel con botones interactivos
    estado_str = "🟢 ACTIVO" if current_status else "🔴 INACTIVO"
    keyboard = [
        [
            InlineKeyboardButton("🟢 Activar Depuración", callback_data="auth_toggle_debug:on"),
            InlineKeyboardButton("🔴 Desactivar Depuración", callback_data="auth_toggle_debug:off")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await safe_reply_html(
        update.message,
        f"🧪 <b>Panel de Modo Depuración (Monitor ATIT)</b>\n\n"
        f"<b>Estado Actual:</b> <code>{estado_str}</code>\n\n"
        f"• <b>Activo:</b> Genera reportes exhaustivos y adjunta el archivo <code>servicelog.txt</code>.\n"
        f"• <b>Inactivo:</b> Envía el resumen ejecutivo estándar.\n\n"
        f"<i>Presiona una opción para cambiar el estado:</i>",
        reply_markup=reply_markup
    )


async def toggle_commands_lock(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner bloquear o desbloquear la ejecución de comandos para el resto de usuarios y grupos (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    user_id = update.effective_user.id
    if user_id != owner_id:
        user = update.effective_user
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/bloqueo_comandos"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] BLOQUEO_COMANDOS DENEGADO | ID: {user.id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {update.effective_chat.id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception:
            pass

        if owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "🚨 <b>Alerta: Intento No Autorizado de Gestión de Bloqueo de Comandos</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user.id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{update.effective_chat.id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>Acción bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"Error notificando al owner sobre intento de bloqueo: {e}")

        await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    current_status = bool(CONFIG.get("commands_locked_for_users", False))

    if context.args:
        arg = context.args[0].lower()
        if arg in ("on", "activar", "activado", "bloquear", "lock", "true", "1", "si"):
            CONFIG["commands_locked_for_users"] = True
            save_config()
            await safe_reply_html(
                update.message,
                "🔒 <b>Bloqueo de Comandos: ACTIVADO</b>\n\n"
                "A partir de ahora, los demás usuarios y los grupos <b>NO podrán ejecutar comandos</b>.\n"
                "Tú como Administrador conservas el acceso total e ilimitado."
            )
            return
        elif arg in ("off", "desactivar", "desactivado", "desbloquear", "unlock", "false", "0", "no"):
            CONFIG["commands_locked_for_users"] = False
            save_config()
            await safe_reply_html(
                update.message,
                "🔓 <b>Bloqueo de Comandos: DESACTIVADO</b>\n\n"
                "Los usuarios y grupos autorizados <b>pueden volver a ejecutar comandos</b> normalmente."
            )
            return
        elif arg in ("status", "estado", "ver"):
            estado_txt = "🔒 <b>ACTIVADO (Comandos bloqueados para usuarios/grupos)</b>" if current_status else "🔓 <b>DESACTIVADO (Comandos habilitados)</b>"
            await safe_reply_html(
                update.message,
                f"🛡️ <b>Estado del Control de Comandos:</b>\n{estado_txt}"
            )
            return

    # Si no se pasó argumento, mostrar panel con botones interactivos
    estado_str = "🔒 BLOQUEADOS PARA OTROS" if current_status else "🔓 HABILITADOS PARA TODOS"
    keyboard = [
        [
            InlineKeyboardButton("🔒 Bloquear a Otros", callback_data="auth_toggle_cmd_lock:on"),
            InlineKeyboardButton("🔓 Habilitar a Todos", callback_data="auth_toggle_cmd_lock:off")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    panel_msg = (
        "🛡️ <b>Control de Acceso a Comandos (Exclusivo Owner)</b>\n\n"
        f"<b>Estado Actual:</b> <code>{estado_str}</code>\n\n"
        "• <b>Bloquear:</b> Desactiva la ejecución de comandos para el grupo y usuarios autorizados.\n"
        "• <b>Habilitar:</b> Restablece el uso de comandos a la normalidad.\n\n"
        "<i>Tú como Administrador siempre mantendrás acceso completo e ilimitado a todas las herramientas.</i>"
    )

    await safe_reply_html(update.message, panel_msg, reply_markup=reply_markup)


def _format_cron_panel_text() -> str:
    """Genera el texto informativo del panel de control de cron."""
    is_enabled = bool(CONFIG.get("cron_reports_enabled", True))
    schedules = CONFIG.get("cron_schedules", ["07:30", "16:00"])
    if not isinstance(schedules, list):
        schedules = ["07:30", "16:00"]
    schedules = sorted(list(set(schedules)))

    updated_at = CONFIG.get("cron_reports_updated_at", "N/A")
    updated_by = CONFIG.get("cron_reports_updated_by", "N/A")

    # Calcular próximo envío
    now = datetime.now()
    now_hm = now.strftime("%H:%M")
    next_sch = None
    next_day = "hoy"
    for s in schedules:
        if s > now_hm:
            next_sch = s
            break
    if not next_sch and schedules:
        next_sch = schedules[0]
        next_day = "mañana"

    estado_label = "🟢 <b>ACTIVADOS (Operando)</b>" if is_enabled else "🔴 <b>PAUSADOS (Silenciados)</b>"
    horarios_str = ", ".join([f"<code>{h}</code>" for h in schedules]) if schedules else "<i>Ninguno</i>"
    proximo_str = f"<code>{next_sch}</code> ({next_day})" if (next_sch and is_enabled) else ("<i>En pausa</i>" if not is_enabled else "<i>Sin programar</i>")

    return (
        "⏰ <b>Control de Envíos Programados por Cron</b>\n\n"
        f"• <b>Estado:</b> {estado_label}\n"
        f"• <b>Horarios Diarios:</b> {horarios_str}\n"
        f"• <b>Próximo Envío:</b> {proximo_str}\n\n"
        f"👤 <b>Último Cambio:</b> {html.escape(str(updated_by))}\n"
        f"📅 <b>Fecha:</b> <code>{updated_at}</code>\n\n"
        "<b>Opciones de gestión por comando:</b>\n"
        "• <code>/cron on</code> o <code>/cron off</code> (Activar / Pausar)\n"
        "• <code>/cron horario 07:30, 16:00</code> (Asignar horarios)\n"
        "• <code>/cron agregar 12:00</code> (Añadir un horario)\n"
        "• <code>/cron quitar 16:00</code> (Eliminar un horario)"
    )


def _get_cron_keyboard():
    """Genera el teclado interactivo con botones inline para el control de cron."""
    is_enabled = bool(CONFIG.get("cron_reports_enabled", True))
    btn_toggle = (
        InlineKeyboardButton("🔴 Pausar Envíos Automáticos", callback_data="cron_act:pause")
        if is_enabled else
        InlineKeyboardButton("🟢 Reanudar Envíos Automáticos", callback_data="cron_act:resume")
    )
    return InlineKeyboardMarkup([
        [btn_toggle],
        [InlineKeyboardButton("🔄 Actualizar Estado", callback_data="cron_act:refresh")]
    ])


async def cmd_cron_control(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Gestiona la activación, pausa y horarios de los reportes programados por cron."""
    if not update.effective_user or not update.message:
        return

    user_id = update.effective_user.id
    owner_id = int(CONFIG.get("owner_id", 38914901))
    is_owner = (user_id == owner_id)
    is_private = (update.effective_chat and update.effective_chat.type == "private")

    # Seguridad: Exclusivo Owner en chat privado
    if not is_owner or not is_private:
        if not is_owner:
            logger.warning(f"Intento no autorizado de acceder a /cron por usuario {user_id}")
            now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            user_handle = f"@{update.effective_user.username}" if update.effective_user.username else update.effective_user.full_name
            owner_alert = (
                "🚨 <b>ALERTA DE SEGURIDAD: INTENTO NO AUTORIZADO</b>\n\n"
                f"El usuario <b>{html.escape(user_handle)}</b> (<code>{user_id}</code>) intentó manipular la configuración de <b>Envíos Programados (/cron)</b>.\n\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>Acción bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"Error notificando al owner sobre intento /cron: {e}")
        await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = f"{update.effective_user.full_name} [Telegram]"

    # Procesar subcomandos si se pasan argumentos
    if context.args:
        subcmd = context.args[0].lower()

        # 1. Activar
        if subcmd in ("on", "activar", "activado", "enable", "start", "1", "si"):
            CONFIG["cron_reports_enabled"] = True
            CONFIG["cron_reports_updated_at"] = now_str
            CONFIG["cron_reports_updated_by"] = admin_name
            save_config()
            await safe_reply_html(
                update.message,
                "🟢 <b>Envíos Programados: ACTIVADOS</b>\n\n"
                "Los reportes de monitoreo se despacharán puntualmente en los horarios configurados."
            )
            return

        # 2. Pausar / Desactivar
        elif subcmd in ("off", "desactivar", "desactivado", "pausar", "pause", "stop", "0", "no"):
            CONFIG["cron_reports_enabled"] = False
            CONFIG["cron_reports_updated_at"] = now_str
            CONFIG["cron_reports_updated_by"] = admin_name
            save_config()
            await safe_reply_html(
                update.message,
                "🔴 <b>Envíos Programados: PAUSADOS</b>\n\n"
                "Los despachos desatendidos a Telegram han sido temporalmente silenciados.\n"
                "<i>Nota: Las consultas manuales directas (/servicios, /sedes) seguirán respondiendo normalmente.</i>"
            )
            return

        # 3. Reemplazar lista de horarios
        elif subcmd in ("horario", "horarios", "schedules", "set"):
            raw_hours = " ".join(context.args[1:]).replace(",", " ").split()
            valid_hours = []
            time_regex = re.compile(r"^([01]\d|2[0-3]):[0-5]\d$")
            for h in raw_hours:
                h_clean = h.strip()
                if time_regex.match(h_clean):
                    valid_hours.append(h_clean)

            if not valid_hours:
                await safe_reply_html(
                    update.message,
                    "⚠️ <b>Formato inválido.</b>\n"
                    "Debes especificar al menos una hora válida en formato 24h (HH:MM).\n"
                    "<i>Ejemplo:</i> <code>/cron horario 07:30, 12:00, 16:00</code>"
                )
                return

            CONFIG["cron_schedules"] = sorted(list(set(valid_hours)))
            CONFIG["cron_reports_updated_at"] = now_str
            CONFIG["cron_reports_updated_by"] = admin_name
            save_config()

            h_list = ", ".join([f"<code>{x}</code>" for x in CONFIG["cron_schedules"]])
            await safe_reply_html(
                update.message,
                f"✅ <b>Nuevos horarios de envío asignados:</b>\n{h_list}\n\n"
                f"Los cambios han sido guardados y sincronizados con el portal web."
            )
            return

        # 4. Agregar un horario individual
        elif subcmd in ("agregar", "add", "anadir", "+"):
            if len(context.args) < 2:
                await safe_reply_html(
                    update.message,
                    "⚠️ Debes indicar la hora a agregar en formato <code>HH:MM</code>.\n"
                    "<i>Ejemplo:</i> <code>/cron agregar 13:15</code>"
                )
                return
            new_time = context.args[1].strip()
            if not re.match(r"^([01]\d|2[0-3]):[0-5]\d$", new_time):
                await safe_reply_html(update.message, "⚠️ Hora inválida. Usa formato 24 horas <code>HH:MM</code> (ej: <code>08:15</code>).")
                return

            schedules = CONFIG.setdefault("cron_schedules", ["07:30", "16:00"])
            if new_time not in schedules:
                schedules.append(new_time)
                CONFIG["cron_schedules"] = sorted(list(set(schedules)))
                CONFIG["cron_reports_updated_at"] = now_str
                CONFIG["cron_reports_updated_by"] = admin_name
                save_config()

            h_list = ", ".join([f"<code>{x}</code>" for x in CONFIG["cron_schedules"]])
            await safe_reply_html(
                update.message,
                f"✅ <b>Horario <code>{new_time}</code> añadido exitosamente.</b>\n\n"
                f"<b>Horarios activos:</b> {h_list}"
            )
            return

        # 5. Quitar un horario individual
        elif subcmd in ("quitar", "del", "eliminar", "remove", "-"):
            if len(context.args) < 2:
                await safe_reply_html(
                    update.message,
                    "⚠️ Debes indicar la hora a eliminar en formato <code>HH:MM</code>.\n"
                    "<i>Ejemplo:</i> <code>/cron quitar 16:00</code>"
                )
                return
            rem_time = context.args[1].strip()
            schedules = CONFIG.setdefault("cron_schedules", ["07:30", "16:00"])
            if rem_time in schedules:
                schedules.remove(rem_time)
                CONFIG["cron_schedules"] = sorted(schedules)
                CONFIG["cron_reports_updated_at"] = now_str
                CONFIG["cron_reports_updated_by"] = admin_name
                save_config()
                h_list = ", ".join([f"<code>{x}</code>" for x in CONFIG["cron_schedules"]]) if CONFIG["cron_schedules"] else "<i>Ninguno</i>"
                await safe_reply_html(
                    update.message,
                    f"🗑️ <b>Horario <code>{rem_time}</code> eliminado.</b>\n\n"
                    f"<b>Horarios activos restantes:</b> {h_list}"
                )
            else:
                await safe_reply_html(update.message, f"⚠️ El horario <code>{rem_time}</code> no estaba en la lista de envíos programados.")
            return

        # 6. Consultar estado
        elif subcmd in ("status", "estado", "ver", "info"):
            pass

    # Mostrar panel interactivo si no hubo subcomando terminal
    panel_msg = _format_cron_panel_text()
    reply_markup = _get_cron_keyboard()
    await safe_reply_html(update.message, panel_msg, reply_markup=reply_markup)


async def handle_cron_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las interacciones de botones del panel de control de cron."""
    query = update.callback_query
    if not query:
        return

    clicker_id = query.from_user.id
    owner_id = int(CONFIG.get("owner_id", 38914901))

    if clicker_id != owner_id:
        await query.answer("⛔ Acción reservada para el Administrador Principal.", show_alert=True)
        return

    action = query.data.split(":", 1)[1] if ":" in query.data else ""
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    admin_name = f"{query.from_user.full_name} [Telegram]"

    if action == "pause":
        CONFIG["cron_reports_enabled"] = False
        CONFIG["cron_reports_updated_at"] = now_str
        CONFIG["cron_reports_updated_by"] = admin_name
        save_config()
        await query.answer("🔴 Envíos programados pausados exitosamente.")
    elif action == "resume":
        CONFIG["cron_reports_enabled"] = True
        CONFIG["cron_reports_updated_at"] = now_str
        CONFIG["cron_reports_updated_by"] = admin_name
        save_config()
        await query.answer("🟢 Envíos programados reactivados exitosamente.")
    elif action == "refresh":
        await query.answer("🔄 Panel actualizado.")

    panel_text = _format_cron_panel_text()
    markup = _get_cron_keyboard()
    try:
        await query.edit_message_text(panel_text, parse_mode='HTML', reply_markup=markup)
    except Exception:
        pass


async def _run_and_send_monitoring_report(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    target: str = "completo",
    target_name: str = "Infraestructura",
    force_debug: bool = False
) -> None:
    """Función unificada para ejecutar chequeos concurrentes y enviar reportes a Telegram."""
    if not update.effective_user or not update.message:
        return

    # Verificar autorización general del usuario
    if not await check_authorization(update, context):
        return

    owner_id = CONFIG.get("owner_id", 0)
    allowed_groups = CONFIG.get("allowed_group_ids", [])
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id

    is_owner = (user_id == owner_id)
    is_allowed_group = (chat_id in allowed_groups)

    # Si es comando de debug directo, es estrictamente reservado para el Owner
    if force_debug and not is_owner:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or f"/debug_{target}"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] DEBUG_DIRECTO DENEGADO ({target.upper()}) | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        await safe_reply_html(
            update.message,
            MSG_UNAUTHORIZED_ADMIN_COMMAND
        )

        if owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Reporte Debug Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📋 <b>Reporte Intentado:</b> <code>{html.escape(target_name)}</code>\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=owner_alert,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre solicitud debug denegada: {e}")

        return

    # Regla de seguridad 2: Solo el owner o usuarios dentro de los grupos autorizados
    if not is_owner and not is_allowed_group:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or f"/{target}"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        # 1. Registrar en log de auditoría
        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] REPORTE DENEGADO ({target.upper()}) | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        # 2. Responder al usuario denegando la ejecución con formato unificado y de alto impacto
        await safe_reply_html(
            update.message,
            MSG_UNAUTHORIZED_GROUP_COMMAND
        )

        # 3. Notificar inmediatamente al Owner en tiempo real
        if owner_id and user_id != owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Solicitud de Reporte Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📋 <b>Reporte Intentado:</b> <code>{html.escape(target_name)}</code>\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente porque el usuario no es el administrador ni la petición se originó en el grupo autorizado.</i>"
            )
            try:
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=owner_alert,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre solicitud de reporte restringido: {e}")

        return

    # Regla de bloqueo global de comandos para usuarios/grupos activada por el Owner
    if CONFIG.get("commands_locked_for_users", False) and not is_owner:
        await safe_reply_html(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return

    # Determinar si se ejecuta en modo depuración
    if force_debug and is_owner:
        is_debug = True
    else:
        is_debug = bool(CONFIG.get("monitor_debug_mode", False)) if is_owner else False

    # Mensaje temporal de espera
    wait_msg = await update.message.reply_text(
        f"⏳ <i>Ejecutando chequeo concurrente de {target_name}... Por favor espere.</i>",
        parse_mode='HTML'
    )

    try:
        from monitor.monitor_engine import execute_monitoring
        result = await execute_monitoring(target=target, debug_mode=is_debug)

        # Borrar mensaje temporal
        try:
            await wait_msg.delete()
        except Exception:
            pass

        # Enviar reporte de servicios si aplica (formateado a HTML)
        if result.get("report_servicios"):
            html_svc = markdown_to_telegram_html(result["report_servicios"])
            try:
                await update.message.reply_text(html_svc, parse_mode='HTML')
            except Exception as e_html:
                logger.warning(f"Fallo envío en HTML de servicios ({e_html}). Enviando en texto plano...")
                await update.message.reply_text(result["report_servicios"])

        # Enviar reporte de sedes si aplica (formateado a HTML)
        if result.get("report_sedes"):
            html_sedes = markdown_to_telegram_html(result["report_sedes"])
            try:
                await update.message.reply_text(html_sedes, parse_mode='HTML')
            except Exception as e_html:
                logger.warning(f"Fallo envío en HTML de sedes ({e_html}). Enviando en texto plano...")
                await update.message.reply_text(result["report_sedes"])

        # Generar y enviar Captura Web en Alta Definición (Playwright)
        arg_first = (context.args[0].lower() if (context.args and len(context.args) > 0) else "")
        if arg_first in ("web", "full", "pantalla", "todo", "global"):
            capture_mode = "full"
            mode_label = "Vista Global"
        elif target == "servicios":
            capture_mode = "servicios"
            mode_label = "Servicios Activos"
        elif target == "sedes":
            capture_mode = "sedes"
            mode_label = "Sedes Regionales y Equipos en Sitio"
        elif target in ("caidas", "incidentes", "fallas"):
            capture_mode = "caidas"
            mode_label = "Incidentes y Servicios Caídos"
        else:
            capture_mode = "full"
            mode_label = "Vista Global"

        try:
            from monitor.web_screenshot import capture_web_dashboard
            screen_file = await capture_web_dashboard(mode=capture_mode)
            if screen_file and screen_file.exists():
                caption = (
                    f"📸 <b>Captura en Tiempo Real</b>\n"
                    f"🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n"
                    f"📌 <i>{mode_label}</i>"
                )
                with open(screen_file, "rb") as photo_doc:
                    await update.message.reply_photo(
                        photo=photo_doc,
                        caption=caption,
                        parse_mode='HTML'
                    )
        except Exception as e_screen:
            logger.warning(f"No se pudo generar/enviar captura web ({e_screen})")

        # Si está en modo depuración y fue solicitado por el Owner, adjuntar el archivo de log
        if is_debug and is_owner and result.get("log_file") and result["log_file"].exists():
            with open(result["log_file"], "rb") as doc:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=doc,
                    filename="servicelog.txt",
                    caption=f"📄 Registro técnico detallado de ejecución ({result['elapsed_seconds']}s)"
                )

    except Exception as e:
        logger.error(f"Error ejecutando monitoreo ({target}): {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el chequeo:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await safe_reply_html(update.message, f"❌ Error ejecutando chequeo: {e}")


async def cmd_reporte_servicios(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Servicios Corporativos."""
    await _run_and_send_monitoring_report(update, context, target="servicios", target_name="Servicios Corporativos")


async def cmd_reporte_sedes(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Sedes y Enlaces."""
    await _run_and_send_monitoring_report(update, context, target="sedes", target_name="Sedes y Equipos de Comunicación")


async def cmd_reporte_caidas(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Incidentes y Caídas."""
    await _run_and_send_monitoring_report(update, context, target="caidas", target_name="Incidentes y Servicios Caídos")


async def cmd_captura_web(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando directo para obtener la captura web en tiempo real."""
    await _run_and_send_monitoring_report(update, context, target="web", target_name="Vista Web en Tiempo Real")


async def cmd_reporte_completo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte completo (Servicios + Sedes)."""
    await _run_and_send_monitoring_report(update, context, target="completo", target_name="Servicios Corporativos y Sedes")


async def cmd_debug_servicios(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte exhaustivo de servicios (MENSAJEDEBUGA en privado)."""
    if not await require_private_chat(update, context):
        return

    await _run_and_send_monitoring_report(
        update, context,
        target="servicios",
        target_name="Servicios Corporativos (Debug Directo)",
        force_debug=True
    )


async def cmd_debug_sedes(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte exhaustivo de sedes (MENSAJEDEBUGB en privado)."""
    if not await require_private_chat(update, context):
        return

    await _run_and_send_monitoring_report(
        update, context,
        target="sedes",
        target_name="Sedes y Equipos (Debug Directo)",
        force_debug=True
    )


async def cmd_debug_completo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner obtenga directamente el reporte técnico integral exhaustivo (Debug Directo en privado)."""
    if not await require_private_chat(update, context):
        return

    await _run_and_send_monitoring_report(
        update, context,
        target="completo",
        target_name="Servicios y Sedes (Debug Directo)",
        force_debug=True
    )


async def cmd_internet(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """
    Comando para verificar la funcionalidad y latencia de las salidas a internet y proxies corporativos.
    Disponible para el Owner y en grupos autorizados.
    Oculta la conexión directa si la solicitud no proviene del Owner.
    """
    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id if update.effective_chat else 0
    is_owner = (user_id == owner_id)

    allowed_groups = CONFIG.get("allowed_group_ids", [])
    is_allowed_group = (chat_id in allowed_groups)

    # Regla estricta de seguridad: Solo el Owner (privado o grupo) o cualquier petición originada dentro del grupo autorizado
    if not is_owner and not is_allowed_group:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/internet"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        # 1. Registrar en log de auditoría
        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] INTERNET DENEGADO | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        # 2. Responder al usuario denegando la ejecución con formato unificado y de alto impacto
        await safe_reply_html(
            update.message,
            MSG_UNAUTHORIZED_GROUP_COMMAND
        )

        # 3. Notificar al Owner si aplica
        if owner_id and user_id != owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Solicitud de Diagnóstico de Internet Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📋 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente porque el usuario no es el administrador ni la petición se originó en el grupo autorizado.</i>"
            )
            try:
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=owner_alert,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre solicitud /internet denegada: {e}")
        return

    # Regla de bloqueo global de comandos para usuarios/grupos activada por el Owner
    if CONFIG.get("commands_locked_for_users", False) and not is_owner:
        await safe_reply_html(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return

    # Mensaje temporal de espera
    wait_msg = await update.message.reply_text(
        "⏳ <i>Verificando estado y latencia de salida a internet y proxies corporativos... Por favor espere.</i>",
        parse_mode='HTML'
    )

    try:
        bot_token = CONFIG.get("bot_token", "")
        tg_url = f"https://api.telegram.org/bot{bot_token}/getMe"

        network_tasks = []
        # Solo incluir conexión directa si el solicitante es el Owner
        if is_owner:
            network_tasks.append(_check_endpoint_health("Conexión Directa a Internet", tg_url, None, timeout=3.5))

        proxies_list = load_proxies_list()
        for p in proxies_list:
            p_name = p.get("name", "Proxy")
            p_url = p.get("url")
            if p_url:
                network_tasks.append(_check_endpoint_health(p_name, tg_url, p_url, timeout=3.5))

        network_results = await asyncio.gather(*network_tasks)

        # Borrar mensaje de espera
        try:
            await wait_msg.delete()
        except Exception:
            pass

        # Construir reporte con barra divisoria compacta bajo el título
        lines = [
            "🌐 <b>DIAGNÓSTICO DE RED E INTERNET</b>",
            "━━━━━━━━━━━━"
        ]

        for res in network_results:
            icon = "🟢" if res["ok"] else "🔴"
            lines.append(f"• <b>{html.escape(res['name'])}:</b>\n   {icon} <code>{html.escape(res['detail'])}</code>")

        response_text = "\n".join(lines)
        await update.message.reply_text(response_text, parse_mode='HTML')

    except Exception as e:
        logger.error(f"Error ejecutando diagnóstico de internet (/internet): {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el diagnóstico:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await safe_reply_html(update.message, f"❌ Error ejecutando diagnóstico de internet: {e}")


async def cmd_analisis_red(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar el análisis y diagnóstico avanzado de red local (ARP / ICMP)."""
    if not update.effective_user or not update.message:
        return

    # Verificar autorización general
    if not await check_authorization(update, context):
        return

    owner_id = CONFIG.get("owner_id", 0)
    allowed_groups = CONFIG.get("allowed_group_ids", [])
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id

    is_owner = (user_id == owner_id)
    is_allowed_group = (chat_id in allowed_groups)

    if not is_owner and not is_allowed_group:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/analisis_red"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] ANALISIS_RED DENEGADO | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        # 2. Responder al usuario denegando la ejecución con formato unificado y de alto impacto
        await safe_reply_html(
            update.message,
            MSG_UNAUTHORIZED_GROUP_COMMAND
        )

        if owner_id and user_id != owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Análisis de Red Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=owner_alert,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre intento de analisis_red: {e}")

        return

    # Regla de bloqueo global de comandos para usuarios/grupos activada por el Owner
    if CONFIG.get("commands_locked_for_users", False) and not is_owner:
        await safe_reply_html(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return

    # Parsear argumentos opcionales (ej. /analisis_red 60, /analisis_red debug, /analisis_red 30 debug)
    duration = 120
    force_debug = False

    if context.args:
        for arg in context.args:
            arg_clean = arg.strip().lower()
            if arg_clean.isdigit():
                val = int(arg_clean)
                if 5 <= val <= 600:
                    duration = val
            elif arg_clean in ("debug", "completo", "full"):
                force_debug = True

    is_debug = (bool(CONFIG.get("monitor_debug_mode", False)) or force_debug) if is_owner else False

    wait_msg = await update.message.reply_text(
        f"⏳ <b>Iniciando captura de tráfico en tiempo real ({duration}s) con tcpdump y análisis profundo con tshark...</b>\n\n"
        "<i>Analizando protocolos, calculando volumen TX/RX, detectando tráfico sospechoso, tormentas de broadcast y midiendo latencias. Por favor espere.</i>",
        parse_mode='HTML'
    )

    try:
        from monitor.network_analyzer import execute_network_analysis
        result = await execute_network_analysis(duration_seconds=duration)

        try:
            await wait_msg.delete()
        except Exception:
            pass

        # 1. Enviar resumen al chat donde se originó la solicitud (Owner o Grupo Permitido)
        await update.message.reply_text(result["summary_text"], parse_mode='HTML')

        # 2. Enviar SIEMPRE los archivos adjuntos (.md y .html) al chat donde se solicitó (Owner o Grupo Permitido)
        md_file = result.get("md_report") or result.get("txt_report")
        html_file = result.get("html_report")

        if md_file and md_file.exists():
            with open(md_file, "rb") as f:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=f,
                    filename=md_file.name,
                    caption="📄 Reporte estructurado de análisis de red"
                )
        if html_file and html_file.exists():
            with open(html_file, "rb") as f:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=f,
                    filename=html_file.name,
                    caption="🌐 Reporte interactivo de análisis de red (HTML)"
                )

        # 3. Si la solicitud se ejecutó en el Grupo Permitido por otro usuario, enviar copia al chat privado del Owner
        if is_allowed_group and not is_owner and owner_id:
            try:
                group_title = update.effective_chat.title or "Grupo Autorizado"
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=f"📋 <b>Copia de Auditoría: Reporte de Red ejecutado en {html.escape(group_title)}</b>\n\n" + result["summary_text"],
                    parse_mode='HTML'
                )
                if md_file and md_file.exists():
                    with open(md_file, "rb") as f:
                        await context.bot.send_document(
                            chat_id=owner_id,
                            document=f,
                            filename=md_file.name,
                            caption=f"📄 Reporte de análisis de red ({group_title})"
                        )
                if html_file and html_file.exists():
                    with open(html_file, "rb") as f:
                        await context.bot.send_document(
                            chat_id=owner_id,
                            document=f,
                            filename=html_file.name,
                            caption=f"🌐 Reporte HTML ({group_title})"
                        )
            except Exception as e:
                logger.error(f"Error enviando copia de auditoría al Owner: {e}")

    except Exception as e:
        logger.error(f"Error ejecutando análisis de red: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el escaneo de red:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await safe_reply_html(update.message, f"❌ Error ejecutando análisis de red: {e}")


async def cmd_limpiador(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para diagnóstico de almacenamiento y limpieza interactiva del sistema (EXCLUSIVO OWNER en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id

    if user_id != owner_id:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/limpiador"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] LIMPIADOR DENEGADO | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        await safe_reply_html(
            update.message,
            MSG_UNAUTHORIZED_ADMIN_COMMAND
        )

        if owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "🚨 <b>Alerta: Intento de Acceso a Herramienta de Limpieza del Sistema</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente porque este comando es exclusivo del Owner.</i>"
            )
            try:
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=owner_alert,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre intento de limpiador: {e}")

        return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Analizando almacenamiento, inodos y cachés del sistema... Por favor espere unos segundos.</i>",
        parse_mode='HTML'
    )

    try:
        from monitor.system_cleaner import build_cleaner_dashboard
        dashboard_text, keyboard = await build_cleaner_dashboard()

        try:
            await wait_msg.delete()
        except Exception:
            pass

        await update.message.reply_text(
            dashboard_text,
            parse_mode='HTML',
            reply_markup=keyboard
        )
    except Exception as e:
        logger.error(f"Error generando panel de limpieza: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error generando panel de limpieza:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await safe_reply_html(update.message, f"❌ Error: {e}")


async def handle_cleaner_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de limpieza."""
    query = update.callback_query
    if not query or not query.data:
        return

    owner_id = CONFIG.get("owner_id", 0)
    clicker_id = query.from_user.id

    if clicker_id != owner_id:
        await query.answer("⛔ Solo el creador del bot puede ejecutar acciones de limpieza del sistema.", show_alert=True)
        return

    try:
        action = query.data.split(":", 1)[1]
    except IndexError:
        await query.answer("⚠️ Solicitud inválida.")
        return

    from monitor.system_cleaner import execute_clean_task, build_cleaner_dashboard

    if action == "refresh":
        await query.answer("🔄 Actualizando diagnóstico del sistema...")
        dashboard_text, keyboard = await build_cleaner_dashboard()
        try:
            await query.edit_message_text(dashboard_text, parse_mode='HTML', reply_markup=keyboard)
        except Exception:
            pass
        return

    await query.answer("⚙️ Ejecutando limpieza en el sistema...")

    result_banner = await execute_clean_task(action)
    dashboard_text, keyboard = await build_cleaner_dashboard()

    full_message = f"{result_banner}\n\n═══════════════════════════════\n\n{dashboard_text}"

    try:
        await query.edit_message_text(
            full_message,
            parse_mode='HTML',
            reply_markup=keyboard
        )
    except Exception:
        try:
            await query.message.reply_text(result_banner, parse_mode='HTML')
        except Exception:
            pass


async def cmd_broadcast_mensaje(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner envíe mensajes tipo Broadcast (difusión en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id

    # 1. Seguridad: Exclusivo Owner
    if user_id != owner_id:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/mensaje"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] BROADCAST DENEGADO | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        await safe_reply_html(
            update.message,
            MSG_UNAUTHORIZED_ADMIN_COMMAND
        )

        if owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "🚨 <b>Alerta: Intento No Autorizado de Difusión Masiva (Broadcast)</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=owner_alert,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre intento de broadcast: {e}")

        return

    # 2. Extraer el texto del mensaje a difundir
    raw_text = update.message.text or ""
    parts = raw_text.split(maxsplit=1)
    if len(parts) < 2 or not parts[1].strip():
        guide_text = (
            "📢 <b>Panel de Difusión Masiva (Broadcast)</b>\n\n"
            "<b>Uso del comando:</b>\n"
            "<code>/mensaje &lt;texto del comunicado&gt;</code>\n\n"
            "<b>Ejemplo:</b>\n"
            "<code>/mensaje Estimado equipo, el día de hoy a las 15:00 se realizará mantenimiento preventivo.</code>\n\n"
            "<i>El mensaje será enviado por el bot a todos los grupos autorizados y usuarios con acceso al sistema.</i>"
        )
        await safe_reply_html(update.message, guide_text)
        return

    broadcast_content = parts[1].strip()
    if (broadcast_content.startswith('"') and broadcast_content.endswith('"')) or \
       (broadcast_content.startswith("'") and broadcast_content.endswith("'")):
        broadcast_content = broadcast_content[1:-1].strip()

    now_str = datetime.now().strftime("%d/%m/%Y %H:%M")
    formatted_announcement = (
        "📢 <b>COMUNICADO OFICIAL</b>\n\n"
        f"{html.escape(broadcast_content)}\n\n"
        f"🏛️ <i>Mensaje emitido por la Administración • {now_str}</i>"
    )

    # 3. Recopilar destinatarios
    allowed_users = [uid for uid in CONFIG.get("allowed_user_ids", []) if uid != owner_id]
    allowed_groups = CONFIG.get("allowed_group_ids", [])

    total_destinatarios = len(allowed_users) + len(allowed_groups)

    if total_destinatarios == 0:
        await safe_reply_html(
            update.message,
            "⚠️ No hay usuarios ni grupos adicionales en la lista de autorizados para enviar la difusión."
        )
        return

    wait_msg = await update.message.reply_text(
        f"⏳ <i>Iniciando difusión masiva hacia {total_destinatarios} destino(s)... Por favor espere.</i>",
        parse_mode='HTML'
    )

    success_count = 0
    fail_count = 0
    delivery_details = []

    # 4. Enviar a Grupos Autorizados
    for gid in allowed_groups:
        g_title = await _resolve_group_info(context.bot, gid)
        try:
            await context.bot.send_message(
                chat_id=gid,
                text=formatted_announcement,
                parse_mode='HTML'
            )
            success_count += 1
            delivery_details.append(f"• 🟢 <b>Grupo:</b> {html.escape(g_title)} (<code>{gid}</code>) → <i>Entregado</i>")
        except Exception as e:
            fail_count += 1
            err_msg = "Bloqueado o expulsado" if "forbidden" in str(e).lower() else "Error de envío"
            delivery_details.append(f"• 🔴 <b>Grupo:</b> {html.escape(g_title)} (<code>{gid}</code>) → <i>{err_msg}</i>")
            logger.warning(f"Fallo al enviar broadcast a grupo {gid}: {e}")

    # 5. Enviar a Usuarios Autorizados
    for uid in allowed_users:
        u_name, u_uname = await _resolve_user_info(context.bot, uid)
        u_label = f"{u_name} ({u_uname})" if u_uname else u_name
        try:
            await context.bot.send_message(
                chat_id=uid,
                text=formatted_announcement,
                parse_mode='HTML'
            )
            success_count += 1
            delivery_details.append(f"• 🟢 <b>Usuario:</b> {html.escape(u_label)} (<code>{uid}</code>) → <i>Entregado</i>")
        except Exception as e:
            fail_count += 1
            err_msg = "Bot bloqueado por el usuario" if "forbidden" in str(e).lower() else "Chat no iniciado / Inaccesible"
            delivery_details.append(f"• 🔴 <b>Usuario:</b> {html.escape(u_label)} (<code>{uid}</code>) → <i>{err_msg}</i>")
            logger.warning(f"Fallo al enviar broadcast a usuario {uid}: {e}")

    try:
        await wait_msg.delete()
    except Exception:
        pass

    # 6. Reporte Final al Owner
    report_lines = [
        "📊 <b>Reporte de Difusión Masiva (Broadcast)</b>",
        "",
        f"✅ <b>Envíos exitosos:</b> {success_count}",
        f"❌ <b>Envíos fallidos:</b> {fail_count}",
        f"👥 <b>Total destinos:</b> {total_destinatarios}",
        "",
        "📋 <b>Detalle de Entrega:</b>",
        *delivery_details,
        "",
        "<i>El comunicado fue emitido y firmado por el Bot con éxito.</i>"
    ]

    await update.message.reply_text(
        "\n".join(report_lines),
        parse_mode='HTML'
    )


async def cmd_info(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el aviso legal, términos de confidencialidad y advertencia de seguridad del bot."""
    if not update.effective_user or not update.message:
        return

    aviso_legal = get_security_warning_html()
    await safe_reply_html(update.message, aviso_legal)


async def cmd_actualizar(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando exclusivo para que el Owner verifique y aplique actualizaciones desde Git (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    owner_id = CONFIG.get("owner_id", 0)
    user_id = update.effective_user.id
    if user_id != owner_id:
        user = update.effective_user
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/actualizar"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] ACTUALIZAR DENEGADO | ID: {user.id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {update.effective_chat.id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception:
            pass

        if owner_id and CONFIG.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "🚨 <b>Alerta: Intento No Autorizado de Actualización del Bot</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user.id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{update.effective_chat.id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>Acción bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"Error notificando al owner sobre intento de actualizacion: {e}")

        await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    from monitor.system_updater import build_update_dashboard, execute_git_update

    # Si se pasa argumento 'now', 'apply', 'force' o 'si' -> aplicar directamente
    if context.args:
        arg = context.args[0].lower()
        if arg in ("now", "apply", "aplicar", "force", "instalar", "si"):
            wait_msg = await update.message.reply_text(
                "⏳ <i>Descargando novedades desde GitHub y respaldando configuración...</i>",
                parse_mode='HTML'
            )
            res = await execute_git_update(bot_instance=context.bot)
            try:
                await wait_msg.delete()
            except Exception:
                pass
            await update.message.reply_text(res, parse_mode='HTML')
            return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Comprobando repositorio remoto oficial en GitHub (origin/master)...</i>",
        parse_mode='HTML'
    )
    dashboard_text, keyboard = await build_update_dashboard(bot_instance=context.bot)
    try:
        await wait_msg.delete()
    except Exception:
        pass

    await safe_reply_html(update.message, dashboard_text, reply_markup=keyboard)


async def handle_update_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones de actualización del sistema presionadas por el Owner."""
    query = update.callback_query
    if not query or not query.data:
        return

    clicker_id = query.from_user.id
    if clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("⛔ Solo el creador y administrador del bot puede gestionar actualizaciones.", show_alert=True)
        return

    from monitor.system_updater import build_update_dashboard, execute_git_update

    action = query.data.replace("update_act:", "")

    if action in ("check", "refresh"):
        await query.answer("🔄 Verificando repositorio...")
        dashboard_text, keyboard = await build_update_dashboard(bot_instance=context.bot)
        try:
            await query.edit_message_text(
                dashboard_text,
                parse_mode='HTML',
                reply_markup=keyboard
            )
        except Exception:
            pass
        return

    elif action in ("apply", "force"):
        await query.answer("🚀 Aplicando actualización...")
        try:
            await query.edit_message_text(
                "⏳ <b>Descargando actualización desde GitHub...</b>\n\n"
                "• Respaldando archivos en <code>config/</code>...\n"
                "• Ejecutando sincronización forzada con el repositorio oficial...\n"
                "• Validando integridad de sintaxis...\n"
                "• Preparando reinicio de servicio...",
                parse_mode='HTML'
            )
        except Exception:
            pass

        res = await execute_git_update(bot_instance=context.bot)
        try:
            await query.edit_message_text(res, parse_mode='HTML')
        except Exception:
            try:
                await query.message.reply_text(res, parse_mode='HTML')
            except Exception:
                pass
        return


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
    except (ValueError, IndexError):
        await query.answer("⚠️ Datos de solicitud inválidos.")
        return

    if action == "auth_toggle_debug":
        new_state = (target_id_str == "on")
        CONFIG["monitor_debug_mode"] = new_state
        save_config()
        estado_label = "🟢 ACTIVADO" if new_state else "🔴 DESACTIVADO"
        await query.answer(f"Modo Depuración {estado_label}")
        if query.message:
            try:
                await query.edit_message_text(
                    f"🧪 <b>Panel de Modo Depuración (Monitor ATIT)</b>\n\n"
                    f"<b>Estado Actual:</b> <code>{estado_label}</code>\n\n"
                    f"Configuración guardada exitosamente. "
                    f"{'Los próximos reportes incluirán archivo servicelog.txt adjunto.' if new_state else 'El monitor operará en modo producción estándar.'}",
                    parse_mode='HTML',
                    reply_markup=None
                )
            except Exception:
                pass
        return

    if action == "auth_toggle_cmd_lock":
        new_state = (target_id_str == "on")
        CONFIG["commands_locked_for_users"] = new_state
        save_config()
        estado_label = "🔒 BLOQUEADOS PARA OTROS" if new_state else "🔓 HABILITADOS PARA TODOS"
        await query.answer(f"Comandos {estado_label}")
        if query.message:
            try:
                await query.edit_message_text(
                    "🛡️ <b>Control de Acceso a Comandos (Exclusivo Owner)</b>\n\n"
                    f"<b>Estado Actual:</b> <code>{estado_label}</code>\n\n"
                    f"Configuración guardada exitosamente.\n"
                    f"{'Los demás usuarios y grupos tienen el uso de comandos temporalmente bloqueado.' if new_state else 'Los usuarios y grupos autorizados pueden usar los comandos normalmente.'}",
                    parse_mode='HTML',
                    reply_markup=None
                )
            except Exception:
                pass
        return

    try:
        target_user_id = int(target_id_str)
    except ValueError:
        await query.answer("⚠️ ID de usuario inválido.")
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
            aviso_legal = get_security_warning_html()
            user_approval_msg = (
                "🎉 <b>¡Acceso Autorizado!</b>\n\n"
                "El administrador ha aprobado tu acceso. Ya puedes interactuar con el bot libremente.\n\n"
                "📌 <b>RECUERDA QUE:</b>\n\n"
                f"{aviso_legal}"
            )
            await context.bot.send_message(
                chat_id=target_user_id,
                text=user_approval_msg,
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


# =========================================================================
# 🔄 MÓDULO DE MIGRACIÓN DINÁMICA DE TOKEN CON DRM (/migrar_token)
# =========================================================================
MIGRAR_TOKEN_WAITING = 1


async def cmd_migrar_token_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Inicia el flujo conversacional interactivo para la migración de identidad del bot (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return ConversationHandler.END

    if not is_authorized(update) or not update.effective_user or update.effective_user.id != IMMUTABLE_OWNER_ID:
        if update.message:
            await safe_reply_html(
                update.message,
                MSG_UNAUTHORIZED_ADMIN_COMMAND
            )
        return ConversationHandler.END

    context.user_data["migrar_token_ts"] = time.time()

    await safe_reply_html(
        update.message,
        "🔄 <b>Migración de Identidad del Bot (Token de Telegram)</b>\n\n"
        "Este proceso re-cifrará el nuevo token con la <b>Clave de Hardware (DRM)</b> sin alterar el anclaje físico ni exponerlo en texto plano.\n\n"
        "1️⃣ Ve a @BotFather y copia tu nuevo Token de bot.\n"
        "2️⃣ <b>Pega y envía el nuevo Token</b> como respuesta a este mensaje.\n\n"
        "⏳ <i>Tienes 2 minutos para responder. Envía <code>/cancelar</code> para abortar.</i>"
    )
    return MIGRAR_TOKEN_WAITING


async def handle_new_token_input(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Procesa, valida con Telegram y re-cifra el nuevo token con el DRM de hardware."""
    if not update.effective_user or update.effective_user.id != IMMUTABLE_OWNER_ID:
        return ConversationHandler.END

    start_ts = context.user_data.get("migrar_token_ts", 0)
    if time.time() - start_ts > 120:
        await safe_reply_html(
            update.message,
            "⏱️ <b>Operación Expirada:</b> Ha transcurrido el tiempo límite de 2 minutos. La migración ha sido cancelada."
        )
        return ConversationHandler.END

    new_token = update.message.text.strip() if (update.message and update.message.text) else ""

    if new_token.lower() in ("/cancelar", "cancelar", "/cancel", "abortar"):
        await safe_reply_html(update.message, "🛑 <b>Migración cancelada:</b> Se mantiene el token actual sin modificaciones.")
        return ConversationHandler.END

    status_msg = await update.message.reply_text("🔄 Validando nuevo token contra la API oficial de Telegram...")

    ok, msg = migrate_core_token(new_token)

    if ok:
        try:
            await status_msg.edit_text(
                f"{msg}\n\n"
                "🚀 <b>Reiniciando servicio en 3 segundos para aplicar la nueva identidad...</b>\n"
                "<i>El bot reanudará la atención automáticamente bajo el nuevo token.</i>",
                parse_mode='HTML'
            )
        except Exception:
            await safe_reply_html(
                update.message,
                f"{msg}\n\n🚀 <b>Reiniciando servicio para aplicar la nueva identidad...</b>"
            )

        # Disparar reinicio asíncrono desacoplado
        async def _restart_service():
            await asyncio.sleep(2.5)
            os.system("sudo systemctl restart tg-admin-bot.service &")

        asyncio.create_task(_restart_service())
        return ConversationHandler.END
    else:
        try:
            await status_msg.edit_text(
                f"❌ <b>Fallo en la Validación:</b>\n\n{html.escape(msg)}\n\n"
                "<i>La operación fue abortada. El token original sigue activo y protegido.</i>",
                parse_mode='HTML'
            )
        except Exception:
            await safe_reply_html(
                update.message,
                f"❌ <b>Fallo en la Validación:</b>\n\n{html.escape(msg)}\n\n"
                "<i>La operación fue abortada. El token original sigue activo y protegido.</i>"
            )
        return ConversationHandler.END


async def cancel_migrar_token(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    """Cancela el flujo de migración de token."""
    if update.message:
        await safe_reply_html(update.message, "🛑 <b>Operación cancelada.</b>")
    return ConversationHandler.END


async def cmd_activar_manual(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Permite al Owner activar manualmente el anclaje de hardware usando /activar <SERIAL> (Exclusivo en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or update.effective_user.id != IMMUTABLE_OWNER_ID:
        return

    serial_arg = ""
    if context.args:
        serial_arg = context.args[0].strip().upper()
    elif update.message and update.message.text:
        match = re.search(r'(AUTH-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})', update.message.text, re.IGNORECASE)
        if match:
            serial_arg = match.group(1).upper()

    if not serial_arg:
        await safe_reply_html(
            update.message,
            "ℹ️ <b>Uso del comando:</b> <code>/activar AUTH-XXXX-XXXX-XXXX-XXXX</code>"
        )
        return

    ok, msg = activate_hardware_first_boot(serial_arg)
    if ok:
        await safe_reply_html(
            update.message,
            f"<b>{msg}</b>\n\n🎉 <b>¡Bienvenido!</b> El sistema ha completado el anclaje físico de hardware y se encuentra ahora 100% <b>OPERACIONAL</b>."
        )
    else:
        await safe_reply_html(
            update.message,
            f"❌ <b>Error de Activación:</b>\n\n<code>{html.escape(msg)}</code>\n\n"
            "<i>Verifique el Serial recibido en la alerta de emergencia e intente nuevamente dentro de la ventana de 10 minutos.</i>"
        )


# =========================================================================
# 🚨 PANEL DE CONTROL DE EMERGENCIA Y CONTINGENCIA (/emergencia)
# =========================================================================

def _build_emergency_panel() -> Tuple[str, InlineKeyboardMarkup]:
    """Construye el texto y botones interactivos para el panel de control de emergencia."""
    is_maint = bool(CONFIG.get("maintenance_mode", False))
    maint_label = "🔴 ACTIVADO (Usuarios y grupos bloqueados)" if is_maint else "🟢 DESACTIVADO (Operación normal)"

    text = (
        "🚨 <b>PANEL DE CONTROL DE EMERGENCIA (EXCLUSIVO OWNER)</b>\n\n"
        "Este panel permite ejecutar acciones de contingencia inmediata sobre el servicio del bot y su configuración:\n\n"
        "• <b>Estado del Servicio:</b> <code>ACTIVO (Running)</code>\n"
        f"• <b>Modo Mantenimiento:</b> <code>{maint_label}</code>\n"
        "• <b>Respaldo de Configuración:</b> <code>Disponible</code>\n\n"
        "<i>Seleccione una acción de emergencia a continuación:</i>"
    )

    keyboard = [
        [
            InlineKeyboardButton("🛑 Detener Servicio del Bot", callback_data="emergencia:stop_prompt"),
        ],
        [
            InlineKeyboardButton("▶️ Reanudar Normalidad" if is_maint else "⏸️ Activar Mantenimiento",
                                 callback_data="emergencia:toggle_maint"),
            InlineKeyboardButton("🔄 Restaurar Configuración", callback_data="emergencia:restore_prompt"),
        ],
        [
            InlineKeyboardButton("❌ Cerrar Panel", callback_data="emergencia:close")
        ]
    ]
    return text, InlineKeyboardMarkup(keyboard)


async def _delayed_emergency_stop() -> None:
    """Espera 1.5 segundos para permitir el envío del mensaje de confirmación y detiene el servicio."""
    await asyncio.sleep(1.5)
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "systemctl", "stop", "tg-admin-bot.service"
        )
        await proc.communicate()
    except Exception as e:
        logger.error(f"Error al detener servicio por emergencia: {e}")


async def cmd_emergencia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el panel interactivo de emergencia exclusivo para el Owner en chat privado."""
    if not await require_private_chat(update, context):
        return

    if not await check_authorization(update, context):
        return

    user = update.effective_user

    if not user or user.id != IMMUTABLE_OWNER_ID:
        await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    text, reply_markup = _build_emergency_panel()
    await safe_reply_html(update.message, text, reply_markup=reply_markup)


async def cmd_reinicia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """
    Comando exclusivo para el Owner: Ejecuta el reinicio completo del sistema operativo (sudo reboot).
    El mensaje de confirmación de reinicio se envía ÚNICA Y EXCLUSIVAMENTE al chat privado del Owner.
    """
    user = update.effective_user
    if not user or user.id != IMMUTABLE_OWNER_ID:
        logger.warning(f"Intento NO AUTORIZADO de ejecutar /reinicia por usuario: {user.id if user else 'desconocido'}")
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            await safe_reply_html(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    logger.critical(f"🛑 INSTRUCCIÓN DE REINICIO DEL SERVIDOR (/reinicia) RECIBIDA POR EL OWNER (ID: {user.id})")

    # Si se ejecutó desde un grupo, borrar el comando del grupo para mayor discreción
    if update.message and update.effective_chat and update.effective_chat.type in ("group", "supergroup"):
        try:
            await update.message.delete()
        except Exception:
            pass

    # Enviar mensaje de confirmación ÚNICA Y EXCLUSIVAMENTE al chat del Owner
    msg_owner = (
        "🔄 <b>REINICIO DEL SERVIDOR EN PROGRESO</b>\n"
        "━━━━━━━━━━━━\n"
        "⚠️ <b>Instrucción recibida:</b> <code>/reinicia</code>\n\n"
        "💾 <i>Sincronizando buffers de disco (sync)...</i>\n"
        "⏳ <i>Ejecutando reinicio del sistema (<code>sudo reboot</code>) en 2 segundos...</i>\n"
        "━━━━━━━━━━━━\n"
        "👋 <i>El bot y todos los servicios se reactivarán automáticamente tras el arranque.</i>"
    )

    try:
        await context.bot.send_message(
            chat_id=IMMUTABLE_OWNER_ID,
            text=msg_owner,
            parse_mode='HTML'
        )
    except Exception as e_send:
        logger.error(f"Error enviando notificación de reinicio al Owner: {e_send}")
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            try:
                await safe_reply_html(update.message, msg_owner)
            except Exception:
                pass

    # Tarea asíncrona para permitir que el mensaje de Telegram se transmita antes del apagado
    async def _execute_system_reboot():
        await asyncio.sleep(2.0)
        try:
            subprocess.run(["sync"], check=False)
            subprocess.run(["sudo", "reboot"], check=False)
        except Exception as err:
            logger.error(f"Error al ejecutar sudo reboot: {err}")

    asyncio.create_task(_execute_system_reboot())


async def handle_emergency_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones interactivas del panel de emergencia del Owner."""
    query = update.callback_query
    if not query or not query.data:
        return

    clicker_id = query.from_user.id
    if clicker_id != IMMUTABLE_OWNER_ID:
        await query.answer("🛑 Acción bloqueada: No posee privilegios administrativos.", show_alert=True)
        return

    action = query.data.replace("emergencia:", "")

    if action == "close":
        await query.answer("Panel cerrado.")
        try:
            await query.message.delete()
        except Exception:
            try:
                await query.edit_message_reply_markup(reply_markup=None)
            except Exception:
                pass
        return

    elif action == "menu":
        await query.answer()
        text, reply_markup = _build_emergency_panel()
        try:
            await query.edit_message_text(text, parse_mode='HTML', reply_markup=reply_markup)
        except Exception:
            pass
        return

    elif action == "stop_prompt":
        await query.answer()
        prompt_text = (
            "⚠️ <b>CONFIRMACIÓN: DETENER SERVICIO</b>\n\n"
            "¿Está seguro de que desea detener el servicio <code>tg-admin-bot.service</code>?\n\n"
            "El bot dejará de responder inmediatamente a todos los usuarios y no ejecutará tareas programadas hasta que se inicie manualmente en el servidor mediante:\n"
            "<code>sudo systemctl start tg-admin-bot.service</code>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🛑 SÍ, DETENER SERVICIO AHORA", callback_data="emergencia:stop_confirm"),
            ],
            [
                InlineKeyboardButton("⬅️ Volver al Panel", callback_data="emergencia:menu")
            ]
        ]
        try:
            await query.edit_message_text(prompt_text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return

    elif action == "stop_confirm":
        await query.answer("🛑 Deteniendo servicio del bot...")
        try:
            await query.edit_message_text(
                "🛑 <b>Servicio Detenido por Emergencia</b>\n\n"
                "El bot ha recibido la orden de apagado del Administrador y se ha detenido correctamente.\n\n"
                "Para reactivarlo en sitio o vía SSH, ejecute en la consola:\n"
                "<code>sudo systemctl start tg-admin-bot.service</code>",
                parse_mode='HTML',
                reply_markup=None
            )
        except Exception:
            pass
        asyncio.create_task(_delayed_emergency_stop())
        return

    elif action == "toggle_maint":
        current_maint = bool(CONFIG.get("maintenance_mode", False))
        new_maint = not current_maint
        CONFIG["maintenance_mode"] = new_maint
        save_config()

        status_msg = "Mantenimiento ACTIVADO (usuarios y grupos bloqueados)" if new_maint else "Operación NORMAL restablecida"
        await query.answer(f"✅ {status_msg}")

        text, reply_markup = _build_emergency_panel()
        try:
            await query.edit_message_text(text, parse_mode='HTML', reply_markup=reply_markup)
        except Exception:
            pass
        return

    elif action == "restore_prompt":
        await query.answer()
        prompt_text = (
            "⚠️ <b>CONFIRMACIÓN: RESTAURAR CONFIGURACIÓN</b>\n\n"
            "¿Desea restaurar los archivos de configuración desde la copia de seguridad segura?\n\n"
            "Se restaurarán:\n"
            "• <code>config/config.json</code>\n"
            "• <code>config/bot.conf</code>\n"
            "• <code>config/monitoreo.conf</code>\n"
            "• <code>config/mensajes.conf</code>\n\n"
            "<i>Las credenciales protegidas y el anclaje DRM de hardware se mantendrán intactos.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔄 SÍ, RESTAURAR AHORA", callback_data="emergencia:restore_confirm"),
            ],
            [
                InlineKeyboardButton("⬅️ Volver al Panel", callback_data="emergencia:menu")
            ]
        ]
        try:
            await query.edit_message_text(prompt_text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return

    elif action == "restore_confirm":
        await query.answer("🔄 Restaurando configuración...")
        ok, msg = restore_golden_backup()
        if ok:
            res_text = (
                "✅ <b>Configuración Restaurada con Éxito</b>\n\n"
                f"{html.escape(msg)}\n\n"
                "Los archivos de configuración han sido restablecidos y recargados en memoria."
            )
        else:
            res_text = (
                "❌ <b>Error al Restaurar Configuración:</b>\n\n"
                f"<code>{html.escape(msg)}</code>"
            )
        keyboard = [
            [
                InlineKeyboardButton("⬅️ Volver al Panel", callback_data="emergencia:menu")
            ]
        ]
        try:
            await query.edit_message_text(res_text, parse_mode='HTML', reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return


async def bot_error_handler(update: object, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Registra y gestiona errores inesperados o fallos de red durante el polling."""
    global _CONSECUTIVE_NETWORK_ERRORS
    err = context.error
    if isinstance(err, (telegram.error.NetworkError, telegram.error.TimedOut, httpx.NetworkError, httpx.TimeoutException)):
        _CONSECUTIVE_NETWORK_ERRORS += 1
        logger.warning(f"Aviso de red en polling Telegram (fallo #{_CONSECUTIVE_NETWORK_ERRORS}): {err}")
        # Solo activar conmutación si hay fallos persistentes sostenidos (mínimo 8 fallos seguidos)
        if _CONSECUTIVE_NETWORK_ERRORS >= 8:
            async def _delayed_failover():
                await asyncio.sleep(15)
                if _CONSECUTIVE_NETWORK_ERRORS >= 8:
                    await trigger_connection_failover(reason=f"8 fallos consecutivos en polling ({err})")
            asyncio.create_task(_delayed_failover())
    else:
        logger.error(f"Excepción en bot handler: {err}", exc_info=err)


def main() -> None:
    global _BOT_APP_INSTANCE
    bot_token = IMMUTABLE_BOT_TOKEN

    if not bot_token:
        logger.error("Error: IMMUTABLE_BOT_TOKEN no configurado en el código fuente.")
        return

    # Selección y conmutación automática de conexión (Directa vs Proxies corporativos)
    active_proxy = None
    if CONFIG.get("auto_proxy_failover", True):
        active_proxy = select_working_connection(bot_token)

    # Configurar clientes HTTP con timeouts optimizados y proxy para API general y polling
    request = HTTPXRequest(
        connection_pool_size=256,
        connect_timeout=15.0,
        read_timeout=35.0,
        write_timeout=35.0,
        pool_timeout=10.0,
        proxy=active_proxy
    )
    get_updates_request = HTTPXRequest(
        connection_pool_size=1,
        connect_timeout=15.0,
        read_timeout=35.0,
        write_timeout=35.0,
        pool_timeout=10.0,
        proxy=active_proxy
    )

    async def on_post_init(app: Application) -> None:
        """Inicia tareas en segundo plano del bot (Watchdog de red, actualizaciones y auto-rollback)."""
        from monitor.system_updater import auto_update_worker
        
        # 1. Iniciar Centinela de Conmutación Dinámica en Caliente (Watchdog)
        if CONFIG.get("auto_proxy_failover", True):
            asyncio.create_task(runtime_connection_watchdog(app))

        # 2. Iniciar Actualizador Autónomo Git
        asyncio.create_task(
            auto_update_worker(
                bot_instance=app.bot,
                get_owner_id_func=lambda: CONFIG.get("owner_id", 0),
                get_config_func=lambda: CONFIG
            )
        )

        # 3. Verificador de Autorrecuperación (Auto-Rollback)
        async def _check_auto_rollback_notice():
            flag_file = Path("/tmp/.auto_rollback_occurred")
            if flag_file.exists():
                try:
                    with open(flag_file, "r", encoding="utf-8") as f:
                        info = json.load(f)
                    cp_id = info.get("checkpoint_id", "LATEST")
                    desc = info.get("description", "Recuperación automática de estado seguro")
                    dt = info.get("datetime", "Reciente")
                    owner_id = CONFIG.get("owner_id", 0)
                    if owner_id:
                        alert_msg = (
                            "⚠️ <b>Alerta de Autorrecuperación (Auto-Rollback)</b>\n"
                            "━━━━━━━━━━━━\n"
                            "El sistema detectó corrupción de archivos o apagón imprevisto durante el arranque.\n\n"
                            "🔄 <b>Acción:</b> <code>Rollback Automático Ejecutado</code>\n"
                            "✅ <b>Estado Actual:</b> <code>Sistema Restaurado y Operativo</code>\n"
                            f"📁 <b>Punto Restaurado:</b> <code>{html.escape(cp_id)}</code>\n"
                            f"📝 <b>Detalle:</b> <i>{html.escape(desc)}</i>\n"
                            f"⏰ <b>Fecha:</b> <code>{html.escape(dt)}</code>\n"
                            "━━━━━━━━━━━━\n"
                            "<i>El bot se encuentra en línea y completamente funcional.</i>"
                        )
                        await app.bot.send_message(chat_id=owner_id, text=alert_msg, parse_mode='HTML')
                    flag_file.unlink(missing_ok=True)
                except Exception as err:
                    logger.error(f"Error procesando notificación de auto-rollback: {err}")

        asyncio.create_task(_check_auto_rollback_notice())

    application = (
        Application.builder()
        .token(bot_token)
        .request(request)
        .get_updates_request(get_updates_request)
        .post_init(on_post_init)
        .build()
    )

    _BOT_APP_INSTANCE = application

    # Manejador global de errores de red y aplicación
    application.add_error_handler(bot_error_handler)

    reserved_commands = {
        "start",
        "help",
        "ayuda",
        "activar",
        "autorizar_hardware",
        "auth_hw",
        "reset_ia",
        "reset_chat",
        "borrar_chat",
        "permisos",
        "autorizados",
        "whitelist",
        "usuarios",
        "botstatus",
        "statusbot",
        "estado_bot",
        "estatus",
        "status",
        "estado",
        "debug_monitor",
        "monitordebug",
        "bloqueo_comandos",
        "bloquear_comandos",
        "lock_commands",
        "pausar_comandos",
        "control_comandos",
        "limpiador",
        "limpieza",
        "cleaner",
        "mensaje",
        "broadcast",
        "difusion",
        "anuncio",
        "comunicado",
        "reporte_servicios",
        "servicios",
        "reporte_sedes",
        "sedes",
        "sitios",
        "reporte_completo",
        "monitoreo",
        "analisis_red",
        "red",
        "escaner_red",
        "network_scan",
        "internet",
        "proxy",
        "proxies",
        "conectividad",
        "info",
        "aviso",
        "legal",
        "terminos",
        "migrar_token",
        "migrartoken",
        "cambiar_token",
        "actualizar",
        "update",
        "upgrade",
        "git_update",
        "check_update",
        "debug_servicios",
        "debugservicios",
        "servicios_debug",
        "debug_sedes",
        "debugsedes",
        "sedes_debug",
        "debug_completo",
        "debugcompleto",
        "debug_monitoreo",
        "monitoreo_debug",
        "emergencia",
        "panico",
        "contingencia",
        "web",
        "pantalla",
        "captura",
        "dashboard",
        "screenshot",
        "caidas",
        "incidentes",
        "fallas",
        "reporte_caidas",
        "reinicia",
        "reboot"
    }

    # Asegurar existencia de copia dorada de respaldo de configuración
    ensure_golden_backup()

    # Conversación exclusiva para que el Owner migre el Token con DRM de hardware
    migrar_token_handler = ConversationHandler(
        entry_points=[CommandHandler(["migrar_token", "migrartoken", "cambiar_token"], cmd_migrar_token_start)],
        states={
            MIGRAR_TOKEN_WAITING: [
                MessageHandler(filters.TEXT & ~filters.COMMAND, handle_new_token_input),
                CommandHandler("cancelar", cancel_migrar_token)
            ]
        },
        fallbacks=[CommandHandler("cancelar", cancel_migrar_token)],
        conversation_timeout=120
    )
    application.add_handler(migrar_token_handler)

    # Comandos base
    application.add_handler(CommandHandler(["start", "help", "ayuda"], start))

    # Comando para activación manual de hardware (Owner)
    application.add_handler(CommandHandler(["activar", "autorizar_hardware", "auth_hw"], cmd_activar_manual))

    # Comando de Panel de Control de Emergencia (Exclusivo Owner en privado)
    application.add_handler(CommandHandler(["emergencia", "panico", "contingencia"], cmd_emergencia))

    # Comando para información legal, privacidad y advertencia de seguridad
    application.add_handler(CommandHandler(["info", "aviso", "legal", "terminos"], cmd_info))

    # Comando para reiniciar conversación IA
    application.add_handler(CommandHandler(["reset_ia", "reset_chat", "borrar_chat"], reset_chat))

    # Comando exclusivo para que el Owner gestione permisos
    application.add_handler(CommandHandler(["permisos", "autorizados", "whitelist", "usuarios"], manage_permissions))

    # Comando exclusivo para que el Owner verifique estado de red, proxies y accesos
    application.add_handler(CommandHandler(["botstatus", "statusbot", "estado_bot", "estatus", "status", "estado"], bot_status))

    # Comando exclusivo para que el Owner controle el Modo Depuración del Monitor
    application.add_handler(CommandHandler(["debug_monitor", "monitordebug"], toggle_debug_monitor))

    # Comandos exclusivos para que el Owner ejecute directamente reportes en Modo Depuración
    application.add_handler(CommandHandler(["debug_servicios", "debugservicios", "servicios_debug"], cmd_debug_servicios))
    application.add_handler(CommandHandler(["debug_sedes", "debugsedes", "sedes_debug"], cmd_debug_sedes))
    application.add_handler(CommandHandler(["debug_completo", "debugcompleto", "debug_monitoreo", "monitoreo_debug"], cmd_debug_completo))

    # Comando exclusivo para que el Owner bloquee/desbloquee comandos al resto de usuarios y grupos
    application.add_handler(CommandHandler(["bloqueo_comandos", "bloquear_comandos", "lock_commands", "pausar_comandos", "control_comandos"], toggle_commands_lock))

    # Comando exclusivo para que el Owner gestione los envíos programados por Cron
    application.add_handler(CommandHandler(["cron", "envios", "reportes_programados", "programacion"], cmd_cron_control))

    # Comando exclusivo para que el Owner ejecute diagnóstico de almacenamiento y limpieza interactiva
    application.add_handler(CommandHandler(["limpiador", "limpieza", "cleaner"], cmd_limpiador))

    # Comando exclusivo para que el Owner verifique y aplique actualizaciones desde Git
    application.add_handler(CommandHandler(["actualizar", "update", "upgrade", "git_update", "check_update"], cmd_actualizar))

    # Comando exclusivo para que el Owner envíe comunicados masivos (Broadcast)
    application.add_handler(CommandHandler(["mensaje", "broadcast", "difusion", "anuncio", "comunicado"], cmd_broadcast_mensaje))

    # Comando exclusivo para que el Owner reinicie el servidor (sudo reboot)
    application.add_handler(CommandHandler(["reinicia", "reboot"], cmd_reinicia))

    # Comandos de ejecución de Monitoreo (Owner y grupos autorizados)
    application.add_handler(CommandHandler(["reporte_servicios", "servicios"], cmd_reporte_servicios))
    application.add_handler(CommandHandler(["reporte_sedes", "sedes", "sitios"], cmd_reporte_sedes))
    application.add_handler(CommandHandler(["reporte_caidas", "caidas", "incidentes", "fallas"], cmd_reporte_caidas))
    application.add_handler(CommandHandler(["web", "pantalla", "captura", "dashboard", "screenshot"], cmd_captura_web))
    application.add_handler(CommandHandler(["reporte_completo", "monitoreo"], cmd_reporte_completo))

    # Comando de Diagnóstico de Internet y Proxies (Owner y grupos autorizados)
    application.add_handler(CommandHandler(["internet", "proxy", "proxies", "conectividad"], cmd_internet))

    # Comando de Análisis de Red Local (Owner y grupos autorizados)
    application.add_handler(CommandHandler(["analisis_red", "red", "escaner_red", "network_scan"], cmd_analisis_red))

    # Callback query handler para panel de control de emergencia
    application.add_handler(CallbackQueryHandler(handle_emergency_callback, pattern=r"^emergencia:"))

    # Callback query handler para botones de autorización interactiva, revocación, debug toggle y bloqueo de comandos
    application.add_handler(CallbackQueryHandler(handle_auth_callback, pattern=r"^auth_(allow|deny|revoke_user|revoke_group|toggle_debug|toggle_cmd_lock):"))

    # Callback query handler para botones del limpiador del sistema
    application.add_handler(CallbackQueryHandler(handle_cleaner_callback, pattern=r"^cleaner_act:"))

    # Callback query handler para botones del actualizador de sistema
    application.add_handler(CallbackQueryHandler(handle_update_callback, pattern=r"^update_act:"))

    # Callback query handler para control de reportes programados por cron
    application.add_handler(CallbackQueryHandler(handle_cron_callback, pattern=r"^cron_act:"))

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

    application.run_polling(
        allowed_updates=Update.ALL_TYPES,
        bootstrap_retries=-1,
        poll_interval=1.0,
        timeout=10,
        drop_pending_updates=False
    )


if __name__ == "__main__":
    main()
