"""
# ==============================================================================
# 🧪 SUITE UNITARIA FASE 3: test_phase3_crypto_resilience.py
# Validación rigurosa de SEC-005 y OPS-001 (Auditoría v5.51.70).
# - SEC-005: Modo estricto CRYPT_STRICT_MODE (AEAD AES-256-GCM vs CBC fail-closed).
# - OPS-001: Resiliencia en memoria volátil (/dev/shm) ante caídas eléctricas
#   imprevistas, archivos truncados a 0 bytes, escrituras atómicas y purga TTL.
# ==============================================================================
"""

import base64
import json
import os
import stat
import time
from pathlib import Path
from unittest.mock import patch
import pytest

from monitor.domain.exceptions import DecryptionException
from monitor.infrastructure.laravel_encryptor import (
    decrypt_laravel_string,
    encrypt_laravel_string,
    encrypt_laravel_string_gcm,
    get_laravel_app_key,
    is_crypt_strict_mode,
)
from monitor.infrastructure.shm_cache import ShmCache, get_shm_cache


@pytest.fixture(autouse=True)
def setup_app_key(monkeypatch):
    """Provee clave fija conocida para pruebas deterministas."""
    monkeypatch.setenv("APP_KEY", "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=")


class TestSec005CryptoHardening:
    """Verifica el cumplimiento de SEC-005: Hardening Criptográfico y CRYPT_STRICT_MODE."""

    def test_is_crypt_strict_mode_detection(self, monkeypatch):
        """Certifica la detección de las banderas de modo estricto en variables de entorno."""
        monkeypatch.delenv("CRYPT_STRICT_MODE", raising=False)
        monkeypatch.delenv("SONAR_CRYPT_STRICT_MODE", raising=False)
        assert not is_crypt_strict_mode()

        for val in ("true", "1", "yes", "on", "TRUE", "Yes"):
            monkeypatch.setenv("CRYPT_STRICT_MODE", val)
            assert is_crypt_strict_mode()

        monkeypatch.delenv("CRYPT_STRICT_MODE", raising=False)
        for val in ("true", "1", "yes", "on"):
            monkeypatch.setenv("SONAR_CRYPT_STRICT_MODE", val)
            assert is_crypt_strict_mode()

    def test_strict_crypto_rejects_cbc_and_permits_gcm(self, monkeypatch):
        """En modo estricto, los payloads CBC deben ser rechazados y los GCM aceptados."""
        secret = "UltraSecretEnterpriseCredential2026!"
        gcm_blob = encrypt_laravel_string_gcm(secret)
        cbc_blob = encrypt_laravel_string(secret)

        # 1. Sin modo estricto: ambos descifran correctamente
        monkeypatch.setenv("CRYPT_STRICT_MODE", "false")
        assert decrypt_laravel_string(gcm_blob, strict=True) == secret
        assert decrypt_laravel_string(cbc_blob, strict=True) == secret

        # 2. Con modo estricto activo: GCM funciona, CBC es bloqueado fail-closed
        monkeypatch.setenv("CRYPT_STRICT_MODE", "true")
        assert decrypt_laravel_string(gcm_blob, strict=True) == secret

        with pytest.raises(DecryptionException) as excinfo:
            decrypt_laravel_string(cbc_blob, strict=True)
        assert "CRYPT_STRICT_MODE" in str(excinfo.value)
        assert "AES-256-CBC deshabilitado" in str(excinfo.value)

        # Modo no-strict retorna cadena vacía
        assert decrypt_laravel_string(cbc_blob, strict=False) == ""

    def test_strict_crypto_override_parameter(self, monkeypatch):
        """Certifica que el parámetro explícito strict_crypto tenga precedencia."""
        monkeypatch.setenv("CRYPT_STRICT_MODE", "false")
        cbc_blob = encrypt_laravel_string("SomeDataCBC")

        with pytest.raises(DecryptionException):
            decrypt_laravel_string(cbc_blob, strict=True, strict_crypto=True)

    def test_cbc_hmac_tamper_fail_closed_before_unpadding(self):
        """Certifica que cualquier manipulación del HMAC en CBC falle cerrado."""
        original = "CorporateSnmpCommunityPassword"
        cbc_blob = encrypt_laravel_string(original)

        payload = json.loads(base64.b64decode(cbc_blob).decode("utf-8"))
        # Tamper HMAC
        payload["mac"] = "f" * 64
        corrupted = base64.b64encode(json.dumps(payload).encode("utf-8")).decode("utf-8")

        with pytest.raises(DecryptionException) as excinfo:
            decrypt_laravel_string(corrupted, strict=True)
        assert "Firma HMAC-SHA256 inválida o payload manipulado" in str(excinfo.value)

    def test_cbc_malformed_iv_or_ciphertext_rejected(self):
        """Certifica rechazo fail-closed de IV o ciphertext truncados o no alineados."""
        key = get_laravel_app_key()
        # IV menor a 16 bytes
        bad_payload = {
            "iv": base64.b64encode(b"short_iv").decode("utf-8"),
            "value": base64.b64encode(b"1234567890123456").decode("utf-8"),
            "mac": "a" * 64,
            "cipher": "aes-256-cbc",
        }
        # Recalcular mac para pasar la primera barrera y probar la validación de IV
        import hashlib, hmac
        bad_payload["mac"] = hmac.new(
            key,
            (bad_payload["iv"] + bad_payload["value"]).encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()

        corrupted = base64.b64encode(json.dumps(bad_payload).encode("utf-8")).decode("utf-8")
        with pytest.raises(DecryptionException) as excinfo:
            decrypt_laravel_string(corrupted, strict=True)
        assert "Longitud de IV inválida" in str(excinfo.value)


class TestOps001ShmResilience:
    """Verifica el cumplimiento de OPS-001: Resiliencia en memoria compartida /dev/shm."""

    def test_empty_zero_byte_file_recovery(self, tmp_path):
        """Simula archivo truncado a 0 bytes tras un corte de energía imprevisto."""
        cache_dir = tmp_path / "test_shm_recovery"
        cache = ShmCache(cache_dir=cache_dir, default_ttl=60.0)

        # Crear archivo vacío (0 bytes) como deja un corte eléctrico abrupto
        key = "damaged_entry_key"
        file_path = cache._get_cache_path(key)
        file_path.write_text("", encoding="utf-8")
        assert file_path.stat().st_size == 0

        # La lectura debe detectar el archivo vacío, purgarlo y retornar None sin excepción
        val = cache.get(key)
        assert val is None
        assert not file_path.exists()

    def test_corrupted_json_recovery(self, tmp_path):
        """Simula archivo con JSON a medio escribir tras una caída del sistema."""
        cache_dir = tmp_path / "test_shm_corrupt"
        cache = ShmCache(cache_dir=cache_dir, default_ttl=60.0)

        key = "half_written_key"
        file_path = cache._get_cache_path(key)
        file_path.write_text('{"val": "partial_content", "time": ', encoding="utf-8")

        val = cache.get(key)
        assert val is None
        assert not file_path.exists()

    def test_atomic_write_leaves_no_temporary_files(self, tmp_path):
        """Certifica que el reemplazo atómico os.replace no deje huellas residuales."""
        cache_dir = tmp_path / "test_shm_atomic"
        cache = ShmCache(cache_dir=cache_dir, default_ttl=60.0)

        cache.set("stable_key", {"status": "operational", "code": 200})
        cache_file = cache._get_cache_path("stable_key")

        assert cache_file.exists()
        # Cero archivos temporales residuales
        tmp_files = list(cache_dir.glob("*.tmp.*"))
        assert len(tmp_files) == 0

        # Permisos estrictos 0600
        mode = stat.S_IMODE(cache_file.stat().st_mode)
        assert mode == 0o600

        # Lectura exitosa
        assert cache.get("stable_key") == {"status": "operational", "code": 200}

    def test_cleanup_expired_and_stale_temp_files(self, tmp_path):
        """Certifica la purga automática de temporales huérfanos y entradas expiradas."""
        cache_dir = tmp_path / "test_shm_cleanup"
        cache = ShmCache(cache_dir=cache_dir, default_ttl=10.0)

        # 1. Entrada viva
        cache.set("live_key", "alive_value", ttl=100.0)

        # 2. Entrada expirada
        cache.set("expired_key", "dead_value", ttl=1.0)
        exp_file = cache._get_cache_path("expired_key")
        # Simular paso del tiempo modificando JSON
        exp_data = json.loads(exp_file.read_text(encoding="utf-8"))
        exp_data["time"] = time.time() - 50.0
        exp_file.write_text(json.dumps(exp_data), encoding="utf-8")

        # 3. Archivo temporal huérfano viejo (>60s)
        stale_tmp = cache_dir / "stale_entry.tmp.1234_5678"
        stale_tmp.write_text("abandoned", encoding="utf-8")
        # Forzar mtime al pasado
        old_time = time.time() - 120.0
        os.utime(stale_tmp, (old_time, old_time))

        # 4. Archivo 0 bytes
        zero_file = cache_dir / "empty_orphan.json"
        zero_file.write_text("", encoding="utf-8")

        # Ejecutar purga
        purged = cache.cleanup_expired()
        assert purged >= 3

        # La entrada viva debe preservarse
        assert cache.get("live_key") == "alive_value"
        # Las entradas muertas deben haber sido eliminadas
        assert not exp_file.exists()
        assert not stale_tmp.exists()
        assert not zero_file.exists()
