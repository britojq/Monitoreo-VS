"""
Despacho de notificaciones y espera activa de red para eventos de arranque y parada.
"""

import asyncio
import logging
from typing import List, Union

import httpx

logger = logging.getLogger("monitor.alerts.boot.notifier")


async def send_telegram_alert(token: str, chat_ids: List[Union[int, str]], message: str, proxies: List[dict] = None) -> bool:
    """Envía la alerta a los chat_ids especificados probando directo y con proxies con reintentos."""
    if not token or not chat_ids:
        logger.error("Token o chat_ids vacíos")
        return False

    url = f"https://api.telegram.org/bot{token}/sendMessage"
    success_any = False

    # Lista de endpoints/clientes a probar: Directo primero, luego proxies
    client_configs = [None]  # None = directo
    if proxies:
        for p in proxies:
            if isinstance(p, dict) and p.get("url"):
                client_configs.append(p["url"])
            elif isinstance(p, str):
                client_configs.append(p)

    for chat_id in chat_ids:
        delivered = False
        for attempt in range(1, 4):
            for proxy_url in client_configs:
                try:
                    async with httpx.AsyncClient(proxy=proxy_url, timeout=10.0) as client:
                        resp = await client.post(url, json={
                            "chat_id": chat_id,
                            "text": message,
                            "parse_mode": "HTML"
                        })
                        if resp.status_code == 200 and resp.json().get("ok"):
                            logger.info(f"Mensaje entregado a {chat_id} exitosamente (intento {attempt}).")
                            delivered = True
                            success_any = True
                            break
                except Exception as e:
                    logger.debug(f"Fallo envío a {chat_id} (intento {attempt}) via {'directo' if not proxy_url else proxy_url}: {e}")

            if delivered:
                break
            await asyncio.sleep(3)

        if not delivered:
            logger.warning(f"No se pudo entregar la alerta al chat {chat_id} tras reintentos.")

    return success_any


async def wait_for_network_and_telegram(token: str, proxies: List[dict], max_wait_seconds: int = 240) -> bool:
    """Espera activamente a que la red y la API de Telegram estén accesibles tras el arranque."""
    logger.info(f"Iniciando espera activa de conectividad (hasta {max_wait_seconds}s)...")
    url = f"https://api.telegram.org/bot{token}/getMe"

    elapsed = 0
    step = 4

    while elapsed < max_wait_seconds:
        # 1. Probar directo
        try:
            async with httpx.AsyncClient(timeout=3.5) as client:
                r = await client.get(url)
                if r.status_code == 200 and r.json().get("ok"):
                    logger.info(f"Conectividad con Telegram confirmada (Directo) tras {elapsed}s.")
                    return True
        except Exception:
            pass

        # 2. Probar proxies
        if proxies:
            for p in proxies:
                p_url = p.get("url") if isinstance(p, dict) else p
                if p_url:
                    try:
                        async with httpx.AsyncClient(proxy=p_url, timeout=3.5) as client:
                            r = await client.get(url)
                            if r.status_code == 200 and r.json().get("ok"):
                                logger.info(f"Conectividad con Telegram confirmada (Proxy: {p_url}) tras {elapsed}s.")
                                return True
                    except Exception:
                        pass

        if elapsed % 20 == 0 and elapsed > 0:
            logger.info(f"Aún esperando conectividad con Telegram ({elapsed}s/{max_wait_seconds}s)...")

        await asyncio.sleep(step)
        elapsed += step

    logger.warning(f"Tiempo de espera de red agotado tras {max_wait_seconds}s.")
    return False


async def send_shutdown_alert_fast(token: str, chat_id: Union[int, str], message: str, proxies: List[dict] = None) -> bool:
    """
    Envío ultra-rápido y prioritario para apagado del sistema.
    Timeout estricto de 3.5s por conexión sin sleeps para ganar la carrera al apagado de interfaces.
    """
    if not token or not chat_id:
        return False

    url = f"https://api.telegram.org/bot{token}/sendMessage"
    payload = {
        "chat_id": chat_id,
        "text": message,
        "parse_mode": "HTML"
    }

    # Targets: Directo primero, luego proxies configurados
    targets = [None]
    if proxies:
        for p in proxies:
            p_url = p.get("url") if isinstance(p, dict) else p
            if p_url and p_url not in targets:
                targets.append(p_url)

    for target in targets:
        try:
            async with httpx.AsyncClient(proxy=target, timeout=4.0) as client:
                resp = await client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta de apagado entregada exitosamente (Vía: {target or 'Directo'}).")
                    return True
        except Exception as e:
            logger.warning(f"Fallo envío rápido de apagado con {target or 'Directo'}: {e}")
            continue

    logger.warning("No se pudo entregar la alerta rápida de apagado por ninguna vía.")
    return False
