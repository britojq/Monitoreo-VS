"""
# ==============================================================================
# 🚨 ACTIONS: CICLO DE VIDA DE ALERTAS: alert_lifecycle_action.py
# Reconocimiento (ACK), Silenciamiento, Resolución y Reglas corporativas.
# Ubicación: /opt/sonar/bot_core/handlers/alerts/actions/alert_lifecycle_action.py
# ==============================================================================
"""
from __future__ import annotations

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
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.alerts.lifecycle")


async def handle_ack(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reconoce un incidente activo."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    cfg = get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)
    authorized_users = cfg.get("authorized_users", [])
    if not (is_admin or user_id in authorized_users or str(user_id) in authorized_users):
        await reply(update.message, "⛔ No tienes permisos para reconocer incidentes de infraestructura.")
        return

    args = context.args or []
    if not args or not args[0].isdigit():
        await reply(update.message, "⚠️ Debe indicar el ID de la alerta a reconocer. Ej: <code>/ack 1 [notas opcionales]</code>")
        return

    aid = int(args[0])
    notes = " ".join(args[1:]) if len(args) > 1 else "Reconocido desde Telegram"

    try:
        from monitor.alert_engine import AlertEngine
        engine = AlertEngine()
        u_id = update.effective_user.id if update.effective_user else 1
        ok = engine.acknowledge_alert(aid, user_id=u_id, notes=notes)

        if ok:
            user_name = update.effective_user.first_name if update.effective_user else "Operador"
            await reply(
                update.message,
                f"👁️ <b>Alerta #{aid} Reconocida con Éxito</b>\n\n"
                f"El incidente ha pasado a estado <code>ACKNOWLEDGED</code> por el usuario <b>{html.escape(user_name)}</b>."
            )
        else:
            await reply(update.message, f"⚠️ La alerta #{aid} no existe, ya fue reconocida o ya está resuelta.")
    except Exception as e:
        await reply(update.message, f"❌ Error reconociendo alerta: {html.escape(str(e))}")


async def handle_resolver(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Resuelve manualmente una alerta (Exclusivo Administrador)."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    cfg = get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if not (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id):
        await reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    args = context.args or []
    if not args or not args[0].isdigit():
        await reply(update.message, "⚠️ Especifique el ID de la alerta a resolver. Ej: <code>/resolver 1 [notas opcionales]</code>")
        return

    aid = int(args[0])
    notes = " ".join(args[1:]) if len(args) > 1 else "Cerrado manualmente por Administrador vía Telegram"

    try:
        from monitor.alert_engine import AlertEngine
        engine = AlertEngine()
        ok = engine.resolve_alert(aid, notes=notes)
        if ok:
            await reply(
                update.message,
                f"🏁 <b>Alerta #{aid} Marcada como Resuelta</b>\n\n"
                f"El incidente ha sido cerrado y archivado en el registro oficial del sistema."
            )
        else:
            await reply(update.message, f"⚠️ La alerta #{aid} no existe o ya se encuentra resuelta.")
    except Exception as e:
        await reply(update.message, f"❌ Error resolviendo alerta: {html.escape(str(e))}")


async def handle_silenciar(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Silencia temporalmente una alerta por N minutos."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    cfg = get_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    if not (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id):
        await reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    args = context.args or []
    if not args or not args[0].isdigit():
        await reply(update.message, "⚠️ Uso: <code>/silenciar &lt;id_alerta&gt; [minutos=60] [motivo]</code>")
        return

    aid = int(args[0])
    minutes = int(args[1]) if len(args) > 1 and args[1].isdigit() else 60
    notes = " ".join(args[2:]) if len(args) > 2 else f"Silenciada por {minutes}m desde Telegram"

    try:
        from monitor.alert_engine import AlertEngine
        engine = AlertEngine()
        ok = engine.silence_alert(aid, minutes=minutes, user_id=user_id, notes=notes)
        if ok:
            await reply(
                update.message,
                f"🛡️ <b>Alerta #{aid} Silenciada</b>\n\n"
                f"Se ha creado una ventana de supresión temporal por <b>{minutes} minutos</b>."
            )
        else:
            await reply(update.message, f"⚠️ No se encontró la alerta #{aid}.")
    except Exception as e:
        await reply(update.message, f"❌ Error silenciando alerta: {html.escape(str(e))}")


async def handle_reglas(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra las reglas de monitoreo y alerta configuradas."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    try:
        with db_cursor() as cur:
            cur.execute("""
                SELECT id, name, entity_type, condition_type, severity, threshold_value, comparison, cooldown_minutes
                FROM alert_rules
                WHERE is_active = 1
                ORDER BY id ASC
            """)
            rules = cur.fetchall()

        if not rules:
            await reply(update.message, "ℹ️ No hay reglas de alerta configuradas en la base de datos.")
            return

        sev_map = {
            "emergency": "🆘",
            "critical": "🚨",
            "warning": "⚠️",
            "info": "ℹ️"
        }
        lines = [
            f"📋 <b>REGLAS CORPORATIVAS DE ALERTA ({len(rules)})</b>\n"
        ]
        for r in rules:
            ico = sev_map.get(r["severity"], "🚨")
            thr = f" ({r['comparison']} {r['threshold_value']})" if r.get("threshold_value") is not None else ""
            lines.append(f"{ico} <b>{html.escape(r['name'])}</b>\n   └ <code>{r['entity_type']}</code> &rarr; <code>{r['condition_type']}</code>{thr} [Cooldown: {r['cooldown_minutes']}m]")

        await reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_reglas: {e}")
        await reply(update.message, f"❌ Error consultando reglas: {html.escape(str(e))}")
