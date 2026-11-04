"""
# ==============================================================================
# ⚡ CACHÉ EN MEMORIA COMPARTIDA (/dev/shm): shm_cache.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Almacenamiento volátil ultra-rápido en RAM con expiración estricta (TTL).
# Hardening F-05: Permisos 0700 en dir, 0600 en archivos, SHA-256 para nombres.
# Ubicación: /opt/sonar/monitor/infrastructure/shm_cache.py
# ==============================================================================
"""

from __future__ import annotations

import hashlib
import json
import logging
import os
import time
from pathlib import Path
from typing import Any, Dict, Optional

logger = logging.getLogger("monitor.infrastructure.shm_cache")

DEFAULT_SHM_DIR = Path("/dev/shm/monitoreo_snmp_cache")


class ShmCache:
    """Gestiona lecturas y escrituras atómicas en memoria compartida /dev/shm.

    Attributes:
        cache_dir: Ruta al directorio en memoria RAM compartida.
        default_ttl: Tiempo de vida predeterminado en segundos para los registros.
    """

    def __init__(self, cache_dir: Path = DEFAULT_SHM_DIR, default_ttl: float = 300.0) -> None:
        """Inicializa la instancia de ShmCache y asegura la existencia del directorio con permisos 0700.

        Args:
            cache_dir: Directorio base en /dev/shm para los archivos temporales.
            default_ttl: Tiempo de expiración por defecto en segundos (300s).
        """
        self.cache_dir: Path = cache_dir
        self.default_ttl: float = default_ttl
        self._ensure_dir()

    def _ensure_dir(self) -> None:
        """Crea el directorio de caché en /dev/shm con permisos estrictos 0700 (F-05 / CWE-732)."""
        try:
            self.cache_dir.mkdir(parents=True, exist_ok=True)
            os.chmod(self.cache_dir, 0o700)
        except Exception as e:
            logger.debug(f"Aviso asegurando directorio SHM {self.cache_dir}: {e}")

    def _get_cache_path(self, key: str) -> Path:
        """Deriva una ruta de archivo canónica y determinista mediante SHA-256 (F-05)."""
        safe_hash = hashlib.sha256(key.encode("utf-8")).hexdigest()[:32]
        return self.cache_dir / f"{safe_hash}.json"

    def get(self, key: str) -> Optional[Any]:
        """Obtiene un valor si existe y no ha superado el tiempo de expiración (TTL).

        Args:
            key: Identificador único de la clave en caché.

        Returns:
            Optional[Any]: El valor deserializado si es válido y no expiró, o None.
        """
        cache_file = self._get_cache_path(key)
        if not cache_file.exists():
            return None

        try:
            data = json.loads(cache_file.read_text(encoding="utf-8"))
            saved_time = data.get("time", 0.0)
            ttl = data.get("ttl", self.default_ttl)
            if (time.time() - saved_time) < ttl:
                return data.get("val")
            cache_file.unlink(missing_ok=True)
            return None
        except Exception as e:
            logger.debug(f"Aviso leyendo SHM cache {key}: {e}")
            return None

    def set(self, key: str, value: Any, ttl: Optional[float] = None) -> None:
        """Almacena un valor serializable en JSON con marca de tiempo, TTL y permisos 0600 (F-05).

        Args:
            key: Clave única identificadora.
            value: Valor serializable en JSON a almacenar.
            ttl: Tiempo de vida en segundos (usa default_ttl si no se especifica).
        """
        cache_file = self._get_cache_path(key)
        try:
            payload = {
                "val": value,
                "time": time.time(),
                "ttl": ttl or self.default_ttl,
            }
            content = json.dumps(payload, ensure_ascii=False)
            # Escritura segura con descriptor os.open fijando modo 0600 (rw-------)
            fd = os.open(
                str(cache_file),
                os.O_WRONLY | os.O_CREAT | os.O_TRUNC,
                0o600,
            )
            with os.fdopen(fd, "w", encoding="utf-8") as f:
                f.write(content)
        except Exception as e:
            logger.debug(f"Aviso escribiendo SHM cache {key}: {e}")

    def invalidate(self, key: str) -> None:
        """Invalida de forma atómica una clave específica de la caché."""
        try:
            self._get_cache_path(key).unlink(missing_ok=True)
        except Exception as e:
            logger.debug(f"Aviso invalidando clave SHM {key}: {e}")

    def clear(self) -> None:
        """Purga todas las entradas de caché en el directorio."""
        try:
            for item in self.cache_dir.glob("*.json"):
                item.unlink(missing_ok=True)
        except Exception as e:
            logger.debug(f"Aviso limpiando SHM cache {self.cache_dir}: {e}")


# Instancia por defecto
_DEFAULT_CACHE: Optional[ShmCache] = None


def get_shm_cache() -> ShmCache:
    """Obtiene o inicializa la instancia singleton canónica de ShmCache.

    Returns:
        ShmCache: Objeto singleton para gestión de caché en /dev/shm.
    """
    global _DEFAULT_CACHE
    if _DEFAULT_CACHE is None:
        _DEFAULT_CACHE = ShmCache()
    return _DEFAULT_CACHE
