"""
# ==============================================================================
# 🔒 MOTOR DE SEGURIDAD Y DRM DE HARDWARE: SecureCore (@IA_ValleSeco_bot)
# Desofuscación criptográfica, anclaje de hardware y protección anti-tamper
# Ubicación: /scripts/telegram-admin-bot/monitor/core_shield.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
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
    """
    Obtiene los identificadores físicos únicos y firmes del hardware del servidor.
    Se basa exclusivamente en la Placa Base (DMI/BIOS), UUID del Sistema Operativo,
    Arquitectura y Hostname, evitando cualquier tarjeta de red para prevenir conflictos.
    """
    components = []

    # 1. Machine ID único del sistema operativo (permanente en /etc/machine-id)
    mid_file = Path("/etc/machine-id")
    if mid_file.exists():
        try:
            components.append(mid_file.read_text(encoding="utf-8").strip())
        except Exception:
            components.append("NO_MID")
    else:
        components.append("NO_MID")

    # 2. DMI Hardware Motherboard / Fabricante / BIOS (firmware de placa base)
    for prop in ("sys_vendor", "product_name", "board_name", "bios_vendor", "bios_version"):
        dmi_p = Path(f"/sys/class/dmi/id/{prop}")
        if dmi_p.exists():
            try:
                val = dmi_p.read_text(encoding="utf-8").strip()
                if val:
                    components.append(val)
            except Exception:
                pass

    # 3. Hostname y Arquitectura de CPU
    components.append(platform.node())
    components.append(platform.machine())
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
    _custom_token: Optional[str] = None
    _state: str = "INIT"  # "OPERATIONAL", "FIRST_BOOT_PENDING", "PENDING_VALIDATION", "TRIPPED"
    _active_serial: Optional[str] = None
    _serial_timestamp: int = 0
    _hw_components: List[str] = []
    _hw_key: bytes = b""

    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(SecureCore, cls).__new__(cls)
            cls._instance._init_drm_layer()
        return cls._instance

    def _init_drm_layer(self) -> None:
        """Inicializa la capa DRM verificando el anclaje físico de hardware."""
        self._hw_components = _get_hardware_components()
        self._hw_key = _derive_hardware_key(self._hw_components)

        # 1. Si no existe .sys_anchor -> Estado FIRST_BOOT_PENDING
        if not ANCHOR_FILE.exists():
            self._state = "FIRST_BOOT_PENDING"
            hw_fp = ":".join(self._hw_components)
            self._active_serial, self._serial_timestamp = self._generate_challenge(hw_fp)
            logger.warning(f"🔐 SecureCore: Primer arranque post-instalación detectado. Estado: {self._state}")
            self._send_activation_alert(self._active_serial)
            return

        # 2. Si existe .sys_anchor -> Intentar desencriptar la Master Key
        master_key, custom_tok = self._unwrap_anchor(self._hw_key)

        if master_key and hashlib.sha256(master_key).hexdigest() == _MASTER_KEY_HASH:
            self._unwrapped_master_key = master_key
            self._custom_token = custom_tok
            self._state = "OPERATIONAL"
            logger.info("🔒 SecureCore: Hardware verificado y anclaje criptográfico validado exitosamente.")
        else:
            # Anomalía o migración detectada -> Estado PENDING_VALIDATION
            self._state = "PENDING_VALIDATION"
            hw_fp = ":".join(self._hw_components)
            self._active_serial, self._serial_timestamp = self._generate_challenge(hw_fp)
            logger.warning("⚠️ SecureCore: Huella de hardware no coincide. Entrando en protocolo de re-validación...")
            self._send_migration_alert(self._active_serial)

    def _write_anchor(self, hw_key: bytes, custom_token: Optional[str] = None) -> bool:
        """Envuelve la Master Key y opcionalmente un token personalizado con la clave de hardware."""
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

            if custom_token:
                tok_bytes = bytearray()
                for i, b in enumerate(custom_token.encode("utf-8")):
                    k = hw_key[i % len(hw_key)]
                    v = b ^ k
                    rotated = ((v << 3) & 0xFF) | (v >> 5)
                    tok_bytes.append(rotated)
                data["tb"] = base64.b64encode(bytes(tok_bytes)).decode("ascii")
                data["ts"] = hmac.new(hw_key, bytes(tok_bytes), hashlib.sha256).hexdigest()

            ANCHOR_FILE.write_text(json.dumps(data, indent=2), encoding="utf-8")
            os.sync()
            return True
        except Exception as e:
            logger.error(f"Error escribiendo anclaje local: {e}")
            return False

    def _unwrap_anchor(self, hw_key: bytes) -> Tuple[Optional[bytes], Optional[str]]:
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

    def _generate_challenge(self, hw_fingerprint: str) -> Tuple[str, int]:
        """Genera un Serial dinámico antifalsificación con ventana de tiempo de 10 min."""
        ts = int(time.time())
        raw = f"{hw_fingerprint}:{ts}:{_CHALLENGE_SALT.hex()}".encode("utf-8")
        h = hashlib.sha256(raw).hexdigest()[:16].upper()
        serial = f"AUTH-{h[:4]}-{h[4:8]}-{h[8:12]}-{h[12:16]}"
        return serial, ts

    def verify_challenge(self, entered_serial: str) -> bool:
        """Verifica la validez y expiración del Serial de validación."""
        if not self._active_serial or not self._serial_timestamp:
            return False
        now = int(time.time())
        if now - self._serial_timestamp > 600 or now < self._serial_timestamp:
            return False
        return hmac.compare_digest(entered_serial.strip().upper(), self._active_serial.strip().upper())

    def _send_activation_alert(self, serial: str) -> None:
        """Envía la alerta de primer arranque para solicitar activación al Owner."""
        canary_token = _get_canary_token()
        if not canary_token:
            return
        hostname = platform.node()
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        msg = (
            "🔐 <b>[ACTIVACIÓN REQUERIDA] Monitor Valle Seco</b>\n"
            f"Se ha detectado una nueva instalación en el servidor <code>{hostname}</code>.\n\n"
            f"🖥️ <b>Host:</b> <code>{hostname}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            f"🔑 <b>Serial de Activación:</b>\n"
            f"<code>{serial}</code>\n\n"
            "⏳ <b>Tiempo Límite:</b> <b>10 Minutos</b>\n\n"
            "<i>📌 <b>Instrucción:</b> Responda directamente a este bot con el <b>Serial exacto</b> o use <code>/activar {serial}</code> para anclar el hardware.</i>"
        )
        self._dispatch_canary_message(canary_token, msg, alert_type="first_boot")

    def _send_migration_alert(self, serial: str) -> None:
        """Envía la alerta de migración/anomalía al Owner."""
        canary_token = _get_canary_token()
        if not canary_token:
            return
        hostname = platform.node()
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        msg = (
            "⚠️ <b>[ALERTA DE SEGURIDAD] Entorno Modificado / Migración</b>\n"
            f"Se ha detectado un cambio en la huella física de hardware en <code>{hostname}</code>.\n\n"
            f"🔑 <b>Serial de Validación:</b>\n"
            f"<code>{serial}</code>\n\n"
            "⏳ <b>Tiempo Límite:</b> <b>10 Minutos</b>\n\n"
            "<i>📌 <b>Instrucción:</b> Si se trata de una migración autorizada, responda con el <b>Serial exacto</b> o use <code>/activar {serial}</code> para re-vincular el hardware.</i>"
        )
        self._dispatch_canary_message(canary_token, msg, alert_type="migration")

    def _dispatch_canary_message(self, canary_token: str, text: str, alert_type: str = "alert") -> None:
        """Despacha un mensaje de emergencia vía Token Canario con soporte multi-proxy y limitación de frecuencia."""
        # Evitar re-envío en bucle: máximo 1 alerta por tipo cada 10 minutos (600s)
        debounce_file = Path(f"/tmp/.last_canary_{alert_type}")
        now = int(time.time())
        if debounce_file.exists():
            try:
                last_ts = int(debounce_file.read_text().strip())
                if now - last_ts < 600:
                    logger.debug(f"Alerta canaria ({alert_type}) omitida por límite de frecuencia (debounce).")
                    return
            except Exception:
                pass

        url = f"https://api.telegram.org/bot{canary_token}/sendMessage"
        payload = {"chat_id": IMMUTABLE_OWNER_ID, "text": text, "parse_mode": "HTML"}

        # Cargar proxies si existen
        proxies_to_test = [None]
        config_p = BASE_DIR / "config" / "config.json"
        if config_p.exists():
            try:
                with open(config_p, "r", encoding="utf-8") as f:
                    data = json.load(f)
                    for p in data.get("proxies", []):
                        p_url = p.get("url") if isinstance(p, dict) else p
                        if p_url and p_url not in proxies_to_test:
                            proxies_to_test.append(p_url)
            except Exception:
                pass

        for p_target in proxies_to_test:
            try:
                with httpx.Client(proxy=p_target, timeout=8.0) as client:
                    resp = client.post(url, json=payload)
                    if resp.status_code == 200 and resp.json().get("ok"):
                        logger.info(f"Alerta de emergencia ({alert_type}) despachada exitosamente al Owner.")
                        try:
                            debounce_file.write_text(str(now))
                        except Exception:
                            pass
                        return
            except Exception:
                continue
        logger.warning("No se pudo entregar la alerta de emergencia tras probar conexión directa y proxies.")

    def activate_hardware(self, entered_serial: str) -> Tuple[bool, str]:
        """Procesa la validación del Serial, ancla la Master Key al hardware y activa el bot."""
        clean_serial = entered_serial.strip().upper()
        # Normalizar serial si viene sin prefijo
        if not clean_serial.startswith("AUTH-") and len(clean_serial) == 19:
            clean_serial = f"AUTH-{clean_serial}"

        if not self.verify_challenge(clean_serial):
            if not self._active_serial:
                return False, "No hay ningún desafío de activación pendiente en este momento."
            now = int(time.time())
            if now - self._serial_timestamp > 600 or now < self._serial_timestamp:
                return False, "El Serial de activación ha expirado (límite 10 minutos). Reinicie el servicio para generar uno nuevo."
            return False, "Serial de activación incorrecto. Verifique el código recibido por Telegram."

        if not self._hw_key:
            self._hw_components = _get_hardware_components()
            self._hw_key = _derive_hardware_key(self._hw_components)

        success = self._write_anchor(self._hw_key, custom_token=self._custom_token)
        if success:
            self._unwrapped_master_key = _CORE_MASTER_KEY
            self._state = "OPERATIONAL"
            self._active_serial = None
            self._serial_timestamp = 0
            logger.info("✅ SecureCore: Anclaje de hardware exitoso. Bot activado en modo OPERATIONAL.")
            return True, "✅ [ACTIVACIÓN EXITOSA] Hardware anclado correctamente a la máquina local."
        return False, "Error al escribir el archivo de anclaje de hardware."

    def migrate_token(self, new_token: str) -> Tuple[bool, str]:
        """
        Módulo de Migración de Token en Tiempo Real (/migrar_token).
        Valida el nuevo token contra Telegram y lo cifra con la Clave de Hardware actual.
        """
        new_token_clean = new_token.strip()
        if ":" not in new_token_clean or len(new_token_clean) < 30:
            return False, "El formato del token no es válido (debe contener ':' y la longitud de BotFather)."

        # 1. Validar nuevo token contra la API oficial de Telegram
        try:
            with httpx.Client(timeout=8.0) as client:
                r = client.get(f"https://api.telegram.org/bot{new_token_clean}/getMe")
                if r.status_code != 200 or not r.json().get("ok"):
                    return False, f"El token fue rechazado por Telegram (HTTP {r.status_code}): {r.text}"
                bot_info = r.json().get("result", {})
                bot_username = bot_info.get("username", "Desconocido")
        except Exception as e:
            return False, f"Fallo al conectar con Telegram para verificar el nuevo token: {e}"

        # 2. Cifrar el nuevo token con la Clave de Hardware actual y guardarlo en .sys_anchor
        if not self._hw_key:
            self._hw_key = _derive_hardware_key(_get_hardware_components())

        success = self._write_anchor(self._hw_key, custom_token=new_token_clean)
        if not success:
            return False, "Error interno al re-cifrar el nuevo token con el DRM de hardware."

        self._custom_token = new_token_clean
        logger.info(f"✅ Token migrado exitosamente hacia @{bot_username} y re-cifrado con DRM.")
        return True, f"✅ <b>Identidad migrada exitosamente</b> hacia <code>@{bot_username}</code> y re-cifrada con DRM de hardware."

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
        self._custom_token = None
        try:
            if ANCHOR_FILE.exists():
                ANCHOR_FILE.write_text(os.urandom(128).hex(), encoding="utf-8")
                os.sync()
            logger.critical(f"🔒 DEAD MAN'S SWITCH ACTIVADO: {reason}. Anclaje destruido.")
            return True
        except Exception as e:
            logger.error(f"Error en invalidación de anclaje: {e}")
            return False

    @property
    def state(self) -> str:
        return self._state

    @property
    def custom_token(self) -> Optional[str]:
        return self._custom_token


# Instancia única global
_ENGINE = SecureCore()


def get_core_status() -> str:
    """Devuelve el estado actual de seguridad del núcleo."""
    return _ENGINE.state


def is_core_operational() -> bool:
    """Indica si el bot ha completado la validación de hardware y está operativo."""
    return _ENGINE.state == "OPERATIONAL"


def activate_hardware_first_boot(serial: str) -> Tuple[bool, str]:
    """Valida el serial del Owner y activa el anclaje de hardware en primer arranque."""
    return _ENGINE.activate_hardware(serial)


def migrate_core_token(new_token: str) -> Tuple[bool, str]:
    """Migra el token de Telegram y lo re-cifra con DRM de hardware."""
    return _ENGINE.migrate_token(new_token)


def get_core_owner_id() -> int:
    """Devuelve el ID del Owner verificado por hardware."""
    try:
        val = _ENGINE.deobfuscate(_O_CIPHER)
        return int(val)
    except Exception:
        return IMMUTABLE_OWNER_ID


def get_core_bot_token() -> str:
    """Devuelve el Token del Bot (personalizado o predeterminado) verificado por hardware."""
    if _ENGINE.custom_token:
        return _ENGINE.custom_token
    token = _ENGINE.deobfuscate(_T_CIPHER)
    if token.startswith("FAIL_SILENT_") or token.startswith("ERR_CORRUPT_"):
        canary = _get_canary_token()
        if canary:
            return canary
    return token


def get_core_repo_url() -> str:
    """Devuelve la URL oficial del Repositorio Git."""
    url = _ENGINE.deobfuscate(_R_CIPHER)
    if not url or not url.startswith("http") or url.startswith("FAIL_SILENT_"):
        return "https://github.com/britojq/tgbot-pyt-bashfull.git"
    return url


def get_core_branch() -> str:
    """Devuelve la rama oficial de Git."""
    branch = _ENGINE.deobfuscate(_B_CIPHER)
    if not branch or branch.startswith("FAIL_SILENT_"):
        return "master"
    return branch


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
