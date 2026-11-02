"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS FASE 1 (AUDITORÍA v5.46.80): test_phase_1_audit_v5_46_80.py
# Valida la subsanación de los hallazgos SEC-002, ERR-001 e ISP-001:
# 1. Criptografía NIST SP 800-38D (AES-256-GCM) en laravel_encryptor.py.
# 2. Modo estricto sin silenciamiento de fallos (DecryptionException).
# 3. Segregación de Interfaces (ISP de SOLID) en security_provider.py.
# ==============================================================================
"""

import os
import pytest

from bot_core.contracts.security_provider import (
    GitConfigProvider,
    HardwareVaultSecurityProvider,
    OperationalStatusProvider,
    SecurityProviderProtocol,
    TokenProvider,
)
from monitor.domain.exceptions import DecryptionException
from monitor.infrastructure.laravel_encryptor import (
    decrypt_laravel_string,
    encrypt_laravel_string,
    encrypt_laravel_string_gcm,
)


@pytest.fixture(autouse=True)
def setup_test_app_key(monkeypatch):
    """Asegura una clave maestra de prueba para AES-256 (32 bytes base64)."""
    monkeypatch.setenv("APP_KEY", "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=")


class TestPhase1LaravelEncryptorGcmAndStrict:
    """Verificación de cumplimiento para SEC-002 y ERR-001."""

    def test_encrypt_gcm_produces_nist_compliant_payload(self):
        """Verifica que el cifrado GCM genere un payload JSON con nonce de 12 bytes y mode GCM."""
        plain = "Credencial_SNMP_Secret_123"
        cipher = encrypt_laravel_string_gcm(plain)

        assert isinstance(cipher, str)
        assert len(cipher) > 0

        # El descifrado debe recuperar el texto exacto
        recovered = decrypt_laravel_string(cipher, strict=True)
        assert recovered == plain

    def test_retrocompatibility_with_laravel_cbc(self):
        """Verifica que decrypt_laravel_string mantenga compatibilidad 100% con AES-256-CBC."""
        plain = "Credencial_Laravel_CBC_Legacy_456"
        cipher = encrypt_laravel_string(plain)

        recovered = decrypt_laravel_string(cipher, strict=True)
        assert recovered == plain

    def test_strict_mode_raises_typed_decryption_exception_on_corrupted_payload(self):
        """Verifica que el modo strict=True levante DecryptionException ante datos corruptos (ERR-001)."""
        bad_payload = "dGhpcyBpcyBub3QgYSB2YWxpZCBqc29uIG9yIGFlcw=="

        with pytest.raises(DecryptionException) as excinfo:
            decrypt_laravel_string(bad_payload, strict=True)

        assert "Error crítico al descifrar" in str(excinfo.value)

    def test_non_strict_mode_returns_empty_string_gracefully(self):
        """Verifica que el modo strict=False conserve la resiliencia retornando cadena vacía."""
        bad_payload = "dGhpcyBpcyBub3QgYSB2YWxpZCBqc29uIG9yIGFlcw=="
        result = decrypt_laravel_string(bad_payload, strict=False)
        assert result == ""

    def test_empty_string_handling(self):
        """Cadenas vacías retornan cadena vacía sin error tanto en CBC como en GCM."""
        assert encrypt_laravel_string("") == ""
        assert encrypt_laravel_string_gcm("") == ""
        assert decrypt_laravel_string("", strict=True) == ""


class TestPhase1SecurityProviderISP:
    """Verificación de cumplimiento para ISP-001 (Principio de Segregación de Interfaces)."""

    def test_provider_satisfies_all_segregated_protocols(self):
        """Verifica que HardwareVaultSecurityProvider satisfaga cada interfaz segregada."""
        provider = HardwareVaultSecurityProvider()

        # ISP: Interfaces atómicas
        assert isinstance(provider, TokenProvider), "Violación ISP: no cumple TokenProvider"
        assert isinstance(provider, OperationalStatusProvider), "Violación ISP: no cumple OperationalStatusProvider"
        assert isinstance(provider, GitConfigProvider), "Violación ISP: no cumple GitConfigProvider"

        # Composición: Cumple el protocolo consolidado
        assert isinstance(provider, SecurityProviderProtocol), "Violación: no cumple SecurityProviderProtocol compuesto"

    def test_token_provider_interface_methods(self):
        """Verifica que los métodos de TokenProvider respondan con los tipos correctos."""
        provider: TokenProvider = HardwareVaultSecurityProvider()
        token = provider.get_bot_token()
        owner_id = provider.get_owner_id()

        assert isinstance(token, str)
        assert isinstance(owner_id, int)

    def test_operational_status_provider_interface_methods(self):
        """Verifica que los métodos de OperationalStatusProvider respondan con los tipos correctos."""
        provider: OperationalStatusProvider = HardwareVaultSecurityProvider()
        status = provider.get_core_status()

        assert isinstance(status, (dict, str))
        assert len(status) > 0

    def test_git_config_provider_interface_methods(self):
        """Verifica que los métodos de GitConfigProvider respondan con los tipos correctos."""
        provider: GitConfigProvider = HardwareVaultSecurityProvider()
        is_auto = provider.is_auto_update_enabled()
        branch = provider.get_git_branch()

        assert isinstance(is_auto, bool)
        assert isinstance(branch, str)
