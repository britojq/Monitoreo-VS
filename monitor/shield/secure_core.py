"""
Orquestador de seguridad SecureCore y wrappers operacionales de runtime.
"""
from __future__ import annotations

import hmac
import logging
import os
from pathlib import Path
import platform
import time
from typing import List, Optional, Tuple

import httpx

from monitor.shield.shield_notifier import (
    notify_console_activation_success,
    send_activation_alert,
    send_conflict_alert,
    send_migration_alert,
)
from monitor.shield.vault_key_deriver import (
    _B_CIPHER,
    _CORE_MASTER_KEY,
    _O_CIPHER,
    IMMUTABLE_OWNER_ID,
    derive_hardware_key,
    get_canary_token,
    get_hardware_components,
    get_local_ip_addresses,
    get_sentinel_token,
    verify_system_auth_token,
)
from monitor.shield.vault_storage import (
    ANCHOR_FILE,
    CHALLENGE_FILE,
    deobfuscate_payload,
    generate_challenge,
    init_vault_dir,
    invalidate_hardware_anchor,
    unwrap_anchor,
    write_anchor,
)

logger = logging.getLogger("monitor.shield.secure_core")
BASE_DIR = Path(__file__).resolve().parent.parent.parent


class SecureCore:
    """Subsistema de seguridad y orquestador del protocolo de validación e integridad."""
    _instance: Optional[SecureCore] = None
    _unwrapped_master_key: Optional[bytes] = None
    _custom_token: Optional[str] = None
    _state: str = "INIT"
    _active_serial: Optional[str] = None
    _serial_timestamp: int = 0
    _hw_components: List[str] = []
    _hw_key: bytes = b""

    def __new__(cls) -> SecureCore:
        if cls._instance is None:
            cls._instance = super(SecureCore, cls).__new__(cls)
            cls._instance._init_integrity_layer()
        return cls._instance

    def _init_integrity_layer(self) -> None:
        init_vault_dir()
        self._hw_components = get_hardware_components()
        self._hw_key = derive_hardware_key(self._hw_components)
        hw_fp = ":".join(self._hw_components)

        master_key, custom_tok = unwrap_anchor(self._hw_key)
        if master_key:
            self._unwrapped_master_key = master_key
            self._custom_token = custom_tok
            self._state = "OPERATIONAL"
            return

        if not ANCHOR_FILE.exists():
            self._state = "FIRST_BOOT_PENDING"
            self._active_serial, self._serial_timestamp = generate_challenge(hw_fp)
            try:
                send_activation_alert(self._active_serial, self.get_token())
            except Exception:
                pass
            return

        self._state = "PENDING_VALIDATION"
        self._active_serial, self._serial_timestamp = generate_challenge(hw_fp)
        try:
            send_migration_alert(self._active_serial, self.get_token())
        except Exception:
            pass

    def verify_challenge(self, entered_serial: str) -> bool:
        if verify_system_auth_token(entered_serial):
            return True
        if not self._active_serial and CHALLENGE_FILE.exists():
            import json
            try:
                data = json.loads(CHALLENGE_FILE.read_text(encoding="utf-8"))
                self._active_serial = data.get("serial")
                self._serial_timestamp = data.get("ts", 0)
            except Exception:
                pass

        if not self._active_serial or not self._serial_timestamp:
            return False
        now = int(time.time())
        if now - self._serial_timestamp > 600 or now < self._serial_timestamp:
            return False
        return hmac.compare_digest(entered_serial.strip().upper(), self._active_serial.strip().upper())

    def send_conflict_alert(self) -> None:
        bot_tok = self.get_token() or get_canary_token()
        send_conflict_alert(bot_tok, self._state)

    def notify_console_activation_success(self, serial: str) -> None:
        bot_tok = self.get_token() or get_canary_token()
        notify_console_activation_success(serial, bot_tok)

    def force_hardware_anchor(self) -> Tuple[bool, str]:
        if not self._hw_key:
            self._hw_components = get_hardware_components()
            self._hw_key = derive_hardware_key(self._hw_components)

        success = write_anchor(self._hw_key, custom_token=self._custom_token)
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
            logger.info("✅ SecureCore: Entorno forzado exitosamente.")
            return True, "✅ [ANCLAJE EXITOSO] Entorno local inicializado correctamente."
        return False, "Error al escribir el archivo de firma de entorno."

    def activate_hardware(self, entered_serial: str, custom_token: Optional[str] = None) -> Tuple[bool, str]:
        clean_serial = entered_serial.strip().upper()
        if not clean_serial.startswith("AUTH-") and len(clean_serial) == 19:
            clean_serial = f"AUTH-{clean_serial}"

        if not self.verify_challenge(clean_serial):
            if not self._active_serial:
                return False, "No hay ningún desafío de activación pendiente en este momento."
            now = int(time.time())
            if now - self._serial_timestamp > 600 or now < self._serial_timestamp:
                return False, "El Serial de activación ha expirado (límite 10 minutos)."
            return False, "Serial de activación incorrecto."

        if not self._hw_key:
            self._hw_components = get_hardware_components()
            self._hw_key = derive_hardware_key(self._hw_components)

        if custom_token and ":" in custom_token and len(custom_token) >= 30:
            self._custom_token = custom_token.strip()

        success = write_anchor(self._hw_key, custom_token=self._custom_token)
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
            logger.info("✅ SecureCore: Validación de entorno exitosa.")
            return True, "✅ [ACTIVACIÓN EXITOSA] Entorno validado correctamente."
        return False, "Error al escribir el archivo de firma de entorno."

    def migrate_token(self, new_token: str) -> Tuple[bool, str]:
        new_token_clean = new_token.strip()
        if ":" not in new_token_clean or len(new_token_clean) < 30:
            return False, "El formato del token no es válido."

        try:
            with httpx.Client(timeout=8.0) as client:
                r = client.get(f"https://api.telegram.org/bot{new_token_clean}/getMe")
                if r.status_code != 200 or not r.json().get("ok"):
                    return False, f"El token fue rechazado por Telegram: {r.text}"
                bot_info = r.json().get("result", {})
                bot_username = bot_info.get("username", "Desconocido")
        except Exception as e:
            return False, f"Fallo al verificar el nuevo token: {e}"

        if not self._hw_key:
            self._hw_key = derive_hardware_key(get_hardware_components())

        success = write_anchor(self._hw_key, custom_token=new_token_clean)
        if not success:
            return False, "Error interno al re-cifrar el nuevo token."

        self._custom_token = new_token_clean
        self._unwrapped_master_key = _CORE_MASTER_KEY
        self._state = "OPERATIONAL"
        self._active_serial = None
        self._serial_timestamp = 0
        if CHALLENGE_FILE.exists():
            try:
                CHALLENGE_FILE.unlink()
            except Exception:
                pass
        return True, f"✅ <b>Identidad migrada exitosamente</b> hacia <code>@{bot_username}</code>."

    def deobfuscate(self, cipher_blob: str) -> str:
        return deobfuscate_payload(cipher_blob, self._unwrapped_master_key, self._state)

    def invalidate_hardware_anchor(self, reason: str = "Aislamiento detectado") -> bool:
        self._state = "TRIPPED"
        self._unwrapped_master_key = b"\x00" * 32
        self._custom_token = None
        return invalidate_hardware_anchor(reason)

    @property
    def state(self) -> str:
        return self._state

    @property
    def custom_token(self) -> Optional[str]:
        return self._custom_token

    def get_token(self) -> str:
        if self._custom_token:
            return self._custom_token
        sentinel = get_sentinel_token()
        if sentinel:
            return sentinel
        return get_canary_token()


# =========================================================================
# WRAPPERS PÚBLICOS DE SEGURIDAD OPERACIONAL
# =========================================================================
_ENGINE = SecureCore()


def get_core_status() -> str:
    return _ENGINE.state


def is_core_operational() -> bool:
    return _ENGINE.state == "OPERATIONAL"


def activate_hardware_first_boot(serial: str, custom_token: Optional[str] = None) -> Tuple[bool, str]:
    return _ENGINE.activate_hardware(serial, custom_token=custom_token)


def send_core_conflict_alert() -> None:
    _ENGINE.send_conflict_alert()


def notify_core_console_activation(serial: str) -> None:
    _ENGINE.notify_console_activation_success(serial)


def force_core_hardware_anchor() -> Tuple[bool, str]:
    return _ENGINE.force_hardware_anchor()


def migrate_core_token(new_token: str) -> Tuple[bool, str]:
    return _ENGINE.migrate_token(new_token)


def get_core_owner_id() -> int:
    try:
        val = _ENGINE.deobfuscate(_O_CIPHER)
        return int(val)
    except Exception:
        return IMMUTABLE_OWNER_ID


def get_core_bot_token() -> str:
    return _ENGINE.get_token()


def get_core_repo_url() -> str:
    if (platform.node() or "").upper() == "CENCARATIT":
        return "https://github.com/britojq/tgbot-pyt-bashfull.git"
    return "https://github.com/britojq/Monitoreo-VS.git"


def get_core_branch() -> str:
    branch = _ENGINE.deobfuscate(_B_CIPHER)
    if not branch or branch.startswith("FAIL_SILENT_"):
        return "master"
    return branch


def is_core_auto_update_enabled() -> bool:
    return True


def trip_deadman_switch(reason: str = "Fallo consecutivo de sincronización Git") -> bool:
    return _ENGINE.invalidate_hardware_anchor(reason=reason)


def get_node_telemetry() -> dict:
    return {
        "hostname": platform.node(),
        "ips": get_local_ip_addresses(),
        "user": os.getenv("USER") or os.getenv("LOGNAME") or "system",
        "path": str(BASE_DIR),
        "state": _ENGINE.state,
        "active_serial": _ENGINE._active_serial,
        "serial_timestamp": _ENGINE._serial_timestamp,
        "has_custom_token": bool(_ENGINE.custom_token),
        "node": platform.node()
    }


IMMUTABLE_BOT_TOKEN: str = get_core_bot_token()
IMMUTABLE_GIT_REPO_URL: str = get_core_repo_url()
IMMUTABLE_GIT_BRANCH: str = get_core_branch()
IMMUTABLE_AUTO_UPDATE_ENABLED: bool = is_core_auto_update_enabled()
