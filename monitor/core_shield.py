"""
Módulo de Seguridad, DRM de Entorno y Protocolo de Re-Validación Remota (SecureCore).
Implementa:
1. Desofuscación Vinculada Criptográficamente al Hardware (Environment-Bound Key Wrapping).
2. Detección de Manipulación (Anti-Tamper) con Control Flow Flattening y Predicados Opacos.
3. Token Canario de Emergencia para Notificación al Owner ante Migraciones o Anomalías.
4. Protocolo de Re-Validación Remota Human-in-the-Loop con Serial Antifalsificación (Challenge).
5. Interruptor de Autodestrucción Local de Anclaje (Dead Man's Switch).
"""

from __future__ import annotations

import asyncio
import base64
import hashlib
import hmac
import json
import logging
import os
import platform
import sys
import time
import uuid
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import httpx

logger = logging.getLogger("monitor.secure_core")

BASE_DIR = Path(__file__).resolve().parent.parent
AUDIT_DIR = BASE_DIR / "audit"
ANCHOR_FILE = AUDIT_DIR / ".sys_anchor"

AUDIT_DIR.mkdir(parents=True, exist_ok=True)

# =========================================================================
# 🎭 DECOY CONSTANTS & DUMMY TELEMETRY PROBES (CAMUFLAJE DE SEGURIDAD)
# =========================================================================
_DECOY_ALLOC_MAP = [0x7E, 0x3A, 0x9F, 0x11, 0x88, 0x24, 0x5C]
_FAKE_SESSION_SEED = b"CORPOELEC_VALLE_SECO_CACHE_DRIVER_V3_INIT"
_CHALLENGE_SALT = b"ATIT_VALLE_SECO_CHALLENGE_SALT_2026"
_CANARY_SECRET = b"CENCARATIT_CANARY_FALLBACK_EMERGENCY_KEY_2026"

_CANARY_BLOB = "e3J3cnNld21+YGUCAAZyNjsLLCt0GHV3Ny0AFAwsAjYpfyoOBjg3PWd8eQFYFg=="
_CANARY_HASH = "4f7b45269d2b2adaba068364d3c27130a09825a8ea830c38ae7f687df188448b"

# =========================================================================
# 🔒 MASTER ENCRYPTED PAYLOADS (DEOBFUSCATED VIA WRAPPED MASTER KEY)
# =========================================================================
_T_CIPHER = "uCnmILOs+4NTSizyl6+Tgr4/TWUEvAgjB04zNnymqpwaGWQT6I7ZaSuZt2HuRw=="
_O_CIPHER = "4FHmIIPcy8M="
_R_CIPHER = "OjOMKrnEMzPRoF67Nv57utb2ZyVWVRLp9/awH03+U7QQE+QKSwZB0anYVpv+nDHqDg=="
_B_CIPHER = "Epu0CgmG"

# Master key de 32 bytes ensamblada internamente
_M_CHUNKS = [
    bytes([0x2f, 0x12, 0xe5, 0x35, 0x44, 0xa2, 0x49, 0x49]),
    bytes([0x5d, 0x7d, 0xbf, 0x1f, 0xb3, 0xbd, 0x41, 0x34]),
    bytes([0xb5, 0xb3, 0xc3, 0xc6, 0xb8, 0xc3, 0x36, 0x52]),
    bytes([0x94, 0xaf, 0x39, 0x97, 0xce, 0xbd, 0x05, 0xe2])
]
_CORE_MASTER_KEY = b"".join(_M_CHUNKS)
_MASTER_KEY_HASH = "4b700b4beff99589979d450350d294d322dda2e1812753e1ff6be582a9801527"

# Constante de Owner inmutable
IMMUTABLE_OWNER_ID: int = 38914901


def _eval_opaque_gate(v: int) -> bool:
    """Predicado opaco matemático: evalúa siempre True para enteros arbitrarios."""
    return ((v * (v + 1)) % 2) == 0


def _get_hardware_components() -> List[str]:
    """Obtiene los identificadores físicos únicos del hardware del servidor."""
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
    return components


def _derive_hardware_key(components: List[str]) -> bytes:
    """Deriva la clave de hardware mediante PBKDF2-HMAC."""
    raw = ":".join(components).encode("utf-8")
    return hashlib.pbkdf2_hmac("sha256", raw, b"SALT_ATIT_VALLE_SECO_2026", 10000)


def _get_canary_token() -> str:
    """Desofusca el Token Canario de emergencia con la clave de respaldo."""
    try:
        raw_b64 = base64.b64decode(_CANARY_BLOB)
        raw = bytes([b ^ _CANARY_SECRET[i % len(_CANARY_SECRET)] for i, b in enumerate(raw_b64)])
        if hashlib.sha256(raw).hexdigest() != _CANARY_HASH:
            return ""
        return raw.decode("utf-8")
    except Exception:
        return ""


class SecureCore:
    """Motor de seguridad DRM y orquestador del protocolo de validación remota."""
    _instance: Optional[SecureCore] = None
    _unwrapped_master_key: Optional[bytes] = None
    _state: str = "INIT"  # "OPERATIONAL", "PENDING_VALIDATION", "TRIPPED"

    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(SecureCore, cls).__new__(cls)
            cls._instance._init_drm_layer()
        return cls._instance

    def _init_drm_layer(self) -> None:
        """Inicializa la capa DRM verificando el anclaje físico de hardware."""
        hw_comps = _get_hardware_components()
        hw_key = _derive_hardware_key(hw_comps)

        # Si el archivo de anclaje no existe en el servidor inicial, crearlo
        if not ANCHOR_FILE.exists():
            self._write_anchor(hw_key)

        # Intentar desencriptar la Master Key usando el Hardware Key actual
        master_key = self._unwrap_anchor(hw_key)

        if master_key and hashlib.sha256(master_key).hexdigest() == _MASTER_KEY_HASH:
            self._unwrapped_master_key = master_key
            self._state = "OPERATIONAL"
            logger.info("🔒 SecureCore: Hardware verificado y anclaje criptográfico validado exitosamente.")
        else:
            # Anomalía o migración detectada -> Estado PENDING_VALIDATION
            self._state = "PENDING_VALIDATION"
            logger.warning("⚠️ SecureCore: Huella de hardware no coincide. Entrando en protocolo de re-validación...")
            self._trigger_emergency_protocol(hw_comps, hw_key)

    def _write_anchor(self, hw_key: bytes) -> bool:
        """Envuelve la Master Key con la clave de hardware actual y la guarda en .sys_anchor."""
        try:
            res = bytearray()
            for i, b in enumerate(_CORE_MASTER_KEY):
                k = hw_key[i % len(hw_key)]
                v = b ^ k
                rotated = ((v << 3) & 0xFF) | (v >> 5)
                res.append(rotated)
            blob = base64.b64encode(bytes(res)).decode("ascii")
            sig = hmac.new(hw_key, bytes(res), hashlib.sha256).hexdigest()
            data = {"b": blob, "s": sig, "t": int(time.time())}
            ANCHOR_FILE.write_text(json.dumps(data, indent=2), encoding="utf-8")
            os.sync()
            return True
        except Exception as e:
            logger.error(f"Error escribiendo anclaje local: {e}")
            return False

    def _unwrap_anchor(self, hw_key: bytes) -> Optional[bytes]:
        """Intenta desencriptar la Master Key desde .sys_anchor con la clave de hardware actual."""
        if not ANCHOR_FILE.exists():
            return None
        try:
            data = json.loads(ANCHOR_FILE.read_text(encoding="utf-8"))
            raw = base64.b64decode(data["b"])
            expected_sig = data["s"]
            computed_sig = hmac.new(hw_key, raw, hashlib.sha256).hexdigest()

            if not hmac.compare_digest(computed_sig, expected_sig):
                return None

            res = bytearray()
            for i, b in enumerate(raw):
                k = hw_key[i % len(hw_key)]
                unrotated = ((b >> 3) | ((b << 5) & 0xFF)) & 0xFF
                v = unrotated ^ k
                res.append(v)

            res_bytes = bytes(res)
            if hashlib.sha256(res_bytes).hexdigest() != _MASTER_KEY_HASH:
                return None
            return res_bytes
        except Exception:
            return None

    def _generate_challenge(self, hw_fingerprint: str) -> Tuple[str, int]:
        """Genera un Serial dinámico antifalsificación con ventana de tiempo de 10 min."""
        ts = int(time.time())
        raw = f"{hw_fingerprint}:{ts}:{_CHALLENGE_SALT.hex()}".encode("utf-8")
        h = hashlib.sha256(raw).hexdigest()[:16].upper()
        serial = f"AUTH-{h[:4]}-{h[4:8]}-{h[8:12]}-{h[12:16]}"
        return serial, ts

    def _verify_challenge(self, hw_fingerprint: str, entered_serial: str, generated_ts: int) -> bool:
        """Verifica la validez y expiración del Serial de validación."""
        now = int(time.time())
        if now - generated_ts > 600 or now < generated_ts:
            return False
        raw = f"{hw_fingerprint}:{generated_ts}:{_CHALLENGE_SALT.hex()}".encode("utf-8")
        h = hashlib.sha256(raw).hexdigest()[:16].upper()
        expected = f"AUTH-{h[:4]}-{h[4:8]}-{h[8:12]}-{h[12:16]}"
        return hmac.compare_digest(entered_serial.strip().upper(), expected)

    def _trigger_emergency_protocol(self, hw_comps: List[str], hw_key: bytes) -> None:
        """Ejecuta el protocolo de alerta y espera de re-validación al Owner."""
        hw_fp = ":".join(hw_comps)
        serial, ts = self._generate_challenge(hw_fp)
        canary_token = _get_canary_token()

        if not canary_token:
            logger.critical("❌ SecureCore: Token Canario de emergencia no disponible.")
            self.invalidate_hardware_anchor(reason="Fallo de Token Canario en entorno no autorizado")
            return

        # 1. Enviar notificación de emergencia vía Token Canario
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        alert_msg = (
            "⚠️ <b>[ALERTA DE SEGURIDAD] Entorno Modificado / Migración Detectada</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "Se ha detectado un cambio en la huella física de hardware o sistema operativo.\n\n"
            f"🖥️ <b>Host:</b> <code>{platform.node()}</code>\n"
            f"🆔 <b>Nodo:</b> <code>{uuid.getnode()}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            f"🔑 <b>Serial de Validación:</b>\n"
            f"<code>{serial}</code>\n\n"
            "⏳ <b>Tiempo Límite de Respuesta:</b> <b>10 Minutos</b>\n\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "<i>📌 <b>Instrucción:</b> Si se trata de una migración legítima autorizada, responda a este mensaje con el <b>Serial exacto</b> para re-vincular el bot al nuevo hardware. "
            "Si no responde en 10 minutos o el serial es incorrecto, el sistema ejecutará el protocolo de autodestrucción local (Dead Man's Switch).</i>"
        )

        try:
            with httpx.Client(timeout=10.0) as client:
                r = client.post(
                    f"https://api.telegram.org/bot{canary_token}/sendMessage",
                    json={"chat_id": IMMUTABLE_OWNER_ID, "text": alert_msg, "parse_mode": "HTML"}
                )
                if r.status_code == 200:
                    logger.info("📡 Notificación de emergencia enviada exitosamente al Owner.")
                else:
                    logger.warning(f"No se pudo enviar alerta canaria: HTTP {r.status_code}")
        except Exception as e:
            logger.error(f"Fallo enviando alerta de emergencia al Owner: {e}")

        # 2. Iniciar listener de re-validación con timeout de 10 minutos (600s)
        success = self._run_canary_listener(canary_token, hw_fp, serial, ts, timeout_seconds=600)

        if success:
            logger.info("✅ SecureCore: Re-validación del Owner exitosa. Re-anclando Master Key al nuevo hardware...")
            self._write_anchor(hw_key)
            self._unwrapped_master_key = _CORE_MASTER_KEY
            self._state = "OPERATIONAL"
            # Notificar éxito al Owner
            try:
                with httpx.Client(timeout=10.0) as client:
                    client.post(
                        f"https://api.telegram.org/bot{canary_token}/sendMessage",
                        json={
                            "chat_id": IMMUTABLE_OWNER_ID,
                            "text": "✅ <b>[RE-VALIDACIÓN EXITOSA]</b>\nEl nuevo hardware ha sido autenticado y re-vinculado. El bot reanuda operaciones normales.",
                            "parse_mode": "HTML"
                        }
                    )
            except Exception:
                pass
        else:
            logger.critical("⛔ SecureCore: Re-validación fallida o expirada. Ejecutando autodestrucción...")
            self.invalidate_hardware_anchor(reason="Timeout de 10m o serial incorrecto en re-validación remota")
            sys.exit(1)

    def _run_canary_listener(self, canary_token: str, hw_fp: str, expected_serial: str, ts: int, timeout_seconds: int = 600) -> bool:
        """Escucha de forma aislada las respuestas exclusivas del Owner durante el challenge."""
        t_start = time.time()
        last_update_id = 0
        get_updates_url = f"https://api.telegram.org/bot{canary_token}/getUpdates"

        # Obtener update_id inicial
        try:
            with httpx.Client(timeout=8.0) as client:
                r = client.get(f"{get_updates_url}?offset=-1")
                if r.status_code == 200:
                    results = r.json().get("result", [])
                    if results:
                        last_update_id = results[-1].get("update_id", 0) + 1
        except Exception:
            pass

        while (time.time() - t_start) < timeout_seconds:
            try:
                with httpx.Client(timeout=12.0) as client:
                    r = client.get(f"{get_updates_url}?offset={last_update_id}&timeout=8")
                    if r.status_code == 200:
                        updates = r.json().get("result", [])
                        for u in updates:
                            last_update_id = max(last_update_id, u.get("update_id", 0) + 1)
                            msg = u.get("message", {})
                            user_id = msg.get("from", {}).get("id")
                            text = (msg.get("text") or "").strip()

                            # Escuchar ÚNICAMENTE al Owner
                            if user_id == IMMUTABLE_OWNER_ID:
                                if self._verify_challenge(hw_fp, text, ts):
                                    return True
                                elif text.startswith("AUTH-"):
                                    logger.warning(f"Serial incorrecto recibido: {text}")
            except Exception as e:
                logger.warning(f"Error en polling de listener canario: {e}")

            time.sleep(2.5)

        return False

    def deobfuscate(self, cipher_blob: str) -> str:
        """Desofusca un payload usando la Master Key validada por hardware."""
        if self._state != "OPERATIONAL" or not self._unwrapped_master_key:
            return "FAIL_SILENT_" + hashlib.sha256(os.urandom(16)).hexdigest()[:24]

        # Control Flow Flattening State Machine
        state = 0x10
        out_bytes = bytearray()
        raw_cipher = b""
        key_len = len(self._unwrapped_master_key)
        iter_val = 13

        while state != 0x00:
            if not _eval_opaque_gate(iter_val):
                state = 0xFF
                continue

            if state == 0x10:
                try:
                    raw_cipher = base64.b64decode(cipher_blob)
                    state = 0x20
                except Exception:
                    state = 0xEE

            elif state == 0x20:
                for i, b in enumerate(raw_cipher):
                    k = self._unwrapped_master_key[i % key_len]
                    unrotated = ((b >> 3) | ((b << 5) & 0xFF)) & 0xFF
                    v = unrotated ^ k
                    out_bytes.append(v)
                state = 0x30

            elif state == 0x30:
                state = 0x00

            elif state == 0xEE:
                return "FAIL_SILENT_" + os.urandom(8).hex()

            iter_val += 1

        try:
            return out_bytes.decode("utf-8")
        except Exception:
            return "ERR_CORRUPT_" + os.urandom(8).hex()

    def invalidate_hardware_anchor(self, reason: str = "Aislamiento detectado") -> bool:
        """DEAD MAN'S SWITCH: Destruye el anclaje de hardware y purga claves en memoria."""
        self._state = "TRIPPED"
        self._unwrapped_master_key = b"\x00" * 32
        try:
            if ANCHOR_FILE.exists():
                ANCHOR_FILE.write_text(os.urandom(128).hex(), encoding="utf-8")
                os.sync()
            logger.critical(f"🔒 DEAD MAN'S SWITCH ACTIVADO: {reason}. Anclaje destruido.")
            return True
        except Exception as e:
            logger.error(f"Error en invalidación de anclaje: {e}")
            return False


# Instancia única global
_ENGINE = SecureCore()


def get_core_owner_id() -> int:
    """Devuelve el ID del Owner verificado por hardware."""
    try:
        val = _ENGINE.deobfuscate(_O_CIPHER)
        return int(val)
    except Exception:
        return IMMUTABLE_OWNER_ID


def get_core_bot_token() -> str:
    """Devuelve el Token del Bot verificado por hardware."""
    return _ENGINE.deobfuscate(_T_CIPHER)


def get_core_repo_url() -> str:
    """Devuelve la URL oficial del Repositorio Git."""
    return _ENGINE.deobfuscate(_R_CIPHER)


def get_core_branch() -> str:
    """Devuelve la rama oficial de Git."""
    return _ENGINE.deobfuscate(_B_CIPHER)


def is_core_auto_update_enabled() -> bool:
    """Garantiza la regla inmutable de actualización."""
    return True


def trip_deadman_switch(reason: str = "Fallo consecutivo de sincronización Git") -> bool:
    """Activa la autodestrucción del anclaje criptográfico local."""
    return _ENGINE.invalidate_hardware_anchor(reason=reason)


# Exportación de constantes evaluadas en tiempo de ejecución
IMMUTABLE_BOT_TOKEN: str = get_core_bot_token()
IMMUTABLE_GIT_REPO_URL: str = get_core_repo_url()
IMMUTABLE_GIT_BRANCH: str = get_core_branch()
IMMUTABLE_AUTO_UPDATE_ENABLED: bool = is_core_auto_update_enabled()
