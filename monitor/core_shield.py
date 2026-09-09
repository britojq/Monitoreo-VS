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
CHALLENGE_FILE = AUDIT_DIR / ".active_challenge"

AUDIT_DIR.mkdir(parents=True, exist_ok=True)

# =========================================================================
# 🎭 DECOY CONSTANTS & DUMMY TELEMETRY PROBES (CAMUFLAJE DE SEGURIDAD)
# =========================================================================
_DECOY_ALLOC_MAP = [0x7E, 0x3A, 0x9F, 0x11, 0x88, 0x24, 0x5C]
_FAKE_SESSION_SEED = b"CENTRAL_MONITORING_VALLE_SECO_CACHE_DRIVER_V3_INIT"
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

# =========================================================================
# 🛡️ SENTINEL SECURITY GATEWAY (TOKEN OFUSCADO INMUTABLE)
# =========================================================================
_SENTINEL_CIPHER = "uFGWILu8g/MjUizyl9/Da2c8p23WFEtQFcebB9ffk1TSg+QiGbb7YVH5VpJU5w=="
_SENTINEL_SECRET = b"CENTRAL_MONITORING_VALLE_SECO_GATEWAY_2026_CORE"
_SENTINEL_BLOB = "e315ZWF0dWh0eHQIFQlrEBdzaD0jDRMdaQQPNHsZMCkhJy4wPisEVUV0Kg52Ew=="
_SENTINEL_HASH = "c959877d928c8457106936f0e112308e7147ca1c529c2d65a4ef10025e2aa665"

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


def _get_sentinel_token() -> str:
    """Desofusca el Token del Bot Centinela de forma inmutable."""
    try:
        raw_b64 = base64.b64decode(_SENTINEL_BLOB)
        raw = bytes([b ^ _SENTINEL_SECRET[i % len(_SENTINEL_SECRET)] for i, b in enumerate(raw_b64)])
        if hashlib.sha256(raw).hexdigest() != _SENTINEL_HASH:
            return ""
        return raw.decode("utf-8")
    except Exception:
        return ""


def get_sentinel_token() -> str:
    """Función pública de acceso seguro al Token del Bot Centinela."""
    return _get_sentinel_token()


def _get_local_ip_addresses() -> List[str]:
    """Obtiene las direcciones IP locales configuradas sin acceder a direcciones MAC."""
    ips = set()
    try:
        import socket
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
        import subprocess
        out = subprocess.check_output(["hostname", "-I"], text=True, timeout=2.0).strip()
        for piece in out.split():
            if piece and ":" not in piece and not piece.startswith("127."):
                ips.add(piece)
    except Exception:
        pass

    return sorted(list(ips)) if ips else ["127.0.0.1"]


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
            cfg_path = BASE_DIR / "config" / "config.json"
            if cfg_path.exists():
                try:
                    c_data = json.loads(cfg_path.read_text(encoding="utf-8"))
                    c_tok = c_data.get("bot_token", "").strip()
                    if c_tok and ":" in c_tok and len(c_tok) >= 30:
                        self._custom_token = c_tok
                except Exception:
                    pass
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
        """Genera o recupera un Serial dinámico antifalsificación con ventana de tiempo de 10 min."""
        now = int(time.time())
        if CHALLENGE_FILE.exists():
            try:
                c_data = json.loads(CHALLENGE_FILE.read_text(encoding="utf-8"))
                c_serial = c_data.get("serial", "")
                c_ts = int(c_data.get("ts", 0))
                if 0 <= now - c_ts < 600 and c_serial:
                    return c_serial, c_ts
            except Exception:
                pass

        ts = now
        raw = f"{hw_fingerprint}:{ts}:{_CHALLENGE_SALT.hex()}".encode("utf-8")
        h = hashlib.sha256(raw).hexdigest()[:16].upper()
        serial = f"AUTH-{h[:4]}-{h[4:8]}-{h[8:12]}-{h[12:16]}"
        try:
            CHALLENGE_FILE.write_text(json.dumps({"serial": serial, "ts": ts}), encoding="utf-8")
        except Exception:
            pass
        return serial, ts

    def verify_challenge(self, entered_serial: str) -> bool:
        """Verifica la validez y expiración del Serial de validación compartida entre procesos."""
        if not self._active_serial or not self._serial_timestamp:
            if CHALLENGE_FILE.exists():
                try:
                    c_data = json.loads(CHALLENGE_FILE.read_text(encoding="utf-8"))
                    self._active_serial = c_data.get("serial", "")
                    self._serial_timestamp = int(c_data.get("ts", 0))
                except Exception:
                    pass

        if not self._active_serial or not self._serial_timestamp:
            return False
        now = int(time.time())
        if now - self._serial_timestamp > 600 or now < self._serial_timestamp:
            return False
        return hmac.compare_digest(entered_serial.strip().upper(), self._active_serial.strip().upper())

    def _send_activation_alert(self, serial: str) -> None:
        """Envía la alerta de primer arranque para solicitar validación al Owner vía consola usando el Bot Principal."""
        bot_token = get_core_bot_token() or _get_canary_token()
        if not bot_token:
            return
        hostname = platform.node()
        ips_str = ", ".join(_get_local_ip_addresses())
        user_name = os.getenv("USER") or os.getenv("LOGNAME") or "system"
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        msg = (
            "🛡️ <b>[SEGURIDAD: NUEVA INSTALACIÓN DETECTADA]</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "Se ha detectado una nueva instalación o despliegue del sistema en este equipo.\n\n"
            f"🖥️ <b>Nombre del Servidor:</b> <code>{hostname}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
            f"👤 <b>Usuario Linux:</b> <code>{user_name}</code>\n"
            f"📁 <b>Ruta Base:</b> <code>{BASE_DIR}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "🔑 <b>Serial de Activación:</b>\n"
            f"<code>{serial}</code>\n\n"
            "⏳ <b>Ventana de Validación:</b> <b>10 Minutos</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "💻 <b>VALIDACIÓN REQUERIDA VÍA CONSOLA (SHELL):</b>\n"
            "Para activar y anclar la instalación en este equipo, inicie sesión por consola (SSH o terminal física) y ejecute:\n\n"
            f"<code>activar {serial}</code>\n\n"
            f"<i>(o: <code>/scripts/telegram-admin-bot/activar {serial}</code>)</i>"
        )
        self._dispatch_alert_message(bot_token, msg, alert_type="first_boot")

    def _send_migration_alert(self, serial: str) -> None:
        """Envía la alerta forense de copia/migración al Owner usando el Bot Principal."""
        bot_token = get_core_bot_token() or _get_canary_token()
        if not bot_token:
            return
        hostname = platform.node()
        ips_str = ", ".join(_get_local_ip_addresses())
        user_name = os.getenv("USER") or os.getenv("LOGNAME") or "system"
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        msg = (
            "🚨 <b>[SEGURIDAD: ALERTA DE COPIA / CLONACIÓN]</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "⚠️ <b>Se detectó una discrepancia en la huella de hardware física (Equipo Clonado o Copiado).</b>\n\n"
            f"🖥️ <b>Nombre del Servidor:</b> <code>{hostname}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
            f"👤 <b>Usuario Ejecutor:</b> <code>{user_name}</code>\n"
            f"📁 <b>Ruta en Disco:</b> <code>{BASE_DIR}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "🔑 <b>Serial de Validación:</b>\n"
            f"<code>{serial}</code>\n\n"
            "⏳ <b>Ventana de Validación:</b> <b>10 Minutos</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "💻 <b>VALIDACIÓN REQUERIDA VÍA CONSOLA (SHELL):</b>\n"
            "Para autorizar la instalación en este nuevo equipo y vincularlo a su hardware, ingrese por consola (SSH o terminal física) y ejecute:\n\n"
            f"<code>activar {serial}</code>\n\n"
            f"<i>(o: <code>/scripts/telegram-admin-bot/activar {serial}</code>)</i>"
        )
        self._dispatch_alert_message(bot_token, msg, alert_type="migration")

    def send_conflict_alert(self) -> None:
        """Envía alerta cuando se detecta que el bot se ha iniciado en dos sitios simultáneamente (Conflict 409)."""
        bot_token = get_core_bot_token() or _get_canary_token()
        if not bot_token:
            return
        hostname = platform.node()
        ips_str = ", ".join(_get_local_ip_addresses())
        user_name = os.getenv("USER") or os.getenv("LOGNAME") or "system"
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")

        hw_fp = ":".join(self._hw_components) if self._hw_components else hostname
        serial, _ = self._generate_challenge(hw_fp)

        msg = (
            "🚨 <b>[ALERTA: BOT INICIADO EN DOS SITIOS SIMULTÁNEAMENTE]</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "⚠️ <b>Se detectó un conflicto de sesión en Telegram (Conflict 409).</b>\n"
            "El bot ha sido iniciado en dos equipos a la vez con el mismo token.\n\n"
            f"🖥️ <b>Nombre del Equipo Local:</b> <code>{hostname}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
            f"👤 <b>Usuario Linux:</b> <code>{user_name}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "🔑 <b>Serial de Validación:</b>\n"
            f"<code>{serial}</code>\n\n"
            "⏳ <b>Ventana de Validación:</b> <b>10 Minutos</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "💻 <b>ACCIONES REQUERIDAS VÍA CONSOLA (SHELL):</b>\n"
            "1. Detenga el servicio en el equipo no deseado o duplicado (<code>sudo systemctl stop tg-admin-bot</code>).\n"
            "2. Si este es el servidor que debe operar activamente, valide la instalación vía consola ejecutando:\n\n"
            f"<code>activar {serial}</code>\n\n"
            f"<i>(o: <code>/scripts/telegram-admin-bot/activar {serial}</code>)</i>"
        )
        self._dispatch_alert_message(bot_token, msg, alert_type="conflict")

    def notify_console_activation_success(self, serial: str) -> None:
        """Notifica al Owner por Telegram tras una validación exitosa por consola."""
        bot_token = get_core_bot_token() or _get_canary_token()
        if not bot_token:
            return
        hostname = platform.node()
        ips_str = ", ".join(_get_local_ip_addresses())
        user_name = os.getenv("USER") or os.getenv("LOGNAME") or "system"
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        msg = (
            "✅ <b>[VALIDACIÓN EXITOSA VÍA CONSOLA]</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "El servidor ha sido validado y anclado al hardware físico exitosamente por shell.\n\n"
            f"🖥️ <b>Servidor:</b> <code>{hostname}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
            f"🔑 <b>Serial Aplicado:</b> <code>{serial}</code>\n"
            f"👤 <b>Usuario de Consola:</b> <code>{user_name}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
            "🚀 <i>El servicio de monitoreo (<code>tg-admin-bot</code>) fue reiniciado y se encuentra 100% OPERATIVO.</i>"
        )
        self._dispatch_alert_message(bot_token, msg, alert_type="activation_success")

    def force_hardware_anchor(self) -> Tuple[bool, str]:
        """Fuerza el anclaje físico de hardware en la máquina local (recuperación administrativa en consola)."""
        self._hw_components = _get_hardware_components()
        self._hw_key = _derive_hardware_key(self._hw_components)
        cfg_path = BASE_DIR / "config" / "config.json"
        if cfg_path.exists():
            try:
                c_data = json.loads(cfg_path.read_text(encoding="utf-8"))
                c_tok = c_data.get("bot_token", "").strip()
                if c_tok and ":" in c_tok and len(c_tok) >= 30:
                    self._custom_token = c_tok
            except Exception:
                pass
        success = self._write_anchor(self._hw_key, custom_token=self._custom_token)
        if success:
            self._unwrapped_master_key = _CORE_MASTER_KEY
            self._state = "OPERATIONAL"
            self._active_serial = None
            self._serial_timestamp = 0
            if CHALLENGE_FILE.exists():
                try:
                    CHALLENGE_FILE.unlink()
                except Exception:
                    pass
            logger.info("✅ SecureCore: Forzado de anclaje de hardware exitoso. Estado: OPERATIONAL.")
            return True, "✅ [FORZADO EXITOSO] Hardware anclado directamente a la máquina local."
        return False, "Error escribiendo el archivo de anclaje de hardware."

    def _dispatch_alert_message(
        self,
        token: str,
        text: str,
        alert_type: str = "alert"
    ) -> None:
        """Despacha un mensaje de alerta de seguridad directamente al Owner usando el Bot Principal y multi-proxy."""
        debounce_file = Path(f"/tmp/.last_security_{alert_type}")
        now = int(time.time())
        if debounce_file.exists():
            try:
                last_ts = int(debounce_file.read_text().strip())
                if now - last_ts < 180:
                    logger.debug(f"Alerta ({alert_type}) omitida por debounce.")
                    return
            except Exception:
                pass

        url = f"https://api.telegram.org/bot{token}/sendMessage"
        payload = {
            "chat_id": IMMUTABLE_OWNER_ID,
            "text": text,
            "parse_mode": "HTML"
        }

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

        bot_conf_p = BASE_DIR / "config" / "bot.conf"
        if bot_conf_p.exists():
            try:
                import urllib.parse
                b_lines = bot_conf_p.read_text(encoding="utf-8").splitlines()
                b_data = {}
                for l in b_lines:
                    l = l.strip()
                    if l and not l.startswith("#") and "=" in l:
                        k, v = l.split("=", 1)
                        b_data[k.strip()] = v.strip().strip("'\"")
                for letter in ("A", "B", "C", "D"):
                    ip = b_data.get(f"IPADDRPORTPROXY{letter}")
                    auth = b_data.get(f"USERPASSWDPROXY{letter}")
                    if ip:
                        if auth and ":" in auth:
                            u, pwd = auth.split(":", 1)
                            p_url = f"http://{urllib.parse.quote(u)}:{urllib.parse.quote(pwd)}@{ip}"
                        else:
                            p_url = f"http://{ip}"
                        if p_url not in proxies_to_test:
                            proxies_to_test.append(p_url)
            except Exception:
                pass

        for p_target in proxies_to_test:
            try:
                with httpx.Client(proxy=p_target, timeout=8.0) as client:
                    resp = client.post(url, json=payload)
                    if resp.status_code == 200 and resp.json().get("ok"):
                        logger.info(f"Alerta ({alert_type}) despachada exitosamente al Owner vía Bot Principal.")
                        try:
                            debounce_file.write_text(str(now))
                        except Exception:
                            pass
                        return
            except Exception:
                continue
        logger.warning(f"No se pudo entregar la alerta ({alert_type}) al Owner tras probar conexión directa y proxies.")

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
            if CHALLENGE_FILE.exists():
                try:
                    CHALLENGE_FILE.unlink()
                except Exception:
                    pass
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


def send_core_conflict_alert() -> None:
    """Despacha alerta de conflicto cuando el bot detecta que se inició en dos sitios a la vez."""
    _ENGINE.send_conflict_alert()


def notify_core_console_activation(serial: str) -> None:
    """Notifica al Owner por Telegram de una activación exitosa por consola."""
    _ENGINE.notify_console_activation_success(serial)


def force_core_hardware_anchor() -> Tuple[bool, str]:
    """Fuerza el anclaje del hardware en la máquina local por consola."""
    return _ENGINE.force_hardware_anchor()


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
    """Devuelve la URL oficial del Repositorio Git (Público para clientes/producción, Privado para desarrollo)."""
    # En desarrollo local (CENCARATIT), se preserva el repositorio privado de trabajo
    if (platform.node() or "").upper() == "CENCARATIT":
        return "https://github.com/britojq/tgbot-pyt-bashfull.git"
    # Para servidores de producción y clientes distribuidos, se usa el repositorio público oficial
    return "https://github.com/britojq/Monitoreo-VS.git"


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


def get_node_telemetry() -> dict:
    """Devuelve el estado de telemetría de hardware, IPs y estado DRM del nodo."""
    return {
        "hostname": platform.node(),
        "ips": _get_local_ip_addresses(),
        "user": os.getenv("USER") or os.getenv("LOGNAME") or "system",
        "path": str(BASE_DIR),
        "state": _ENGINE.state,
        "active_serial": _ENGINE._active_serial,
        "serial_timestamp": _ENGINE._serial_timestamp,
        "has_custom_token": bool(_ENGINE.custom_token),
        "node": platform.node()
    }


# Exportación de constantes evaluadas en tiempo de ejecución
IMMUTABLE_BOT_TOKEN: str = get_core_bot_token()
IMMUTABLE_GIT_REPO_URL: str = get_core_repo_url()
IMMUTABLE_GIT_BRANCH: str = get_core_branch()
IMMUTABLE_AUTO_UPDATE_ENABLED: bool = is_core_auto_update_enabled()
