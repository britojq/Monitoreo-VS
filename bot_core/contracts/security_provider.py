"""
# ==============================================================================
# 📜 CONTRATOS DE SEGURIDAD Y BÓVEDA CRIPTOGRÁFICA: security_provider.py
# Arquitectura SOLID - Principio de Inversión de Dependencias (DIP) e Interfaces Segregadas (ISP).
# Desacopla el punto de entrada del bot y componentes de red de la implementación
# concreta de seguridad física y derivación criptográfica (core_shield.py).
# Ubicación: /opt/sonar/bot_core/contracts/security_provider.py
# License: GNU Affero General Public License v3.0
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Any, Dict, List, Optional, Protocol, Union, runtime_checkable

logger = logging.getLogger("bot_core.security_provider")


@runtime_checkable
class TokenProvider(Protocol):
    """Protocolo segregado para credenciales y tokens del bot (ISP de SOLID)."""

    def get_bot_token(self) -> str:
        """Obtiene el token del bot de Telegram desenrollado en memoria volátil."""
        ...

    def get_owner_id(self) -> int:
        """Obtiene el identificador de usuario Telegram del Administrador único."""
        ...

    def migrate_token(self, new_token: str) -> bool:
        """Cifra y persiste un nuevo token en la bóveda de hardware."""
        ...


@runtime_checkable
class OperationalStatusProvider(Protocol):
    """Protocolo segregado para salud y telemetría del núcleo físico (ISP de SOLID)."""

    def is_core_operational(self) -> bool:
        """Indica si el núcleo de seguridad y anclaje de hardware está activo."""
        ...

    def get_core_status(self) -> Union[Dict[str, Any], str]:
        """Obtiene el diagnóstico operativo del núcleo y hardware vault."""
        ...

    def send_conflict_alert(self) -> None:
        """Despacha alerta de conflicto de sesión (Conflict 409) al chat privado."""
        ...

    def activate_first_boot(self) -> bool:
        """Inicializa la bóveda física en el primer arranque del servidor."""
        ...


@runtime_checkable
class GitConfigProvider(Protocol):
    """Protocolo segregado para aprovisionamiento y configuración GitOps (ISP de SOLID)."""

    def is_auto_update_enabled(self) -> bool:
        """Verifica si las actualizaciones automáticas están permitidas."""
        ...

    def get_git_repo_url(self) -> str:
        """Retorna la URL oficial del repositorio git."""
        ...

    def get_git_branch(self) -> str:
        """Retorna la rama de ejecución oficial."""
        ...


@runtime_checkable
class SecurityProviderProtocol(TokenProvider, OperationalStatusProvider, GitConfigProvider, Protocol):
    """
    Protocolo formal para proveedores de seguridad, tokens y bóveda criptográfica.
    Compone TokenProvider, OperationalStatusProvider y GitConfigProvider cumpliendo con ISP.
    """
    ...


class HardwareVaultSecurityProvider:
    """
    Implementación canónica de SecurityProviderProtocol vinculada
    al núcleo de seguridad de hardware del servidor (core_shield).
    """

    def get_bot_token(self) -> str:
        try:
            from monitor.core_shield import IMMUTABLE_BOT_TOKEN
            return str(IMMUTABLE_BOT_TOKEN or "")
        except Exception as e:
            logger.error(f"Error accediendo a token en hardware vault: {e}")
            return ""

    def get_owner_id(self) -> int:
        try:
            from monitor.core_shield import IMMUTABLE_OWNER_ID
            return int(IMMUTABLE_OWNER_ID or 0)
        except Exception as e:
            logger.error(f"Error accediendo a owner_id en hardware vault: {e}")
            return 0

    def is_core_operational(self) -> bool:
        try:
            from monitor.core_shield import is_core_operational
            return bool(is_core_operational())
        except Exception as e:
            logger.error(f"Error verificando operatividad del core: {e}")
            return False

    def get_core_status(self) -> Union[Dict[str, Any], str]:
        try:
            from monitor.core_shield import get_core_status
            return get_core_status()
        except Exception as e:
            logger.error(f"Error obteniendo estado del core: {e}")
            return {"status": "ERROR", "detail": str(e)}

    def is_auto_update_enabled(self) -> bool:
        try:
            from monitor.core_shield import IMMUTABLE_AUTO_UPDATE_ENABLED
            return bool(IMMUTABLE_AUTO_UPDATE_ENABLED)
        except Exception:
            return False

    def get_git_repo_url(self) -> str:
        try:
            from monitor.core_shield import IMMUTABLE_GIT_REPO_URL
            return str(IMMUTABLE_GIT_REPO_URL or "")
        except Exception:
            return ""

    def get_git_branch(self) -> str:
        try:
            from monitor.core_shield import IMMUTABLE_GIT_BRANCH
            return str(IMMUTABLE_GIT_BRANCH or "master")
        except Exception:
            return "master"

    def send_conflict_alert(self) -> None:
        try:
            from monitor.core_shield import send_core_conflict_alert
            send_core_conflict_alert()
        except Exception as e:
            logger.error(f"Error enviando alerta de conflicto desde provider: {e}")

    def activate_first_boot(self) -> bool:
        try:
            from monitor.core_shield import activate_hardware_first_boot
            return bool(activate_hardware_first_boot())
        except Exception as e:
            logger.error(f"Error en activate_first_boot: {e}")
            return False

    def migrate_token(self, new_token: str) -> bool:
        try:
            from monitor.core_shield import migrate_core_token
            return bool(migrate_core_token(new_token))
        except Exception as e:
            logger.error(f"Error migrando token en hardware vault: {e}")
            return False


# Instancia única del proveedor por defecto
_DEFAULT_PROVIDER: Optional[SecurityProviderProtocol] = None


def get_default_security_provider() -> SecurityProviderProtocol:
    """Obtiene o inicializa el proveedor de seguridad por defecto."""
    global _DEFAULT_PROVIDER
    if _DEFAULT_PROVIDER is None:
        _DEFAULT_PROVIDER = HardwareVaultSecurityProvider()
    return _DEFAULT_PROVIDER


def set_default_security_provider(provider: SecurityProviderProtocol) -> None:
    """Permite inyectar un proveedor de seguridad alternativo (DIP / Testing)."""
    global _DEFAULT_PROVIDER
    _DEFAULT_PROVIDER = provider
