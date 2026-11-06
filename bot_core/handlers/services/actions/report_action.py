"""
# ==============================================================================
# 📊 ACTIONS: MONITOREO Y REPORTES: report_action.py
# Orquestación de reportes de Sedes, Servicios, Caídas y Capturas de Pantalla.
# Ubicación: /opt/sonar/bot_core/handlers/services/actions/report_action.py
# ==============================================================================
"""
from __future__ import annotations

import asyncio
import html
import logging

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.services.dispatcher import (
    dispatch_monitoring_to_chat,
    dispatch_monitoring_to_group,
)
from bot_core.handlers.services.helpers import reply
from bot_core.handlers.services.report_security import validate_report_request

logger = logging.getLogger("bot.handlers.services.report")


async def run_and_send_monitoring_report(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    target: str = "completo",
    target_name: str = "Infraestructura",
    force_debug: bool = False,
) -> None:
    """Función unificada para ejecutar chequeos concurrentes y enviar reportes a Telegram."""
    val = await validate_report_request(update, context, target, target_name, force_debug)
    if not val or not update.message:
        return

    if val.send_to_group:
        wait_msg = await update.message.reply_text(
            f"⏳ <i>Ejecutando chequeo de {target_name} ({'On-Demand' if val.is_ondemand else 'Snapshot'}) y preparando despacho hacia el grupo corporativo...</i>",
            parse_mode='HTML'
        )
    elif val.is_ondemand:
        wait_msg = await update.message.reply_text(
            f"⏳ <i>Ejecutando sondeo físico de red en caliente (On-Demand) de {target_name}... Por favor espere.</i>",
            parse_mode='HTML'
        )
    else:
        wait_msg = await update.message.reply_text(
            f"⏳ <i>Consultando reporte oficial (Snapshot) de {target_name}... Por favor espere.</i>",
            parse_mode='HTML'
        )

    try:
        from monitor.application import execute_monitoring
        from monitor.tools.web_screenshot import capture_web_dashboard

        if val.is_ondemand:
            mode_label = "Chequeo Físico en Caliente (On-Demand)"
            screen_file = None
            result = await execute_monitoring(
                target=target,
                debug_mode=val.is_debug,
                capture_screenshot=False,
                mode="ondemand"
            )
        else:
            args_lower = [a.lower() for a in (context.args or [])]
            arg_first = (context.args[0].lower() if (context.args and len(context.args) > 0) else "")
            if val.send_to_group or arg_first in ("web", "full", "pantalla", "todo", "global"):
                capture_mode = "full"
                mode_label = "Vista Global"
            elif target == "servicios":
                capture_mode = "servicios"
                mode_label = "Servicios Activos"
            elif target == "sedes":
                capture_mode = "sedes"
                mode_label = "Sedes Regionales y Equipos en Sitio"
            elif target in ("caidas", "incidentes", "fallas"):
                capture_mode = "caidas"
                mode_label = "Incidentes y Servicios Caídos"
            else:
                capture_mode = "full"
                mode_label = "Vista Global"

            for a in args_lower:
                if a in ("full", "global", "todo", "pantalla"):
                    capture_mode = "full"
                    mode_label = "Vista Global"
                    break
                elif a == "servicios":
                    capture_mode = "servicios"
                    mode_label = "Servicios Activos"
                    break
                elif a == "sedes":
                    capture_mode = "sedes"
                    mode_label = "Sedes Regionales y Equipos en Sitio"
                    break
                elif a in ("caidas", "fallas", "incidentes"):
                    capture_mode = "caidas"
                    mode_label = "Incidentes y Servicios Caídos"
                    break

            monitoring_task = asyncio.create_task(
                execute_monitoring(target=target, debug_mode=val.is_debug, capture_screenshot=False, mode="snapshot")
            )
            screenshot_task = asyncio.create_task(
                capture_web_dashboard(mode=capture_mode)
            )

            monitoring_res, screen_res = await asyncio.gather(monitoring_task, screenshot_task, return_exceptions=True)

            if isinstance(monitoring_res, Exception):
                raise monitoring_res
            result = monitoring_res

            screen_file = None
            if isinstance(screen_res, Exception):
                logger.warning(f"No se pudo generar captura web ({screen_res})")
            else:
                screen_file = screen_res

        if val.send_to_group:
            await dispatch_monitoring_to_group(
                bot=context.bot,
                target_groups=val.target_groups,
                result=result,
                screen_file=screen_file,
                mode_label=mode_label,
                target_name=target_name,
                target=target,
                is_debug=val.is_debug,
                is_owner=val.is_owner,
                chat_id=val.chat_id,
                message_obj=update.message,
                wait_msg=wait_msg,
            )
        else:
            await dispatch_monitoring_to_chat(
                bot=context.bot,
                message_obj=update.message,
                result=result,
                screen_file=screen_file,
                mode_label=mode_label,
                is_debug=val.is_debug,
                is_owner=val.is_owner,
                chat_id=val.chat_id,
                wait_msg=wait_msg,
            )

    except Exception as e:
        logger.error(f"Error ejecutando monitoreo ({target}): {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el chequeo:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await reply(update.message, f"❌ Error ejecutando chequeo: {e}")


async def cmd_reporte_servicios(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Servicios Corporativos."""
    await run_and_send_monitoring_report(update, context, target="servicios", target_name="Servicios Corporativos")


async def cmd_reporte_sedes(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Sedes y Enlaces."""
    await run_and_send_monitoring_report(update, context, target="sedes", target_name="Sedes y Equipos de Comunicación")


async def cmd_reporte_caidas(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte de Incidentes y Caídas."""
    await run_and_send_monitoring_report(update, context, target="caidas", target_name="Incidentes y Servicios Caídos")


async def cmd_captura_web(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando directo para obtener la captura web en tiempo real."""
    await run_and_send_monitoring_report(update, context, target="web", target_name="Vista Web en Tiempo Real")


async def cmd_reporte_completo(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar y despachar el reporte completo (Servicios + Sedes)."""
    await run_and_send_monitoring_report(update, context, target="completo", target_name="Servicios Corporativos y Sedes")
