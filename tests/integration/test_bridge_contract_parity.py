"""
# ==============================================================================
# 🧪 TEST DE INTEGRACIÓN: PARIDAD DE CONTRATO LARAVELBRIDGE (QUAL-001)
# Valida la adherencia estricta del contrato inter-servicios LaravelBridge
# y la paridad criptográfica exacta contra Illuminate\\Support\\Facades\\Crypt.
# Ubicación: /opt/sonar/tests/integration/test_bridge_contract_parity.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

import pytest

from monitor.contracts.laravel_bridge import LaravelBridge
from monitor.domain.exceptions import DatabaseTransactionException
from monitor.infrastructure.bridges.laravel_mariadb_bridge import LaravelMariaDbBridge


# Fixtures reales generados por Illuminate\\Support\\Facades\\Crypt::encryptString() en Laravel 13
LARAVEL_ENCRYPTED_FIXTURES = [
    (
        "eyJpdiI6IncySkV5UTZIVDlVelV4NFVUL3dwbUE9PSIsInZhbHVlIjoiaEVwRVA5M1pMU2pFdXY3UXI4WWhrQ2IwV1JQTkZEbSt5ZXZ4UzVuYmEvUT0iLCJtYWMiOiIzZmRlZGM2YWQ5MTQzYjgxN2NkY2NkYzg5OTcyOGFkMTc0ZGIwYzIxNDRiYjE2ODc1YjQ0MThiMDM3ZjI3MjBhIiwidGFnIjoiIn0=",
        "Router_Core_Cisco_2960",
    ),
    (
        "eyJpdiI6ImZuWHk3RCtuUnpFd1crWFhpbjFHcGc9PSIsInZhbHVlIjoid2g5MFJEZ29Ya0d5a293dWRFeXdEcmlXciszUGNpWVVHTVNIWmJxVFhFZkhQaElZK1c1LzFtbGdUNWpvbnVBUyIsIm1hYyI6Ijk5NzVlYTNlYzlhZTk0OWIzYjllZGZlNjNkMzAzNjM1YzRlYjlkMjNlYzY3OWJhZTRhMWU1ZTg0MTRlMjI0OTciLCJ0YWciOiIifQ==",
        '{"community":"public_snmp","port":161}',
    ),
    (
        "eyJpdiI6Ik5mRkRRcG1vSHZCdjZ6U3BSSTEraFE9PSIsInZhbHVlIjoiWUs3NlBjWXhlajlRSGdObUtlQzlmd29QUWFXeFpYVXhGSTZpTzlGKzhmRT0iLCJtYWMiOiJkODBmMmQxNWRlZjIzZGIxZjU0Y2IzYTQ1MWI1ZDFmZTlmMGE1MmFkMTBjNmM2ZDVlNDI5NzNjY2FiMGU3ZTExIiwidGFnIjoiIn0=",
        "P@$$w0rd!#%&*()[]{}",
    ),
    (
        "eyJpdiI6Im1xNVA5R3Jhc1VKeWtiVmx5dlNSVHc9PSIsInZhbHVlIjoiVU0yeDN0UHNZQW9ISERTeWU1RlRzZz09IiwibWFjIjoiOWU3OWI3OGI0YWU2NTc3Zjc1NmMxN2JhZDNmOTAyNjFhYTlhODZjZjk3YzRkYzhjZDUwZjdlOGZmOGVhY2FiOCIsInRhZyI6IiJ9",
        "",
    ),
]


class TestLaravelBridgeContractParity:
    """Verifica que LaravelMariaDbBridge cumpla rigurosamente el contrato tipado y paridad criptográfica."""

    def test_bridge_satisfies_protocol_runtime_checkable(self):
        """Certifica que LaravelMariaDbBridge satisfaga el Protocol runtime_checkable LaravelBridge."""
        bridge = LaravelMariaDbBridge()
        assert isinstance(bridge, LaravelBridge), "LaravelMariaDbBridge no implementa el Protocol LaravelBridge"

    @pytest.mark.parametrize("ciphertext, expected_plaintext", LARAVEL_ENCRYPTED_FIXTURES)
    def test_decrypt_parity_with_illuminate_encryption(self, ciphertext: str, expected_plaintext: str):
        """Certifica que el puente descifre con 100% de paridad las salidas reales de Laravel."""
        bridge = LaravelMariaDbBridge()
        decrypted = bridge.decrypt_payload(ciphertext)
        assert decrypted == expected_plaintext

    def test_bridge_read_config_contract(self):
        """Certifica que read_config retorne default si la clave no existe."""
        bridge = LaravelMariaDbBridge()
        result = bridge.read_config("non_existent_key_xyz_999", default="fallback_val")
        assert result == "fallback_val"

    def test_bridge_write_telemetry_validates_columns_safely(self):
        """Certifica que write_telemetry rechace columnas con caracteres maliciosos levantando DatabaseTransactionException."""
        bridge = LaravelMariaDbBridge()
        malicious_data = {"valid_col; DROP TABLE users; --": "exploit"}
        with pytest.raises(DatabaseTransactionException):
            bridge.write_telemetry("audit_logs", malicious_data)
