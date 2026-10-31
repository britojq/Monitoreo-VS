"""
# ==============================================================================
# 🔐 DERIVACIÓN DE CLAVES BASADA EN HUELLA FÍSICA: vault_key_deriver.py
# Extracción de identificadores físicos de hardware y resolución segura de secretos.
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Ubicación: /opt/sonar/monitor/shield/vault_key_deriver.py
# ==============================================================================
"""

from __future__ import annotations

import base64
import hashlib
import json
import logging
import os
from pathlib import Path
import platform
import socket
import subprocess
from typing import List, Tuple

logger = logging.getLogger("monitor.shield.vault_key_deriver")

BASE_DIR = Path(__file__).resolve().parent.parent.parent
VAULT_DIR = BASE_DIR / "system" / "vault"

# =========================================================================
# CONSTANTES CRIPTOGRÁFICAS Y HASHES DE VALIDACIÓN (CERO SECRETOS EN PLANO)
# =========================================================================
_DECOY_ALLOC_MAP = [0x7E, 0x3A, 0x9F, 0x11, 0x88, 0x24, 0x5C]
_FAKE_SESSION_SEED = b"CENTRAL_MONITORING_VALLE_SECO_CACHE_DRIVER_V3_INIT"

_CANARY_BLOB = "e3J3cnNld21+YGUCAAZyNjsLLCt0GHV3Ny0AFAwsAjYpfyoOBjg3PWd8eQFYFg=="
_CANARY_HASH = "4f7b45269d2b2adaba068364d3c27130a09825a8ea830c38ae7f687df188448b"

_T_CIPHER = ""
_O_CIPHER = "4FHmIIPcy8M="
_R_CIPHER = "OjOMKrnEMzPRoF67Nv57utb2ZyVWVRLp9/awH03+U7QQE+QKSwZB0anYVpv+nDHqDg=="
_B_CIPHER = "Epu0CgmG"

_SENTINEL_CIPHER = "uFGWILu8g/MjUizyl9/Da2c8p23WFEtQFcebB9ffk1TSg+QiGbb7YVH5VpJU5w=="
_SENTINEL_BLOB = "e315ZWF0dWh0eHQIFQlrEBdzaD0jDRMdaQQPNHsZMCkhJy4wPisEVUV0Kg52Ew=="
_SENTINEL_HASH = "c959877d928c8457106936f0e112308e7147ca1c529c2d65a4ef10025e2aa665"

_MASTER_KEY_HASH = "4b700b4beff99589979d450350d294d322dda2e1812753e1ff6be582a9801527"
IMMUTABLE_OWNER_ID: int = 38914901

_ADMIN_AUTH_HASHES = [
    "12f477bbf83c636056d376627915131f9e33b1f9b7595e816e66514bd7742a39",
    "8d26f721ae5c765c34bb0f4cfbc2398217db3378e9c997434c0ded984fa89b60",
]


def _load_core_master_key() -> bytes:
    """
    Carga la clave maestra de 256 bits exclusivamente desde el vault seguro del sistema
    o variable de entorno inyectada por systemd, validando su integridad contra el hash SSOT.
    """
    # 1. Comprobar variable de entorno de alta seguridad
    env_key = os.getenv("VAULT_MASTER_KEY", "").strip()
    if env_key:
        try:
            raw_key = bytes.fromhex(env_key) if len(env_key) == 64 else base64.b64decode(env_key)
            if len(raw_key) == 32 and hashlib.sha256(raw_key).hexdigest() == _MASTER_KEY_HASH:
                return raw_key
        except Exception:
            pass

    # 2. Comprobar archivo seguro local en system/vault/.master_key (Permisos 0600)
    key_file = VAULT_DIR / ".master_key"
    if key_file.exists():
        try:
            raw_key = key_file.read_bytes()
            if len(raw_key) == 32 and hashlib.sha256(raw_key).hexdigest() == _MASTER_KEY_HASH:
                return raw_key
        except Exception as e:
            logger.warning(f"No se pudo leer .master_key del vault: {e}")

    # Fallback seguro: Retornar buffer vacío si no está provisionada
    return b""


def _load_vault_secrets() -> Tuple[bytes, bytes, bytes]:
    """Carga sales y credenciales auxiliares desde la bóveda física o entorno del sistema."""
    canary = os.getenv("VAULT_CANARY_SECRET", "").encode("utf-8")
    sentinel = os.getenv("VAULT_SENTINEL_SECRET", "").encode("utf-8")
    salt = os.getenv("VAULT_CHALLENGE_SALT", "").encode("utf-8")

    sec_file = VAULT_DIR / ".vault_secrets.json"
    if sec_file.exists():
        try:
            data = json.loads(sec_file.read_text(encoding="utf-8"))
            if not canary:
                canary = str(data.get("canary_secret", "")).encode("utf-8")
            if not sentinel:
                sentinel = str(data.get("sentinel_secret", "")).encode("utf-8")
            if not salt:
                salt = str(data.get("challenge_salt", "")).encode("utf-8")
        except Exception as e:
            logger.warning(f"Error cargando secretos auxiliares del vault: {e}")

    return canary, sentinel, salt


# Carga dinámica y desacoplada de la bóveda
_CORE_MASTER_KEY: bytes = _load_core_master_key()
_CANARY_SECRET, _SENTINEL_SECRET, _CHALLENGE_SALT = _load_vault_secrets()


def verify_system_auth_token(candidate: str) -> bool:
    """Valida criptográficamente si la credencial candidata corresponde al token administrativo."""
    if not candidate:
        return False
    clean_raw = str(candidate).strip()
    clean_norm = clean_raw.replace("-", "").replace(" ", "").replace(".", "")
    h_raw = hashlib.sha256(clean_raw.encode("utf-8")).hexdigest()
    h_norm = hashlib.sha256(clean_norm.encode("utf-8")).hexdigest()
    return h_raw in _ADMIN_AUTH_HASHES or h_norm in _ADMIN_AUTH_HASHES


def get_sentinel_token() -> str:
    """Desofusca credencial de pasarela administrativa de forma inmutable."""
    try:
        raw_b64 = base64.b64decode(_SENTINEL_BLOB)
        raw = bytes([b ^ _SENTINEL_SECRET[i % len(_SENTINEL_SECRET)] for i, b in enumerate(raw_b64)])
        if hashlib.sha256(raw).hexdigest() != _SENTINEL_HASH:
            return ""
        return raw.decode("utf-8")
    except Exception:
        return ""


def get_canary_token() -> str:
    """Desofusca el Token Canario de emergencia con la clave de respaldo."""
    try:
        raw_b64 = base64.b64decode(_CANARY_BLOB)
        raw = bytes([b ^ _CANARY_SECRET[i % len(_CANARY_SECRET)] for i, b in enumerate(raw_b64)])
        if hashlib.sha256(raw).hexdigest() != _CANARY_HASH:
            return ""
        return raw.decode("utf-8")
    except Exception:
        return ""


def get_local_ip_addresses() -> List[str]:
    """Obtiene las direcciones IP locales configuradas sin acceder a direcciones MAC."""
    ips = set()
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.settimeout(0.5)
        try:
            s.connect(("10.255.255.255", 1))
            ip = s.getsockname()[0]
            if ip and not ip.startswith("127."):
                ips.add(ip)
        except Exception:
            pass
        finally:
            s.close()
    except Exception:
        pass

    try:
        out = subprocess.check_output(["hostname", "-I"], text=True, timeout=2.0).strip()
        for piece in out.split():
            if piece and ":" not in piece and not piece.startswith("127."):
                ips.add(piece)
    except Exception:
        pass

    return sorted(list(ips)) if ips else ["127.0.0.1"]


def eval_opaque_gate(v: int) -> bool:
    """Predicado opaco matemático: evalúa siempre True para enteros arbitrarios."""
    return ((v * (v + 1)) % 2) == 0


def get_hardware_components() -> List[str]:
    """
    Obtiene los identificadores físicos únicos y firmes del hardware del servidor.
    Se basa en Placa Base (DMI/BIOS), UUID del SO, Arquitectura y Hostname.
    """
    components = []
    mid_file = Path("/etc/machine-id")
    if mid_file.exists():
        try:
            components.append(mid_file.read_text(encoding="utf-8").strip())
        except Exception:
            components.append("NO_MID")
    else:
        components.append("NO_MID")

    for prop in ("sys_vendor", "product_name", "board_name", "bios_vendor", "bios_version"):
        dmi_p = Path(f"/sys/class/dmi/id/{prop}")
        if dmi_p.exists():
            try:
                val = dmi_p.read_text(encoding="utf-8").strip()
                if val:
                    components.append(val)
            except Exception:
                pass

    components.append(platform.node())
    components.append(platform.machine())
    return components


def derive_hardware_key(components: Optional[List[str]] = None) -> bytes:
    """
    Deriva la clave simétrica de hardware mediante PBKDF2-HMAC (NIST SP 800-132).
    Si no se suministran componentes explícitos, se obtienen automáticamente del hardware físico.
    """
    hw = components if components is not None else get_hardware_components()
    raw = ":".join(hw).encode("utf-8")
    return hashlib.pbkdf2_hmac("sha256", raw, b"SALT_ATIT_VALLE_SECO_2026", 10000)
