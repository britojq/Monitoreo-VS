"""
# ==============================================================================
# 🚪 MANEJADORES BASE Y MENÚS PRINCIPALES: base_handlers.py
# Bienvenida, ayuda contextual (/start, /ayuda) y comandos no reconocidos.
# Principio de Responsabilidad Única (S) y Principios SOLID.
# ==============================================================================
"""

from __future__ import annotations

import html
import logging
import sys
from typing import Any, Dict, List

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.security.auth import check_authorization
from bot_core.services.ai_chat_service import load_commands_data
from bot_core.services.config_manager import load_config
from bot_core.utils.telegram_helpers import safe_reply_html

logger = logging.getLogger("bot.handlers.base")


def _get_active_config() -> Dict[str, Any]:
    """Obtiene la configuración activa del bot."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


def _get_commands() -> Dict[str, Any]:
    """Obtiene el catálogo de comandos dinámicos."""
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "COMMANDS"):
        return getattr(bot_mod, "COMMANDS")
    res = load_commands_data()
    if isinstance(res, tuple):
        return res[1]
    return res


async def _reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Invoca safe_reply_html de manera dinámica permitiendo mockeo en tests."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html)
    await fn(message_obj, text, **kwargs)


async def start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el mensaje inicial y el panel de comandos personalizados según el rol del usuario (Owner vs Usuario Autorizado)."""
    if not await check_authorization(update, context):
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", 0)
    user = update.effective_user
    chat = update.effective_chat
    user_id = user.id if user else 0
    is_owner = (user_id == owner_id)
    is_private = (chat.type == "private") if chat else False
    commands_dict = _get_commands()

    # Si se solicitó ayuda específica de un comando (/help comando)
    if context.args:
        target_cmd = context.args[0].lstrip("/").lower()
        if target_cmd in commands_dict:
            help_msg = commands_dict[target_cmd].get(
                "help_text",
                f"Sin información detallada para /{target_cmd}."
            )
            await _reply(update.message, help_msg)
            return

    first_name = (user.first_name or "").strip() if user else ""
    last_name = (user.last_name or "").strip() if user else ""
    if last_name.lower() == "none":
        last_name = ""
    display_name = " ".join([p for p in [first_name, last_name] if p]) or "Administrador"

    commands_enabled = bool(cfg.get("commands_enabled", True))

    # =========================================================================
    # 👑 MENÚ PERSONALIZADO EXCLUSIVO PARA EL PROPIETARIO (SOLO EN CHAT PRIVADO)
    # =========================================================================
    if is_owner and is_private:
        owner_menu_p1 = [
            "👑 <b>PANEL DE CONTROL PRINCIPAL • PARTE 1/2</b>\n",
            f"¡Bienvenido, <b>{html.escape(display_name)}</b>!\n",
            "A continuación tienes el inventario de herramientas administrativas:\n",
            "🛡️ <b>Gestión de Seguridad y Accesos</b>",
            "• <code>/permisos</code> <i>(/autorizados, /whitelist)</i> - Gestión interactiva de usuarios y grupos autorizados.",
            "• <code>/grupos</code> <i>(/mis_grupos, /chat_grupos)</i> - Auditoría en tiempo real de grupos activos y forzar salida.",
            "• <code>/salir_grupo [ID]</code> <i>(/leave_group, /expulsar_grupo)</i> - Forzar salida del bot de un grupo específico.",
            "• <code>/bloqueo_comandos</code> <i>(/bloquear_comandos)</i> - Bloquear o reactivar el uso de comandos para usuarios y grupos.",
            "• <code>/botstatus</code> <i>(/estatus, /status, /estado_bot)</i> - Diagnóstico de conectividad, proxies corporativos y accesos denegados.",
            "• <code>/mensaje [texto]</code> <i>(/broadcast, /comunicado)</i> - Envío de comunicados y avisos masivos a usuarios autorizados.",
            "• <code>/migrar_token</code> - Migración guiada y segura del token del bot a un nuevo bot.",
            "• <code>/info</code> <i>(/aviso, /legal)</i> - Información legal, privacidad y advertencia de seguridad.\n",
            "🛠️ <b>Mantenimiento y Rendimiento del Sistema</b>",
            "• <code>/recursos</code> <i>(/memoria, /optimizar, /plasma)</i> - Diagnóstico de RAM, Swap, CPU y panel interactivo para liberar memoria / reiniciar Plasma Shell.",
            "• <code>/temperatura</code> <i>(/temp, /termal, /cpu_temp)</i> - Telemetría térmica de CPU en tiempo real y estado del guardián de hardware.",
            "• <code>/cron</code> <i>(/envios, /programacion)</i> - Panel interactivo para activar, pausar y configurar horarios de reportes automáticos.",
            "• <code>/emergencia</code> <i>(/panico, /contingencia)</i> - Panel de emergencia (detener servicio, modo mantenimiento, restaurar config).",
            "• <code>/limpiador</code> <i>(/limpieza, /cleaner)</i> - Diagnóstico de almacenamiento, inodos y panel interactivo de limpieza.",
            "• <code>/actualizar</code> <i>(/update, /git_update)</i> - Comprobar y aplicar actualizaciones desde GitHub.",
            "• <code>/rollback</code> <i>(/revertir)</i> - Reversión segura de versión y restauración atómica de MariaDB.",
            "• <code>/estado_deploy</code> <i>(/deploy_status)</i> - Estado de GitOps, ruta de red, respaldos y Circuit Breaker.",
            "• <code>/desbloquear_update</code> - Liberar Circuit Breaker de actualización tras solventar incidentes.",
            "• <code>/reinicia</code> <i>(/reboot)</i> - Reinicio completo del servidor host del sistema."
        ]

        owner_menu_p2 = [
            "📊 <b>PANEL DE CONTROL PRINCIPAL • PARTE 2/2</b>\n",
            "<b>Monitoreo e Infraestructura de Red</b>",
            "• <code>/servicios [web|grupo]</code> <i>(/reporte_servicios, /servicios_grupo)</i> - Chequeo de Servicios Corporativos (con captura web o despacho al grupo).",
            "• <code>/sedes [web|grupo]</code> <i>(/reporte_sedes, /sedes_grupo, /sitios)</i> - Chequeo de Sedes y Enlaces de Comunicación (con captura web o despacho al grupo).",
            "• <code>/caidas [web|grupo]</code> <i>(/incidentes, /fallas, /caidas_grupo)</i> - Reporte enfocado en fallas, servicios caídos y sedes desconectadas.",
            "• <code>/web [modo]</code> <i>(/pantalla, /dashboard)</i> - Captura gráfica panorámica HD del portal en tiempo real.",
            "• <code>/monitoreo [grupo]</code> <i>(/reporte_completo, /monitoreo_grupo)</i> - Reporte unificado integral (Servicios + Sedes).",
            "• <code>/internet</code> <i>(/proxy, /proxies)</i> - Diagnóstico de conectividad a internet y proxies corporativos.",
            "• <code>/ups</code> <i>(/energia, /bateria, /power)</i> - Telemetría en tiempo real del UPS ZTG LV6KL, modo de operación, voltajes y autonomía de batería.",
            "• <code>/analisis_red [tiempo]</code> <i>(/red)</i> - Captura de tráfico en vivo (<code>tcpdump</code> 120s), análisis profundo (<code>tshark</code>) y entrega de reportes <code>.md</code> y <code>.html</code>.\n",
            "🧪 <b>Diagnóstico Exhaustivo y Depuración (Exclusivo Owner)</b>",
            "• <code>/debug_servicios</code> - Reporte exhaustivo de todos los servicios (A a Z) con plantilla <code>MENSAJEDEBUGA</code> + <code>servicelog.txt</code>.",
            "• <code>/debug_sedes</code> - Reporte exhaustivo de todas las sedes y equipos con plantilla <code>MENSAJEDEBUGB</code> + <code>servicelog.txt</code>.",
            "• <code>/debug_completo</code> <i>(/debug_monitoreo)</i> - Reporte técnico integral exhaustivo (Servicios + Sedes) + <code>servicelog.txt</code>.",
            "• <code>/debug_monitor</code> <i>(/monitordebug)</i> - Conmutar interruptor de modo depuración global para todos los reportes.\n",
            "🧠 <b>ASISTENTE (IA)</b>",
            "• <code>/ia [on|off|status]</code> <i>(/motor_ia, /toggle_ia)</i> - Panel interactivo para activar o desactivar en caliente el motor local de IA.",
            "• <code>/reset_ia</code> <i>(/borrar_chat)</i> - Reiniciar el contexto de la conversación con el asistente.\n",
            "<i>Recuerda también que puedes escribir directamente en el chat para interactuar con la IA.</i>\n",
            "<i>📌 <b>Nota sobre la memoria:</b> Después de entregar la respuesta técnica detallada, el bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento hasta que se use el comando <code>/reset_ia</code> y reinicie para una nueva consulta referente a otro tema.</i>\n",
            "<i>💡 Todas las interacciones que se tengan con el asistente de IA sirven de retroalimentación, en caso de obtener una solución se le puede enviar para ampliar el conocimiento de la IA.</i>\n"
        ]

        if commands_enabled and commands_dict:
            already_listed = {
                "permisos", "grupos", "mis_grupos", "listar_grupos", "chat_grupos",
                "salir_grupo", "leave_group", "expulsar_grupo", "abandonar_grupo",
                "bloqueo_comandos", "botstatus", "info", "mensaje", "migrar_token",
                "recursos", "temperatura", "temp", "termal", "cpu_temp", "cron", "emergencia", "limpiador", "actualizar", "reinicia",
                "rollback", "revertir", "estado_deploy", "deploy_status", "desbloquear_update", "unlock_update",
                "servicios", "sedes", "caidas", "web", "monitoreo", "internet", "analisis_red",
                "analisisred", "debug_servicios", "debug_sedes", "debug_completo", "debug_monitor",
                "ia", "motor_ia", "toggle_ia", "servicio_ia", "reset_ia", "ups", "energia", "bateria", "power",
                "inventario", "ciclovida", "configs", "predicciones", "alertas", "ack", "resolver", "silenciar", "mantenimiento", "reglas"
            }
            extra_cmds = [
                f"• <code>/{cmd_name}</code> - {html.escape(cmd_info.get('description', 'Sin descripción'))}"
                for cmd_name, cmd_info in commands_dict.items()
                if cmd_name.lower() not in already_listed
            ]
            if extra_cmds:
                owner_menu_p2.append("⚙️ <b>Comandos Adicionales del Sistema:</b>")
                owner_menu_p2.extend(extra_cmds)

        owner_menu_p2.append("\n<i>Sistema operando en Debian GNU/Linux • Python 3.11</i>")

        await _reply(update.message, "\n".join(owner_menu_p1))
        await _reply(update.message, "\n".join(owner_menu_p2))
        return

    # =========================================================================
    # 👤 MENÚ PARA USUARIOS AUTORIZADOS EN CHAT PRIVADO (ASISTENTE IA)
    # =========================================================================
    if is_private:
        user_private_menu = [
            "🤖 <b>ASISTENTE VIRTUAL CON INTELIGENCIA ARTIFICIAL</b>\n",
            f"¡Hola, <b>{html.escape(display_name)}</b>!\n",
            "En este chat privado tienes acceso directo al Asistente Técnico con IA Local.\n",
            "🧠 <b>¿Cómo interactuar?</b>",
            "• Escribe directamente en este chat tu duda, consulta técnica o problema de infraestructura para recibir asistencia en tiempo real.",
            "• <code>/reset_ia</code> - Reiniciar la memoria de la conversación para comenzar una nueva consulta sobre otro tema.",
            "• <code>/info</code> - Términos de uso, privacidad y políticas de seguridad.\n",
            "📌 <i><b>Nota:</b> Los demás comandos solo se ejecutan directamente en los grupos de operaciones autorizados.</i>\n",
            "<i>Sistema operando en Debian GNU/Linux</i>"
        ]
        await _reply(update.message, "\n".join(user_private_menu))
        return

    # =========================================================================
    # 👥 MENÚ PARA GRUPOS AUTORIZADOS (COMANDOS FUNCIONALES DE MONITOREO)
    # =========================================================================
    user_menu = [
        "🤖 <b>COMANDOS FUNCIONALES DEL BOT</b>\n",
        f"¡Hola, <b>{html.escape(display_name)}</b>!\n",
        "Tienes acceso a las siguientes funciones del sistema en este grupo:\n",
        "📊 <b>Monitoreo de Infraestructura</b>",
        "• <code>/servicios</code> - Consultar estado de los Servicios Corporativos.",
        "• <code>/sedes</code> - Consultar estado de Sedes y Enlaces de Comunicación.",
        "• <code>/monitoreo</code> - Ejecutar reporte completo de infraestructura.",
        "• <code>/internet</code> - Diagnóstico de salidas a internet y proxies corporativos.",
        "• <code>/analisis_red</code> - Solicitar análisis y diagnóstico de la red local.",
        "• <code>/info</code> - Información legal, privacidad y advertencia de seguridad.\n",
        "🧠 <b>ASISTENTE (IA)</b>",
        "• <code>/reset_ia</code> - Reiniciar la memoria de la conversación.\n",
        "<i>Recuerda también que puedes escribir directamente en el chat para interactuar con la IA.</i>\n",
        "<i>📌 <b>Nota sobre la memoria:</b> Después de entregar la respuesta técnica detallada, el bot mantendrá el hilo de memoria de la conversación para preguntas de seguimiento hasta que se use el comando <code>/reset_ia</code> y reinicie para una nueva consulta referente a otro tema.</i>\n",
        "<i>💡 Todas las interacciones que se tengan con el asistente de IA sirven de retroalimentación, en caso de obtener una solución se le puede enviar para ampliar el conocimiento de la IA.</i>\n"
    ]

    if commands_enabled and commands_dict:
        group_extra_cmds = []
        for cmd_name in ["status", "network", "disk"]:
            if cmd_name in commands_dict:
                desc = commands_dict[cmd_name].get("description", "Sin descripción")
                group_extra_cmds.append(f"• <code>/{cmd_name}</code> - {html.escape(desc)}")
        if group_extra_cmds:
            user_menu.append("⚙️ <b>Diagnósticos Básicos del Sistema:</b>")
            user_menu.extend(group_extra_cmds)

    user_menu.append("\n<i>Sistema de Monitoreo operando en Debian GNU/Linux</i>")
    await _reply(update.message, "\n".join(user_menu))


async def unknown_cmd(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja comandos no registrados informando al usuario y sugiriendo opciones."""
    if not await check_authorization(update, context):
        return

    cmd_text = update.message.text.split()[0] if (update.message and update.message.text) else "/comando"
    unk_msg = (
        f"⚠️ <b>Comando no reconocido (<code>{html.escape(cmd_text)}</code>).</b>\n\n"
        "• Usa <code>/start</code> o <code>/ayuda</code> para consultar los comandos disponibles.\n"
        "• O escribe tu mensaje directamente en texto plano para consultar al asistente de IA."
    )
    await _reply(update.message, unk_msg)
