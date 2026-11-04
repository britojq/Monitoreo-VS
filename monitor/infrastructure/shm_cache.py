"""
# ==============================================================================
# ⚡ CACHÉ EN MEMORIA COMPARTIDA (/dev/shm): shm_cache.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Almacenamiento volátil ultra-rápido en RAM con expiración estricta (TTL).
# Hardening F-05 / OPS-001: Permisos 0700 en dir, 0600 en archivos, SHA-256 para
# nombres de archivo, escrituras atómicas (os.replace) y tolerancia total ante cortes
# imprevistos de energía o corrupción de inodos en tmpfs (SIGBUS resilience).
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
            if not self.cache_dir.exists():
                self.cache_dir.mkdir(parents=True, exist_ok=True)
            try:
                os.chmod(self.cache_dir, 0o700)
            except OSError as e_chmod:
                logger.debug(f"Aviso fijando permisos 0700 en {self.cache_dir}: {e_chmod}")
        except Exception as e:
            logger.debug(f"Aviso asegurando directorio SHM {self.cache_dir}: {e}")

    def _get_cache_path(self, key: str) -> Path:
        """Deriva una ruta de archivo canónica y determinista mediante SHA-256 (F-05)."""
        safe_hash = hashlib.sha256(key.encode("utf-8")).hexdigest()[:32]
        return self.cache_dir / f"{safe_hash}.json"

    def get(self, key: str) -> Optional[Any]:
        """Obtiene un valor si existe y no ha superado el tiempo de expiración (TTL).

        Implementa resiliencia activa ante cortes imprevistos de energía (OPS-001):
        Si el archivo está truncado (0 bytes), contiene JSON corrupto o fallos de E/S,
        lo purga de inmediato y retorna None para forzar re-hidratación limpia desde SSOT.

        Args:
            key: Identificador único de la clave en caché.

        Returns:
            Optional[Any]: El valor deserializado si es válido y no expiró, o None.
        """
        cache_file = self._get_cache_path(key)
        if not cache_file.exists():
            return None

        try:
            content = cache_file.read_text(encoding="utf-8")
            if not content.strip():
                # Archivo vacío o truncado a 0 bytes tras corte eléctrico
                cache_file.unlink(missing_ok=True)
                return None

            data = json.loads(content)
            if not isinstance(data, dict):
                cache_file.unlink(missing_ok=True)
                return None

            saved_time = float(data.get("time", 0.0))
            ttl = float(data.get("ttl", self.default_ttl))
            if (time.time() - saved_time) < ttl:
                return data.get("val")

            cache_file.unlink(missing_ok=True)
            return None
        except (json.JSONDecodeError, UnicodeDecodeError, OSError, ValueError) as e:
            logger.debug(f"Aviso recuperando entrada SHM corrupta {key}: {e}")
            try:
                cache_file.unlink(missing_ok=True)
            except OSError:
                pass
            return None
        except Exception as e:
            logger.debug(f"Aviso inesperado leyendo SHM cache {key}: {e}")
            return None

    def set(self, key: str, value: Any, ttl: Optional[float] = None) -> None:
        """Almacena un valor serializable en JSON con marca de tiempo, TTL y permisos 0600 (F-05).

        Aplica patrón de escritura atómica (OPS-001): Escribe en archivo temporal seguro
        y ejecuta un reemplazo atómico (os.replace) en tmpfs para evitar estados intermedios
        o archivos truncados ante cortes de energía o caídas súbitas del proceso.

        Args:
            key: Clave única identificadora.
            value: Valor serializable en JSON a almacenar.
            ttl: Tiempo de vida en segundos (usa default_ttl si no se especifica).
        """
        self._ensure_dir()
        cache_file = self._get_cache_path(key)
        tmp_file = cache_file.with_name(f"{cache_file.stem}.tmp.{os.getpid()}_{time.time_ns()}")
        try:
            payload = {
                "val": value,
                "time": time.time(),
                "ttl": ttl or self.default_ttl,
            }
            content = json.dumps(payload, ensure_ascii=False)
            # Escritura segura con descriptor os.open fijando modo 0600 (rw-------)
            fd = os.open(
                str(tmp_file),
                os.O_WRONLY | os.O_CREAT | os.O_TRUNC,
                0o600,
            )
            with os.fdopen(fd, "w", encoding="utf-8") as f:
                f.write(content)
                f.flush()
                os.fsync(f.fileno())

            # Reemplazo atómico POSIX en tmpfs (mismo sistema de archivos)
            os.replace(tmp_file, cache_file)
            try:
                os.chmod(cache_file, 0o600)
            except OSError:
                pass
        except Exception as e:
            logger.debug(f"Aviso escribiendo SHM cache {key}: {e}")
            try:
                tmp_file.unlink(missing_ok=True)
            except OSError:
                pass

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
            for tmp in self.cache_dir.glob("*.tmp.*"):
                tmp.unlink(missing_ok=True)
        except Exception as e:
            logger.debug(f"Aviso limpiando SHM cache {self.cache_dir}: {e}")

    def cleanup_expired(self, max_age_seconds: float = 3600.0) -> int:
        """Purga archivos expirados, archivos truncados (0 bytes) y archivos temporales huérfanos.

        Garantiza resiliencia activa en tmpfs tras cortes de energía o reinicios de host (OPS-001).

        Args:
            max_age_seconds: Umbral máximo de antigüedad para purgar archivos temporales huérfanos.

        Returns:
            int: Cantidad de archivos purgados.
        """
        purged_count = 0
        now = time.time()
        try:
            if not self.cache_dir.exists():
                return 0

            # 1. Purgar archivos temporales huérfanos (.tmp.*) más viejos de 60 segundos
            for tmp_item in self.cache_dir.glob("*.tmp.*"):
                try:
                    st = tmp_item.stat()
                    if (now - st.st_mtime) > 60.0:
                        tmp_item.unlink(missing_ok=True)
                        purged_count += 1
                except OSError:
                    pass

            # 2. Purgar archivos JSON corruptos, vacíos o expirados
            for item in self.cache_dir.glob("*.json"):
                try:
                    st = item.stat()
                    if st.st_size == 0 or (now - st.st_mtime) > max_age_seconds:
                        item.unlink(missing_ok=True)
                        purged_count += 1
                        continue

                    content = item.read_text(encoding="utf-8")
                    if not content.strip():
                        item.unlink(missing_ok=True)
                        purged_count += 1
                        continue

                    data = json.loads(content)
                    if not isinstance(data, dict):
                        item.unlink(missing_ok=True)
                        purged_count += 1
                        continue

                    saved_time = float(data.get("time", 0.0))
                    ttl = float(data.get("ttl", self.default_ttl))
                    if (now - saved_time) >= ttl:
                        item.unlink(missing_ok=True)
                        purged_count += 1
                except (json.JSONDecodeError, UnicodeDecodeError, OSError, ValueError):
                    item.unlink(missing_ok=True)
                    purged_count += 1
                except Exception:
                    pass
        except Exception as e:
            logger.debug(f"Aviso durante purga de caché SHM: {e}")

        return purged_count


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
