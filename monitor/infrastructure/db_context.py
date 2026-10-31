"""
# ==============================================================================
# 🗄️ CONTEXT MANAGER CENTRALIZADO DE BASE DE DATOS: db_context.py
# Arquitectura SOLID - Principio de Inversión de Dependencias y Responsabilidad Única.
# Administrador de contexto canónico para conexiones y transacciones MariaDB (SSOT).
# Previene fugas de sockets, asegura rollback automático y estandariza excepciones.
# Ubicación: /opt/sonar/monitor/infrastructure/db_context.py
# License: GNU Affero General Public License v3.0
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import logging
from contextlib import contextmanager
from typing import Any, Dict, Generator, List, Optional, Sequence, Union

import pymysql
import pymysql.cursors

from monitor.domain.exceptions import (
    DatabaseConnectionException,
    DatabaseQueryException,
)
from monitor.monitor_db import load_env_db_config

logger = logging.getLogger("monitor.infrastructure.db_context")


@contextmanager
def db_connection(
    cursorclass: Any = pymysql.cursors.DictCursor,
    autocommit: bool = True,
    connect_timeout: int = 5,
) -> Generator[pymysql.connections.Connection, None, None]:
    """
    Context manager que provee una conexión segura y gestionada a MariaDB.
    Garantiza el cierre del socket al finalizar el bloque with bajo cualquier circunstancia.
    
    Uso:
        with db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT 1")
    """
    conn: Optional[pymysql.connections.Connection] = None
    try:
        cfg = load_env_db_config()
        conn = pymysql.connect(
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
        logger.error(f"Fallo crítico al conectar con MariaDB (SSOT): {e}")
        raise DatabaseConnectionException(f"No se pudo conectar a MariaDB (SSOT): {e}") from e

    try:
        yield conn
        if not autocommit:
            conn.commit()
    except Exception as e:
        if not autocommit and conn is not None:
            try:
                conn.rollback()
            except Exception as rb_err:
                logger.warning(f"Error ejecutando rollback en MariaDB: {rb_err}")
        if not isinstance(e, (DatabaseConnectionException, DatabaseQueryException)):
            logger.error(f"Excepción en bloque de contexto MariaDB: {e}")
        raise
    finally:
        if conn is not None:
            try:
                conn.close()
            except Exception as close_err:
                logger.debug(f"Aviso al cerrar socket MariaDB: {close_err}")


@contextmanager
def db_cursor(
    cursorclass: Any = pymysql.cursors.DictCursor,
    autocommit: bool = True,
    connect_timeout: int = 5,
) -> Generator[pymysql.cursors.Cursor, None, None]:
    """
    Context manager que provee un cursor listo para usar sobre una conexión gestionada.
    Cierra automáticamente el cursor y la conexión asociada al salir del contexto.
    
    Uso:
        with db_cursor() as cur:
            cur.execute("SELECT * FROM sites WHERE status = %s", ("UP",))
            sites = cur.fetchall()
    """
    with db_connection(
        cursorclass=cursorclass,
        autocommit=autocommit,
        connect_timeout=connect_timeout,
    ) as conn:
        cur = conn.cursor()
        try:
            yield cur
        except pymysql.MySQLError as e:
            logger.error(f"Error de sintaxis o ejecución SQL en MariaDB: {e}")
            raise DatabaseQueryException(f"Error en consulta MariaDB: {e}") from e
        finally:
            try:
                cur.close()
            except Exception:
                pass


def execute_query(
    sql: str,
    params: Optional[Union[Sequence[Any], Dict[str, Any]]] = None,
    cursorclass: Any = pymysql.cursors.DictCursor,
) -> List[Dict[str, Any]]:
    """
    Ejecuta una consulta SELECT y retorna todas las filas resultantes como lista de diccionarios.
    Asegura liberación inmediata del cursor y conexión.
    """
    with db_cursor(cursorclass=cursorclass) as cur:
        cur.execute(sql, params)
        rows = cur.fetchall()
        return list(rows) if rows else []


def execute_scalar(
    sql: str,
    params: Optional[Union[Sequence[Any], Dict[str, Any]]] = None,
) -> Any:
    """
    Ejecuta una consulta y retorna el valor escalar de la primera columna de la primera fila.
    """
    with db_cursor(cursorclass=pymysql.cursors.Cursor) as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
        return row[0] if row else None


def execute_statement(
    sql: str,
    params: Optional[Union[Sequence[Any], Dict[str, Any]]] = None,
    autocommit: bool = True,
) -> int:
    """
    Ejecuta una sentencia DML (INSERT, UPDATE, DELETE) y retorna el número de filas afectadas.
    """
    with db_cursor(autocommit=autocommit) as cur:
        affected = cur.execute(sql, params)
        return int(affected)


def test_db_connectivity() -> bool:
    """
    Comprueba de forma rápida si MariaDB (SSOT) responde a una consulta atómica.
    """
    try:
        val = execute_scalar("SELECT 1")
        return bool(val == 1)
    except Exception as e:
        logger.error(f"Fallo de conectividad comprobando MariaDB: {e}")
        return False
