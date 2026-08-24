"""
Módulo autónomo de detección de arranque y apagado del servidor.
Detecta si el arranque es limpio o tras falla eléctrica / apagado forzado.
Notifica a Telegram con soporte multi-proxy y espera activa de red.
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import os
import socket
import subprocess
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import httpx

# Configuración de logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)
logger = logging.getLogger("monitor.boot_alert")

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_PATH = BASE_DIR / "config" / "config.json"
AUDIT_DIR = BASE_DIR / "audit"
MARKER_FILE = AUDIT_DIR / ".clean_shutdown"

AUDIT_DIR.mkdir(parents=True, exist_ok=True)


def load_config() -> Dict[str, any]:
    """Carga config.json con fallback seguro."""
    if CONFIG_PATH.exists():
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception as e:
            logger.error(f"Error leyendo {CONFIG_PATH}: {e}")
    return {}


def get_local_ips() -> str:
    """Obtiene las direcciones IP locales activas del sistema."""
    try:
        res = subprocess.run(["hostname", "-I"], capture_output=True, text=True, timeout=3)
        ips = res.stdout.strip()
        if ips:
            return ips
    except Exception:
        pass

    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "No asignada (sin red detectada)"


async def send_telegram_alert(token: str, chat_ids: List[int | str], message: str, proxies: List[dict] = None) -> bool:
    """Envía la alerta a todos los chat_ids especificados probando directo y con proxies."""
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
        for proxy_url in client_configs:
            try:
                async with httpx.AsyncClient(proxy=proxy_url, timeout=8.0) as client:
                    resp = await client.post(url, json={
                        "chat_id": chat_id,
                        "text": message,
                        "parse_mode": "HTML"
                    })
                    if resp.status_code == 200 and resp.json().get("ok"):
                        logger.info(f"Mensaje entregado a {chat_id} exitosamente.")
                        delivered = True
                        success_any = True
                        break
            except Exception as e:
                logger.debug(f"Fallo envío a {chat_id} via {'directo' if not proxy_url else proxy_url}: {e}")

        if not delivered:
            logger.warning(f"No se pudo entregar la alerta al chat {chat_id}.")

    return success_any


async def wait_for_network_and_telegram(token: str, proxies: List[dict], max_wait_seconds: int = 120) -> bool:
    """Espera activamente a que la red y la API de Telegram estén accesibles tras el arranque."""
    logger.info(f"Iniciando espera activa de conectividad (máximo {max_wait_seconds}s)...")
    url = f"https://api.telegram.org/bot{token}/getMe"
    
    elapsed = 0
    step = 5
    
    while elapsed < max_wait_seconds:
        # Probar directo
        try:
            async with httpx.AsyncClient(timeout=4.0) as client:
                r = await client.get(url)
                if r.status_code == 200 and r.json().get("ok"):
                    logger.info(f"Conectividad con Telegram verificada exitosamente tras {elapsed}s.")
                    return True
        except Exception:
            pass

        # Probar proxies
        if proxies:
            for p in proxies:
                p_url = p.get("url") if isinstance(p, dict) else p
                if p_url:
                    try:
                        async with httpx.AsyncClient(proxy=p_url, timeout=4.0) as client:
                            r = await client.get(url)
                            if r.status_code == 200 and r.json().get("ok"):
                                logger.info(f"Conectividad con Telegram verificada via proxy tras {elapsed}s.")
                                return True
                    except Exception:
                        pass

        await asyncio.sleep(step)
        elapsed += step

    logger.warning(f"Tiempo de espera de red agotado tras {max_wait_seconds}s.")
    return False


async def handle_start() -> int:
    """Maneja la notificación de arranque del sistema."""
    config = load_config()
    token = config.get("bot_token")
    owner_id = config.get("owner_id")
    group_ids = config.get("allowed_group_ids", [])
    proxies = config.get("proxies", [])

    if not token or not owner_id:
        logger.error("Configuración incompleta (bot_token u owner_id faltante).")
        return 0

    # Esperar conectividad activa
    await wait_for_network_and_telegram(token, proxies, max_wait_seconds=90)

    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    local_ips = get_local_ips()

    # Evaluar archivo testigo de apagado limpio
    if MARKER_FILE.exists():
        estado_titulo = "🟢 <b>Reinicio Limpio o Apagado Controlado</b>"
        detalle_texto = "El equipo se apagó o reinició de manera controlada por el sistema."
        try:
            MARKER_FILE.unlink()
        except Exception:
            pass
    else:
        estado_titulo = "🚨 <b>Falla Eléctrica / Apagado Forzado Inesperado</b>"
        detalle_texto = "Se detectó un corte de energía eléctrica, desconexión o apagado abrupto (no se registró una parada limpia previa)."

    mensaje = (
        "🚀 <b>Servidor Iniciado</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Host:</b> <code>{html.escape(hostname)}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
        f"🌐 <b>IPs Locales:</b> <code>{html.escape(local_ips)}</code>\n"
        f"🏷️ <b>Estado:</b> {estado_titulo}\n"
        f"📋 <b>Detalle:</b> {detalle_texto}\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "<i>Sistema operando en Debian GNU/Linux • CENCARATIT</i>"
    )

    # Enviar alerta de arranque EXCLUSIVAMENTE al Owner
    recipients = [owner_id]

    await send_telegram_alert(token, recipients, mensaje, proxies)
    return 0


async def handle_stop() -> int:
    """Maneja la notificación de parada / apagado limpio del sistema."""
    config = load_config()
    token = config.get("bot_token")
    owner_id = config.get("owner_id")
    proxies = config.get("proxies", [])

    # 1. Crear archivo testigo de apagado limpio
    try:
        MARKER_FILE.write_text(datetime.now().strftime("%Y-%m-%d %H:%M:%S\n"), encoding="utf-8")
        logger.info("Archivo testigo .clean_shutdown creado exitosamente.")
    except Exception as e:
        logger.error(f"Error creando archivo testigo: {e}")

    # 2. Enviar alerta de apagado inmediato si hay conectividad
    if token and owner_id:
        hostname = socket.gethostname()
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        mensaje = (
            "🛑 <b>Alerta: Servidor Apagándose / Reiniciando</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"🖥️ <b>Host:</b> <code>{html.escape(hostname)}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
            f"📋 <b>Motivo:</b> Parada limpia / reinicio ordenado por el sistema.\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "<i>El sistema está cerrando servicios antes de desconectar la red.</i>"
        )
        try:
            await send_telegram_alert(token, [owner_id], mensaje, proxies)
        except Exception as e:
            logger.warning(f"No se pudo enviar notificación de stop: {e}")

    return 0


def main():
    action = sys.argv[1].lower() if len(sys.argv) > 1 else "start"
    if action == "stop":
        sys.exit(asyncio.run(handle_stop()))
    elif action in ("start", "test"):
        sys.exit(asyncio.run(handle_start()))
    else:
        print(f"Uso: {sys.argv[0]} [start|stop|test]")
        sys.exit(1)


if __name__ == "__main__":
    main()
