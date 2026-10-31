"""
Gestión y almacenamiento seguro de anclaje de hardware y control-flow flattening.
"""
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
CHALLENGE_FILE = VAULT_DIR / ".active_challenge"


def init_vault_dir() -> None:
    try:
        VAULT_DIR.mkdir(parents=True, exist_ok=True)
        os.chmod(VAULT_DIR, 0o750)
    except Exception:
        pass


def write_anchor(hw_key: bytes, custom_token: Optional[str] = None) -> bool:
    """Envuelve la Master Key y opcionalmente un token personalizado con la clave de hardware."""
    init_vault_dir()
    try:
        res = bytearray()
        for i, b in enumerate(_CORE_MASTER_KEY):
            k = hw_key[i % len(hw_key)]
            v = b ^ k
            rotated = ((v << 3) & 0xFF) | (v >> 5)
            res.append(rotated)
        blob = base64.b64encode(bytes(res)).decode("ascii")
        sig = hmac.new(hw_key, bytes(res), hashlib.sha256).hexdigest()

        data = {
            "b": blob,
            "s": sig,
            "t": int(time.time()),
            "node": platform.node()
        }

        if custom_token and not custom_token.startswith("["):
            tok_bytes = bytearray()
            for i, b in enumerate(custom_token.encode("utf-8")):
                k = hw_key[i % len(hw_key)]
                v = b ^ k
                rotated = ((v << 3) & 0xFF) | (v >> 5)
                tok_bytes.append(rotated)
            data["tb"] = base64.b64encode(bytes(tok_bytes)).decode("ascii")
            data["ts"] = hmac.new(hw_key, bytes(tok_bytes), hashlib.sha256).hexdigest()

        ANCHOR_FILE.write_text(json.dumps(data, indent=2), encoding="utf-8")
        try:
            os.chmod(ANCHOR_FILE, 0o640)
        except Exception:
            pass
        os.sync()
        return True
    except Exception as e:
        logger.error(f"Error escribiendo anclaje local: {e}")
        return False


def unwrap_anchor(hw_key: bytes) -> Tuple[Optional[bytes], Optional[str]]:
    """Intenta desencriptar la Master Key y token personalizado desde .sys_anchor."""
    if not ANCHOR_FILE.exists():
        return None, None
    try:
        data = json.loads(ANCHOR_FILE.read_text(encoding="utf-8"))
        raw = base64.b64decode(data["b"])
        expected_sig = data["s"]
        computed_sig = hmac.new(hw_key, raw, hashlib.sha256).hexdigest()

        if not hmac.compare_digest(computed_sig, expected_sig):
            return None, None

        res = bytearray()
        for i, b in enumerate(raw):
            k = hw_key[i % len(hw_key)]
            unrotated = ((b >> 3) | ((b << 5) & 0xFF)) & 0xFF
            v = unrotated ^ k
            res.append(v)

        res_bytes = bytes(res)
        if hashlib.sha256(res_bytes).hexdigest() != _MASTER_KEY_HASH:
            return None, None

        custom_tok = None
        if "tb" in data and "ts" in data:
            raw_tb = base64.b64decode(data["tb"])
            if hmac.compare_digest(hmac.new(hw_key, raw_tb, hashlib.sha256).hexdigest(), data["ts"]):
                tok_res = bytearray()
                for i, b in enumerate(raw_tb):
                    k = hw_key[i % len(hw_key)]
                    unrotated = ((b >> 3) | ((b << 5) & 0xFF)) & 0xFF
                    tok_res.append(unrotated ^ k)
                custom_tok = tok_res.decode("utf-8")

        return res_bytes, custom_tok
    except Exception:
        return None, None


def generate_challenge(hw_fingerprint: str) -> Tuple[str, int]:
    """Genera o recupera un Serial dinámico antifalsificación con ventana de 10 min."""
    init_vault_dir()
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
        "hw": hashlib.sha256(hw_fingerprint.encode("utf-8") + _CHALLENGE_SALT).hexdigest()
    }
    try:
        CHALLENGE_FILE.write_text(json.dumps(ch_data, indent=2), encoding="utf-8")
        try:
            os.chmod(CHALLENGE_FILE, 0o640)
        except Exception:
            pass
    except Exception:
        pass

    return serial, now


def deobfuscate_payload(cipher_blob: str, master_key: Optional[bytes], state: str) -> str:
    """Desofusca un payload usando la Master Key validada por entorno."""
    if state != "OPERATIONAL" or not master_key:
        return "FAIL_SILENT_" + hashlib.sha256(os.urandom(16)).hexdigest()[:24]

    st = 0x10
    out_bytes = bytearray()
    raw_cipher = b""
    key_len = len(master_key)
    iter_val = 13

    while st != 0x00:
        if not eval_opaque_gate(iter_val):
            st = 0xFF
            continue

        if st == 0x10:
            try:
                raw_cipher = base64.b64decode(cipher_blob)
                st = 0x20
            except Exception:
                st = 0xEE

        elif st == 0x20:
            for i, b in enumerate(raw_cipher):
                k = master_key[i % key_len]
                unrotated = ((b >> 3) | ((b << 5) & 0xFF)) & 0xFF
                v = unrotated ^ k
                out_bytes.append(v)
            st = 0x30

        elif st == 0x30:
            st = 0x00

        elif st == 0xEE:
            return "FAIL_SILENT_" + os.urandom(8).hex()

        iter_val += 1

    try:
        return out_bytes.decode("utf-8")
    except Exception:
        return "ERR_CORRUPT_" + os.urandom(8).hex()


def invalidate_hardware_anchor(reason: str = "Aislamiento detectado") -> bool:
    """Invalida firma de entorno y purga archivo."""
    try:
        if ANCHOR_FILE.exists():
            ANCHOR_FILE.write_text(os.urandom(128).hex(), encoding="utf-8")
            os.sync()
        logger.critical(f"🔒 PROTOCOLO DE CONTENCIÓN ACTIVADO: {reason}. Firma invalidada.")
        return True
    except Exception as e:
        logger.error(f"Error en invalidación de firma: {e}")
        return False
