"""
# ==============================================================================
# 📡 ACTIONS: SNMP Y TRAPS: snmp_action.py
# Comandos /snmp y /traps para telemetría, métricas y sondeo activo.
# Ubicación: /opt/sonar/bot_core/handlers/network/actions/snmp_action.py
# ==============================================================================
"""
import asyncio
import html
import logging
from pathlib import Path
import sys
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.network.helpers import get_active_config, reply
from bot_core.security.auth import check_authorization
from monitor.core_shield import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("bot.handlers.network.snmp")


async def handle_snmp(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando de Telemetría y Monitoreo SNMP (Fase 2)."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    args = context.args or []
    subcmd = args[0].lower() if args else "status"

    user_id = update.effective_user.id if update.effective_user else 0
    cfg = get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)
    chat_id = update.effective_chat.id if update.effective_chat else 0

    try:
        if subcmd in ("status", "resumen", "list"):
            with db_cursor() as cur:
                cur.execute("SELECT COUNT(*) as total FROM snmp_devices")
                total = cur.fetchone()["total"]
                cur.execute("SELECT COUNT(*) as online FROM snmp_devices WHERE last_poll_status = 'success'")
                online = cur.fetchone()["online"]
                cur.execute("SELECT COUNT(*) as timeout FROM snmp_devices WHERE last_poll_status = 'timeout'")
                timeout = cur.fetchone()["timeout"]
                cur.execute("SELECT COUNT(*) as total_if FROM snmp_interfaces")
                total_if = cur.fetchone()["total_if"]
                cur.execute("SELECT COUNT(*) as mon_if FROM snmp_interfaces WHERE is_monitored = 1")
                mon_if = cur.fetchone()["mon_if"]

                cur.execute("""
                    SELECT id, name, ip_address, device_type, sys_name, last_poll_status, sys_uptime, consecutive_failures
                    FROM snmp_devices
                    ORDER BY is_active DESC, name ASC
                """)
                devices = cur.fetchall()

            text = (
                "📡 <b>Monitoreo y Telemetría SNMP Valle Seco</b>\n\n"
                f"📊 <b>Estado de Infraestructura:</b>\n"
                f"• Total dispositivos: <b>{total}</b>\n"
                f"• 🟢 Respondiendo (UP): <b>{online}</b>\n"
                f"• 🔴 Con fallas / Timeout: <b>{timeout}</b>\n"
                f"• 🔌 Puertos de red: <b>{mon_if} monitoreados / {total_if} detectados</b>\n\n"
                "📋 <b>Inventario SNMP:</b>\n"
            )

            if not devices:
                text += "<i>No hay dispositivos SNMP registrados aún.</i>\n"
            else:
                for d in devices:
                    if d["last_poll_status"] == "success":
                        icon = "🟢"
                        st_txt = "UP"
                    elif d["last_poll_status"] == "timeout":
                        icon = "🔴"
                        st_txt = f"Timeout ({d['consecutive_failures']}x)"
                    else:
                        icon = "⚪"
                        st_txt = d["last_poll_status"] or "Pendiente"

                    name_disp = html.escape(d["sys_name"] or d["name"])
                    text += f"{icon} <code>{d['ip_address']}</code> - <b>{name_disp}</b> [{d['device_type']}] ({st_txt})\n"

            text += (
                "\n💡 <i>Subcomandos disponibles:</i>\n"
                "• <code>/snmp detail &lt;ip&gt;</code> — Detalles del equipo\n"
                "• <code>/snmp interfaces &lt;ip&gt;</code> — Interfaces y puertos\n"
            )
            if is_admin:
                text += (
                    "• <code>/snmp poll &lt;ip&gt;</code> — Forzar sondeo inmediato\n"
                    "• <code>/snmp activate &lt;ip&gt;</code> — Activar SNMP remotamente\n"
                )
            await reply(update.message, text)

        elif subcmd in ("detail", "detalle", "info"):
            if len(args) < 2:
                await reply(update.message, "⚠️ Uso: <code>/snmp detail &lt;ip_o_nombre&gt;</code>")
                return

            target = args[1].strip()
            with db_cursor() as cur:
                cur.execute("""
                    SELECT id, name, ip_address, snmp_version, device_type, vendor, model,
                           sys_name, sys_description, sys_location, sys_uptime,
                           last_poll_at, last_poll_status, consecutive_failures
                    FROM snmp_devices
                    WHERE ip_address = %s OR name = %s OR id = %s
                    LIMIT 1
                """, (target, target, target if target.isdigit() else 0))
                dev = cur.fetchone()

                if not dev:
                    await reply(update.message, f"❌ Dispositivo <code>{html.escape(target)}</code> no encontrado en el inventario SNMP.")
                    return

                cur.execute("SELECT COUNT(*) as if_count FROM snmp_interfaces WHERE snmp_device_id = %s", (dev["id"],))
                if_count = cur.fetchone()["if_count"]

            uptime_str = "—"
            if dev["sys_uptime"]:
                secs = int(dev["sys_uptime"]) // 100
                uptime_str = f"{secs // 86400}d {(secs % 86400) // 3600}h {(secs % 3600) // 60}m"

            st_emoji = "🟢" if dev["last_poll_status"] == "success" else "🔴"

            text = (
                f"📡 <b>Detalle SNMP: {html.escape(dev['name'])}</b>\n\n"
                f"• <b>Dirección IP:</b> <code>{dev['ip_address']}</code> (v{dev['snmp_version']})\n"
                f"• <b>Tipo / Hardware:</b> {dev['device_type']} | {html.escape(dev['vendor'] or 'N/A')} {html.escape(dev['model'] or '')}\n"
                f"• <b>SysName:</b> <code>{html.escape(dev['sys_name'] or 'N/A')}</code>\n"
                f"• <b>Ubicación:</b> {html.escape(dev['sys_location'] or 'N/A')}\n"
                f"• <b>Uptime:</b> {uptime_str}\n"
                f"• <b>Estado Poll:</b> {st_emoji} {dev['last_poll_status']} (Fallas: {dev['consecutive_failures']})\n"
                f"• <b>Último Sondeo:</b> {dev['last_poll_at'] or 'Pendiente'}\n"
                f"• <b>Puertos Totales:</b> {if_count}\n"
            )
            if dev["sys_description"]:
                text += f"\n<i>Descripción:</i> <code>{html.escape(dev['sys_description'][:200])}</code>\n"
            await reply(update.message, text)

        elif subcmd in ("interfaces", "puertos", "ports"):
            if len(args) < 2:
                await reply(update.message, "⚠️ Uso: <code>/snmp interfaces &lt;ip_o_nombre&gt;</code>")
                return

            target = args[1].strip()
            with db_cursor() as cur:
                cur.execute("SELECT id, name, ip_address FROM snmp_devices WHERE ip_address = %s OR name = %s OR id = %s LIMIT 1", (target, target, target if target.isdigit() else 0))
                dev = cur.fetchone()
                if not dev:
                    await reply(update.message, f"❌ Dispositivo <code>{html.escape(target)}</code> no encontrado.")
                    return

                cur.execute("""
                    SELECT if_index, if_name, if_oper_status, if_speed, is_monitored
                    FROM snmp_interfaces
                    WHERE snmp_device_id = %s
                    ORDER BY if_index ASC
                    LIMIT 25
                """, (dev["id"],))
                interfaces = cur.fetchall()

            text = f"🔌 <b>Interfaces de {html.escape(dev['name'])} (<code>{dev['ip_address']}</code>):</b>\n\n"
            if not interfaces:
                text += "<i>No hay interfaces descubiertas aún. Ejecute el descubrimiento vía portal web o /snmp poll.</i>\n"
            else:
                for iface in interfaces:
                    op_icon = "🟢" if iface["if_oper_status"] == "up" else "🔴"
                    spd = f"{(iface['if_speed'] // 1000000)}M" if iface["if_speed"] else ""
                    mon_txt = " [Monitoreado]" if iface["is_monitored"] else ""
                    text += f"{op_icon} <b>#{iface['if_index']} {html.escape(iface['if_name'] or '')}</b>: {iface['if_oper_status'].upper()} {spd}{mon_txt}\n"

                text += "\n<i>(Mostrando primeras 25 interfaces. Ver inventario completo en portal web: /admin/snmp)</i>"

            await reply(update.message, text)

        elif subcmd in ("poll", "sondear", "test"):
            if not is_admin:
                await reply(update.message, "⛔ <b>Acceso Restringido:</b> Solo el Administrador puede forzar sondeos SNMP manuales.")
                return

            if len(args) < 2:
                await reply(update.message, "⚠️ Uso: <code>/snmp poll &lt;ip_o_id&gt;</code>")
                return

            target = args[1].strip()
            with db_cursor() as cur:
                cur.execute("SELECT id, name, ip_address FROM snmp_devices WHERE ip_address = %s OR name = %s OR id = %s LIMIT 1", (target, target, target if target.isdigit() else 0))
                dev = cur.fetchone()

            if not dev:
                await reply(update.message, f"❌ Dispositivo <code>{html.escape(target)}</code> no encontrado.")
                return

            await reply(update.message, f"⏳ <i>Ejecutando sondeo SNMP inmediato para {html.escape(dev['name'])} (<code>{dev['ip_address']}</code>)...</i>")

            python_bin = sys.executable or "/opt/sonar/venv/bin/python3"
            poller_script = Path(__file__).resolve().parent.parent.parent.parent / "monitor" / "snmp_poller.py"
            proc = await asyncio.create_subprocess_exec(
                python_bin, str(poller_script), "--poll", "--device", str(dev['id']),
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE
            )
            await proc.communicate()

            if proc.returncode == 0:
                await reply(update.message, f"✅ <b>Sondeo SNMP completado con éxito</b> para <b>{html.escape(dev['name'])}</b>.")
            else:
                await reply(update.message, f"⚠️ <b>Sondeo SNMP finalizado con observaciones para {html.escape(dev['name'])}.</b>")

        elif subcmd in ("activate", "activar"):
            if not is_admin:
                await reply(update.message, "⛔ <b>Acceso Restringido:</b> Solo el Administrador puede ejecutar activación remota SNMP.")
                return

            if chat_id < 0:
                await reply(update.message, "🔒 <b>Por razones estrictas de seguridad, la activación remota de equipos solo puede gestionarse en privado.</b>")
                return

            text = (
                "⚡ <b>Activación Remota SNMP (SSH / Telnet / pfSense)</b>\n\n"
                "Para configurar SNMP remotamente en un equipo Cisco o pfSense de forma segura con verificación en 2 pasos, ingrese al portal web administrativo:\n\n"
                "🌐 <b>Ruta:</b> <code>/admin/snmp</code> → Botón <b>⚡ Activación Remota</b>\n\n"
                "<i>El portal permite seleccionar el método (SSH, Telnet Legacy o API pfSense), ingresar credenciales cifradas y ver en tiempo real el log de comandos aplicados.</i>"
            )
            await reply(update.message, text)

        else:
            await reply(
                update.message,
                "⚠️ Subcomando no reconocido. Use:\n"
                "• <code>/snmp</code>\n"
                "• <code>/snmp detail &lt;ip&gt;</code>\n"
                "• <code>/snmp interfaces &lt;ip&gt;</code>\n"
                + ("• <code>/snmp poll &lt;ip&gt;</code>\n" if is_admin else "")
            )

    except Exception as e:
        logger.error(f"Error en cmd_snmp: {e}")
        await reply(update.message, f"❌ Error procesando comando SNMP: {html.escape(str(e))}")

