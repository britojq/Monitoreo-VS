"""
# ==============================================================================
# 🔐 DESCIFRADOR DE CREDENCIALES LARAVEL: laravel_encryptor.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Descifrado AES-256-CBC compatible con Illuminate\\Encryption\\Encrypter.
# Ubicación: /opt/sonar/monitor/infrastructure/laravel_encryptor.py
# ==============================================================================
"""

from __future__ import annotations

import base64
import json
import logging
from pathlib import Path
from typing import Optional

from cryptography.hazmat.primitives import padding
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

from monitor.domain.exceptions import DecryptionException
from monitor.infrastructure.shm_cache import get_shm_cache

logger = logging.getLogger("monitor.infrastructure.laravel_encryptor")

BASE_DIR = Path(__file__).resolve().parent.parent.parent

_CACHED_APP_KEY: Optional[bytes] = None


def get_laravel_app_key(force_reload: bool = False) -> bytes:
    """Extrae y decodifica la clave AES de Laravel desde el archivo .env."""
    global _CACHED_APP_KEY
    if _CACHED_APP_KEY is not None and not force_reload:
        return _CACHED_APP_KEY

    env_paths = [
        BASE_DIR / "web_portal" / ".env",
        Path("/opt/sonar/web_portal/.env"),
        BASE_DIR / ".env",
    ]

    raw_key = ""
    for p in env_paths:
        if p.exists():
            try:
                for line in p.read_text(encoding="utf-8", errors="ignore").splitlines():
                    line = line.strip()
                    if line.startswith("APP_KEY=") and "=" in line:
                        raw_key = line.split("=", 1)[1].strip("\"' ")
                        break
            except Exception as e:
                logger.debug(f"Aviso leyendo APP_KEY de {p}: {e}")
        if raw_key:
            break

    if not raw_key:
        raise DecryptionException("APP_KEY no configurado en web_portal/.env")

    if raw_key.startswith("base64:"):
        key_bytes = base64.b64decode(raw_key[7:])
    else:
        key_bytes = raw_key.encode("utf-8")

    _CACHED_APP_KEY = key_bytes
    return key_bytes


def decrypt_laravel_string(encrypted_str: str) -> str:
    """
    Descifra una cadena generada por Crypt::encryptString() en Laravel (AES-256-CBC).
    Utiliza memoria compartida /dev/shm para acelerar consultas repetidas de credenciales SNMP.
    """
    if not encrypted_str:
        return ""

    cache = get_shm_cache()
    cache_key = f"cred_{abs(hash(encrypted_str))}"
    cached_val = cache.get(cache_key)
    if cached_val is not None:
        return str(cached_val)

    try:
        key = get_laravel_app_key()
        payload = json.loads(base64.b64decode(encrypted_str).decode("utf-8"))
        iv = base64.b64decode(payload["iv"])
        ciphertext = base64.b64decode(payload["value"])

        cipher = Cipher(algorithms.AES(key), modes.CBC(iv))
        decryptor = cipher.decryptor()
        padded = decryptor.update(ciphertext) + decryptor.finalize()

        unpadder = padding.PKCS7(128).unpadder()
        unpadded = unpadder.update(padded) + unpadder.finalize()
        val = unpadded.decode("utf-8", errors="ignore")

        # Limpiar serialización de PHP si aplica (ej: s:6:"public";)
        if val.startswith("s:") and '"' in val:
            val = val.split('"')[1]

        cache.set(cache_key, val, ttl=300.0)
        return val
    except Exception as e:
        logger.debug(f"Fallo al descifrar cadena Laravel: {e}")
        return ""
