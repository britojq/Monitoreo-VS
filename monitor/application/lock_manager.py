"""
# ==============================================================================
# 🔒 CONTROL DE CONCURRENCIA Y LOCKS: lock_manager.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Administra la exclusión mutua de procesos de monitoreo mediante archivos lock.
# Ubicación: /opt/sonar/monitor/application/lock_manager.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
import time
from contextlib import contextmanager
from pathlib import Path
from typing import Generator, Optional

logger = logging.getLogger("monitor.application.lock_manager")

DEFAULT_LOCK_PATH = Path("/tmp/monitor_engine.lock")
DEFAULT_EXPIRATION_SECONDS = 180.0


class LockManager:
    """Gestiona la adquisición y liberación atómica de bloqueos de ejecución."""

    def __init__(
        self,
        lock_file: Path = DEFAULT_LOCK_PATH,
        expiration_seconds: float = DEFAULT_EXPIRATION_SECONDS,
    ) -> None:
        """Inicializa la instancia con sus dependencias y configuración operativa.

        Args:
            lock_file: Parámetro operativo de entrada.
            expiration_seconds: Parámetro operativo de entrada.
        """
        self.lock_file: Path = lock_file
        self.expiration_seconds: float = expiration_seconds

    def is_locked(self) -> bool:
        """Verifica si existe un bloqueo activo y no expirado."""
        if not self.lock_file.exists():
            return False

        try:
            mtime = self.lock_file.stat().st_mtime
            age = time.time() - mtime
            if age < self.expiration_seconds:
                return True
            logger.warning(
                f"Lock expirado detectado ({age:.1f}s > {self.expiration_seconds}s). Liberando: {self.lock_file}"
            )
            self.release()
            return False
        except Exception as e:
            logger.debug(f"Aviso comprobando lock {self.lock_file}: {e}")
            return False

    def acquire(self) -> bool:
        """
        Intenta adquirir el bloqueo.
        Retorna True si fue adquirido exitosamente, False si ya estaba bloqueado.
        """
        if self.is_locked():
            return False

        try:
            self.lock_file.parent.mkdir(parents=True, exist_ok=True)
            content = f"{os.getpid()}\n{time.time()}\n"
            self.lock_file.write_text(content, encoding="utf-8")
            return True
        except Exception as e:
            logger.error(f"Error escribiendo archivo de bloqueo {self.lock_file}: {e}")
            return False

    def release(self) -> None:
        """Libera el bloqueo eliminando el archivo correspondiente."""
        try:
            self.lock_file.unlink(missing_ok=True)
        except Exception as e:
            logger.debug(f"Aviso al liberar lock {self.lock_file}: {e}")

    @contextmanager
    def context(self) -> Generator[bool, None, None]:
        """
        Context manager para encapsular bloques de código con bloqueo automático.
        Yields True si el lock fue adquirido, False si se omitió por estar en curso.
        """
        acquired = self.acquire()
        try:
            yield acquired
        finally:
            if acquired:
                self.release()
