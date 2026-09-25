"""
# ==============================================================================
# 🗄️ CONECTOR CENTRALIZADO DE BASE DE DATOS: monitor_db.py (@IA_ValleSeco_bot)
# Punto único de conexión a MariaDB (SSOT) para demonios de monitoreo y bot.
# Carga dinámicamente las credenciales desde los entornos .env sin credenciales en código.
# Ubicación: /scripts/telegram-admin-bot/monitor/monitor_db.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
import sys
from pathlib import Path
from typing import Any, Dict, Optional

import pymysql
import pymysql.cursors

logger = logging.getLogger("monitor.db")

BASE_DIR = Path(__file__).resolve().parent.parent

# Cache en memoria de configuración
_DB_CONFIG_CACHE: Optional[Dict[str, Any]] = None


def load_env_db_config(force_reload: bool = False) -> Dict[str, Any]:
    """
    Carga y valida la configuración de base de datos desde los archivos .env del sistema.
    Prioridad:
    1. /var/www/monitoreo/.env (entorno de producción / webroot activo)
    2. web_portal/.env (entorno de desarrollo local)
    3. .env en la raíz del proyecto
    4. Variables de entorno del proceso
    """
    global _DB_CONFIG_CACHE
    if _DB_CONFIG_CACHE is not None and not force_reload:
        return dict(_DB_CONFIG_CACHE)

    env_paths = [
        Path("/var/www/monitoreo/.env"),
        BASE_DIR / "web_portal" / ".env",
        BASE_DIR / ".env",
    ]

    loaded_vars: Dict[str, str] = {}
    for p in env_paths:
        if p.exists():
            try:
                for line in p.read_text(encoding="utf-8", errors="ignore").splitlines():
                    line = line.strip()
                    if line and not line.startswith("#") and "=" in line:
                        k, v = line.split("=", 1)
                        k = k.strip()
                        v = v.strip().strip('"').strip("'")
                        if k not in loaded_vars:
                            loaded_vars[k] = v
                if "DB_PASSWORD" in loaded_vars and loaded_vars["DB_PASSWORD"]:
                    break
            except Exception as e:
                logger.debug(f"No se pudo leer {p}: {e}")

    # Fallback a variables de entorno del sistema operativo si no estaban en archivos
    db_host = loaded_vars.get("DB_HOST") or os.getenv("DB_HOST", "127.0.0.1")
    db_port = int(loaded_vars.get("DB_PORT") or os.getenv("DB_PORT", "3306"))
    db_database = loaded_vars.get("DB_DATABASE") or os.getenv("DB_DATABASE", "monitoreo_vs")
    db_username = loaded_vars.get("DB_USERNAME") or os.getenv("DB_USERNAME", "monitoreo_user")
    db_password = loaded_vars.get("DB_PASSWORD") or os.getenv("DB_PASSWORD", "")

    if not db_password:
        raise RuntimeError(
            "CRÍTICO: 'DB_PASSWORD' no está configurado en /var/www/monitoreo/.env ni en el entorno. "
            "Por seguridad, no se admiten contraseñas por defecto en código fuente."
        )

    config = {
        "host": db_host,
        "port": db_port,
        "database": db_database,
        "user": db_username,
        "username": db_username,
        "password": db_password,
        "charset": "utf8mb4",
        "autocommit": True,
        "connect_timeout": 5,
    }

    _DB_CONFIG_CACHE = dict(config)
    return config


def get_db_connection(
    cursorclass: Any = pymysql.cursors.DictCursor,
    autocommit: bool = True,
    connect_timeout: int = 5,
) -> pymysql.connections.Connection:
    """
    Obtiene una conexión limpia y validada a MariaDB.
    Las credenciales se resuelven dinámicamente sin fallbacks estáticos en código.
    """
    cfg = load_env_db_config()
    return pymysql.connect(
        host=cfg["host"],
        port=cfg["port"],
        user=cfg["user"],
        password=cfg["password"],
        database=cfg["database"],
        charset=cfg["charset"],
        cursorclass=cursorclass,
        autocommit=autocommit,
        connect_timeout=connect_timeout,
    )


def test_db_connection() -> bool:
    """Valida la conectividad a la base de datos MariaDB."""
    try:
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT 1 AS ok")
                row = cur.fetchone()
                return bool(row and row.get("ok") == 1)
        finally:
            conn.close()
    except Exception as e:
        logger.error(f"Fallo al verificar conexión con MariaDB: {e}")
        return False
