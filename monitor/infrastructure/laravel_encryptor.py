"""
# ==============================================================================
# 🔐 DESCIFRADOR DE CREDENCIALES LARAVEL: laravel_encryptor.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Soporte dual: AES-256-CBC (compatible con Illuminate\\Encryption\\Encrypter)
# y AES-256-GCM AEAD (conforme a NIST SP 800-38D).
# Registro de Decisión Arquitectónica: docs/ADR_CRIPTO_LARAVEL_CBC_GCM.md
# Cumple con OWASP A02:2021 externalizando claves a variables de entorno.
# Hardening SEC-005: Modo estricto CRYPT_STRICT_MODE para forzar AEAD, verificación
# HMAC constante y fail-closed previo a unpadding, y cero fuga de secretos en logs.
# Ubicación: /opt/sonar/monitor/infrastructure/laravel_encryptor.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import base64
import hashlib
import hmac
import json
import logging
import os
from pathlib import Path
import re
from typing import Optional

from cryptography.hazmat.primitives import padding
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

from monitor.domain.exceptions import DecryptionException
from monitor.infrastructure.shm_cache import get_shm_cache

logger = logging.getLogger("monitor.infrastructure.laravel_encryptor")

BASE_DIR = Path(__file__).resolve().parent.parent.parent

_CACHED_APP_KEY: Optional[bytes] = None


def is_crypt_strict_mode() -> bool:
    """Retorna True si la directiva exige AES-256-GCM AEAD exclusivamente (SEC-005).

    Rechaza cualquier payload cifrado con AES-256-CBC si CRYPT_STRICT_MODE está activo.
    """
    raw = os.getenv("CRYPT_STRICT_MODE") or os.getenv("SONAR_CRYPT_STRICT_MODE") or "false"
    return raw.strip().lower() in ("true", "1", "yes", "on")


def get_laravel_app_key(force_reload: bool = False) -> bytes:
    """Obtiene y decodifica la clave criptográfica maestra de Laravel (AES-256-CBC/GCM).

    Prioriza estrictamente variables de entorno y archivos seguros del sistema
    operativo (/etc/default/monitoreo-vs) para cumplir con OWASP A02:2021 y
    erradicar la lectura directa de archivos planos en el árbol del código.

    Args:
        force_reload: Si es True, invalida la caché en memoria y relee la clave.

    Returns:
        bytes: Clave simétrica decodificada de 32 bytes para AES-256.

    Raises:
        DecryptionException: Si la clave no está aprovisionada o su longitud es inválida.

    Security Considerations:
        - Nunca persiste la clave en disco ni en logs (CWE-532).
        - Soporta formato 'base64:...' estándar de Laravel y cadenas directas.
    """
    global _CACHED_APP_KEY
    if _CACHED_APP_KEY is not None and not force_reload:
        return _CACHED_APP_KEY

    raw_key = ""

    # 1. Prioridad Máxima: Variables de entorno inyectadas
    raw_key = os.getenv("LARAVEL_APP_KEY") or os.getenv("APP_KEY") or ""

    # 2. Segunda Prioridad: Configuración segura de servicios del sistema operativo (0640)
    if not raw_key:
        sys_env = Path("/etc/default/monitoreo-vs")
        if sys_env.exists():
            try:
                for line in sys_env.read_text(encoding="utf-8", errors="ignore").splitlines():
                    line = line.strip()
                    if line.startswith("LARAVEL_APP_KEY=") and "=" in line:
                        raw_key = line.split("=", 1)[1].strip("\"' ")
                        break
                    if line.startswith("APP_KEY=") and "=" in line:
                        raw_key = line.split("=", 1)[1].strip("\"' ")
                        break
            except Exception as e_sys:
                logger.debug(f"Aviso leyendo clave desde {sys_env}: {type(e_sys).__name__}")

    # 3. Fallback de entorno local de desarrollo (solo si no se definió variable de entorno)
    if not raw_key:
        env_paths = [
            BASE_DIR / "web_portal" / ".env",
            Path("/opt/sonar/web_portal/.env"),
            BASE_DIR / ".env",
        ]
        for p in env_paths:
            if p.exists():
                try:
                    for line in p.read_text(encoding="utf-8", errors="ignore").splitlines():
                        line = line.strip()
                        if line.startswith("APP_KEY=") and "=" in line:
                            raw_key = line.split("=", 1)[1].strip("\"' ")
                            break
                except Exception as e:
                    logger.debug(f"Aviso leyendo APP_KEY de {p}: {type(e).__name__}")
            if raw_key:
                break

    if not raw_key:
        raise DecryptionException(
            "LARAVEL_APP_KEY / APP_KEY no configurada en entorno ni en /etc/default/monitoreo-vs"
        )

    if raw_key.startswith("base64:"):
        key_bytes = base64.b64decode(raw_key[7:])
    else:
        key_bytes = raw_key.encode("utf-8")

    if len(key_bytes) not in (16, 24, 32):
        raise DecryptionException(
            f"Longitud de clave Laravel no válida para AES ({len(key_bytes)} bytes). Se requieren 32 bytes."
        )

    _CACHED_APP_KEY = key_bytes
    return key_bytes


def _parse_php_serialized_string(raw: str) -> str:
    """Parsea de forma segura una cadena serializada de PHP: s:<length>:"<content>";

    Soporta contraseñas y textos que contienen comillas dobles, puntos y comas
    o caracteres multibyte (CWE-20 / CWE-704 / SEC-08).
    """
    if not (raw.startswith("s:") and ':"' in raw):
        return raw

    m = re.match(r"^s:(\d+):\"", raw)
    if not m:
        return raw

    raw_bytes = raw.encode("utf-8")
    m_bytes = re.match(rb"^s:(\d+):\"", raw_bytes)
    if m_bytes:
        b_len = int(m_bytes.group(1))
        b_start = m_bytes.end()
        if len(raw_bytes) >= b_start + b_len:
            content_bytes = raw_bytes[b_start : b_start + b_len]
            remainder = raw_bytes[b_start + b_len :]
            if remainder in (b'";', b'"', b''):
                return content_bytes.decode("utf-8", errors="replace")

    if raw.endswith('";'):
        return raw[m.end() : -2]
    if raw.endswith('"'):
        return raw[m.end() : -1]

    return raw


def decrypt_laravel_string(
    encrypted_str: str,
    strict: bool = False,
    strict_crypto: Optional[bool] = None,
) -> str:
    """Descifra una cadena generada por Crypt::encryptString() en Laravel (AES-256-CBC)

    o por el subsistema criptográfico NIST SP 800-38D (AES-256-GCM).
    Utiliza memoria compartida /dev/shm para acelerar consultas repetidas de credenciales SNMP.

    Hardening SEC-005:
    - CRYPT_STRICT_MODE: Rechaza cargas CBC heredadas forzando AEAD.
    - CBC Fail-Closed: Verifica HMAC en tiempo constante previo a invocar unpadder.
    - Cero fugas de secretos en registros de auditoría o excepciones.

    Args:
        encrypted_str: Cadena cifrada en base64 con el payload JSON.
        strict: Si True, levanta DecryptionException ante fallos en vez de retornar "".
        strict_crypto: Si se especifica, sobreescribe is_crypt_strict_mode() para el llamado.

    Returns:
        str: Cadena en texto plano o vacía si el descifrado falla (en modo no strict).

    Raises:
        DecryptionException: Si strict=True y el descifrado falla.
    """
    if not encrypted_str:
        return ""

    if strict_crypto is None:
        strict_crypto = is_crypt_strict_mode()

    cache = get_shm_cache()
    cache_key = f"cred_{hashlib.sha256(encrypted_str.encode('utf-8')).hexdigest()[:32]}"
    cached_val = cache.get(cache_key)
    if cached_val is not None and not strict_crypto:
        return str(cached_val)

    try:
        key = get_laravel_app_key()
        try:
            raw_json = base64.b64decode(encrypted_str, validate=True).decode("utf-8")
            payload = json.loads(raw_json)
        except Exception as e_dec:
            raise DecryptionException("Payload criptográfico base64/JSON inválido o malformado") from e_dec

        if not isinstance(payload, dict):
            raise DecryptionException("Estructura de payload criptográfico inválida (debe ser objeto JSON)")

        cipher_field = str(payload.get("cipher") or "").lower()
        mode_field = str(payload.get("mode") or "").upper()
        is_gcm = cipher_field in ("aes-256-gcm", "gcm") or mode_field == "GCM" or "nonce" in payload

        # 1. Soporte Nativo NIST SP 800-38D (AES-256-GCM AEAD)
        if is_gcm:
            nonce_b64 = payload.get("nonce")
            val_b64 = payload.get("value")
            if not nonce_b64 or not val_b64:
                raise DecryptionException("Payload GCM inválido: faltan campos requeridos nonce o value")

            nonce = base64.b64decode(nonce_b64)
            ciphertext = base64.b64decode(val_b64)
            if len(nonce) not in (12, 16):
                raise DecryptionException(f"Longitud de nonce inválida para AES-GCM ({len(nonce)} bytes)")

            aesgcm = AESGCM(key)
            plain_bytes = aesgcm.decrypt(nonce, ciphertext, None)
            val = plain_bytes.decode("utf-8", errors="ignore")
        else:
            # SEC-005: Rechazo obligatorio de CBC si el modo estricto está habilitado
            if strict_crypto:
                raise DecryptionException(
                    "Modo estricto criptográfico activo (CRYPT_STRICT_MODE): descifrado AES-256-CBC deshabilitado. "
                    "Se requiere AES-256-GCM AEAD conforme a NIST SP 800-38D."
                )

            # 2. Soporte Estándar Laravel (AES-256-CBC con HMAC-SHA256 Obligatorio)
            iv_b64 = payload.get("iv")
            val_b64 = payload.get("value")
            expected_mac = payload.get("mac")

            if not iv_b64 or not val_b64:
                raise DecryptionException("Payload CBC inválido: faltan campos iv o value")

            # F-06 / CWE-347: Fail-closed estricto. En CBC el MAC es OBLIGATORIO para verificar integridad.
            if not expected_mac or not isinstance(expected_mac, str) or len(expected_mac) != 64:
                raise DecryptionException("Firma HMAC-SHA256 ausente, truncada o inválida en payload CBC")

            calculated_mac = hmac.new(key, (iv_b64 + val_b64).encode("utf-8"), hashlib.sha256).hexdigest()
            if not hmac.compare_digest(calculated_mac, expected_mac):
                raise DecryptionException("Firma HMAC-SHA256 inválida o payload manipulado")

            iv = base64.b64decode(iv_b64)
            if len(iv) != 16:
                raise DecryptionException(f"Longitud de IV inválida para AES-CBC ({len(iv)} bytes)")

            ciphertext = base64.b64decode(val_b64)
            if len(ciphertext) == 0 or len(ciphertext) % 16 != 0:
                raise DecryptionException("Longitud de ciphertext inválida para bloque AES-CBC")

            cipher = Cipher(algorithms.AES(key), modes.CBC(iv))
            decryptor = cipher.decryptor()
            padded = decryptor.update(ciphertext) + decryptor.finalize()

            unpadder = padding.PKCS7(128).unpadder()
            unpadded = unpadder.update(padded) + unpadder.finalize()
            val = unpadded.decode("utf-8", errors="ignore")

        # SEC-08 (CWE-20 / CWE-704): Deserialización formal respetando comillas dobles y multibyte
        val = _parse_php_serialized_string(val)

        cache.set(cache_key, val, ttl=300.0)
        return val
    except DecryptionException as de:
        logger.error(f"Fallo al descifrar credencial Laravel: {de}")
        if strict:
            raise DecryptionException(f"Error crítico al descifrar credencial: {de}") from de
        return ""
    except Exception as e:
        logger.error(f"Fallo al descifrar credencial Laravel ({type(e).__name__})")
        if strict:
            raise DecryptionException(f"Error crítico al descifrar credencial: {type(e).__name__}") from e
        return ""


def encrypt_laravel_string_gcm(plain_text: str) -> str:
    """Cifra una cadena usando AES-256-GCM conforme a NIST SP 800-38D (AEAD).

    Incluye un nonce criptográfico de 12 bytes (96 bits) recomendado por NIST.

    Args:
        plain_text: Cadena en texto plano a cifrar.

    Returns:
        str: Blob codificado en base64 con payload JSON que incluye nonce, ciphertext y mode="GCM".

    Raises:
        DecryptionException: Si la clave criptográfica de Laravel no está disponible.
    """
    if not plain_text:
        return ""

    key = get_laravel_app_key()
    nonce = os.urandom(12)  # NIST SP 800-38D: 96 bits nonce
    aesgcm = AESGCM(key)
    ciphertext = aesgcm.encrypt(nonce, plain_text.encode("utf-8"), None)

    payload = json.dumps({
        "nonce": base64.b64encode(nonce).decode("utf-8"),
        "value": base64.b64encode(ciphertext).decode("utf-8"),
        "mode": "GCM",
        "cipher": "aes-256-gcm",
    })
    return base64.b64encode(payload.encode("utf-8")).decode("utf-8")


def encrypt_laravel_string(plain_text: str) -> str:
    """Cifra una cadena usando el esquema canónico de Laravel (AES-256-CBC con HMAC-SHA256).

    Genera un blob compatible con Illuminate\\Support\\Facades\\Crypt::decryptString().

    Args:
        plain_text: Cadena en texto plano a cifrar.

    Returns:
        str: Cadena codificada en base64 que contiene JSON con iv, value, mac y tag.

    Raises:
        DecryptionException: Si la clave criptográfica de Laravel no está disponible.
    """
    if not plain_text:
        return ""

    key = get_laravel_app_key()
    iv = os.urandom(16)

    # Pad PKCS7 para bloque de 128 bits
    padder = padding.PKCS7(128).padder()
    padded_data = padder.update(plain_text.encode("utf-8")) + padder.finalize()

    # Cifrado AES-256-CBC
    cipher = Cipher(algorithms.AES(key), modes.CBC(iv))
    encryptor = cipher.encryptor()
    ciphertext = encryptor.update(padded_data) + encryptor.finalize()

    iv_b64 = base64.b64encode(iv).decode("utf-8")
    val_b64 = base64.b64encode(ciphertext).decode("utf-8")

    # Firma HMAC-SHA256 sobre IV + Ciphertext (estándar Laravel Encrypter)
    mac = hmac.new(key, (iv_b64 + val_b64).encode("utf-8"), hashlib.sha256).hexdigest()

    payload = json.dumps({
        "iv": iv_b64,
        "value": val_b64,
        "mac": mac,
        "tag": "",
        "cipher": "aes-256-cbc",
    })
    return base64.b64encode(payload.encode("utf-8")).decode("utf-8")
