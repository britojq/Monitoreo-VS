"""
# ==============================================================================
# 🛠️ UTILIDADES TELEGRAM Y SANITIZACIÓN: telegram_helpers.py
# Principio de Responsabilidad Única (S) - Formateo, partición y filtros de seguridad.
# ==============================================================================
"""

from __future__ import annotations

import html
import logging
import re
import time
from typing import Any, Dict, List, Optional

logger = logging.getLogger("bot.helpers")

# Tabla de límites de tasa en memoria
AI_RATE_LIMITS: Dict[int, List[float]] = {}


def split_message(text: str, limit: int = 4000) -> List[str]:
    """Divide un texto largo en fragmentos compatibles con el límite de Telegram."""
    text = (text or "").strip()
    if not text:
        return ["(Sin respuesta)"]

    chunks: List[str] = []
    while len(text) > limit:
        cut = text.rfind("\n", 0, limit)
        if cut <= limit // 2:
            cut = limit
        chunks.append(text[:cut].rstrip())
        text = text[cut:].lstrip()

    if text:
        chunks.append(text)
    return chunks


async def safe_reply_html(message_obj: Any, text: str, **kwargs: Any) -> None:
    """
    Envía un mensaje con parse_mode='HTML' de forma robusta.
    Particiona automáticamente si excede 4,000 caracteres con fallback seguro a texto plano.
    """
    text = (text or "").strip()
    if not text:
        return

    if len(text) > 4000:
        chunks = split_message(text, limit=4000)
        reply_markup = kwargs.pop("reply_markup", None)
        for i, chunk in enumerate(chunks):
            is_last = (i == len(chunks) - 1)
            extra_args = dict(kwargs)
            if is_last and reply_markup is not None:
                extra_args["reply_markup"] = reply_markup
            try:
                await message_obj.reply_text(chunk, parse_mode="HTML", **extra_args)
            except Exception as e_chunk:
                logger.warning(f"Error enviando fragmento {i+1}/{len(chunks)} en HTML ({e_chunk}). Reintentando plano...")
                clean_chunk = re.sub(r"<[^>]+>", "", chunk)
                try:
                    await message_obj.reply_text(clean_chunk, **extra_args)
                except Exception as e_final:
                    logger.error(f"Error crítico al enviar fragmento plano: {e_final}")
        return

    try:
        await message_obj.reply_text(text, parse_mode="HTML", **kwargs)
    except Exception as e:
        logger.warning(f"Error al enviar mensaje con parse_mode='HTML': {e}. Reintentando sin HTML.")
        clean_text = re.sub(r"<[^>]+>", "", text)
        if len(clean_text) > 4000:
            chunks = split_message(clean_text, limit=4000)
            reply_markup = kwargs.pop("reply_markup", None)
            for i, chunk in enumerate(chunks):
                is_last = (i == len(chunks) - 1)
                extra_args = dict(kwargs)
                if is_last and reply_markup is not None:
                    extra_args["reply_markup"] = reply_markup
                try:
                    await message_obj.reply_text(chunk, **extra_args)
                except Exception as e_f:
                    logger.error(f"Error enviando fragmento plano fallback: {e_f}")
        else:
            try:
                await message_obj.reply_text(clean_text, **kwargs)
            except Exception as e_direct:
                logger.error(f"Error crítico enviando mensaje plano final: {e_direct}")


def markdown_to_telegram_html(text: str) -> str:
    """Convierte Markdown estándar (títulos, negritas, código de consola) a HTML compatible con Telegram."""
    if not text:
        return ""

    code_blocks: List[tuple[str, str]] = []

    def _save_code_block(match: re.Match) -> str:
        lang = (match.group(1) or "").strip()
        code_content = match.group(2)
        idx = len(code_blocks)
        code_blocks.append((lang, code_content))
        return f"QQQBLOCKCODE{idx}ZZZ"

    text = re.sub(r"```([a-zA-Z0-9_-]*)\n?(.*?)```", _save_code_block, text, flags=re.DOTALL)

    inline_codes: List[str] = []

    def _save_inline_code(match: re.Match) -> str:
        inline_content = match.group(1)
        idx = len(inline_codes)
        inline_codes.append(inline_content)
        return f"QQQINLINECODE{idx}ZZZ"

    text = re.sub(r"`([^`\n]+)`", _save_inline_code, text)
    text = html.escape(text)
    text = re.sub(r"^[ \t]*[\*\-][ \t]+", r"• ", text, flags=re.MULTILINE)
    text = re.sub(r"^(#{1,6})\s+(.+)$", r"<b>\2</b>", text, flags=re.MULTILINE)
    text = re.sub(r"\*\*\*(.+?)\*\*\*", r"<b><i>\1</i></b>", text, flags=re.DOTALL)
    text = re.sub(r"___(.+?)___", r"<b><i>\1</i></b>", text, flags=re.DOTALL)
    text = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", text, flags=re.DOTALL)
    text = re.sub(r"(?<![a-zA-Z0-9])__(.+?)__(?![a-zA-Z0-9])", r"<b>\1</b>", text, flags=re.DOTALL)
    text = re.sub(r"(?<!\*)\*([^\*\n]+)\*(?!\*)", r"<i>\1</i>", text)
    text = re.sub(r"(?<![a-zA-Z0-9_])_([^_\n]+)_(?![a-zA-Z0-9_])", r"<i>\1</i>", text)

    for idx, (lang, code_content) in enumerate(code_blocks):
        escaped_code = html.escape(code_content.strip("\r\n"))
        if lang:
            replacement = f'<pre><code class="language-{html.escape(lang)}">{escaped_code}</code></pre>'
        else:
            replacement = f"<pre><code>{escaped_code}</code></pre>"
        text = text.replace(f"QQQBLOCKCODE{idx}ZZZ", replacement)

    for idx, inline_content in enumerate(inline_codes):
        escaped_inline = html.escape(inline_content)
        replacement = f"<code>{escaped_inline}</code>"
        text = text.replace(f"QQQINLINECODE{idx}ZZZ", replacement)

    return text


async def safe_deliver_update_result(target: Any, text: str, edit_msg: Any = None) -> None:
    """
    Garantiza la entrega del mensaje final de actualización o rollback sin colgar Telegram:
    - Soporta CallbackQuery y Message.
    - Si edit_msg existe, intenta eliminarlo limpiamente.
    - Maneja textos largos (>4000 caracteres) y etiquetas HTML inválidas con fallback a texto plano.
    """
    text = (text or "").strip()
    if not text:
        text = "ℹ️ Operación finalizada sin salida registrada."

    if edit_msg is not None:
        try:
            await edit_msg.delete()
        except Exception:
            pass

    from telegram import CallbackQuery, Message

    if isinstance(target, CallbackQuery):
        if len(text) <= 4000:
            try:
                await target.edit_message_text(text, parse_mode="HTML")
                return
            except Exception as e_html:
                logger.warning(f"Fallo al editar CallbackQuery con HTML ({e_html}). Reintentando texto plano...")
                try:
                    clean_text = re.sub(r"<[^>]+>", "", text)
                    await target.edit_message_text(clean_text)
                    return
                except Exception as e_plain:
                    logger.warning(f"Fallo al editar CallbackQuery con texto plano ({e_plain}). Enviando mensaje nuevo...")

        try:
            await target.edit_message_text(
                "✅ <b>Proceso completado.</b> <i>(Detalles enviados en el siguiente mensaje)</i>",
                parse_mode="HTML",
            )
        except Exception:
            pass

        if target.message:
            await safe_reply_html(target.message, text)
        return

    if isinstance(target, Message):
        await safe_reply_html(target, text)
        return

    if hasattr(target, "reply_text"):
        await safe_reply_html(target, text)


def sanitizar_pii(texto: str) -> str:
    """
    Filtro Anti-Fuga de Datos (PII):
    Sanitiza cédulas venezolanas y teléfonos locales reemplazándolos
    por [C.I. OCULTA] y [TLF. OCULTO] antes de procesar con IA.
    """
    if not texto:
        return ""

    # Teléfonos locales venezolanos
    patron_tlf = r"(?:\+?58[-\s]?)?0?(?:412|414|424|416|426|418|2\d{2})[-\s]?\d{3}[-\s]?\d{4}\b"
    texto = re.sub(patron_tlf, "[TLF. OCULTO]", texto)

    # Cédulas venezolanas
    patron_ci = r"\b(?:[VvEe][-\s]?|[Cc][Ii][:\.\s]*)?\d{7,8}\b"
    texto = re.sub(patron_ci, "[C.I. OCULTA]", texto)
    return texto


def check_ai_rate_limit(entity_id: int, max_requests: int = 5, window_seconds: float = 60.0) -> bool:
    """
    Limitador de tasa (Rate Limiting Anti-DoS) para consultas de IA.
    Retorna True si está dentro del límite permitido, False si lo excede.
    """
    now = time.time()
    timestamps = AI_RATE_LIMITS.setdefault(entity_id, [])
    timestamps = [t for t in timestamps if now - t < window_seconds]

    if len(timestamps) >= max_requests:
        AI_RATE_LIMITS[entity_id] = timestamps
        return False

    timestamps.append(now)
    AI_RATE_LIMITS[entity_id] = timestamps
    return True
