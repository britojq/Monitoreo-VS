"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS SOLID: INFRAESTRUCTURA BASE, EXCEPCIONES Y DIP
# Arquitectura SOLID - Verificación de Context Manager MariaDB, Excepciones y DIP.
# Ubicación: /opt/sonar/tests/unit/test_infrastructure_base_solid.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import os
from unittest.mock import MagicMock, patch
import pytest
import pymysql

from monitor.domain.exceptions import (
    CheckExecutionException,
    ClusterSyncException,
    DatabaseConnectionException,
    DatabaseException,
    DatabaseQueryException,
    DecryptionException,
    DomainException,
    EntityNotFoundException,
    HardwareVaultException,
    InfrastructureException,
    InvalidConfigurationException,
    NetworkCaptureException,
    NotificationDispatchException,
    SnmpConnectionException,
    SnmpException,
    SnmpTimeoutException,
    SonarBaseException,
)
from monitor.infrastructure.db_context import (
    db_connection,
    db_cursor,
    execute_query,
    execute_scalar,
    execute_statement,
    test_db_connectivity as check_db_connectivity,
)
from bot_core.contracts.security_provider import (
    HardwareVaultSecurityProvider,
    SecurityProviderProtocol,
    get_default_security_provider,
    set_default_security_provider,
)
import bot


class TestDomainExceptions:
    """Valida la jerarquía tipada de excepciones de dominio e infraestructura."""

    def test_inheritance_hierarchy(self) -> None:
        """Verifica que todas las excepciones hereden correctamente de SonarBaseException."""
        assert issubclass(DomainException, SonarBaseException)
        assert issubclass(InfrastructureException, SonarBaseException)
        assert issubclass(DatabaseException, InfrastructureException)
        assert issubclass(DatabaseConnectionException, DatabaseException)
        assert issubclass(DatabaseConnectionException, DomainException)
        assert issubclass(DatabaseQueryException, DatabaseException)
        assert issubclass(DecryptionException, InfrastructureException)
        assert issubclass(SnmpException, InfrastructureException)
        assert issubclass(SnmpConnectionException, SnmpException)
        assert issubclass(SnmpTimeoutException, SnmpException)
        assert issubclass(ClusterSyncException, InfrastructureException)
        assert issubclass(NetworkCaptureException, InfrastructureException)
        assert issubclass(HardwareVaultException, InfrastructureException)
        assert issubclass(EntityNotFoundException, DomainException)
        assert issubclass(CheckExecutionException, DomainException)
        assert issubclass(InvalidConfigurationException, DomainException)
        assert issubclass(NotificationDispatchException, DomainException)

    def test_exception_instantiation_and_repr(self) -> None:
        """Verifica mensajes e instanciación de excepciones."""
        err = DatabaseConnectionException("Fallo de socket MariaDB")
        assert str(err) == "Fallo de socket MariaDB"
        assert "DatabaseConnectionException" in repr(err)
        assert isinstance(err, DomainException)
        assert isinstance(err, InfrastructureException)


class TestDbContext:
    """Valida el Context Manager canónico de base de datos MariaDB."""

    def test_db_connection_success(self) -> None:
        """Verifica que db_connection provea la conexión y la cierre al salir."""
        mock_conn = MagicMock()
        with patch("pymysql.connect", return_value=mock_conn) as mock_connect:
            with db_connection(autocommit=True) as conn:
                assert conn == mock_conn
            mock_connect.assert_called_once()
            mock_conn.close.assert_called_once()

    def test_db_connection_failure_raises_typed_exception(self) -> None:
        """Verifica que un fallo al conectar lance DatabaseConnectionException."""
        with patch("pymysql.connect", side_effect=Exception("Timeout simulado")):
            with pytest.raises(DatabaseConnectionException) as exc_info:
                with db_connection():
                    pass
            assert "No se pudo conectar a MariaDB (SSOT)" in str(exc_info.value)

    def test_db_connection_rollback_on_error_when_autocommit_false(self) -> None:
        """Verifica que se ejecute rollback cuando autocommit es False y ocurre un error."""
        mock_conn = MagicMock()
        with patch("pymysql.connect", return_value=mock_conn):
            with pytest.raises(RuntimeError):
                with db_connection(autocommit=False) as conn:
                    raise RuntimeError("Error transaccional en bloque")
            mock_conn.rollback.assert_called_once()
            mock_conn.close.assert_called_once()

    def test_db_cursor_context_lifecycle(self) -> None:
        """Verifica ciclo de vida del cursor y la conexión subyacente."""
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value = mock_cursor

        with patch("pymysql.connect", return_value=mock_conn):
            with db_cursor() as cur:
                assert cur == mock_cursor
                cur.execute("SELECT 1")

            mock_cursor.execute.assert_called_once_with("SELECT 1")
            mock_cursor.close.assert_called_once()
            mock_conn.close.assert_called_once()

    def test_db_cursor_sql_error_translated_to_query_exception(self) -> None:
        """Verifica que errores de pymysql se traduzcan a DatabaseQueryException."""
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.execute.side_effect = pymysql.MySQLError(1064, "Syntax error")
        mock_conn.cursor.return_value = mock_cursor

        with patch("pymysql.connect", return_value=mock_conn):
            with pytest.raises(DatabaseQueryException) as exc_info:
                with db_cursor() as cur:
                    cur.execute("SELECT BAD SYNTAX")
            assert "Error en consulta MariaDB" in str(exc_info.value)
            mock_cursor.close.assert_called_once()
            mock_conn.close.assert_called_once()

    def test_execute_query_helper(self) -> None:
        """Verifica que execute_query retorne lista de filas."""
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.fetchall.return_value = [{"id": 1, "name": "Valle Seco"}]
        mock_conn.cursor.return_value = mock_cursor

        with patch("pymysql.connect", return_value=mock_conn):
            result = execute_query("SELECT id, name FROM sites WHERE id = %s", (1,))
            assert result == [{"id": 1, "name": "Valle Seco"}]
            mock_cursor.execute.assert_called_once_with("SELECT id, name FROM sites WHERE id = %s", (1,))

    def test_execute_scalar_helper(self) -> None:
        """Verifica que execute_scalar retorne el primer campo."""
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.fetchone.return_value = (42,)
        mock_conn.cursor.return_value = mock_cursor

        with patch("pymysql.connect", return_value=mock_conn):
            val = execute_scalar("SELECT COUNT(*) FROM sites")
            assert val == 42

    def test_execute_statement_helper(self) -> None:
        """Verifica que execute_statement retorne el conteo de filas afectadas."""
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.execute.return_value = 3
        mock_conn.cursor.return_value = mock_cursor

        with patch("pymysql.connect", return_value=mock_conn):
            affected = execute_statement("UPDATE sites SET status = %s WHERE id IN (1, 2, 3)", ("UP",))
            assert affected == 3

    def test_test_db_connectivity_live(self) -> None:
        """Comprueba conectividad real contra MariaDB (SSOT) del entorno."""
        assert check_db_connectivity() is True


class TestSecurityProviderDIP:
    """Valida la Inversión de Dependencias (DIP) y desacoplamiento de bot.py."""

    def test_hardware_vault_provider_satisfies_protocol(self) -> None:
        """Verifica que HardwareVaultSecurityProvider implemente SecurityProviderProtocol."""
        provider = HardwareVaultSecurityProvider()
        assert isinstance(provider, SecurityProviderProtocol)
        assert provider.get_owner_id() == 38914901
        assert provider.get_git_branch() == "master"
        assert provider.is_core_operational() is True

    def test_bot_entrypoint_uses_security_provider(self) -> None:
        """Verifica que bot.py exponga propiedades y métodos gobernados por el SecurityProvider."""
        assert bot._SECURITY_PROVIDER is not None
        assert isinstance(bot._SECURITY_PROVIDER, SecurityProviderProtocol)
        assert bot.IMMUTABLE_OWNER_ID == 38914901
        assert bot.is_core_operational() is True

    def test_bot_main_accepts_injected_security_provider(self) -> None:
        """Verifica que bot.main admita un proveedor alternativo inyectado (DIP)."""
        mock_provider = MagicMock(spec=SecurityProviderProtocol)
        mock_provider.get_bot_token.return_value = ""  # Termina de inmediato sin conectar a Telegram

        # Debe terminar de forma limpia registrando el log sin excepción
        bot.main(security_provider=mock_provider)
        mock_provider.get_bot_token.assert_called_once()
