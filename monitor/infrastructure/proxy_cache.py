"""
# ==============================================================================
# 🛡️ FACHADA DE CACHÉ DE PROXIES: monitor/infrastructure/proxy_cache.py
# Delegación hacia bot_core.services.proxy_cache (ARCH-002).
# Preserva compatibilidad total de importación hacia atrás.
# ==============================================================================
"""

from __future__ import annotations

from bot_core.services.proxy_cache import (
    SHM_DIR,
    TMP_DIR,
    ProxyInflightGuard,
    get_cache_dir,
    get_cached_proxy_result,
    get_proxy_hash,
    get_proxy_key_hash,
    normalize_proxy_key,
    save_cached_proxy_result,
)

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
