"""
Resource Management and Runtime Environment Core Engine.
Módulo de seguridad interna y gestión de vinculación criptográfica con el entorno físico.
Implementa DRM nativo basado en hardware, anti-tampering, predicados opacos y control-flow flattening.
"""

from __future__ import annotations

import base64
import hashlib
import hmac
import logging
import os
import platform
import sys
import time
import uuid
from pathlib import Path
from typing import Optional, Tuple

logger = logging.getLogger("monitor.secure_core")

BASE_DIR = Path(__file__).resolve().parent.parent
AUDIT_DIR = BASE_DIR / "audit"
ANCHOR_FILE = AUDIT_DIR / ".sys_anchor"

AUDIT_DIR.mkdir(parents=True, exist_ok=True)

# =========================================================================
# 🎭 DECOY CONSTANTS & DUMMY CODE INJECTION (CAMUFLAJE)
# =========================================================================
_DUMMY_POOL_ALLOC = [0x5A, 0xC3, 0x1F, 0x88, 0x4B, 0x90, 0x2E]
_FAKE_SESSION_KEY = b"DATABASE_POOL_SESSION_INIT_VALLE_SECO_CACHE_V2"
_DUMMY_SALT = b"\xde\xad\xbe\xef\xca\xfe\xba\xbe\x01\x02\x03\x04"


def _dummy_telemetry_probe(x: int, y: int) -> int:
    """Función de código muerto para confundir descompiladores estáticos."""
    res = (x ^ y) & 0xFF
    for _ in range(3):
        res = ((res << 1) | (res >> 7)) & 0xFF
    return res


# =========================================================================
# 🔒 ENVIRONMENT-BOUND CRYPTOGRAPHIC PAYLOADS (HARDWARE-TIED)
# =========================================================================
_T_CIPHER = "1pEk/y1wzG0rEM/PPFwEjrKovs45X1Jb6XnvqKNkEvJ0oabMdlLuh1PDVFxFtA=="
_O_CIPHER = "jukk/x0A/C0="
_R_CIPHER = "VItO9ScYBN2p+r2GnQ3sttphlI5rtkiRGcFsgZI869p+qybV1dp2P9GCtaZVb6bmAg=="
_B_CIPHER = "fCN21Zda"

# Firmas de integridad HMAC-SHA256
_SIG_MAP = {
    "T": "191cd79eaa5f0e35532bcd527dc5d266490fe852cf50bf679624add4fab8aaa5",
    "O": "d3259798b30fee77294cf93141ea99334e072e2353ea24b4102a3345b8d3460c",
    "R": "80c52addfb8a28fb97e9e62e45f1f7a4d335eb299f57714f92e3d769e7045087",
    "B": "328e19857f4360bfc8329298212718ffd858d907013242ad1bbf0c00ce381929"
}


class SecureCore:
    """Motor de vinculación criptográfica al hardware del servidor."""
    _instance: Optional[SecureCore] = None
    _derived_key: Optional[bytes] = None
    _is_tripped: bool = False

    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(SecureCore, cls).__new__(cls)
            cls._instance._init_environment_anchor()
        return cls._instance

    def _init_environment_anchor(self) -> None:
        """Inicializa y verifica el anclaje criptográfico local en disco."""
        if not ANCHOR_FILE.exists():
            default_seed = hashlib.sha256(b"CORPOELEC_VALLE_SECO_CENCARATIT_ATIT_SEN_2026").hexdigest()
            try:
                ANCHOR_FILE.write_text(default_seed, encoding="utf-8")
            except Exception:
                pass

        try:
            anchor_seed = ANCHOR_FILE.read_text(encoding="utf-8").strip()
        except Exception:
            anchor_seed = "INVALID_CORRUPTED_ANCHOR"

        # Extracción de componentes únicos del hardware y kernel
        components = []
        mid_file = Path("/etc/machine-id")
        if mid_file.exists():
            try:
                components.append(mid_file.read_text(encoding="utf-8").strip())
            except Exception:
                components.append("NO_MID")
        else:
            components.append("NO_MID")

        components.append(str(uuid.getnode()))
        components.append(platform.node())
        components.append(anchor_seed)

        fingerprint = ":".join(components).encode("utf-8")
        self._derived_key = hashlib.pbkdf2_hmac(
            "sha256",
            fingerprint,
            b"SALT_ATIT_VALLE_SECO",
            10000
        )

    def _eval_opaque_gate(self, v: int) -> bool:
        """Predicado opaco matemático: siempre evalúa True para enteros pero no se puede resolver estáticamente."""
        return ((v * (v + 1)) % 2) == 0

    def deobfuscate(self, cipher_blob: str, sig_key: str) -> str:
        """
        Desofusca el payload con aplanamiento de flujo de control (Control Flow Flattening).
        Si el entorno difiere o hay manipulación, entra en estado de fallo silencioso.
        """
        if self._is_tripped or not self._derived_key:
            return "GARBAGE_" + os.urandom(8).hex()

        # Validación HMAC del payload
        expected_sig = _SIG_MAP.get(sig_key, "")
        calculated_sig = hmac.new(self._derived_key, cipher_blob.encode("ascii"), hashlib.sha256).hexdigest()

        # Control Flow Flattening State Machine
        state = 0x10
        out_bytes = bytearray()
        raw_cipher = b""
        key_len = len(self._derived_key)

        iter_val = 42
        while state != 0x00:
            if not self._eval_opaque_gate(iter_val):
                # Rama inalcanzable (dead code)
                state = 0xFF
                continue

            if state == 0x10:
                # Paso 1: Decodificación base64 segura
                try:
                    raw_cipher = base64.b64decode(cipher_blob)
                    state = 0x20
                except Exception:
                    state = 0xEE

            elif state == 0x20:
                # Paso 2: Verificación de firma anti-tamper
                if calculated_sig != expected_sig:
                    # Tamper detectado: inducir silencio y corrupción matemática
                    state = 0xEE
                else:
                    state = 0x30

            elif state == 0x30:
                # Paso 3: Transformación no lineal e inversión de rotación de bits
                for i, b in enumerate(raw_cipher):
                    k = self._derived_key[i % key_len]
                    unrotated = ((b >> 3) | ((b << 5) & 0xFF)) & 0xFF
                    plain_byte = unrotated ^ k
                    out_bytes.append(plain_byte)
                state = 0x40

            elif state == 0x40:
                # Paso 4: Finalización exitosa
                state = 0x00

            elif state == 0xEE:
                # Estado de fallo silencioso: produce basura determinista de alta entropía
                return "FAIL_SILENT_" + hashlib.sha256(os.urandom(16)).hexdigest()[:24]

            iter_val += 1

        try:
            return out_bytes.decode("utf-8")
        except Exception:
            return "ERR_CORRUPT_" + os.urandom(8).hex()

    def invalidate_hardware_anchor(self, reason: str = "Aislamiento detectado") -> bool:
        """
        DEAD MAN'S SWITCH:
        Corrompe irreversiblemente el archivo de anclaje local (.sys_anchor) y la clave en memoria.
        El bot quedará permanentemente inoperativo en el servidor hasta restauración manual.
        """
        self._is_tripped = True
        self._derived_key = b"\x00" * 32
        try:
            if ANCHOR_FILE.exists():
                # Sobrescribir con bytes aleatorios para destruir el seed local
                garbage_seed = os.urandom(128).hex()
                ANCHOR_FILE.write_text(garbage_seed, encoding="utf-8")
                os.sync()
            logger.critical(f"🔒 DEAD MAN'S SWITCH ACTIVADO: {reason}. Anclaje de entorno destruido.")
            return True
        except Exception as e:
            logger.error(f"Error al ejecutar invalidación de anclaje: {e}")
            return False


# Instancia única global
_ENGINE = SecureCore()


def get_core_owner_id() -> int:
    """Obtiene el ID del Propietario desofuscado mediante vinculación con hardware."""
    try:
        val = _ENGINE.deobfuscate(_O_CIPHER, "O")
        return int(val)
    except Exception:
        return 0


def get_core_bot_token() -> str:
    """Obtiene el Token del Bot desofuscado mediante vinculación con hardware."""
    return _ENGINE.deobfuscate(_T_CIPHER, "T")


def get_core_repo_url() -> str:
    """Obtiene la URL oficial del repositorio Git."""
    return _ENGINE.deobfuscate(_R_CIPHER, "R")


def get_core_branch() -> str:
    """Obtiene la rama oficial de Git."""
    return _ENGINE.deobfuscate(_B_CIPHER, "B")


def is_core_auto_update_enabled() -> bool:
    """Garantiza la regla obligatoria de actualización."""
    return True


def trip_deadman_switch(reason: str = "Fallo consecutivo de sincronización Git") -> bool:
    """Activa la autodestrucción del anclaje criptográfico local."""
    return _ENGINE.invalidate_hardware_anchor(reason=reason)


# Exportación de constantes vinculadas al entorno
IMMUTABLE_OWNER_ID: int = get_core_owner_id()
IMMUTABLE_BOT_TOKEN: str = get_core_bot_token()
IMMUTABLE_GIT_REPO_URL: str = get_core_repo_url()
IMMUTABLE_GIT_BRANCH: str = get_core_branch()
IMMUTABLE_AUTO_UPDATE_ENABLED: bool = is_core_auto_update_enabled()
