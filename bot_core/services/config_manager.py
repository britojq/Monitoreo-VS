"""
# ==============================================================================
# ⚙️ SERVICIO DE CONFIGURACIÓN Y PERSISTENCIA: config_manager.py
# Gestión segura de parámetros de ejecución, políticas doradas y respaldo inmutable.
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# SSOT: MariaDB (monitoreo_vs.bot_settings) - Cero persistencia en archivos planos.
# ==============================================================================
"""

from __future__ import annotations

import json
import logging
import os
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
    """
    Carga los parámetros del bot exclusivamente desde MariaDB (bot_settings y monitored_proxies),
    blindando incondicionalmente las políticas de hardware inmutables (SSOT - Regla de Oro #7).
    """
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
        "auto_proxy_failover": True,
        "proxies": [],
        "monitor_debug_mode": False,

        # Actualizaciones Git
        "auto_update_enabled": IMMUTABLE_AUTO_UPDATE_ENABLED,
        "auto_update_interval_hours": 48,
        "git_repo_url": IMMUTABLE_GIT_REPO_URL,
        "git_branch": IMMUTABLE_GIT_BRANCH,

        # Motor local de IA
        "ollama_enabled": False,
        "ollama_allow_all": False,
        "ollama_base_url": os.getenv("OLLAMA_BASE_URL", "http://localhost:11434"),
        "ollama_model": os.getenv("OLLAMA_MODEL", "qwen-empresa"),
        "ollama_timeout": 300,
        "ollama_temperature": 0.3,
        "ollama_num_ctx": 4096,
        "ollama_max_history": 12,
        "ollama_include_system_prompt": False,
        "ollama_system_prompt": "",
    }

    # SSOT: MariaDB (bot_settings) como Única Fuente de Verdad (Regla de Oro #7)
    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                # 1. Cargar parámetros generales de bot_settings
                cur.execute("SELECT setting_key, setting_value FROM bot_settings")
                db_settings = cur.fetchall()
                for row in db_settings:
                    k = row.get("setting_key")
                    v = row.get("setting_value")
                    if not k or v is None:
                        continue
                    if isinstance(v, str) and v.startswith(("[", "{")) and v.endswith(("]", "}")):
                        try:
                            default_config[k] = json.loads(v)
                            continue
                        except Exception:
                            pass
                    if v in ("1", "true", "True"):
                        default_config[k] = True
                    elif v in ("0", "false", "False"):
                        default_config[k] = False
                    elif isinstance(v, str) and v.isdigit():
                        default_config[k] = int(v)
                    else:
                        default_config[k] = v

                # 2. Cargar proxies activos desde monitored_proxies (SSOT)
                cur.execute(
                    "SELECT ip_port, auth_userpass FROM monitored_proxies WHERE is_active = 1 ORDER BY letter ASC"
                )
                active_proxies = cur.fetchall()
                proxies_list = []
                for p in active_proxies:
                    ip_port = p.get("ip_port", "")
                    userpass = p.get("auth_userpass", "")
                    if ip_port:
                        if userpass:
                            proxies_list.append(f"http://{userpass}@{ip_port}")
                        else:
                            proxies_list.append(f"http://{ip_port}")
                if proxies_list:
                    default_config["proxies"] = proxies_list
        finally:
            conn.close()
        logger.info("Configuración cargada exitosamente desde MariaDB bot_settings y monitored_proxies (SSOT)")
    except Exception as e_db:
        logger.error(f"Error al consultar MariaDB bot_settings (SSOT): {e_db}")

    # Overrides por variables de entorno
    if os.getenv("OLLAMA_BASE_URL"):
        default_config["ollama_base_url"] = os.getenv("OLLAMA_BASE_URL")

    if os.getenv("OLLAMA_MODEL"):
        default_config["ollama_model"] = os.getenv("OLLAMA_MODEL")

    if os.getenv("COMMANDS_ENABLED"):
        default_config["commands_enabled"] = os.getenv("COMMANDS_ENABLED", "").strip().lower() in ("true", "1", "yes")

    # FORZAR BLINDAJE INMUTABLE: Estas variables jamás pueden ser alteradas por agentes externos
    default_config["owner_id"] = IMMUTABLE_OWNER_ID
    default_config["bot_token"] = IMMUTABLE_BOT_TOKEN
    default_config["auto_update_enabled"] = IMMUTABLE_AUTO_UPDATE_ENABLED
    default_config["git_repo_url"] = IMMUTABLE_GIT_REPO_URL
    default_config["git_branch"] = IMMUTABLE_GIT_BRANCH

    return default_config


def save_config(config_dict: Dict[str, Any] | None = None) -> bool:
    """
    Persiste la configuración exclusivamente en MariaDB bot_settings (SSOT).
    Queda terminantemente prohibido escribir archivos planos en disco (Regla de Oro #7).
    """
    import sys
    target = config_dict
    if target is None:
        bot_mod = sys.modules.get("bot")
        if bot_mod and hasattr(bot_mod, "CONFIG"):
            target = getattr(bot_mod, "CONFIG")
        else:
            target = load_config()

    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                for k, v in target.items():
                    if k in ("bot_token", "proxies"):
                        # bot_token reside en Hardware-Bound Vault (Regla #13)
                        # proxies reside en monitored_proxies (SSOT)
                        continue
                    v_str = json.dumps(v, ensure_ascii=False) if isinstance(v, (list, dict)) else str(v)
                    cur.execute("""
                        INSERT INTO bot_settings (setting_key, setting_value, updated_at)
                        VALUES (%s, %s, NOW())
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    """, (k, v_str))
                conn.commit()
        finally:
            conn.close()
        logger.info("Configuración persistida exitosamente en MariaDB bot_settings (SSOT)")
        return True
    except Exception as e_db:
        logger.error(f"Error al persistir configuración en MariaDB bot_settings: {e_db}")
        return False


def ensure_golden_backup() -> None:
    """Compatibilidad de API: La persistencia y respaldos residen exclusivamente en MariaDB SSOT."""
    logger.debug("Persistencia de configuración gobernada exclusivamente por MariaDB SSOT (Regla de Oro #7)")


def restore_golden_backup() -> Tuple[bool, str]:
    """Restaura y sincroniza en memoria la configuración directamente desde MariaDB bot_settings (SSOT)."""
    import sys
    try:
        new_cfg = load_config()
        bot_mod = sys.modules.get("bot")
        if bot_mod and hasattr(bot_mod, "CONFIG"):
            bot_mod.CONFIG.clear()
            bot_mod.CONFIG.update(new_cfg)

        return True, "Configuración recargada y sincronizada directamente desde MariaDB bot_settings (SSOT)"
    except Exception as e:
        logger.error(f"Error restaurando configuración desde MariaDB SSOT: {e}")
        return False, str(e)
