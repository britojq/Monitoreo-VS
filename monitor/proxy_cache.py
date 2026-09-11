"""
==============================================================================
🛡️ GESTOR DE CACHÉ Y COOLDOWN PARA PROXIES Y PFSENSE: proxy_cache.py
Ubicación: /scripts/telegram-admin-bot/monitor/proxy_cache.py
Propósito: Prevenir colisiones de concurrencia y bloqueos de sesión en pfSense
           almacenando en memoria compartida (/dev/shm) los estados recientes
           de proxies corporativos con un TTL de 60 segundos y candado In-Flight.
==============================================================================
"""

import asyncio
import hashlib
import json
import logging
import os
import time
from pathlib import Path
from typing import Dict, Optional

logger = logging.getLogger("proxy.cache")

# Directorio de caché en memoria RAM compartida (/dev/shm) para latencia 0 ms
SHM_DIR = Path("/dev/shm/monitoreo_proxy_cache")
TMP_DIR = Path("/tmp/monitoreo_proxy_cache")


def get_cache_dir() -> Path:
    """Obtiene el directorio de caché asegurando permisos universales (0o777)."""
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
    Ejemplos:
    - 'http://A1746281:Abrito2026.*@10.20.0.119:8080' -> '10.20.0.119:8080'
    - '10.20.0.119:8080' -> '10.20.0.119:8080'
    - 'http://10.20.0.119:8080/' -> '10.20.0.119:8080'
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
    """Genera un hash determinista para identificar únicamente el host:puerto del proxy."""
    return hashlib.sha256(proxy_key.strip().lower().encode("utf-8")).hexdigest()[:16]


def get_proxy_hash(proxy_key: str, test_url: str = "") -> str:
    """Genera un hash determinista para identificar el par proxy:test_url."""
    norm_url = (test_url or "").strip().lower()
    payload = f"{proxy_key}::{norm_url}"
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()[:16]


def get_cached_proxy_result(
    proxy_str_or_url: str,
    test_url: str = "",
    max_age_seconds: float = 60.0
) -> Optional[Dict[str, any]]:
    """
    Lee el resultado de la caché en memoria si existe y tiene menos de max_age_seconds.
    1. Primero intenta coincidencia exacta con test_url.
    2. Si no coincide o test_url difiere, comprueba el resultado más reciente del mismo proxy (host:puerto).
    Retorna None si no hay caché válida o si ha expirado.
    """
    proxy_key = normalize_proxy_key(proxy_str_or_url)
    if not proxy_key:
        return None

    cache_dir = get_cache_dir()

    # 1. Intentar coincidencia exacta con test_url si se especificó
    if test_url:
        h = get_proxy_hash(proxy_key, test_url)
        cache_file = cache_dir / f"proxy_{h}.json"
        if cache_file.exists():
            try:
                data = json.loads(cache_file.read_text(encoding="utf-8"))
                age = time.time() - float(data.get("timestamp", 0))
                if 0 <= age <= max_age_seconds:
                    logger.debug(f"[CACHE HIT EXACT] Proxy {proxy_key} (edad: {age:.1f}s)")
                    data["from_cache"] = True
                    data["cache_age_seconds"] = round(age, 1)
                    return data
            except Exception:
                pass

    # 2. Intentar estado más reciente global del mismo host:puerto
    key_h = get_proxy_key_hash(proxy_key)
    latest_file = cache_dir / f"latest_{key_h}.json"
    if latest_file.exists():
        try:
            data = json.loads(latest_file.read_text(encoding="utf-8"))
            age = time.time() - float(data.get("timestamp", 0))
            if 0 <= age <= max_age_seconds:
                logger.debug(f"[CACHE HIT LATEST] Proxy {proxy_key} (edad: {age:.1f}s)")
                data["from_cache"] = True
                data["cache_age_seconds"] = round(age, 1)
                return data
        except Exception:
            pass

    return None


def save_cached_proxy_result(
    proxy_str_or_url: str,
    test_url: str,
    is_ok: bool,
    code_str: str,
    detail: str,
    latency_ms: float,
    source: str = "unknown"
) -> None:
    """Guarda un resultado verificado en memoria compartida con permisos permisivos (0o666)."""
    proxy_key = normalize_proxy_key(proxy_str_or_url)
    if not proxy_key:
        return

    cache_dir = get_cache_dir()
    payload = {
        "proxy_key": proxy_key,
        "test_url": test_url,
        "is_ok": bool(is_ok),
        "code_str": str(code_str),
        "detail": str(detail),
        "latency_ms": round(float(latency_ms), 1),
        "timestamp": time.time(),
        "source": source
    }
    encoded = json.dumps(payload)

    # 1. Guardar por par específico (proxy, test_url)
    h = get_proxy_hash(proxy_key, test_url)
    cache_file = cache_dir / f"proxy_{h}.json"
    try:
        cache_file.write_text(encoded, encoding="utf-8")
        try:
            os.chmod(cache_file, 0o666)
        except Exception:
            pass
    except Exception as e:
        logger.warning(f"No se pudo guardar caché de proxy ({proxy_key}): {e}")

    # 2. Guardar también como estado más reciente global de este host:puerto
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
    Candado de Vuelo (In-Flight Lock):
    Si dos procesos intentan chequear el mismo proxy en el mismo instante (milisegundos),
    el segundo espera hasta que el primero termine y lee el resultado recién generado,
    evitando que pfSense reciba peticiones simultáneas de sesión.
    Se bloquea a nivel de host:puerto para proteger la sesión de autenticación Squid/pfSense.
    """
    def __init__(self, proxy_str_or_url: str, test_url: str = ""):
        self.proxy_key = normalize_proxy_key(proxy_str_or_url)
        self.test_url = test_url
        self.cache_dir = get_cache_dir()
        key_h = get_proxy_key_hash(self.proxy_key)
        self.lock_file = self.cache_dir / f"proxy_{key_h}.inflight"
        self.acquired = False

    async def acquire_or_wait(self, timeout: float = 4.5) -> Optional[Dict[str, any]]:
        """
        Intenta adquirir el candado. Si otro proceso ya lo tiene:
        espera de forma asíncrona hasta timeout segundos verificando si el otro proceso
        guarda el resultado en caché.
        Retorna el resultado en caché si lo obtuvo de la espera, o None si adquirió el candado para chequear.
        """
        if not self.proxy_key:
            return None

        t0 = time.time()
        while (time.time() - t0) < timeout:
            if self.lock_file.exists():
                try:
                    mtime = self.lock_file.stat().st_mtime
                    if (time.time() - mtime) > 8.0:
                        # Lock huérfano (más de 8 segundos)
                        self.lock_file.unlink(missing_ok=True)
                    else:
                        # Otro proceso está chequeando ahora mismo, esperar resultado
                        await asyncio.sleep(0.3)
                        cached = get_cached_proxy_result(self.proxy_key, self.test_url, max_age_seconds=15.0)
                        if cached:
                            return cached
                        continue
                except Exception:
                    pass

            # Intentar crear el lock de forma atómica
            try:
                fd = os.open(str(self.lock_file), os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o666)
                with os.fdopen(fd, "w") as f:
                    f.write(f"{os.getpid()}\n{time.time()}\n")
                self.acquired = True
                return None
            except FileExistsError:
                # Conflicto simultáneo, esperar a que el otro termine
                await asyncio.sleep(0.3)
                cached = get_cached_proxy_result(self.proxy_key, self.test_url, max_age_seconds=15.0)
                if cached:
                    return cached

        self.acquired = True
        return None

    def release(self):
        """Libera el candado de vuelo."""
        if self.acquired:
            try:
                self.lock_file.unlink(missing_ok=True)
            except Exception:
                pass
            self.acquired = False
