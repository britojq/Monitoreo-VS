"""
Pruebas unitarias para el conector centralizado monitor_db.py
"""

import os
from unittest.mock import MagicMock, patch
import pytest

from monitor.monitor_db import (
    get_db_connection,
    load_env_db_config,
    test_db_connection as check_db_connection,
)


class TestMonitorDb:
    def test_load_env_db_config_success(self):
        """Verifica que load_env_db_config cargue los parámetros correctamente."""
        cfg = load_env_db_config(force_reload=True)
        assert cfg is not None
        assert "host" in cfg
        assert "port" in cfg
        assert "database" in cfg
        assert "user" in cfg
        assert "username" in cfg
        assert "password" in cfg
        assert cfg["port"] == 3306
        assert cfg["user"] == "monitoreo_user"

    def test_load_env_db_config_missing_password_raises_error(self):
        """Verifica que lance RuntimeError si no hay contraseña configurada."""
        with patch("pathlib.Path.exists", return_value=False):
            with patch.dict(os.environ, {"DB_PASSWORD": ""}, clear=True):
                with pytest.raises(RuntimeError, match="DB_PASSWORD"):
                    load_env_db_config(force_reload=True)

    def test_get_db_connection_mocked(self):
        """Prueba get_db_connection usando mock de pymysql.connect."""
        mock_conn = MagicMock()
        mock_conn.open = True
        with patch("pymysql.connect", return_value=mock_conn) as mock_connect:
            conn = get_db_connection()
            assert conn == mock_conn
            mock_connect.assert_called_once()

    def test_test_db_connection_live(self):
        """Prueba la conectividad real a la base de datos MariaDB local."""
        result = check_db_connection()
        assert result is True
