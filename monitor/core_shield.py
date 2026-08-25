"""
Módulo de Seguridad y Ofuscación del Núcleo del Bot.
Protege las constantes inmutables de seguridad contra alteraciones y lectura en texto plano:
- ID del Propietario (Owner ID)
- Token Oficial del Bot
- URL y Rama del Repositorio Git
- Política permanente de Auto-Actualización

Implementa cifrado multicapa (XOR dinámico con desplazamiento de bytes + Base64)
y validación de integridad SHA-256 en tiempo de ejecución.
"""

from __future__ import annotations

import base64
import hashlib
import sys

# Fragmentos de clave ensamblados dinámicamente en memoria mediante desplazamiento de bytes
_K_0 = bytes([67, 69, 78, 67, 65, 82, 65, 84, 73, 84])
_K_1 = bytes([95, 65, 84, 73, 84, 95])
_K_2 = bytes([86, 65, 76, 76, 69, 95, 83, 69, 67, 79])
_K_3 = bytes([95, 50, 48, 50, 54, 95])
_K_4 = bytes([83, 72, 73, 69, 76, 68, 95, 75, 69, 89])

_SYS_SECRET_KEY = _K_0 + _K_1 + _K_2 + _K_3 + _K_4

# Cargas ofuscadas y hashes criptográficos de integridad
_TOKEN_BLOB = "e3J3cnNld21+YGUAFQFnOzQVJiZ9C2RzNykAY3FbZi4/eSASFTctL30XCnYgFg=="
_TOKEN_HASH = "4f7b45269d2b2adaba068364d3c27130a09825a8ea830c38ae7f687df188448b"

_OWNER_BLOB = "cH13cnVrcWU="
_OWNER_HASH = "cd44e4bf71856b7fefc2e20e5f31185b92ee094a4c91c2439b08a7bfe9c8e97a"

_REPO_BLOB = "KzE6MzJobnsuPSspISt6PDksYy43NicqKT5wRldQWSt+ODAxYSY+OC0/NikibSY7NQ=="
_REPO_HASH = "a4c86b217114db9dab0710b3ea09b9774e0dbdf200b4d1d5bfce043bb51f746c"

_BRANCH_BLOB = "LiQ9NyQg"
_BRANCH_HASH = "fc613b4dfd6736a7bd268c8a0e74ed0d1c04a959f59dd74ef2874983fd443fc9"


def _decrypt_payload(blob: str, expected_hash: str) -> str:
    """Descifra el payload en memoria y verifica su integridad SHA-256."""
    try:
        raw_b64 = base64.b64decode(blob)
        raw_bytes = bytes([b ^ _SYS_SECRET_KEY[i % len(_SYS_SECRET_KEY)] for i, b in enumerate(raw_b64)])
        computed_hash = hashlib.sha256(raw_bytes).hexdigest()

        if computed_hash != expected_hash:
            raise SecurityError("CRITICAL_SECURITY_VIOLATION: Obfuscated constant integrity verification failed.")

        return raw_bytes.decode("utf-8")
    except Exception as e:
        sys.stderr.write(f"[FATAL ERROR] Fallo de integridad de seguridad en core_shield: {e}\n")
        raise SystemExit(1)


class SecurityError(Exception):
    """Excepción generada ante intentos de manipulación o corrupción del payload."""
    pass


# Getters seguros en tiempo de ejecución
def get_core_owner_id() -> int:
    """Devuelve el ID inmutable del Propietario tras verificar integridad."""
    return int(_decrypt_payload(_OWNER_BLOB, _OWNER_HASH))


def get_core_bot_token() -> str:
    """Devuelve el Token inmutable del Bot tras verificar integridad."""
    return _decrypt_payload(_TOKEN_BLOB, _TOKEN_HASH)


def get_core_repo_url() -> str:
    """Devuelve la URL inmutable del Repositorio Git oficial tras verificar integridad."""
    return _decrypt_payload(_REPO_BLOB, _REPO_HASH)


def get_core_branch() -> str:
    """Devuelve la rama inmutable de Git tras verificar integridad."""
    return _decrypt_payload(_BRANCH_BLOB, _BRANCH_HASH)


def is_core_auto_update_enabled() -> bool:
    """Devuelve la política obligatoria de auto-actualización."""
    return True


# Exportación de constantes evaluadas en memoria
IMMUTABLE_OWNER_ID: int = get_core_owner_id()
IMMUTABLE_BOT_TOKEN: str = get_core_bot_token()
IMMUTABLE_GIT_REPO_URL: str = get_core_repo_url()
IMMUTABLE_GIT_BRANCH: str = get_core_branch()
IMMUTABLE_AUTO_UPDATE_ENABLED: bool = is_core_auto_update_enabled()
