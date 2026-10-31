"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS DE SEGURIDAD CRIPTOGRÁFICA Y VAULT (FASE A)
# Verificación de AES-256-GCM, transacciones atómicas y anti-inyección subprocess.
# Ubicación: /opt/sonar/tests/unit/test_phase_a_security_vault.py
# ==============================================================================
"""

import os
import pytest
from monitor.domain.exceptions import (
    DecryptionException,
    DatabaseTransactionException,
    SubprocessSecurityException,
    VaultIntegrityError,
)
from monitor.shield.standard_encryptor import StandardEncryptor
from monitor.infrastructure.db_context import atomic_cursor, atomic_transaction
from monitor.gitops.services.git_client import validate_process_args, GitClient
from monitor.shield.vault_key_deriver import derive_hardware_key, _CORE_MASTER_KEY
from monitor.shield.vault_storage import write_anchor, unwrap_anchor


class TestStandardEncryptor:
    """Verificación formal de NIST SP 800-38D AES-256-GCM."""

    def test_encrypt_decrypt_roundtrip(self):
        key = os.urandom(32)
        plaintext = b"SONAR_CONFIDENTIAL_PAYLOAD_TEST_2026"
        encrypted_blob = StandardEncryptor.encrypt(plaintext, key, associated_data=b"AUTH_HEADER")
        decrypted = StandardEncryptor.decrypt(encrypted_blob, key, associated_data=b"AUTH_HEADER")
        assert decrypted == plaintext

    def test_base64_serialization_roundtrip(self):
        key = os.urandom(32)
        plaintext = b"TELEGRAM_BOT_TOKEN_CORPORATIVO_PROTEGIDO"
        b64_blob = StandardEncryptor.encrypt_to_base64(plaintext, key, associated_data=b"TOKEN")
        recovered = StandardEncryptor.decrypt_from_base64(b64_blob, key, associated_data=b"TOKEN")
        assert recovered == plaintext

    def test_tampered_ciphertext_detection(self):
        key = os.urandom(32)
        plaintext = b"CRITICAL_ALERT_STATE"
        encrypted_blob = bytearray(StandardEncryptor.encrypt(plaintext, key))
        # Tamper a byte in the ciphertext portion (after 12 bytes of nonce)
        encrypted_blob[14] ^= 0xFF
        with pytest.raises(DecryptionException):
            StandardEncryptor.decrypt(bytes(encrypted_blob), key)

    def test_tampered_nonce_detection(self):
        key = os.urandom(32)
        plaintext = b"CRITICAL_ALERT_STATE"
        encrypted_blob = bytearray(StandardEncryptor.encrypt(plaintext, key))
        # Tamper a byte in the nonce portion
        encrypted_blob[0] ^= 0xFF
        with pytest.raises(DecryptionException):
            StandardEncryptor.decrypt(bytes(encrypted_blob), key)

    def test_tampered_aad_detection(self):
        key = os.urandom(32)
        plaintext = b"CRITICAL_ALERT_STATE"
        encrypted_blob = StandardEncryptor.encrypt(plaintext, key, associated_data=b"VALID_AAD")
        with pytest.raises(DecryptionException):
            StandardEncryptor.decrypt(encrypted_blob, key, associated_data=b"INVALID_AAD")

    def test_invalid_key_length_rejection(self):
        with pytest.raises(ValueError):
            StandardEncryptor.encrypt(b"data", b"short_key_16_byte")


class TestVaultStorageV2:
    """Verificación de persistencia cifrada AES-256-GCM en el anclaje de hardware."""

    def test_write_and_unwrap_anchor(self):
        hw_key = derive_hardware_key()
        assert len(hw_key) == 32
        operational_token = "1234567890:AAEMMMmCAV1I5EaUMkTQ3uVBJ2LpK9aIAoQ_dummy"
        success = write_anchor(hw_key, custom_token=operational_token)
        assert success is True

        master_key, custom_tok = unwrap_anchor(hw_key)
        assert master_key == _CORE_MASTER_KEY
        assert custom_tok == operational_token


class TestAtomicDatabaseTransactions:
    """Verificación de atomicidad y rollback automático en MariaDB."""

    def test_atomic_cursor_commit(self):
        with atomic_cursor() as cur:
            cur.execute("SELECT 1 AS num")
            row = cur.fetchone()
            assert row["num"] == 1

    def test_atomic_cursor_rollback_on_exception(self):
        with pytest.raises(DatabaseTransactionException):
            with atomic_cursor() as cur:
                cur.execute("SELECT 1")
                raise RuntimeError("Intentionally induced failure to test transaction rollback")


class TestSubprocessSecurityValidation:
    """Verificación de filtros contra inyección de comandos en GitClient."""

    def test_safe_arguments_allowed(self):
        assert validate_process_args(["status", "--short"]) is True
        assert validate_process_args(["log", "-n", "10", "--oneline"]) is True
        assert GitClient.validate_args(["diff", "HEAD~1"]) is True

    def test_semicolon_rejection(self):
        with pytest.raises(SubprocessSecurityException):
            validate_process_args(["status", "; touch /tmp/pwned"])

    def test_pipe_rejection(self):
        with pytest.raises(SubprocessSecurityException):
            validate_process_args(["status", "| cat /etc/passwd"])

    def test_command_substitution_rejection(self):
        with pytest.raises(SubprocessSecurityException):
            validate_process_args(["checkout", "$(id)"])

    def test_dangerous_git_flags_rejection(self):
        with pytest.raises(SubprocessSecurityException):
            validate_process_args(["fetch", "--upload-pack=/bin/sh"])
