"""
# ==============================================================================
# 🛠️ ACTIONS: VENTANAS DE MANTENIMIENTO: maintenance_action.py
# Programación y consulta de ventanas de supresión y mantenimiento.
# Ubicación: /opt/sonar/bot_core/handlers/alerts/actions/maintenance_action.py
# ==============================================================================
"""
from __future__ import annotations

from datetime import datetime, timedelta
import html
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.alerts.helpers import get_config, reply
from bot_core.security.auth import (
    MSG_UNAUTHORIZED_ADMIN_COMMAND,
    check_authorization,
)
from monitor.core_shield import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.alerts.maintenance")


async def handle_mantenimiento(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Gestiona o lista ventanas de mantenimiento."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    cfg = get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    args = context.args or []
    try:
        if not args:
            with db_cursor() as cur:
                cur.execute("""
                    SELECT id, title, entity_type, entity_id, starts_at, ends_at, is_active
                    FROM maintenance_windows
                    WHERE ends_at >= NOW() - INTERVAL 2 HOUR
                    ORDER BY starts_at DESC
                    LIMIT 10
                """)
                windows = cur.fetchall()

            if not windows:
                await reply(
                    update.message,
                    "🛠️ <b>VENTANAS DE MANTENIMIENTO</b>\n\n"
                    "No hay ventanas de mantenimiento programadas ni en curso."
                )
                return

            lines = ["🛠️ <b>VENTANAS DE MANTENIMIENTO PROGRAMADAS</b>\n"]
            now = datetime.now()
            for w in windows:
                s_at = w["starts_at"]
                e_at = w["ends_at"]
                is_running = (s_at and e_at and s_at <= now <= e_at)
                st_icon = "🟢 En Curso" if is_running else ("⏳ Programado" if s_at > now else "🏁 Finalizado")
                s_str = s_at.strftime("%d/%m %H:%M") if s_at else "N/A"
                e_str = e_at.strftime("%d/%m %H:%M") if e_at else "N/A"
                lines.append(
                    f"• <b>{html.escape(w['title'])}</b> [{st_icon}]\n"
                    f"   └ Entidad: <code>{w['entity_type']}</code> | {s_str} &rarr; {e_str}"
                )
            await reply(update.message, "\n".join(lines))
            return

        if not is_admin:
            await reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
            return

        entity = args[0].lower()
        minutes = int(args[1]) if len(args) > 1 and args[1].isdigit() else 60
        title = " ".join(args[2:]) if len(args) > 2 else f"Mantenimiento {entity} ({minutes}m)"

        now = datetime.now()
        ends = now + timedelta(minutes=minutes)
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")
        ends_str = ends.strftime("%Y-%m-%d %H:%M:%S")

        with db_cursor() as cur:
            cur.execute("""
                INSERT INTO maintenance_windows
                (title, description, entity_type, starts_at, ends_at, is_active, created_at, updated_at)
                VALUES (%s, 'Programado desde Telegram', %s, %s, %s, 1, %s, %s)
            """, (title, entity, now_str, ends_str, now_str, now_str))

        await reply(
            update.message,
            f"🛠️ <b>Ventana de Mantenimiento Programada</b>\n\n"
            f"• <b>Título:</b> {html.escape(title)}\n"
            f"• <b>Entidad:</b> <code>{entity}</code>\n"
            f"• <b>Duración:</b> {minutes} minutos (hasta {ends.strftime('%d/%m/%Y %H:%M')})\n"
            f"• <b>Estado:</b> 🟢 Activa inmediatamente"
        )

    except Exception as e:
        logger.error(f"Error en cmd_mantenimiento: {e}")
        await reply(update.message, f"❌ Error: {html.escape(str(e))}")
