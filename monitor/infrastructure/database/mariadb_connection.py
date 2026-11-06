"""
# ==============================================================================
# 🗄️ CONEXIÓN INFRAESTRUCTURA MARIADB: mariadb_connection.py
# Implementación de conexión a MariaDB (SSOT) cumpliendo Clean Architecture.
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Any, Dict, Optional
import pymysql
import pymysql.cursors

from monitor.infrastructure.monitor_db import load_env_db_config
from monitor.domain.exceptions.domain_exceptions import DatabaseConnectionException

logger = logging.getLogger("infrastructure.database")


class MariaDBConnectionPool:
    """Proveedor centralizado de conexiones MariaDB con manejo robusto de excepciones."""

    @staticmethod
    def get_connection(
        cursorclass: Any = pymysql.cursors.DictCursor,
        autocommit: bool = True,
        connect_timeout: int = 5,
    ) -> pymysql.Connection:
        """Obtiene una conexión viva a MariaDB o lanza DatabaseConnectionException."""
        try:
            cfg = load_env_db_config()
            return pymysql.connect(
                host=cfg["host"],
                port=cfg["port"],
                user=cfg["user"],
                password=cfg["password"],
                database=cfg["database"],
                charset=cfg.get("charset", "utf8mb4"),
                cursorclass=cursorclass,
                autocommit=autocommit,
                connect_timeout=connect_timeout,
            )
        except Exception as e:
            logger.error(f"Error de conexión a MariaDB: {e}")
            raise DatabaseConnectionException(f"No se pudo conectar a MariaDB (SSOT): {e}") from e

    @staticmethod
    def get_connection_context(**kwargs: Any):
        """Retorna el context manager canónico db_connection."""
        from monitor.infrastructure.db_context import db_connection
        return db_connection(**kwargs)

    @staticmethod
    def get_cursor_context(**kwargs: Any):
        """Retorna el context manager canónico db_cursor."""
        from monitor.infrastructure.db_context import db_cursor
        return db_cursor(**kwargs)

