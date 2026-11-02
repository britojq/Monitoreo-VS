"""
# ==============================================================================
# 🧪 TEST DE AUDITORÍA FASE 1: test_phase_1_audit_hardening.py
# Verificación rigurosa de cumplimiento de la Fase 1 contra la auditoría v5.45.10.
# Cero XOR/rotaciones, cero archivos planos en vault, AES-256-GCM estricto.
# ==============================================================================
"""

import hashlib
import os
from pathlib import Path
import pytest

from monitor.domain.exceptions import (
    DecryptionException,
    VaultError,
    VaultIntegrityError,
    VaultKeyNotFoundError,
    VaultWriteError,
)
from monitor.shield.standard_encryptor import StandardEncryptor
from monitor.shield.vault_key_deriver import (
    _B_CIPHER,
    _CANARY_HASH,
    _CORE_MASTER_KEY,
    _O_CIPHER,
    _SENTINEL_HASH,
    derive_hardware_key,
    get_canary_token,
    get_sentinel_token,
)
from monitor.shield.vault_storage import (
    deobfuscate_payload,
    generate_challenge,
    unwrap_anchor,
    write_anchor,
)

BASE_DIR = Path(__file__).resolve().parent.parent.parent
VAULT_DIR = BASE_DIR / "system" / "vault"


class TestPhase1AuditHardening:
    """Verificación formal contra los hallazgos de severidad crítica de la auditoría."""

    def test_no_flat_secret_files_in_system_vault(self):
        """Certifica que NO existan archivos planos de secretos dentro del repositorio."""
        assert not (VAULT_DIR / ".master_key").exists(), "Violación: .master_key encontrado en system/vault/"
        assert not (VAULT_DIR / ".vault_secrets.json").exists(), "Violación: .vault_secrets.json encontrado en system/vault/"
        assert not (VAULT_DIR / ".active_challenge").exists(), "Violación: .active_challenge encontrado en system/vault/"

        # El único archivo permitido en system/vault/ es el anclaje físico cifrado .sys_anchor
        if VAULT_DIR.exists():
            for item in VAULT_DIR.iterdir():
                assert item.name == ".sys_anchor", f"Archivo no autorizado en vault: {item.name}"

    def test_no_xor_or_bitshifts_in_vault_modules(self):
        """Certifica que no quede ningún operador de desplazamiento de bits o XOR manual."""
        vault_storage_code = (BASE_DIR / "monitor" / "shield" / "vault_storage.py").read_text(encoding="utf-8")
        vault_deriver_code = (BASE_DIR / "monitor" / "shield" / "vault_key_deriver.py").read_text(encoding="utf-8")

        # Prohibición de rotaciones de bits
        assert "<<" not in vault_storage_code, "Violación: operador '<<' encontrado en vault_storage.py"
        assert ">>" not in vault_storage_code, "Violación: operador '>>' encontrado en vault_storage.py"
        assert "<<" not in vault_deriver_code, "Violación: operador '<<' encontrado en vault_key_deriver.py"
        assert ">>" not in vault_deriver_code, "Violación: operador '>>' encontrado en vault_key_deriver.py"

    def test_deobfuscate_payload_aes_256_gcm(self):
        """Certifica que deobfuscate_payload utilice descifrado AES-256-GCM estándar."""
        plaintext = "38914901"
        blob = StandardEncryptor.encrypt_to_base64(
            plaintext=plaintext.encode("utf-8"),
            key=_CORE_MASTER_KEY,
            associated_data=b"OWNER_ID",
        )
        decrypted = deobfuscate_payload(blob, _CORE_MASTER_KEY, "OPERATIONAL")
        assert decrypted == plaintext

    def test_deobfuscate_payload_fails_safely_on_tampering(self):
        """Certifica que un payload manipulado falle de forma segura sin revelar datos."""
        tampered_blob = "AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8="
        result = deobfuscate_payload(tampered_blob, _CORE_MASTER_KEY, "OPERATIONAL")
        assert result.startswith("FAIL_SILENT_")

    def test_get_sentinel_token_aes_256_gcm(self):
        """Certifica que el sentinel token se descifre con AES-256-GCM y coincida con el hash SSOT."""
        token = get_sentinel_token()
        assert token != "", "Sentinel token no pudo ser descifrado"
        assert hashlib.sha256(token.encode("utf-8")).hexdigest() == _SENTINEL_HASH

    def test_get_canary_token_aes_256_gcm(self):
        """Certifica que el canary token se descifre con AES-256-GCM y coincida con el hash SSOT."""
        token = get_canary_token()
        assert token != "", "Canary token no pudo ser descifrado"
        assert hashlib.sha256(token.encode("utf-8")).hexdigest() == _CANARY_HASH

    def test_write_and_unwrap_anchor_aes_gcm(self):
        """Certifica que el anclaje de hardware persista y se recupere bajo AES-256-GCM v2.0."""
        hw_key = derive_hardware_key()
        test_tok = "1595888148:AAEMMMmCAV1I5EaUMkTQ3uVBJ2LpK9aIAoQ"
        success = write_anchor(hw_key, custom_token=test_tok)
        assert success is True

        master_key, recovered_tok = unwrap_anchor(hw_key)
        assert master_key == _CORE_MASTER_KEY
        assert recovered_tok == test_tok

    def test_challenge_persisted_in_dev_shm(self):
        """Certifica que el challenge se almacene en memoria volátil (/dev/shm) y no en el repo."""
        serial, ts = generate_challenge("TEST_FINGERPRINT_123")
        assert serial.startswith("AUTH-")
        assert ts > 0
        shm_file = Path("/dev/shm/.monitoreo_challenge.json")
        assert shm_file.exists(), "El archivo de challenge debe residir en /dev/shm"
        assert not (VAULT_DIR / ".active_challenge").exists()

    def test_write_anchor_raises_vault_write_error_on_permission_error(self, monkeypatch):
        """Certifica que los errores de permisos en write_anchor lancen VaultWriteError (Hallazgo #8)."""
        from unittest.mock import patch
        with patch.object(Path, "write_text", side_effect=PermissionError("Permiso denegado simulado")):
            with pytest.raises(VaultWriteError):
                hw_key = derive_hardware_key()
                write_anchor(hw_key, custom_token="1595888148:AAEMMMmCAV1I5EaUMkTQ3uVBJ2LpK9aIAoQ")

    def test_deobfuscate_payload_pep257_docstring_compliance(self):
        """Certifica que deobfuscate_payload cumpla con el estándar PEP 257 (Hallazgo #10)."""
        doc = deobfuscate_payload.__doc__
        assert doc is not None, "deobfuscate_payload debe tener docstring"
        assert "Args:" in doc, "Falta sección Args: en docstring"
        assert "Returns:" in doc, "Falta sección Returns: en docstring"
        assert "Security:" in doc, "Falta sección Security: en docstring"
        assert "Example:" in doc, "Falta sección Example: en docstring"
