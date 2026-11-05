"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS CIFRADO GCM/CBC: test_laravel_encryptor_gcm.py
# Valida la subsanación integral de SEC-002, ERR-001, F-05 y F-06 en laravel_encryptor.py.
# ==============================================================================
"""

import base64
import hashlib
import json
import os
import stat
from unittest.mock import patch
import pytest

from monitor.domain.exceptions import DecryptionException
from monitor.infrastructure.laravel_encryptor import (
    decrypt_laravel_string,
    encrypt_laravel_string,
    encrypt_laravel_string_gcm,
    get_laravel_app_key,
)
from monitor.infrastructure.shm_cache import ShmCache, get_shm_cache


@pytest.fixture(autouse=True)
def setup_app_key(monkeypatch):
    """Provee una clave AES-256 válida en base64 para los tests."""
    monkeypatch.setenv("APP_KEY", "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=")


class TestLaravelEncryptorNistGcmAndLegacyCbc:
    """Verificación de compatibilidad dual GCM (NIST SP 800-38D) y CBC (Legacy Laravel)."""

    def test_encrypt_gcm_structure_and_roundtrip(self):
        """Verifica que encrypt_laravel_string_gcm genere el payload esperado con cipher explícito."""
        original = "Router_Admin_Password_2026!"
        encrypted = encrypt_laravel_string_gcm(original)

        # Inspección del payload JSON interno
        raw_payload = json.loads(base64.b64decode(encrypted).decode("utf-8"))
        assert raw_payload["mode"] == "GCM"
        assert raw_payload["cipher"] == "aes-256-gcm"
        assert "nonce" in raw_payload
        assert "value" in raw_payload

        # Verificación del tamaño del nonce (12 bytes = 96 bits conforme a NIST SP 800-38D)
        nonce_bytes = base64.b64decode(raw_payload["nonce"])
        assert len(nonce_bytes) == 12

        # Descifrado
        decrypted = decrypt_laravel_string(encrypted, strict=True)
        assert decrypted == original

    def test_legacy_cbc_roundtrip(self):
        """Verifica que encrypt_laravel_string (CBC) genere HMAC y sea descifrado correctamente."""
        original = "Squid_Proxy_Auth_Secret_888"
        encrypted = encrypt_laravel_string(original)

        raw_payload = json.loads(base64.b64decode(encrypted).decode("utf-8"))
        assert "iv" in raw_payload
        assert "value" in raw_payload
        assert "mac" in raw_payload
        assert raw_payload["cipher"] == "aes-256-cbc"

        iv_bytes = base64.b64decode(raw_payload["iv"])
        assert len(iv_bytes) == 16  # AES CBC IV: 16 bytes

        decrypted = decrypt_laravel_string(encrypted, strict=True)
        assert decrypted == original

    def test_shm_cache_acceleration(self):
        """Verifica que consultas sucesivas aprovechen la caché en /dev/shm con clave SHA-256 (F-05)."""
        original = "Repeated_Query_Credential"
        encrypted = encrypt_laravel_string_gcm(original)

        # Primer descifrado (genera caché)
        val1 = decrypt_laravel_string(encrypted)
        assert val1 == original

        # Segundo descifrado (debe provenir de caché)
        cache = get_shm_cache()
        cache_key = f"cred_{hashlib.sha256(encrypted.encode('utf-8')).hexdigest()[:32]}"
        assert cache.get(cache_key) == original

        val2 = decrypt_laravel_string(encrypted)
        assert val2 == original

    def test_php_serialization_stripping(self):
        """Verifica que se limpie la serialización nativa de PHP s:N:\"valor\"."""
        serialized = 's:6:"public";'
        encrypted = encrypt_laravel_string_gcm(serialized)

        decrypted = decrypt_laravel_string(encrypted, strict=True)
        assert decrypted == "public"

    def test_shm_cache_hardening_permissions(self, tmp_path):
        """F-05: Verifica que el directorio tenga permisos 0700 y los archivos 0600."""
        shm_dir = tmp_path / "test_shm_cache"
        cache = ShmCache(cache_dir=shm_dir, default_ttl=60.0)

        # Permisos del directorio 0700 (rwx------)
        dir_mode = stat.S_IMODE(os.stat(shm_dir).st_mode)
        assert dir_mode == 0o700

        cache.set("test_key", {"token": "secret_data"})
        cache_file = cache._get_cache_path("test_key")
        assert cache_file.exists()

        # Permisos del archivo 0600 (rw-------)
        file_mode = stat.S_IMODE(os.stat(cache_file).st_mode)
        assert file_mode == 0o600

        # Lectura e invalidación
        assert cache.get("test_key") == {"token": "secret_data"}
        cache.invalidate("test_key")
        assert cache.get("test_key") is None


class TestStrictModeErrorHandling:
    """Verificación de ERR-001 y F-06: Modo estricto y fail-closed en CBC."""

    def test_strict_true_raises_on_invalid_base64(self):
        """Modo strict=True levanta DecryptionException ante base64 inválido."""
        with pytest.raises(DecryptionException):
            decrypt_laravel_string("bad-base64-content!@#$%", strict=True)

    def test_strict_true_raises_on_tampered_gcm_ciphertext(self):
        """Modo strict=True detecta alteración en ciphertext GCM y levanta DecryptionException."""
        original = "Secure_Data"
        encrypted = encrypt_laravel_string_gcm(original)

        raw_payload = json.loads(base64.b64decode(encrypted).decode("utf-8"))
        ct_bytes = bytearray(base64.b64decode(raw_payload["value"]))
        ct_bytes[0] ^= 0xFF
        raw_payload["value"] = base64.b64encode(ct_bytes).decode("utf-8")
        corrupted = base64.b64encode(json.dumps(raw_payload).encode("utf-8")).decode("utf-8")

        with pytest.raises(DecryptionException):
            decrypt_laravel_string(corrupted, strict=True)

    def test_strict_false_returns_empty_string_on_tampering(self):
        """Modo strict=False retorna cadena vacía sin propagar excepciones."""
        corrupted = "bad_data_non_strict"
        result = decrypt_laravel_string(corrupted, strict=False)
        assert result == ""

    def test_strict_true_raises_on_tampered_cbc_hmac(self):
        """Modo strict=True lanza DecryptionException ante HMAC alterado en AES-256-CBC."""
        original = "SecretCommunityCBC"
        encrypted = encrypt_laravel_string(original)

        raw_payload = json.loads(base64.b64decode(encrypted).decode("utf-8"))
        raw_payload["mac"] = "0000000000000000000000000000000000000000000000000000000000000000"
        corrupted = base64.b64encode(json.dumps(raw_payload).encode("utf-8")).decode("utf-8")

        with pytest.raises(DecryptionException):
            decrypt_laravel_string(corrupted, strict=True)

        assert decrypt_laravel_string(corrupted, strict=False) == ""

    def test_cbc_missing_mac_fails_closed(self):
        """F-06: Payload CBC sin MAC o con MAC nulo debe fallar cerrado (DecryptionException)."""
        original = "SecretCBCNoMac"
        encrypted = encrypt_laravel_string(original)

        raw_payload = json.loads(base64.b64decode(encrypted).decode("utf-8"))
        del raw_payload["mac"]
        corrupted = base64.b64encode(json.dumps(raw_payload).encode("utf-8")).decode("utf-8")

        with pytest.raises(DecryptionException):
            decrypt_laravel_string(corrupted, strict=True)

        assert decrypt_laravel_string(corrupted, strict=False) == ""

    def test_cbc_empty_mac_fails_closed(self):
        """F-06: Payload CBC con MAC vacío debe fallar cerrado (DecryptionException)."""
        original = "SecretCBCEmptyMac"
        encrypted = encrypt_laravel_string(original)

        raw_payload = json.loads(base64.b64decode(encrypted).decode("utf-8"))
        raw_payload["mac"] = ""
        corrupted = base64.b64encode(json.dumps(raw_payload).encode("utf-8")).decode("utf-8")

        with pytest.raises(DecryptionException):
            decrypt_laravel_string(corrupted, strict=True)

        assert decrypt_laravel_string(corrupted, strict=False) == ""

    def test_sec_08_serialized_passwords_with_embedded_quotes_and_multibyte(self):
        """SEC-08: Verifica que la deserialización formal de PHP preserve comillas dobles y caracteres multibyte."""
        from monitor.infrastructure.laravel_encryptor import _parse_php_serialized_string

        # Caso con comillas dobles intermedias
        assert _parse_php_serialized_string('s:9:"p"ss"w0rd";') == 'p"ss"w0rd'
        # Caso con multibyte UTF-8 y comillas
        assert _parse_php_serialized_string('s:10:"p"ña"w0rd";') == 'p"ña"w0rd'
        # Caso simple
        assert _parse_php_serialized_string('s:6:"public";') == 'public'
        # Cadena sin serializar
        assert _parse_php_serialized_string('raw_password_no_php') == 'raw_password_no_php'
        # Múltiples comillas consecutivas
        assert _parse_php_serialized_string('s:11:"adm""in""123";') == 'adm""in""123'
