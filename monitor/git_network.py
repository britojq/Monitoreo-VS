"""
# ==============================================================================
# 🌐 GITHUB NETWORK EVALUATOR & PROXY FAILOVER: git_network.py
# Determina la mejor ruta de salida a Internet (Directa vs Proxies Corporativos)
# para operaciones Git (fetch, pull, clone) en @IA_ValleSeco_bot.
# ==============================================================================
"""

import asyncio
import logging
import os
import sys
import urllib.parse
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import httpx

logger = logging.getLogger("monitor.git_network")

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_DIR = BASE_DIR / "config"
GITHUB_TEST_URL = "https://github.com"


def load_corporate_proxies() -> List[Dict[str, str]]:
    """Lee y decodifica la lista de proxies configurados en config.json o bot.conf."""
    proxies = []

    # 1. Intentar desde config.json
    cfg_json = CONFIG_DIR / "config.json"
    if cfg_json.exists():
        try:
            import json
            data = json.loads(cfg_json.read_text(encoding="utf-8"))
            if "proxies" in data and isinstance(data["proxies"], list):
                for p in data["proxies"]:
                    if p.get("enabled", True) and p.get("url"):
                        proxies.append({
                            "name": p.get("name", "Proxy"),
                            "url": p["url"]
                        })
                if proxies:
                    return proxies
        except Exception as e:
            logger.warning(f"Error leyendo proxies de config.json: {e}")

    # 2. Leer desde bot.conf
    bot_conf = CONFIG_DIR / "bot.conf"
    if not bot_conf.exists():
        bot_conf = BASE_DIR / "bot.conf"

    if bot_conf.exists():
        try:
            content = bot_conf.read_text(encoding="utf-8")
            data = {}
            for line in content.splitlines():
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    data[k.strip()] = v.strip().strip("'\"")

            for letter in ("A", "B", "C", "D"):
                ip = data.get(f"IPADDRPORTPROXY{letter}")
                auth = data.get(f"USERPASSWDPROXY{letter}")
                name = data.get(f"NAMEPROXY{letter}", f"Proxy {letter}")
                if ip:
                    if auth and ":" in auth:
                        user, pwd = auth.split(":", 1)
                        user_enc = urllib.parse.quote(user)
                        pwd_enc = urllib.parse.quote(pwd)
                        url = f"http://{user_enc}:{pwd_enc}@{ip}"
                    else:
                        url = f"http://{ip}"
                    proxies.append({
                        "name": name,
                        "url": url
                    })
        except Exception as e:
            logger.warning(f"Error leyendo proxies de bot.conf: {e}")

    return proxies


async def evaluate_github_connectivity(timeout: float = 4.0) -> Tuple[Optional[str], str, bool]:
    """
    Evalúa la salida a GitHub en tiempo real probando en orden de prioridad:
    1. Conexión directa a Internet.
    2. Proxies corporativos configurados (Squid Carabobo, Valle Seco, pfSense).
    Retorna: (proxy_url_o_none, etiqueta_ruta, es_funcional)
    """
    # 1. Probar conexión directa
    try:
        async with httpx.AsyncClient(timeout=timeout, follow_redirects=True) as client:
            r = await client.get(GITHUB_TEST_URL)
            if r.status_code in (200, 301, 302):
                logger.info("🌐 Salida DIRECTA a GitHub verificada exitosamente.")
                return None, "Conexión Directa a Internet", True
    except Exception as e:
        logger.info(f"Conexión directa a GitHub no disponible ({e}). Evaluando proxies...")

    # 2. Probar proxies corporativos
    proxies = load_corporate_proxies()
    for p in proxies:
        p_name = p["name"]
        p_url = p["url"]
        try:
            async with httpx.AsyncClient(proxy=p_url, timeout=timeout + 1.0, follow_redirects=True) as client:
                r = await client.get(GITHUB_TEST_URL)
                if r.status_code in (200, 301, 302):
                    logger.info(f"🔄 Salida a GitHub exitosa vía [{p_name}]: {p_url}")
                    return p_url, f"Proxy Corporativo ({p_name})", True
        except Exception as e:
            logger.debug(f"Proxy [{p_name}] sin salida a GitHub: {e}")

    logger.warning("❌ No se encontró salida a GitHub ni por conexión directa ni por proxies.")
    return None, "Sin Conexión a GitHub", False


def get_git_proxy_args(proxy_url: Optional[str]) -> List[str]:
    """Retorna los argumentos '-c' requeridos por el comando Git si se requiere proxy."""
    if not proxy_url:
        return []
    return [
        "-c", f"http.proxy={proxy_url}",
        "-c", f"https.proxy={proxy_url}"
    ]


if __name__ == "__main__":
    # Prueba interactiva desde terminal
    async def _test():
        print("🔍 Evaluando conectividad de Git hacia GitHub...")
        url, label, ok = await evaluate_github_connectivity()
        print(f"Resultado: {label}")
        print(f"Proxy URL: {url}")
        print(f"Funcional: {ok}")
        print(f"Git Args: {get_git_proxy_args(url)}")
    asyncio.run(_test())
