"""
# ==============================================================================
# 🧪 SUITE DE PRUEBAS UNITARIAS: FASE 3 SUBSANACIÓN DE AUDITORÍA
# Valida: Transacciones atómicas, rollback explícito, desacoplamiento de APP_KEY,
# validación estricta de parámetros SNMP y contrato LaravelBridge.
# Ubicación: /opt/sonar/tests/unit/test_phase_3_audit_hardening.py
# ==============================================================================
"""

import asyncio
import os
import pytest
from unittest.mock import patch, MagicMock

from monitor.contracts.laravel_bridge import LaravelBridge
from monitor.domain.exceptions import (
    DatabaseConcurrencyError,
    DatabaseTransactionException,
    DecryptionException,
    SnmpDeviceValidationError,
)
from monitor.infrastructure.db_context import (
    atomic_inventory_update,
    atomic_transaction,
    pessimistic_row_lock,
)
from monitor.infrastructure.bridges.laravel_mariadb_bridge import LaravelMariaDbBridge
from monitor.infrastructure.laravel_encryptor import (
    decrypt_laravel_string,
    encrypt_laravel_string,
    get_laravel_app_key,
)
from monitor.checkers.snmp.snmp_engine import SNMPDeviceConfig, poll_device_oids


class TestPhase3TransactionalIntegrity:
    """Pruebas de integridad transaccional y rollback explícito (Hallazgo #4)."""

    def test_atomic_transaction_rolls_back_on_exception(self):
        """Valida que ante cualquier excepción dentro del bloque, se ejecute conn.rollback() incondicionalmente."""
        mock_conn = MagicMock()
        mock_conn.commit = MagicMock()
        mock_conn.rollback = MagicMock()

        with patch("monitor.infrastructure.db_context.db_connection") as mock_db_conn:
            mock_db_conn.return_value.__enter__.return_value = mock_conn

            with pytest.raises(DatabaseTransactionException):
                with atomic_transaction():
                    raise RuntimeError("Error forzado para simular caída en transacción")

            mock_conn.rollback.assert_called_once()
            mock_conn.commit.assert_not_called()

    def test_pessimistic_row_lock_invalid_identifier(self):
        """Valida que nombres de tabla o columna con caracteres maliciosos sean rechazados."""
        with pytest.raises(DatabaseConcurrencyError):
            with pessimistic_row_lock("users; DROP TABLE users;--", 1):
                pass

    def test_atomic_inventory_update_invalid_column(self):
        """Valida que campos maliciosos en actualizaciones de inventario disparen DatabaseConcurrencyError."""
        with pytest.raises(DatabaseConcurrencyError):
            atomic_inventory_update(1, {"valid_col": "val", "malicious_col--": "bad"})

    def test_atomic_inventory_update_non_existent_asset(self):
        """Valida que intentar actualizar un activo inexistente genere DatabaseConcurrencyError."""
        mock_conn = MagicMock()
        mock_cur = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cur
        mock_cur.fetchone.return_value = None  # No row found

        with patch("monitor.infrastructure.db_context.db_connection") as mock_db_conn:
            mock_db_conn.return_value.__enter__.return_value = mock_conn

            with pytest.raises(DatabaseConcurrencyError, match="No existe activo con ID 999999"):
                atomic_inventory_update(999999, {"name": "Test"})


class TestPhase3LaravelAppKeyResolution:
    """Pruebas de desacoplamiento de APP_KEY y cifrado (Hallazgo #5)."""

    def test_get_laravel_app_key_prioritizes_env_variable(self):
        """Valida que os.getenv('LARAVEL_APP_KEY') tenga prioridad sin leer disco."""
        test_key = "base64:MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTIzNDU2Nzg5MDE="
        with patch.dict(os.environ, {"LARAVEL_APP_KEY": test_key}, clear=False):
            key_bytes = get_laravel_app_key(force_reload=True)
            assert len(key_bytes) == 32
            assert key_bytes == b"01234567890123456789012345678901"

    def test_get_laravel_app_key_rejects_invalid_length(self):
        """Valida que claves con longitud distinta de 16, 24 o 32 bytes sean rechazadas."""
        with patch.dict(os.environ, {"LARAVEL_APP_KEY": "corta"}, clear=False):
            with pytest.raises(DecryptionException):
                get_laravel_app_key(force_reload=True)

    def test_encrypt_and_decrypt_laravel_string_roundtrip(self):
        """Valida cifrado y descifrado bidireccional compatible con Laravel."""
        test_key = "base64:MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTIzNDU2Nzg5MDE="
        with patch.dict(os.environ, {"LARAVEL_APP_KEY": test_key}, clear=False):
            original_text = "SNMP_COMMUNITY_SUPER_SECRET_2026"
            encrypted = encrypt_laravel_string(original_text)
            assert isinstance(encrypted, str)
            assert len(encrypted) > 20

            decrypted = decrypt_laravel_string(encrypted)
            assert decrypted == original_text


class TestPhase3SnmpInputValidation:
    """Pruebas de validación estricta de inputs SNMP bajo OWASP A03:2021 (Hallazgo #9)."""

    def test_snmp_device_config_valid(self):
        """Valida instanciación de SNMPDeviceConfig con parámetros válidos."""
        dev = SNMPDeviceConfig(
            ip_address="192.168.1.10",
            snmp_port=161,
            snmp_version="v2c",
        )
        assert dev.ip_address == "192.168.1.10"
        assert dev.snmp_port == 161

    def test_snmp_device_config_invalid_ip(self):
        """Valida que IPs sintácticamente inválidas disparen SnmpDeviceValidationError."""
        with pytest.raises(SnmpDeviceValidationError, match="Dirección IP SNMP inválida"):
            SNMPDeviceConfig(ip_address="999.999.999.999")

        with pytest.raises(SnmpDeviceValidationError, match="Dirección IP SNMP inválida"):
            SNMPDeviceConfig(ip_address="192.168.1.1; cat /etc/passwd")

    def test_snmp_device_config_invalid_port(self):
        """Valida que puertos fuera de 1-65535 sean rechazados."""
        with pytest.raises(SnmpDeviceValidationError, match="Puerto SNMP fuera de rango"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_port=0)

        with pytest.raises(SnmpDeviceValidationError, match="Puerto SNMP fuera de rango"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_port=70000)

    def test_snmp_device_config_invalid_version(self):
        """Valida que versiones SNMP no soportadas sean rechazadas."""
        with pytest.raises(SnmpDeviceValidationError, match="Versión SNMP no soportada"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_version="v4")

    def test_poll_device_oids_graceful_on_invalid_device(self):
        """Valida que poll_device_oids no crashee ante configuración corrupta y retorne status controlado."""
        invalid_dev = {"ip_address": "ip_invalida_total", "snmp_port": -5}
        res = asyncio.run(poll_device_oids(invalid_dev, []))
        assert res["status"] == "validation_error"
        assert res["success"] is False
        assert "inválid" in res["error"].lower()


class TestPhase3LaravelBridgeArchitecture:
    """Pruebas del contrato desacoplado LaravelBridge (Hallazgo #11)."""

    def test_laravel_mariadb_bridge_implements_protocol(self):
        """Valida que LaravelMariaDbBridge cumpla fielmente con el protocolo LaravelBridge."""
        bridge = LaravelMariaDbBridge()
        assert isinstance(bridge, LaravelBridge)

    def test_laravel_mariadb_bridge_read_config(self):
        """Valida lectura de configuración desde bot_settings delegada a DB."""
        bridge = LaravelMariaDbBridge()
        with patch("monitor.infrastructure.bridges.laravel_mariadb_bridge.execute_query") as mock_exec:
            mock_exec.return_value = [{"value": "true"}]
            val = bridge.read_config("cron_reports_enabled")
            assert val == "true"
            mock_exec.assert_called_once()

    def test_laravel_mariadb_bridge_write_telemetry_validates_table_and_columns(self):
        """Valida que write_telemetry prevenga inyecciones en identificadores de tabla y columna."""
        bridge = LaravelMariaDbBridge()
        with pytest.raises(DatabaseTransactionException, match="Nombre de tabla inválido"):
            bridge.write_telemetry("history; DROP TABLE users;", {"val": 1})

        with pytest.raises(DatabaseTransactionException, match="Nombre de columna inválido"):
            bridge.write_telemetry("snmp_history", {"bad-col-name;": 1})
