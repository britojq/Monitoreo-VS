"""
==============================================================================
🛡️ GESTOR DE CACHÉ Y COOLDOWN PARA PROXIES Y PFSENSE: proxy_cache.py
Ubicación: /opt/sonar/monitor/proxy_cache.py
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
    """Obtiene el directorio de caché asegurando permisos universales (0o777).

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
    """Normaliza una URL o dirección de proxy extrayendo únicamente host:puerto en minúsculas.

    Ejemplos:
    - 'http://usuario:clave@192.168.0.119:8080' -> '192.168.0.119:8080'
    - '192.168.0.119:8080' -> '192.168.0.119:8080'
    - 'http://192.168.0.119:8080/' -> '192.168.0.119:8080'

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
    """Genera un hash determinista para identificar únicamente el host:puerto del proxy.

    Args:
        proxy_key: Cadena normalizada host:puerto.

    Returns:
        str: Hash SHA-256 truncado a 16 caracteres hexadecimales.
    """
    return hashlib.sha256(proxy_key.strip().lower().encode("utf-8")).hexdigest()[:16]


def get_proxy_hash(proxy_key: str, test_url: str = "") -> str:
    """Genera un hash determinista para identificar el par proxy:test_url.

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
    max_age_seconds: float = 60.0
) -> Optional[Dict[str, any]]:
    """Lee el resultado de la caché en memoria si existe y tiene menos de max_age_seconds.

    1. Primero intenta coincidencia exacta con test_url.
    2. Si no coincide o test_url difiere, comprueba el resultado más reciente del mismo proxy (host:puerto).

    Args:
        proxy_str_or_url: Cadena con el proxy a consultar.
        test_url: URL de prueba específica.
        max_age_seconds: Tiempo máximo de validez en segundos (por defecto 60.0).

    Returns:
        Optional[Dict[str, any]]: Datos en caché con clave 'from_cache'=True o None si expiró.
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
    """Guarda un resultado verificado en memoria compartida con permisos universales (0o666).

    Args:
        proxy_str_or_url: Cadena con el proxy a almacenar.
        test_url: URL de prueba empleada.
        is_ok: Booleano indicando si el sondeo fue satisfactorio.
        code_str: Código HTTP o de estado obtenido.
        detail: Descripción o mensaje legible de resultado.
        latency_ms: Latencia en milisegundos observada.
        source: Identificador del verificador u origen de la comprobación.
    """
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
    """Candado de Vuelo (In-Flight Lock).

    Si dos procesos intentan chequear el mismo proxy en el mismo instante (milisegundos),
    el segundo espera hasta que el primero termine y lee el resultado recién generado,
    evitando que pfSense reciba peticiones simultáneas de sesión.
    Se bloquea a nivel de host:puerto para proteger la sesión de autenticación Squid/pfSense.

    Attributes:
        proxy_key: Clave normalizada del proxy (host:puerto).
        test_url: URL de destino del sondeo.
        cache_dir: Directorio de almacenamiento volátil.
        lock_file: Ruta al archivo de candado atómico.
        acquired: Booleano que indica si este proceso retiene el candado.
    """

    def __init__(self, proxy_str_or_url: str, test_url: str = ""):
        """Inicializa el guardián de vuelo para el proxy especificado.

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

    async def acquire_or_wait(self, timeout: float = 4.5) -> Optional[Dict[str, any]]:
        """Intenta adquirir el candado o espera el resultado si otro proceso ya lo retiene.

        Args:
            timeout: Tiempo máximo de espera en segundos (por defecto 4.5s).

        Returns:
            Optional[Dict[str, any]]: Resultado obtenido de la caché tras la espera, o None
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

    def release(self) -> None:
        """Libera el candado de vuelo eliminando el archivo de memoria compartida."""
        if self.acquired:
            try:
                self.lock_file.unlink(missing_ok=True)
            except Exception:
                pass
            self.acquired = False
