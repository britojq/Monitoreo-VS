"""
# ==============================================================================
# 📤 DESPACHADOR TELEGRAM CON MULTI-PROXY: telegram_dispatcher.py (@IA_ValleSeco_bot)
# Despacho resiliente de reportes y documentos técnicos con failover automático
# Ubicación: /opt/sonar/monitor/telegram_dispatcher.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Optional, Tuple

import httpx

from monitor.config_parser import MonitorConfigLoader
from monitor.core_shield import get_core_bot_token

logger = logging.getLogger("monitor.dispatcher")


class TelegramDispatcher:
    """Despachador robusto de alertas con failover por proxies corporativos.

    Attributes:
        loader: Cargador de configuración del monitor.
        token: Token del bot de Telegram.
        proxies: Lista de proxies corporativos configurados.
        active_proxy_url: URL del proxy activo en uso o None si la conexión es directa.
    """

    def __init__(self, token: Optional[str] = None):
        """Inicializa el despachador configurando el token y la lista de proxies.

        Args:
            token: Token de Telegram opcional; si no se provee, se obtiene del hardware vault o DB.
        """
        self.loader = MonitorConfigLoader()
        self.token = token or get_core_bot_token() or self.loader.raw_bot.get("TOKENA") or self.loader.raw_bot.get("TOKEN")
        self.proxies = self.loader.get_proxies()
        self.active_proxy_url: Optional[str] = None

    async def get_active_client(self, timeout: float = 10.0) -> httpx.AsyncClient:
        """Determina si hay salida directa o selecciona el proxy funcional.

        Args:
            timeout: Tiempo de espera en segundos para las peticiones HTTP del cliente.

        Returns:
            httpx.AsyncClient: Cliente asíncrono configurado para salir directamente o vía proxy.
        """
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
        parse_mode: Optional[str] = "HTML",
        reply_markup: Optional[dict | str] = None
    ) -> Tuple[bool, str]:
        """Envía un mensaje de texto formateado a Telegram con soporte opcional de teclado en línea.

        Args:
            chat_id: Identificador único del chat destinatario.
            text: Mensaje de texto a transmitir.
            parse_mode: Formato de marcado ('HTML' o 'Markdown').
            reply_markup: Teclado o menú inline serializado.

        Returns:
            Tuple[bool, str]: (éxito, mensaje descriptivo de confirmación o error).
        """
        if not self.token:
            return False, "Token de Telegram no configurado"

        import json
        from monitor.checker_base import markdown_to_telegram_html

        url = f"https://api.telegram.org/bot{self.token}/sendMessage"
        final_text = markdown_to_telegram_html(text) if parse_mode == "HTML" else text

        payload = {
            "chat_id": chat_id,
            "text": final_text
        }
        if parse_mode:
            payload["parse_mode"] = parse_mode
        if reply_markup:
            if isinstance(reply_markup, (dict, list)):
                payload["reply_markup"] = json.dumps(reply_markup)
            else:
                payload["reply_markup"] = reply_markup

        client = await self.get_active_client(timeout=12.0)
        try:
            async with client:
                r = await client.post(url, data=payload)
                if r.status_code == 200 and r.json().get("ok"):
                    return True, "Mensaje enviado exitosamente"

                # Si falló por formato de parse_mode, reintentar en texto plano
                if parse_mode and r.status_code == 400:
                    payload["text"] = text
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
        """Envía un archivo como documento adjunto a Telegram.

        Args:
            chat_id: Identificador del chat destinatario.
            document_path: Ruta en disco del documento a transmitir.
            caption: Texto explicativo opcional.

        Returns:
            Tuple[bool, str]: (éxito, mensaje de confirmación o detalle del fallo).
        """
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

    async def send_photo(
        self,
        chat_id: int | str,
        photo_path: str | Path,
        caption: Optional[str] = None
    ) -> Tuple[bool, str]:
        """Envía una imagen o captura de pantalla en alta definición a Telegram.

        Args:
            chat_id: Identificador del chat destinatario.
            photo_path: Ruta en disco de la imagen.
            caption: Texto explicativo opcional en formato HTML.

        Returns:
            Tuple[bool, str]: (éxito, mensaje de confirmación o detalle del error).
        """
        if not self.token:
            return False, "Token de Telegram no configurado"

        photo_p = Path(photo_path)
        if not photo_p.exists():
            return False, f"Imagen no encontrada: {photo_p}"

        url = f"https://api.telegram.org/bot{self.token}/sendPhoto"
        data = {"chat_id": str(chat_id)}
        if caption:
            data["caption"] = caption
            data["parse_mode"] = "HTML"

        client = await self.get_active_client(timeout=30.0)
        try:
            async with client:
                with open(photo_p, "rb") as f:
                    files = {"photo": (photo_p.name, f, "image/png")}
                    r = await client.post(url, data=data, files=files)
                    if r.status_code == 200 and r.json().get("ok"):
                        return True, "Foto enviada exitosamente"
                    return False, f"HTTP {r.status_code}: {r.text}"
        except Exception as e:
            return False, f"Error al enviar foto: {e}"

