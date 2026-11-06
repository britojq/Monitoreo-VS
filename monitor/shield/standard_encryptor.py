"""
# ==============================================================================
# 🔐 SERVICIO DE CIFRADO ESTÁNDAR AES-256-GCM: standard_encryptor.py
# Implementación criptográfica autenticada (AEAD) bajo norma NIST SP 800-38D.
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Ubicación: /opt/sonar/monitor/shield/standard_encryptor.py
# ==============================================================================
"""

from __future__ import annotations

import base64
import os
from typing import Optional

from cryptography.exceptions import InvalidTag
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

from monitor.domain.exceptions import DecryptionException


class StandardEncryptor:
    """
    Motor criptográfico de estándar industrial AES-256-GCM con autenticación integrada.
    Reemplaza algoritmos propietarios garantizando integridad (AEAD) y resistencia a tampering.
    """

    NONCE_SIZE_BYTES: int = 12  # Recomendado por NIST para modo GCM
    TAG_SIZE_BYTES: int = 16    # Tamaño estándar de autenticación GCM

    @classmethod
    def encrypt(
        cls,
        plaintext: bytes,
        key: bytes,
        associated_data: Optional[bytes] = None,
    ) -> bytes:
        """
        Cifra datos en bruto usando AES-256 en modo Galois/Counter Mode (GCM).
        
        Args:
            plaintext: Datos en bytes a cifrar.
            key: Clave simétrica de exactamente 32 bytes (256 bits).
            associated_data: Datos adicionales autenticados (opcional, no confidencial).
            
        Returns:
            bytes: Nonce (12 bytes) concatenado con Ciphertext + Tag (16 bytes).
            
        Raises:
            ValueError: Si la longitud de la clave no es de 32 bytes.
        """
        if len(key) != 32:
            raise ValueError(f"La clave AES-256 debe tener exactamente 32 bytes (obtenido: {len(key)})")

        nonce = os.urandom(cls.NONCE_SIZE_BYTES)
        aesgcm = AESGCM(key)
        ciphertext_with_tag = aesgcm.encrypt(nonce, plaintext, associated_data)

        # Empaquetado binario: Nonce || Ciphertext+Tag
        return nonce + ciphertext_with_tag

    @classmethod
    def decrypt(
        cls,
        encrypted_blob: bytes,
        key: bytes,
        associated_data: Optional[bytes] = None,
    ) -> bytes:
        """
        Descifra y verifica la autenticidad de un blob cifrado con AES-256-GCM.
        
        Args:
            encrypted_blob: Nonce (12 bytes) || Ciphertext + Tag.
            key: Clave simétrica de 32 bytes.
            associated_data: Datos asociados que deben coincidir con la fase de cifrado.
            
        Returns:
            bytes: Datos originales descifrados.
            
        Raises:
            DecryptionException: Si los datos fueron manipulados, corruptos o la clave no coincide.
        """
        if len(key) != 32:
            raise ValueError(f"La clave AES-256 debe tener exactamente 32 bytes (obtenido: {len(key)})")

        min_len = cls.NONCE_SIZE_BYTES + cls.TAG_SIZE_BYTES
        if len(encrypted_blob) < min_len:
            raise DecryptionException("El payload cifrado es demasiado corto para contener Nonce y Tag GCM")

        nonce = encrypted_blob[:cls.NONCE_SIZE_BYTES]
        ciphertext_with_tag = encrypted_blob[cls.NONCE_SIZE_BYTES:]

        aesgcm = AESGCM(key)
        try:
            return aesgcm.decrypt(nonce, ciphertext_with_tag, associated_data)
        except InvalidTag as e:
            raise DecryptionException("Fallo de autenticación GCM: los datos han sido manipulados o la clave es incorrecta") from e
        except Exception as e:
            raise DecryptionException(f"Error inesperado al descifrar AES-256-GCM: {e}") from e

    @classmethod
    def encrypt_to_base64(
        cls,
        plaintext: bytes,
        key: bytes,
        associated_data: Optional[bytes] = None,
    ) -> str:
        """Helper para cifrar y codificar el resultado en Base64 UTF-8."""
        blob = cls.encrypt(plaintext, key, associated_data)
        return base64.b64encode(blob).decode("ascii")

    @classmethod
    def decrypt_from_base64(
        cls,
        b64_string: str,
        key: bytes,
        associated_data: Optional[bytes] = None,
    ) -> bytes:
        """Helper para decodificar desde Base64 y descifrar con verificación."""
        try:
            blob = base64.b64decode(b64_string)
        except Exception as e:
            raise DecryptionException(f"Formato Base64 inválido: {e}") from e
        return cls.decrypt(blob, key, associated_data)
