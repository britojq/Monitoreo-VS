"""
# ==============================================================================
# 🔐 ALMACENAMIENTO SEGURO Y ANCLAJE DE HARDWARE: vault_storage.py
# Persistencia cifrada en reposo mediante AES-256-GCM (NIST SP 800-38D).
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Ubicación: /opt/sonar/monitor/shield/vault_storage.py
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
import platform
import time
from typing import Optional, Tuple
import uuid

from monitor.domain.exceptions import (
    DecryptionException,
    VaultIntegrityError,
    VaultReadError,
    VaultWriteError,
)
from monitor.shield.standard_encryptor import StandardEncryptor
from monitor.shield.vault_key_deriver import (
    _CHALLENGE_SALT,
    _CORE_MASTER_KEY,
    _MASTER_KEY_HASH,
    eval_opaque_gate,
)

logger = logging.getLogger("monitor.shield.vault_storage")

BASE_DIR = Path(__file__).resolve().parent.parent.parent
VAULT_DIR = BASE_DIR / "system" / "vault"
ANCHOR_FILE = VAULT_DIR / ".sys_anchor"

# Desafíos en RAM volátil (tmpfs) para erradicar archivos planos en el repositorio
CHALLENGE_FILE = Path("/dev/shm/.monitoreo_challenge.json")


def init_vault_dir() -> None:
    """Inicializa el directorio de la bóveda garantizando permisos restrictivos."""
    try:
        VAULT_DIR.mkdir(parents=True, exist_ok=True)
        os.chmod(VAULT_DIR, 0o750)
    except Exception as e:
        logger.warning(f"No se pudieron ajustar permisos de vault: {e}")


def write_anchor(hw_key: bytes, custom_token: Optional[str] = None) -> bool:
    """
    Envuelve la Master Key y opcionalmente el token corporativo usando AES-256-GCM
    anclado a la clave criptográfica del silicio del host (NIST SP 800-38D).
    
    Args:
        hw_key: Clave simétrica de 32 bytes derivada del hardware del host.
        custom_token: Token de bot administrativo a encapsular de forma inmutable.
        
    Returns:
        bool: True si el anclaje fue escrito y sincronizado a disco exitosamente.
        
    Raises:
        VaultWriteError: Si ocurre un error de permisos o I/O al escribir el anclaje.
    """
    init_vault_dir()
    if not _CORE_MASTER_KEY:
        logger.error("No se puede escribir anclaje: _CORE_MASTER_KEY no está aprovisionada")
        return False

    try:
        # Cifrado autenticado AES-256-GCM de la Master Key
        b64_master_blob = StandardEncryptor.encrypt_to_base64(
            plaintext=_CORE_MASTER_KEY,
            key=hw_key,
            associated_data=b"MASTER_KEY_V2",
        )
        sig = hmac.new(hw_key, _CORE_MASTER_KEY, hashlib.sha256).hexdigest()

        data = {
            "v": "2.0",
            "algo": "AES-256-GCM",
            "b": b64_master_blob,
            "s": sig,
            "t": int(time.time()),
            "node": platform.node(),
        }

        target_token = custom_token
        if not target_token and ANCHOR_FILE.exists():
            try:
                _, existing_token = unwrap_anchor(hw_key)
                if existing_token:
                    target_token = existing_token
            except Exception:
                pass

        if target_token and not target_token.startswith("["):
            tok_bytes = target_token.encode("utf-8")
            data["tb"] = StandardEncryptor.encrypt_to_base64(
                plaintext=tok_bytes,
                key=hw_key,
                associated_data=b"TELEGRAM_BOT_TOKEN_V2",
            )
            data["ts"] = hmac.new(hw_key, tok_bytes, hashlib.sha256).hexdigest()

        ANCHOR_FILE.write_text(json.dumps(data, indent=2), encoding="utf-8")
        try:
            os.chmod(ANCHOR_FILE, 0o640)
        except Exception:
            pass
        os.sync()
        logger.info("✅ Anclaje de hardware sellado exitosamente con AES-256-GCM v2.0")
        return True
    except PermissionError as e:
        logger.error(f"Permisos insuficientes al escribir anclaje de hardware en {ANCHOR_FILE}: {e}")
        raise VaultWriteError("Permisos insuficientes al escribir anclaje en vault") from e
    except Exception as e:
        logger.exception(f"Fallo crítico al escribir anclaje de hardware: {e}")
        raise VaultWriteError(f"Error inesperado al escribir anclaje: {e}") from e


def unwrap_anchor(hw_key: bytes) -> Tuple[Optional[bytes], Optional[str]]:
    """
    Desencripta la Master Key y token personalizado desde .sys_anchor.
    Cumple con el estándar autenticado AES-256-GCM v2.0 NIST SP 800-38D.
    Rechaza de forma estricta cualquier payload no estándar o no autenticado.
    
    Args:
        hw_key: Clave simétrica de 32 bytes derivada del hardware físico.
        
    Returns:
        Tuple[Optional[bytes], Optional[str]]: (master_key_bytes, custom_token_string)
    """
    if not ANCHOR_FILE.exists():
        return None, None

    try:
        data = json.loads(ANCHOR_FILE.read_text(encoding="utf-8"))
    except Exception as e:
        logger.error(f"Error al leer estructura JSON de .sys_anchor: {e}")
        return None, None

    # Formato v2.0 Estándar AES-256-GCM autenticado
    if data.get("v") == "2.0" or data.get("algo") == "AES-256-GCM":
        try:
            master_key = StandardEncryptor.decrypt_from_base64(
                b64_string=data["b"],
                key=hw_key,
                associated_data=b"MASTER_KEY_V2",
            )
            if hashlib.sha256(master_key).hexdigest() != _MASTER_KEY_HASH:
                logger.error("Violación de integridad: hash de master key no coincide en formato v2")
                return None, None

            custom_tok: Optional[str] = None
            if "tb" in data:
                tok_bytes = StandardEncryptor.decrypt_from_base64(
                    b64_string=data["tb"],
                    key=hw_key,
                    associated_data=b"TELEGRAM_BOT_TOKEN_V2",
                )
                custom_tok = tok_bytes.decode("utf-8")

            return master_key, custom_tok
        except DecryptionException as e:
            logger.error(f"Error descifrando anclaje AES-256-GCM: {e}")
            return None, None
        except Exception as e:
            logger.error(f"Error inesperado al desencapsular vault v2: {e}")
            return None, None

    logger.error("Rechazando anclaje legacy no autenticado: formato AES-256-GCM v2.0 obligatorio.")
    return None, None


def generate_challenge(hw_fingerprint: str) -> Tuple[str, int]:
    """
    Genera o recupera un Serial dinámico antifalsificación con ventana de 10 min.
    Persiste exclusivamente en RAM volátil (/dev/shm) para erradicar archivos planos en el repo.
    """
    now = int(time.time())
    if CHALLENGE_FILE.exists():
        try:
            data = json.loads(CHALLENGE_FILE.read_text(encoding="utf-8"))
            ts = data.get("ts", 0)
            if now - ts <= 600 and ts <= now:
                return data.get("serial", ""), ts
        except Exception:
            pass

    u = uuid.uuid4().hex.upper()
    serial_body = f"{u[:4]}-{u[4:8]}-{u[8:12]}-{u[12:16]}"
    serial = f"AUTH-{serial_body}"

    ch_data = {
        "serial": serial,
        "ts": now,
        "hw": hashlib.sha256(hw_fingerprint.encode("utf-8") + _CHALLENGE_SALT).hexdigest(),
    }
    try:
        CHALLENGE_FILE.write_text(json.dumps(ch_data, indent=2), encoding="utf-8")
        try:
            os.chmod(CHALLENGE_FILE, 0o600)
        except Exception:
            pass
    except Exception as e:
        logger.debug(f"Aviso persistiendo challenge en volatile RAM: {e}")

    return serial, now


def deobfuscate_payload(
    cipher_blob: str,
    master_key: Optional[bytes],
    state: str,
    associated_data: Optional[bytes] = None,
) -> str:
    """
    Desofusca un payload cifrado usando la Master Key validada bajo estándar AES-256-GCM.
    
    Args:
        cipher_blob: String Base64 del payload cifrado con AES-256-GCM (NIST SP 800-38D).
        master_key: Clave maestra de 32 bytes (None si el escudo no está operacional).
        state: Estado actual del sistema ("OPERATIONAL", "TRIPPED", etc.).
        associated_data: Datos adicionales autenticados (AAD) opcionales.
    
    Returns:
        str: Cadena desofuscada en UTF-8, o "FAIL_SILENT_*" si el estado no es OPERATIONAL.
    
    Raises:
        ValueError: Si cipher_blob no es Base64 válido.
    
    Security:
        - Estándar criptográfico autenticado AES-256-GCM (erradica rotación de bits y XOR).
        - Requiere estado OPERATIONAL y master_key de 32 bytes validada contra SSOT.
        - Retorna token pseudoaleatorio en caso de tampering o fallo (anti-fingerprinting).
    
    Example:
        payload = deobfuscate_payload("base64data...", master_key, "OPERATIONAL")
        print(payload)
        "<OWNER_TELEGRAM_ID>"
    """
    if state != "OPERATIONAL" or not master_key:
        return "FAIL_SILENT_" + hashlib.sha256(os.urandom(16)).hexdigest()[:24]

    if not eval_opaque_gate(len(cipher_blob)):
        return "FAIL_SILENT_GATE"

    try:
        # Descifrado autenticado AES-256-GCM con soporte para etiquetas AAD conocidas
        candidates = [associated_data] if associated_data is not None else [b"OWNER_ID", b"BRANCH", b"PAYLOAD_AEAD", None]
        for ad in candidates:
            try:
                dec_bytes = StandardEncryptor.decrypt_from_base64(
                    b64_string=cipher_blob,
                    key=master_key,
                    associated_data=ad,
                )
                return dec_bytes.decode("utf-8")
            except Exception:
                continue

        return "FAIL_SILENT_" + hashlib.sha256(os.urandom(16)).hexdigest()[:24]
    except Exception as e:
        logger.error(f"Fallo al descifrar payload con AES-256-GCM: {e}")
        return "FAIL_SILENT_" + hashlib.sha256(os.urandom(16)).hexdigest()[:24]


def invalidate_hardware_anchor(reason: str = "Aislamiento detectado") -> bool:
    """
    Invalida de forma destructiva y permanente el anclaje de hardware ante intrusión o anomalía.
    Sobrescribe el archivo de anclaje con bytes criptográficos aleatorios y sincroniza a disco.
    """
    try:
        if ANCHOR_FILE.exists():
            ANCHOR_FILE.write_text(os.urandom(128).hex(), encoding="utf-8")
            os.sync()
        if CHALLENGE_FILE.exists():
            try:
                CHALLENGE_FILE.unlink()
            except Exception:
                pass
        logger.critical(f"🔒 PROTOCOLO DE CONTENCIÓN ACTIVADO: {reason}. Firma invalidada.")
        return True
    except Exception as e:
        logger.error(f"Error en invalidación de firma: {e}")
        return False
