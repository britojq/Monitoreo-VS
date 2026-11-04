"""
# ==============================================================================
# 🛡️ GESTOR DE CACHÉ Y COOLDOWN PARA PROXIES: proxy_cache.py
# Previene colisiones de concurrencia y bloqueos de sesión en pfSense/Squid
# almacenando en memoria compartida (/dev/shm) los estados recientes
# de proxies corporativos con TTL y candado In-Flight (ARCH-002).
# Ubicación: /opt/sonar/bot_core/services/proxy_cache.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import hashlib
import json
import logging
import os
import time
from pathlib import Path
from typing import Any, Dict, Optional

logger = logging.getLogger("bot.services.proxy_cache")

# Directorio de caché en memoria RAM compartida (/dev/shm) para latencia ultrabaja
SHM_DIR = Path("/dev/shm/monitoreo_proxy_cache")
TMP_DIR = Path("/tmp/monitoreo_proxy_cache")


def get_cache_dir() -> Path:
    """
    Obtiene el directorio de caché asegurando permisos universales (0o777).

    Returns:
        Path: Ruta al directorio de caché (/dev/shm o /tmp como fallback).
    """
    target = SHM_DIR if Path("/dev/shm").exists() and os.access("/dev/shm", os.W_OK) else TMP_DIR
    try:
        target.mkdir(parents=True, exist_ok=True)
        try:
            os.chmod(target, 0o777)
        except Exception:
            pass
    except Exception:
        TMP_DIR.mkdir(parents=True, exist_ok=True)
        return TMP_DIR
    return target


def normalize_proxy_key(proxy_str_or_url: str) -> str:
    """
    Normaliza una URL o dirección de proxy extrayendo únicamente host:puerto en minúsculas.

    Args:
        proxy_str_or_url: Cadena con la URL o dirección del proxy.

    Returns:
        str: Cadena normalizada con 'host:puerto' en minúsculas.
    """
    if not proxy_str_or_url:
        return ""
    clean = proxy_str_or_url.strip()
    if "://" in clean:
        clean = clean.split("://", 1)[1]
    if "@" in clean:
        clean = clean.split("@", 1)[1]
    clean = clean.split("/", 1)[0].strip().lower()
    return clean


def get_proxy_key_hash(proxy_key: str) -> str:
    """
    Genera un hash determinista para identificar únicamente el host:puerto del proxy.

    Args:
        proxy_key: Cadena normalizada host:puerto.

    Returns:
        str: Hash SHA-256 truncado a 16 caracteres hexadecimales.
    """
    return hashlib.sha256(proxy_key.strip().lower().encode("utf-8")).hexdigest()[:16]


def get_proxy_hash(proxy_key: str, test_url: str = "") -> str:
    """
    Genera un hash determinista para identificar el par proxy:test_url.

    Args:
        proxy_key: Cadena normalizada host:puerto.
        test_url: URL de prueba opcional.

    Returns:
        str: Hash SHA-256 de 16 caracteres.
    """
    norm_url = (test_url or "").strip().lower()
    payload = f"{proxy_key}::{norm_url}"
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()[:16]


def get_cached_proxy_result(
    proxy_str_or_url: str,
    test_url: str = "",
    max_age_seconds: float = 60.0,
) -> Optional[Dict[str, Any]]:
    """
    Recupera el resultado reciente en caché para un proxy si no ha expirado.

    Args:
        proxy_str_or_url: Proxy o URL del proxy.
        test_url: URL de destino del chequeo.
        max_age_seconds: Tiempo de vida máximo del caché en segundos.

    Returns:
        Optional[Dict[str, Any]]: Datos cacheados o None si expiró o no existe.
    """
    proxy_key = normalize_proxy_key(proxy_str_or_url)
    if not proxy_key:
        return None

    cache_dir = get_cache_dir()
    cache_file = cache_dir / f"proxy_{get_proxy_hash(proxy_key, test_url)}.json"

    if not cache_file.exists():
        if test_url:
            latest_file = cache_dir / f"latest_{get_proxy_key_hash(proxy_key)}.json"
            if latest_file.exists():
                cache_file = latest_file
            else:
                return None
        else:
            return None

    try:
        raw = cache_file.read_text(encoding="utf-8")
        data = json.loads(raw)
        age = time.time() - data.get("timestamp", 0)
        if age <= max_age_seconds:
            data["cached"] = True
            data["cache_age_seconds"] = round(age, 1)
            return data
    except Exception as e:
        logger.debug("Error leyendo caché de proxy (%s): %s", proxy_key, e)
    return None


def save_cached_proxy_result(
    proxy_str_or_url: str,
    test_url: str,
    is_ok: bool,
    latency_ms: float,
    error_msg: str = "",
) -> None:
    """
    Persiste en memoria compartida el resultado de sondeo de un proxy.

    Args:
        proxy_str_or_url: Proxy evaluado.
        test_url: URL destino de la prueba.
        is_ok: Estado booleano de éxito.
        latency_ms: Latencia en milisegundos.
        error_msg: Detalle o motivo de fallo si aplica.
    """
    proxy_key = normalize_proxy_key(proxy_str_or_url)
    if not proxy_key:
        return

    cache_dir = get_cache_dir()
    data = {
        "proxy": proxy_key,
        "test_url": test_url,
        "is_ok": is_ok,
        "latency_ms": round(latency_ms, 1),
        "error": error_msg,
        "timestamp": time.time(),
        "datetime": time.strftime("%Y-%m-%d %H:%M:%S"),
    }
    encoded = json.dumps(data)

    try:
        cache_file = cache_dir / f"proxy_{get_proxy_hash(proxy_key, test_url)}.json"
        cache_file.write_text(encoded, encoding="utf-8")
        try:
            os.chmod(cache_file, 0o666)
        except Exception:
            pass
    except Exception as e:
        logger.warning("No se pudo guardar caché de proxy (%s): %s", proxy_key, e)

    try:
        key_h = get_proxy_key_hash(proxy_key)
        latest_file = cache_dir / f"latest_{key_h}.json"
        latest_file.write_text(encoded, encoding="utf-8")
        try:
            os.chmod(latest_file, 0o666)
        except Exception:
            pass
    except Exception:
        pass


class ProxyInflightGuard:
    """
    Candado de Vuelo (In-Flight Lock) para chequeo de proxies.

    Si dos procesos o corrutinas intentan chequear el mismo proxy concurrentemente,
    el segundo espera hasta que el primero termine y lee el resultado recién generado,
    evitando que pfSense/Squid reciba peticiones simultáneas de sesión.

    Attributes:
        proxy_key: Clave normalizada del proxy (host:puerto).
        test_url: URL de destino del sondeo.
        cache_dir: Directorio de almacenamiento volátil.
        lock_file: Ruta al archivo de candado atómico.
        acquired: Booleano que indica si este proceso retiene el candado.
    """

    def __init__(self, proxy_str_or_url: str, test_url: str = "") -> None:
        """
        Inicializa el guardián de vuelo para el proxy especificado.

        Args:
            proxy_str_or_url: Cadena con el proxy o URL del proxy.
            test_url: URL de prueba asociada.
        """
        self.proxy_key = normalize_proxy_key(proxy_str_or_url)
        self.test_url = test_url
        self.cache_dir = get_cache_dir()
        key_h = get_proxy_key_hash(self.proxy_key)
        self.lock_file = self.cache_dir / f"proxy_{key_h}.inflight"
        self.acquired = False

    async def acquire_or_wait(self, timeout: float = 4.5) -> Optional[Dict[str, Any]]:
        """
        Intenta adquirir el candado o espera el resultado si otro proceso ya lo retiene.

        Args:
            timeout: Tiempo máximo de espera en segundos (por defecto 4.5s).

        Returns:
            Optional[Dict[str, Any]]: Resultado obtenido de la caché tras la espera, o None
                si este proceso adquirió exitosamente el candado para proceder con el chequeo.
        """
        if not self.proxy_key:
            return None

        t0 = time.time()
        while (time.time() - t0) < timeout:
            if self.lock_file.exists():
                try:
                    mtime = self.lock_file.stat().st_mtime
                    if (time.time() - mtime) > 8.0:
                        self.lock_file.unlink(missing_ok=True)
                    else:
                        await asyncio.sleep(0.3)
                        cached = get_cached_proxy_result(self.proxy_key, self.test_url, max_age_seconds=15.0)
                        if cached:
                            return cached
                        continue
                except Exception:
                    pass

            try:
                fd = os.open(str(self.lock_file), os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o666)
                with os.fdopen(fd, "w") as f:
                    f.write(f"{os.getpid()}\n{time.time()}\n")
                self.acquired = True
                return None
            except FileExistsError:
                await asyncio.sleep(0.3)
                cached = get_cached_proxy_result(self.proxy_key, self.test_url, max_age_seconds=15.0)
                if cached:
                    return cached

        self.acquired = True
        return None

    def release(self) -> None:
        """Libera el candado de vuelo eliminando el archivo de memoria compartida."""
        if self.acquired:
            try:
                self.lock_file.unlink(missing_ok=True)
            except Exception:
                pass
            self.acquired = False


__all__ = [
    "SHM_DIR",
    "TMP_DIR",
    "ProxyInflightGuard",
    "get_cache_dir",
    "get_cached_proxy_result",
    "get_proxy_hash",
    "get_proxy_key_hash",
    "normalize_proxy_key",
    "save_cached_proxy_result",
]
