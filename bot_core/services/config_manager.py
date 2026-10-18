"""
# ==============================================================================
# ⚙️ SERVICIO DE CONFIGURACIÓN Y PERSISTENCIA: config_manager.py
# Gestión segura de parámetros de ejecución, políticas doradas y respaldo inmutable.
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

import json
import logging
import os
import shutil
import subprocess
from pathlib import Path
from typing import Any, Dict, Tuple

from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    IMMUTABLE_BOT_TOKEN,
    IMMUTABLE_AUTO_UPDATE_ENABLED,
    IMMUTABLE_GIT_REPO_URL,
    IMMUTABLE_GIT_BRANCH,
)

logger = logging.getLogger("bot.services.config")

BASE_DIR = Path(__file__).resolve().parent.parent.parent
CONFIG_DIR = BASE_DIR / "config"
CONFIG_PATH = CONFIG_DIR / "config.json"
GOLDEN_BACKUP_DIR = CONFIG_DIR / ".backup_golden"
AUDIT_DIR = BASE_DIR / "audit"
AI_DIR = BASE_DIR / "ai"
DOCS_DIR = BASE_DIR / "docs"
SYSTEM_PROMPT_PATH = AI_DIR / "system_prompt.txt"


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


def load_config() -> Dict[str, Any]:
    """Carga los parámetros del bot desde config/config.json o variables de entorno blindando las políticas inmutables."""
    default_config: Dict[str, Any] = {
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

        # Motor local de IA
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
        default_config["commands_enabled"] = os.getenv("COMMANDS_ENABLED", "").strip().lower() in ("true", "1", "yes")

    # FORZAR BLINDAJE INMUTABLE: Estas variables jamás pueden ser alteradas por config.json ni variables de entorno
    default_config["owner_id"] = IMMUTABLE_OWNER_ID
    default_config["bot_token"] = IMMUTABLE_BOT_TOKEN
    default_config["auto_update_enabled"] = IMMUTABLE_AUTO_UPDATE_ENABLED
    default_config["git_repo_url"] = IMMUTABLE_GIT_REPO_URL
    default_config["git_branch"] = IMMUTABLE_GIT_BRANCH

    return default_config


def save_config(config_dict: Dict[str, Any] | None = None) -> bool:
    """Guarda la configuración actual en config/config.json asegurando persistencia de cambios y blindaje de inmutables."""
    import sys
    target = config_dict
    if target is None:
        bot_mod = sys.modules.get("bot")
        if bot_mod and hasattr(bot_mod, "CONFIG"):
            target = getattr(bot_mod, "CONFIG")
        else:
            target = load_config()

    try:
        # En disco o archivo plano jamás se escribe el token en texto plano (Regla de Oro #13)
        disk_target = dict(target)
        disk_target["owner_id"] = IMMUTABLE_OWNER_ID
        disk_target["bot_token"] = "[ENCRYPTED_HARDWARE_VAULT]"
        disk_target["auto_update_enabled"] = IMMUTABLE_AUTO_UPDATE_ENABLED
        disk_target["git_repo_url"] = IMMUTABLE_GIT_REPO_URL
        disk_target["git_branch"] = IMMUTABLE_GIT_BRANCH

        with open(CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(disk_target, f, indent=2, ensure_ascii=False)
        logger.info(f"Configuración guardada exitosamente en {CONFIG_PATH}")
        return True
    except Exception as e:
        logger.error(f"Error al guardar configuración en {CONFIG_PATH}: {e}")
        return False


def ensure_golden_backup() -> None:
    """Crea una copia de respaldo segura y dorada de config.json si no existe."""
    try:
        GOLDEN_BACKUP_DIR.mkdir(parents=True, exist_ok=True)
        for fname in ("config.json",):
            src = CONFIG_DIR / fname
            dst = GOLDEN_BACKUP_DIR / fname
            if src.exists() and (not dst.exists() or dst.stat().st_size == 0):
                shutil.copy2(src, dst)
    except Exception as e:
        logger.warning(f"No se pudo crear el backup dorado de configuración: {e}")


def restore_golden_backup() -> Tuple[bool, str]:
    """Restaura los archivos de configuración desde la copia dorada o desde el commit HEAD de Git."""
    import sys
    restored_files = []
    try:
        if GOLDEN_BACKUP_DIR.exists():
            for fname in ("config.json",):
                src = GOLDEN_BACKUP_DIR / fname
                dst = CONFIG_DIR / fname
                if src.exists():
                    shutil.copy2(src, dst)
                    restored_files.append(fname)
        if not restored_files:
            res = subprocess.run(["git", "checkout", "HEAD", "--", "config/"], cwd=str(BASE_DIR), capture_output=True, text=True)
            if res.returncode == 0:
                restored_files.append("todos (vía Git HEAD)")

        new_cfg = load_config()
        bot_mod = sys.modules.get("bot")
        if bot_mod and hasattr(bot_mod, "CONFIG"):
            bot_mod.CONFIG.clear()
            bot_mod.CONFIG.update(new_cfg)

        return True, f"Archivos restaurados: {', '.join(restored_files) if restored_files else 'config/'}"
    except Exception as e:
        logger.error(f"Error restaurando configuración dorada: {e}")
        return False, str(e)
