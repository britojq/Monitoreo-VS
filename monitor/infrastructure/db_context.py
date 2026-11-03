"""
# ==============================================================================
# 🗄️ CONTEXT MANAGER CENTRALIZADO DE BASE DE DATOS: db_context.py
# Arquitectura SOLID - Principio de Inversión de Dependencias y Responsabilidad Única.
# Administrador de contexto canónico para conexiones y transacciones MariaDB (SSOT).
# Previene fugas de sockets, asegura rollback automático y estandariza excepciones.
# Ubicación: /opt/sonar/monitor/infrastructure/db_context.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import logging
import re
from contextlib import contextmanager
from typing import Any, Dict, Generator, List, Optional, Sequence, Union

import pymysql
import pymysql.cursors

from monitor.domain.exceptions import (
    DatabaseConcurrencyError,
    DatabaseConnectionException,
    DatabaseQueryException,
    DatabaseTransactionException,
)
from monitor.infrastructure.monitor_db import load_env_db_config

logger = logging.getLogger("monitor.infrastructure.db_context")


@contextmanager
def db_connection(
    cursorclass: Any = pymysql.cursors.DictCursor,
    autocommit: bool = True,
    connect_timeout: int = 5,
) -> Generator[pymysql.connections.Connection, None, None]:
    """Context manager que provee una conexión segura y gestionada a MariaDB.

    Garantiza el cierre del socket al finalizar el bloque with bajo cualquier circunstancia.

    Args:
        cursorclass: Clase de cursor a utilizar (por defecto DictCursor).
        autocommit: Booleano que define si las consultas confirman inmediatamente.
        connect_timeout: Tiempo de espera en segundos para la conexión TCP.

    Yields:
        pymysql.connections.Connection: Objeto de conexión gestionada a MariaDB.

    Raises:
        DatabaseConnectionException: Si no es posible conectar a MariaDB (SSOT).
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
    """Context manager que provee un cursor listo para usar sobre una conexión gestionada.

    Cierra automáticamente el cursor y la conexión asociada al salir del contexto.

    Args:
        cursorclass: Clase de cursor a instanciar (por defecto DictCursor).
        autocommit: Booleano que define confirmación automática de transacciones.
        connect_timeout: Tiempo de espera en segundos para la conexión TCP.

    Yields:
        pymysql.cursors.Cursor: Cursor listo para ejecutar sentencias SQL.

    Raises:
        DatabaseConnectionException: Si la conexión a la base de datos falla.
        DatabaseQueryException: Si ocurre un error de sintaxis o ejecución SQL.
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


@contextmanager
def atomic_transaction(
    cursorclass: Any = pymysql.cursors.DictCursor,
    connect_timeout: int = 5,
) -> Generator[pymysql.connections.Connection, None, None]:
    """Context manager para transacciones atómicas seguras en MariaDB (ACID).

    Garantiza autocommit=False, commit explícito al completar con éxito el bloque,
    y rollback incondicional ante cualquier excepción, encapsulando fallos en
    DatabaseTransactionException.

    Args:
        cursorclass: Clase de cursor a instanciar en las conexiones de la transacción.
        connect_timeout: Tiempo de espera en segundos para la conexión TCP.

    Yields:
        pymysql.connections.Connection: Conexión con transacción en curso.

    Raises:
        DatabaseConcurrencyError: Si ocurre un conflicto de bloqueo concurrente.
        DatabaseTransactionException: Si la transacción atómica falla y es revertida.
    """
    with db_connection(
        cursorclass=cursorclass,
        autocommit=False,
        connect_timeout=connect_timeout,
    ) as conn:
        try:
            yield conn
            conn.commit()
        except Exception as e:
            try:
                conn.rollback()
            except Exception as rb_err:
                logger.warning(f"Error secundario en rollback MariaDB: {rb_err}")
            logger.error(f"Fallo en transacción atómica MariaDB (rollback ejecutado): {e}")
            if isinstance(e, DatabaseConcurrencyError):
                raise
            raise DatabaseTransactionException(f"Transacción atómica fallida y revertida: {e}") from e


@contextmanager
def atomic_cursor(
    cursorclass: Any = pymysql.cursors.DictCursor,
    connect_timeout: int = 5,
) -> Generator[pymysql.cursors.Cursor, None, None]:
    """Context manager transaccional de alto nivel que entrega un cursor directamente.

    Bajo una transacción atómica segura, ejecuta commit al salir del bloque with
    o rollback si ocurre un error.

    Args:
        cursorclass: Clase de cursor a instanciar (por defecto DictCursor).
        connect_timeout: Tiempo de espera en segundos para la conexión TCP.

    Yields:
        pymysql.cursors.Cursor: Cursor transaccional para ejecutar sentencias.

    Raises:
        DatabaseConcurrencyError: Si ocurre un conflicto de concurrencia.
        DatabaseTransactionException: Si la transacción atómica falla y es revertida.
    """
    with atomic_transaction(cursorclass=cursorclass, connect_timeout=connect_timeout) as conn:
        cur = conn.cursor()
        try:
            yield cur
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
    """Ejecuta una consulta SELECT y retorna todas las filas resultantes como lista de diccionarios.

    Asegura liberación inmediata del cursor y conexión.

    Args:
        sql: Sentencia SQL SELECT parametrizada.
        params: Parámetros posicionales (tupla/lista) o nombrados (dict) para la consulta.
        cursorclass: Clase de cursor (DictCursor por defecto).

    Returns:
        List[Dict[str, Any]]: Lista de filas obtenidas como diccionarios asociativos.

    Raises:
        DatabaseConnectionException: Si no se puede conectar a MariaDB.
        DatabaseQueryException: Si la sentencia SQL contiene errores o falla.
    """
    with db_cursor(cursorclass=cursorclass) as cur:
        cur.execute(sql, params)
        rows = cur.fetchall()
        return list(rows) if rows else []


def execute_scalar(
    sql: str,
    params: Optional[Union[Sequence[Any], Dict[str, Any]]] = None,
) -> Any:
    """Ejecuta una consulta y retorna el valor escalar de la primera columna de la primera fila.

    Args:
        sql: Sentencia SQL a ejecutar.
        params: Parámetros opcionales para la sentencia.

    Returns:
        Any: Valor de la celda única obtenida, o None si no hay registros coincidentes.

    Raises:
        DatabaseConnectionException: Si la conexión a MariaDB falla.
        DatabaseQueryException: Si la sentencia SQL falla durante su ejecución.
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
    """Ejecuta una sentencia DML (INSERT, UPDATE, DELETE) y retorna el número de filas afectadas.

    Args:
        sql: Sentencia SQL DML parametrizada.
        params: Parámetros para la sentencia.
        autocommit: Booleano para confirmar inmediatamente los cambios.

    Returns:
        int: Cantidad de filas afectadas por la operación.

    Raises:
        DatabaseConnectionException: Si la conexión a MariaDB no pudo establecerse.
        DatabaseQueryException: Si la sentencia falla.
    """
    with db_cursor(autocommit=autocommit) as cur:
        affected = cur.execute(sql, params)
        return int(affected)


def test_db_connectivity() -> bool:
    """Comprueba de forma rápida si MariaDB (SSOT) responde a una consulta atómica.

    Returns:
        bool: True si la base de datos responde exitosamente a SELECT 1; False en caso contrario.
    """
    try:
        val = execute_scalar("SELECT 1")
        return bool(val == 1)
    except Exception as e:
        logger.error(f"Fallo de conectividad comprobando MariaDB: {e}")
        return False


@contextmanager
def pessimistic_row_lock(
    table: str,
    row_id: Union[int, str],
    id_column: str = "id",
    connect_timeout: int = 5,
) -> Generator[Dict[str, Any], None, None]:
    """
    Context manager transaccional que adquiere un bloqueo pesimista exclusivo (SELECT ... FOR UPDATE)
    sobre una fila específica de MariaDB (InnoDB). Garantiza aislamiento ACID y previene
    condiciones de carrera durante lecturas-modificaciones concurrentes.

    Uso:
        with pessimistic_row_lock("inventory_assets", asset_id) as row:
            if row["status"] != "DECOMMISSIONED":
                cur.execute("UPDATE inventory_assets SET status = %s WHERE id = %s", ("IN_MAINTENANCE", asset_id))

    Raises:
        DatabaseConcurrencyError: Si la tabla/columna es inválida, el registro no existe o el bloqueo expira.
        DatabaseTransactionException: Si la transacción falla y es revertida.
    """
    if not re.match(r"^[a-zA-Z0-9_]+$", table):
        raise DatabaseConcurrencyError(f"Identificador de tabla inválido para bloqueo pesimista: {table}")
    if not re.match(r"^[a-zA-Z0-9_]+$", id_column):
        raise DatabaseConcurrencyError(f"Identificador de columna ID inválido para bloqueo pesimista: {id_column}")

    with atomic_transaction(connect_timeout=connect_timeout) as conn:
        with conn.cursor() as cur:
            sql = f"SELECT * FROM `{table}` WHERE `{id_column}` = %s FOR UPDATE"
            try:
                cur.execute(sql, (row_id,))
                row = cur.fetchone()
            except pymysql.MySQLError as err:
                logger.error(f"Error adquiriendo bloqueo pesimista sobre {table} (ID {row_id}): {err}")
                raise DatabaseConcurrencyError(f"Fallo de concurrencia al bloquear {table}:{row_id}: {err}") from err

            if not row:
                raise DatabaseConcurrencyError(f"Registro no encontrado para bloqueo pesimista en {table} con {id_column}={row_id}")

            yield dict(row)


def atomic_inventory_update(
    asset_id: int,
    updates: Dict[str, Any],
    table: str = "inventory_assets",
) -> bool:
    """
    Ejecuta una actualización atómica y aislada sobre un activo de inventario utilizando
    bloqueo pesimista (SELECT ... FOR UPDATE) y rollback automático ante cualquier fallo.

    Args:
        asset_id: Identificador único del activo.
        updates: Diccionario de columnas y nuevos valores a actualizar.
        table: Nombre de la tabla de inventario (por defecto 'inventory_assets').

    Returns:
        bool: True si la actualización fue confirmada exitosamente.

    Raises:
        DatabaseConcurrencyError: Si el identificador es inválido o el activo no existe.
        DatabaseTransactionException: Si la transacción es revertida por fallo de integridad.
    """
    if not re.match(r"^[a-zA-Z0-9_]+$", table):
        raise DatabaseConcurrencyError(f"Tabla de inventario inválida: {table}")
    if not updates:
        return True

    for col in updates.keys():
        if not re.match(r"^[a-zA-Z0-9_]+$", col):
            raise DatabaseConcurrencyError(f"Nombre de columna inválido para actualización de inventario: {col}")

    with atomic_transaction() as conn:
        with conn.cursor() as cur:
            # 1. Bloqueo pesimista exclusivo
            lock_sql = f"SELECT id FROM `{table}` WHERE id = %s FOR UPDATE"
            cur.execute(lock_sql, (asset_id,))
            locked_row = cur.fetchone()
            if not locked_row:
                raise DatabaseConcurrencyError(f"No existe activo con ID {asset_id} en {table} para actualización atómica")

            # 2. Sentencia DML atómica
            set_clauses = [f"`{col}` = %s" for col in updates.keys()]
            update_sql = f"UPDATE `{table}` SET {', '.join(set_clauses)} WHERE id = %s"
            params = list(updates.values()) + [asset_id]
            cur.execute(update_sql, params)

    logger.info(f"Actualización atómica de inventario confirmada para ID {asset_id} en {table}")
    return True
