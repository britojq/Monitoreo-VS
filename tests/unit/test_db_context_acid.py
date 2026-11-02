"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS TRANSACCIONES ACID: test_db_context_acid.py
# Valida la atomicidad, rollback ante excepciones y concurrencia (pessimistic_row_lock).
# ==============================================================================
"""

from unittest.mock import MagicMock, patch
import pymysql
import pytest

from monitor.domain.exceptions import (
    DatabaseConcurrencyError,
    DatabaseConnectionException,
    DatabaseQueryException,
    DatabaseTransactionException,
)
from monitor.infrastructure.db_context import (
    atomic_cursor,
    atomic_inventory_update,
    atomic_transaction,
    db_connection,
    db_cursor,
    execute_query,
    execute_scalar,
    execute_statement,
    pessimistic_row_lock,
)


class TestDbContextACID:
    """Verificación de transacciones atómicas seguras y rollback automático."""

    def test_atomic_transaction_commits_on_success(self):
        """Verifica que atomic_transaction ejecute commit() al salir limpiamente del bloque with."""
        mock_conn = MagicMock(spec=pymysql.connections.Connection)

        with patch("monitor.infrastructure.db_context.db_connection") as mock_db_conn:
            mock_db_conn.return_value.__enter__.return_value = mock_conn
            mock_db_conn.return_value.__exit__.return_value = None

            with atomic_transaction() as conn:
                assert conn is mock_conn

            mock_conn.commit.assert_called_once()
            mock_conn.rollback.assert_not_called()

    def test_atomic_transaction_rolls_back_and_raises_transaction_exception_on_error(self):
        """Verifica que atomic_transaction ejecute rollback() incondicional si ocurre una excepción."""
        mock_conn = MagicMock(spec=pymysql.connections.Connection)

        with patch("monitor.infrastructure.db_context.db_connection") as mock_db_conn:
            mock_db_conn.return_value.__enter__.return_value = mock_conn
            mock_db_conn.return_value.__exit__.return_value = None

            with pytest.raises(DatabaseTransactionException) as excinfo:
                with atomic_transaction():
                    raise RuntimeError("Fallo de simulación en mitad de transacción")

            mock_conn.rollback.assert_called_once()
            assert "Transacción atómica fallida y revertida" in str(excinfo.value)

    def test_atomic_cursor_lifecycle(self):
        """Verifica que atomic_cursor provea un cursor gestionado con cierre automático."""
        mock_cur = MagicMock()
        mock_conn = MagicMock(spec=pymysql.connections.Connection)
        mock_conn.cursor.return_value = mock_cur

        with patch("monitor.infrastructure.db_context.atomic_transaction") as mock_atomic:
            mock_atomic.return_value.__enter__.return_value = mock_conn
            mock_atomic.return_value.__exit__.return_value = None

            with atomic_cursor() as cur:
                assert cur is mock_cur

            mock_cur.close.assert_called_once()


class TestPessimisticRowLock:
    """Verificación de bloqueos pesimistas (SELECT ... FOR UPDATE) y control de concurrencia."""

    def test_pessimistic_row_lock_acquires_lock_and_yields_row(self):
        """Verifica que pessimistic_row_lock adquiera SELECT FOR UPDATE y retorne la fila."""
        mock_cur = MagicMock()
        mock_cur.fetchone.return_value = {"id": 101, "name": "Servidor Core", "status": "ACTIVE"}

        mock_conn = MagicMock(spec=pymysql.connections.Connection)
        mock_conn.cursor.return_value.__enter__.return_value = mock_cur
        mock_conn.cursor.return_value.__exit__.return_value = None

        with patch("monitor.infrastructure.db_context.atomic_transaction") as mock_atomic:
            mock_atomic.return_value.__enter__.return_value = mock_conn
            mock_atomic.return_value.__exit__.return_value = None

            with pessimistic_row_lock("monitored_services", 101) as row:
                assert row["id"] == 101
                assert row["name"] == "Servidor Core"

            mock_cur.execute.assert_called_once_with(
                "SELECT * FROM `monitored_services` WHERE `id` = %s FOR UPDATE",
                (101,),
            )

    def test_pessimistic_row_lock_raises_concurrency_error_when_row_not_found(self):
        """Verifica que levante DatabaseConcurrencyError si el registro objetivo no existe."""
        mock_cur = MagicMock()
        mock_cur.fetchone.return_value = None

        mock_conn = MagicMock(spec=pymysql.connections.Connection)
        mock_conn.cursor.return_value.__enter__.return_value = mock_cur
        mock_conn.cursor.return_value.__exit__.return_value = None

        with patch("monitor.infrastructure.db_context.atomic_transaction") as mock_atomic:
            mock_atomic.return_value.__enter__.return_value = mock_conn
            mock_atomic.return_value.__exit__.return_value = None

            with pytest.raises(DatabaseConcurrencyError) as excinfo:
                with pessimistic_row_lock("monitored_services", 999):
                    pass

            assert "Registro no encontrado para bloqueo pesimista" in str(excinfo.value)

    def test_pessimistic_row_lock_rejects_sql_injection_in_table_name(self):
        """Verifica la validación estricta por regex previniendo inyección SQL en nombres de tabla/columna."""
        with pytest.raises(DatabaseConcurrencyError) as excinfo:
            with pessimistic_row_lock("sites; DROP TABLE users;--", 1):
                pass
        assert "Identificador de tabla inválido" in str(excinfo.value)

        with pytest.raises(DatabaseConcurrencyError) as excinfo:
            with pessimistic_row_lock("sites", 1, id_column="id; DELETE FROM sites;"):
                pass
        assert "Identificador de columna ID inválido" in str(excinfo.value)

    def test_atomic_inventory_update_sanitizes_columns_and_executes(self):
        """Verifica que atomic_inventory_update rechace nombres de columnas ilegales."""
        with pytest.raises(DatabaseConcurrencyError) as excinfo:
            atomic_inventory_update(1, {"columna_invalida; DROP TABLE users;--": "valor"})
        assert "Nombre de columna inválido" in str(excinfo.value)
