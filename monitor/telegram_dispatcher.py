"""
Módulo de despacho de mensajes y documentos a Telegram con conmutación automática de proxies.
Garantiza la entrega de reportes y logs técnicos en cualquier condición de red.
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Optional, Tuple

import httpx

from monitor.config_parser import MonitorConfigLoader

logger = logging.getLogger("monitor.dispatcher")


class TelegramDispatcher:
    """Despachador robusto de alertas con failover por proxies corporativos."""

    def __init__(self, token: Optional[str] = None):
        self.loader = MonitorConfigLoader()
        self.token = token or self.loader.raw_bot.get("TOKENA") or self.loader.raw_bot.get("TOKEN")
        self.proxies = self.loader.get_proxies()
        self.active_proxy_url: Optional[str] = None

    async def get_active_client(self, timeout: float = 10.0) -> httpx.AsyncClient:
        """Determina si hay salida directa o selecciona el proxy funcional."""
        api_test_url = f"https://api.telegram.org/bot{self.token}/getMe"

        # 1. Probar conexión directa
        try:
            async with httpx.AsyncClient(timeout=3.5) as client:
                r = await client.get(api_test_url)
                if r.status_code == 200 and r.json().get("ok"):
                    self.active_proxy_url = None
                    return httpx.AsyncClient(timeout=timeout)
        except Exception as e:
            logger.warning(f"Conexión directa falló ({e}). Evaluando proxies...")

        # 2. Probar proxies disponibles
        for p in self.proxies:
            try:
                async with httpx.AsyncClient(proxy=p.url, timeout=4.5) as client:
                    r = await client.get(api_test_url)
                    if r.status_code == 200 and r.json().get("ok"):
                        logger.info(f"Proxy funcional detectado: {p.name}")
                        self.active_proxy_url = p.url
                        return httpx.AsyncClient(proxy=p.url, timeout=timeout)
            except Exception:
                continue

        logger.warning("No se detectó ningún canal de salida funcional. Intentando directo...")
        return httpx.AsyncClient(timeout=timeout)

    async def send_text(
        self,
        chat_id: int | str,
        text: str,
        parse_mode: Optional[str] = "Markdown"
    ) -> Tuple[bool, str]:
        """Envía un mensaje de texto formateado a Telegram."""
        if not self.token:
            return False, "Token de Telegram no configurado"

        url = f"https://api.telegram.org/bot{self.token}/sendMessage"
        payload = {
            "chat_id": chat_id,
            "text": text
        }
        if parse_mode:
            payload["parse_mode"] = parse_mode

        client = await self.get_active_client(timeout=12.0)
        try:
            async with client:
                r = await client.post(url, data=payload)
                if r.status_code == 200 and r.json().get("ok"):
                    return True, "Mensaje enviado exitosamente"

                # Si falló por formato de parse_mode (Markdown inválido), reintentar sin parse_mode
                if parse_mode and r.status_code == 400:
                    payload.pop("parse_mode", None)
                    r_retry = await client.post(url, data=payload)
                    if r_retry.status_code == 200 and r_retry.json().get("ok"):
                        return True, "Mensaje enviado (sin parse_mode)"

                return False, f"HTTP {r.status_code}: {r.text}"
        except Exception as e:
            return False, f"Error al enviar mensaje: {e}"

    async def send_document(
        self,
        chat_id: int | str,
        document_path: str | Path,
        caption: Optional[str] = None
    ) -> Tuple[bool, str]:
        """Envía un archivo como documento adjunto a Telegram."""
        if not self.token:
            return False, "Token de Telegram no configurado"

        doc_p = Path(document_path)
        if not doc_p.exists():
            return False, f"Archivo no encontrado: {doc_p}"

        url = f"https://api.telegram.org/bot{self.token}/sendDocument"
        data = {"chat_id": str(chat_id)}
        if caption:
            data["caption"] = caption

        client = await self.get_active_client(timeout=30.0)
        try:
            async with client:
                with open(doc_p, "rb") as f:
                    files = {"document": (doc_p.name, f, "text/plain")}
                    r = await client.post(url, data=data, files=files)
                    if r.status_code == 200 and r.json().get("ok"):
                        return True, "Documento enviado exitosamente"
                    return False, f"HTTP {r.status_code}: {r.text}"
        except Exception as e:
            return False, f"Error al enviar documento: {e}"
