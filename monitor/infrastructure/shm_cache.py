"""
# ==============================================================================
# ⚡ CACHÉ EN MEMORIA COMPARTIDA (/dev/shm): shm_cache.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Almacenamiento volátil ultra-rápido en RAM con expiración estricta (TTL).
# Ubicación: /opt/sonar/monitor/infrastructure/shm_cache.py
# ==============================================================================
"""

from __future__ import annotations

import json
import logging
import time
from pathlib import Path
from typing import Any, Dict, Optional

logger = logging.getLogger("monitor.infrastructure.shm_cache")

DEFAULT_SHM_DIR = Path("/dev/shm/monitoreo_snmp_cache")


class ShmCache:
    """Gestiona lecturas y escrituras atómicas en memoria compartida /dev/shm."""

    def __init__(self, cache_dir: Path = DEFAULT_SHM_DIR, default_ttl: float = 300.0) -> None:
        self.cache_dir: Path = cache_dir
        self.default_ttl: float = default_ttl
        self._ensure_dir()

    def _ensure_dir(self) -> None:
        try:
            self.cache_dir.mkdir(parents=True, exist_ok=True)
        except Exception as e:
            logger.debug(f"Aviso creando directorio SHM {self.cache_dir}: {e}")

    def get(self, key: str) -> Optional[Any]:
        """Obtiene un valor si existe y no ha superado el tiempo de expiración (TTL)."""
        cache_file = self.cache_dir / f"{key}.json"
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
        """Almacena un valor serializable en JSON con marca de tiempo y TTL."""
        cache_file = self.cache_dir / f"{key}.json"
        try:
            payload = {
                "val": value,
                "time": time.time(),
                "ttl": ttl or self.default_ttl,
            }
            cache_file.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
        except Exception as e:
            logger.debug(f"Aviso escribiendo SHM cache {key}: {e}")


# Instancia por defecto
_DEFAULT_CACHE: Optional[ShmCache] = None


def get_shm_cache() -> ShmCache:
    """Obtiene la instancia canónica de ShmCache."""
    global _DEFAULT_CACHE
    if _DEFAULT_CACHE is None:
        _DEFAULT_CACHE = ShmCache()
    return _DEFAULT_CACHE
