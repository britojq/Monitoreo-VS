# monitor/sync/probes/proxy_probe.py
import time
import urllib.parse
from typing import Optional, Tuple
import httpx
from monitor.sync.probes.ssl_factory import SSL_PERMISSIVE_CTX
from monitor.monitor_db import get_db_connection
from monitor.proxy_cache import (
    get_cached_proxy_result,
    save_cached_proxy_result,
    ProxyInflightGuard,
)

_KNOWN_PROXY_AUTH_CACHE: dict[str, str] = {}

def get_known_proxy_auth(proxy_str: str) -> str:
    """Recupera credenciales reales de proxies corporativos directamente desde MariaDB (SSOT)."""
    global _KNOWN_PROXY_AUTH_CACHE
    if not _KNOWN_PROXY_AUTH_CACHE:
        try:
            conn = get_db_connection()
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT ip_port, auth_userpass FROM monitored_proxies "
                    "WHERE auth_userpass IS NOT NULL AND auth_userpass != '' "
                    "AND auth_userpass != 'USUARIO:CLAVE'"
                )
                for row in cur.fetchall():
                    ip_p = (row.get("ip_port") or "").strip()
                    auth_p = (row.get("auth_userpass") or "").strip()
                    if ip_p and auth_p:
                        _KNOWN_PROXY_AUTH_CACHE[ip_p] = auth_p
            conn.close()
        except Exception:
            pass

    if not proxy_str:
        return ""
    cleaned = proxy_str.strip()
    if cleaned in _KNOWN_PROXY_AUTH_CACHE:
        return _KNOWN_PROXY_AUTH_CACHE[cleaned]
    ip_only = cleaned.split(":")[0]
    for k, v in _KNOWN_PROXY_AUTH_CACHE.items():
        if k == ip_only or k.startswith(ip_only + ":"):
            return v
    return ""

async def check_proxy_service(
    proxy_str: str,
    auth_userpass: Optional[str] = None,
    test_url: str = "https://core.telegram.org/bots",
    timeout: float = 5.0,
) -> Tuple[bool, float]:
    """Comprueba la operatividad de un proxy corporativo con autenticación sanitizada, URL encoding y caché."""
    if not proxy_str:
        return False, 0.0

    target = test_url if test_url else "https://core.telegram.org/bots"

    # Resolver credenciales reales si vienen vacías o con placeholder dummy 'USUARIO:CLAVE'
    effective_auth = auth_userpass
    if not effective_auth or effective_auth == "USUARIO:CLAVE":
        effective_auth = get_known_proxy_auth(proxy_str)

    # 1. Comprobar si existe resultado reciente EXITOSO en memoria RAM compartida (/dev/shm)
    cached = get_cached_proxy_result(proxy_str, target, max_age_seconds=60.0)
    if cached and cached.get("is_ok"):
        return cached["is_ok"], float(cached.get("latency_ms", 0.0))

    # 2. Candado In-Flight: Si otro proceso ya está chequeando este proxy en este instante, esperar resultado exitoso
    guard = ProxyInflightGuard(proxy_str, target)
    waited_cached = await guard.acquire_or_wait(timeout=timeout)
    if waited_cached and waited_cached.get("is_ok"):
        return waited_cached["is_ok"], float(waited_cached.get("latency_ms", 0.0))

    if effective_auth and ":" in effective_auth and effective_auth != "USUARIO:CLAVE":
        user, pwd = effective_auth.split(":", 1)
        user_enc = urllib.parse.quote(user)
        pwd_enc = urllib.parse.quote(pwd)
        proxy_url = f"http://{user_enc}:{pwd_enc}@{proxy_str}"
    else:
        proxy_url = f"http://{proxy_str}"

    start = time.perf_counter()
    try:
        async with httpx.AsyncClient(proxy=proxy_url, verify=SSL_PERMISSIVE_CTX, timeout=timeout) as client:
            r = await client.get(target)
            elapsed = (time.perf_counter() - start) * 1000.0
            is_ok = (r.status_code in (200, 301, 302))
            if not is_ok:
                # Fallback de contingencia contra endpoint alternativo para mitigar falsos positivos
                fb_target = "https://api.telegram.org" if "core.telegram" in target else "https://core.telegram.org/bots"
                try:
                    r_fb = await client.get(fb_target)
                    if r_fb.status_code in (200, 301, 302):
                        is_ok = True
                        r = r_fb
                        elapsed = (time.perf_counter() - start) * 1000.0
                except Exception:
                    pass
            detail = f"Proxy operativo (HTTP {r.status_code})" if is_ok else f"HTTP {r.status_code}"
            save_cached_proxy_result(proxy_str, target, is_ok, str(r.status_code), detail, elapsed, source="web_sync")
            return is_ok, round(elapsed, 1)
    except Exception:
        # Reintento con endpoint alternativo antes de declarar caída del proxy
        try:
            fb_target = "https://api.telegram.org" if "core.telegram" in target else "https://core.telegram.org/bots"
            async with httpx.AsyncClient(proxy=proxy_url, verify=SSL_PERMISSIVE_CTX, timeout=timeout) as client_fb:
                r_fb = await client_fb.get(fb_target)
                if r_fb.status_code in (200, 301, 302):
                    elapsed = (time.perf_counter() - start) * 1000.0
                    detail = f"Proxy operativo (HTTP {r_fb.status_code})"
                    save_cached_proxy_result(proxy_str, target, True, str(r_fb.status_code), detail, elapsed, source="web_sync")
                    return True, round(elapsed, 1)
        except Exception:
            pass
        elapsed = (time.perf_counter() - start) * 1000.0
        # No envenenar la memoria compartida si no se tenían credenciales válidas
        if effective_auth and effective_auth != "USUARIO:CLAVE":
            save_cached_proxy_result(proxy_str, target, False, "0", "Fallo de conexión", elapsed, source="web_sync")
        return False, 0.0
    finally:
        guard.release()
