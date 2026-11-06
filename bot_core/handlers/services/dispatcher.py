"""
# ==============================================================================
# 📤 DISPATCHER: bot_core/handlers/services/dispatcher.py
# Despacho formateado de reportes de monitoreo hacia chats privados o grupos.
# ==============================================================================
"""
from __future__ import annotations

import html
import logging
from pathlib import Path
from typing import Any, Dict, List, Optional

from bot_core.handlers.services.helpers import reply, resolve_group_info
from bot_core.utils.telegram_helpers import markdown_to_telegram_html

logger = logging.getLogger("bot.handlers.services.dispatcher")


async def dispatch_monitoring_to_group(
    bot: Any,
    target_groups: List[int],
    result: Dict[str, Any],
    screen_file: Optional[Path],
    mode_label: str,
    target_name: str,
    target: str,
    is_debug: bool,
    is_owner: bool,
    chat_id: int,
    message_obj: Any,
    wait_msg: Any,
) -> None:
    """Despacha reportes de monitoreo consolidados a los grupos corporativos indicados."""
    dispatched_groups = []
    html_svc = markdown_to_telegram_html(result["report_servicios"]) if result.get("report_servicios") else None
    html_sedes = markdown_to_telegram_html(result["report_sedes"]) if result.get("report_sedes") else None
    photo_bytes = screen_file.read_bytes() if (screen_file and screen_file.exists()) else None

    for gid in target_groups:
        try:
            if html_svc:
                try:
                    await bot.send_message(chat_id=gid, text=html_svc, parse_mode='HTML')
                except Exception as e_html:
                    logger.warning(f"Fallo envío HTML a grupo {gid} ({e_html}). Enviando texto plano...")
                    await bot.send_message(chat_id=gid, text=result["report_servicios"])

            if html_sedes:
                try:
                    await bot.send_message(chat_id=gid, text=html_sedes, parse_mode='HTML')
                except Exception as e_html:
                    logger.warning(f"Fallo envío HTML sedes a grupo {gid} ({e_html}). Enviando texto plano...")
                    await bot.send_message(chat_id=gid, text=result["report_sedes"])

            if photo_bytes:
                caption = (
                    f"📸 <b>Captura en Tiempo Real</b>\n"
                    f"🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n"
                    f"📌 <i>{mode_label}</i>"
                )
                await bot.send_photo(
                    chat_id=gid,
                    photo=photo_bytes,
                    caption=caption,
                    parse_mode='HTML'
                )

            gtitle = await resolve_group_info(bot, gid)
            dispatched_groups.append(f"• 👥 <b>{html.escape(gtitle)}</b> (<code>{gid}</code>)")
            logger.info(f"Reporte ({target}) despachado exitosamente al grupo {gid}")
        except Exception as e_grp:
            logger.error(f"Error despachando reporte a grupo {gid}: {e_grp}")
            dispatched_groups.append(f"• ⚠️ <code>{gid}</code>: Error ({html.escape(str(e_grp))})")

    try:
        await wait_msg.delete()
    except Exception:
        pass

    groups_summary = "\n".join(dispatched_groups) if dispatched_groups else "Sin destinatarios"
    elapsed_sec = result.get("elapsed_seconds", 0)
    confirm_text = (
        "✅ <b>Reporte Despachado al Grupo Corporativo</b>\n\n"
        f"📋 <b>Tipo de Reporte:</b> <code>{html.escape(target_name)}</code> ({mode_label})\n"
        f"⏱️ <b>Tiempo de Chequeo:</b> <code>{elapsed_sec}s</code>\n\n"
        f"<b>Destinatario(s):</b>\n{groups_summary}\n\n"
        "<i>ℹ️ El reporte textual y la captura de pantalla fueron enviados directamente al grupo corporativo autorizado.</i>"
    )
    await reply(message_obj, confirm_text)

    if is_debug and is_owner and result.get("log_file") and Path(result["log_file"]).exists():
        with open(result["log_file"], "rb") as doc:
            await bot.send_document(
                chat_id=chat_id,
                document=doc,
                filename="servicelog.txt",
                caption=f"📄 Registro técnico detallado de ejecución ({result['elapsed_seconds']}s)"
            )


async def dispatch_monitoring_to_chat(
    bot: Any,
    message_obj: Any,
    result: Dict[str, Any],
    screen_file: Optional[Path],
    mode_label: str,
    is_debug: bool,
    is_owner: bool,
    chat_id: int,
    wait_msg: Any,
) -> None:
    """Despacha reportes de monitoreo consolidados al chat origen de la solicitud."""
    try:
        await wait_msg.delete()
    except Exception:
        pass

    if result.get("skipped"):
        await reply(
            message_obj,
            "⚠️ <b>Chequeo en curso:</b> Ya existe una ronda de monitoreo ejecutándose en el servidor. Por favor espere unos segundos e intente nuevamente."
        )
        return

    if result.get("report_servicios"):
        html_svc = markdown_to_telegram_html(result["report_servicios"])
        await reply(message_obj, html_svc)

    if result.get("report_sedes"):
        html_sedes = markdown_to_telegram_html(result["report_sedes"])
        await reply(message_obj, html_sedes)

    if screen_file and screen_file.exists():
        caption = (
            f"📸 <b>Captura en Tiempo Real</b>\n"
            f"🏢 <b>SISTEMA DE MONITOREO VALLE SECO</b>\n"
            f"📌 <i>{mode_label}</i>"
        )
        with open(screen_file, "rb") as photo_doc:
            await message_obj.reply_photo(
                photo=photo_doc,
                caption=caption,
                parse_mode='HTML'
            )

    if is_debug and is_owner and result.get("log_file") and Path(result["log_file"]).exists():
        with open(result["log_file"], "rb") as doc:
            await bot.send_document(
                chat_id=chat_id,
                document=doc,
                filename="servicelog.txt",
                caption=f"📄 Registro técnico detallado de ejecución ({result['elapsed_seconds']}s)"
            )
