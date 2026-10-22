"""
# ==============================================================================
# 🌐 HANDLERS DE TELEMETRÍA DE RED: network_handlers.py
# Análisis ARP/ICMP, SNMP, Traps, Syslog, NetFlow, Topología, Clúster, WoL e IPs.
# Principio de Responsabilidad Única (S) y Principios SOLID.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import os
import platform
import re
import socket
import subprocess
import sys
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List

from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes

from bot_core.security.auth import (
    MSG_UNAUTHORIZED_ADMIN_COMMAND,
    MSG_UNAUTHORIZED_GROUP_COMMAND,
    check_authorization,
    get_audit_log_path,
    is_authorized,
    require_private_chat,
)
from bot_core.services.config_manager import BASE_DIR, load_config
from bot_core.utils.telegram_helpers import safe_reply_html
from monitor.core_shield import IMMUTABLE_OWNER_ID

try:
    import psutil
except ImportError:
    psutil = None

logger = logging.getLogger("bot.handlers.network")


def _get_active_config() -> Dict[str, Any]:
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


async def _reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


async def cmd_analisis_red(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para ejecutar el análisis y diagnóstico avanzado de red local (ARP / ICMP)."""
    if not update.effective_user or not update.message:
        return

    if not await check_authorization(update, context):
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    allowed_groups = cfg.get("allowed_group_ids", [])
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id if update.effective_chat else 0

    is_owner = (user_id == owner_id)
    is_allowed_group = (chat_id in allowed_groups)

    if not is_owner and not is_allowed_group:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        user = update.effective_user
        first_name = (user.first_name or "").strip()
        last_name = (user.last_name or "").strip()
        if last_name.lower() == "none":
            last_name = ""
        full_name = " ".join([p for p in [first_name, last_name] if p]) or "(sin nombre)"
        username_str = f"@{user.username}" if user.username else ""
        msg_text = update.message.text or "/analisis_red"
        chat_title = update.effective_chat.title if (update.effective_chat and update.effective_chat.type in ['group', 'supergroup']) else "Chat Privado"

        audit_path = get_audit_log_path()
        log_line = (
            f"[{now_str}] ANALISIS_RED DENEGADO | ID: {user_id} | "
            f"Username: {username_str} | Nombre: {full_name} | "
            f"Chat: {chat_title} (ID: {chat_id}) | Comando: {msg_text}\n"
        )
        try:
            with open(audit_path, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logger.error(f"Error escribiendo en log de auditoría ({audit_path}): {e}")

        await _reply(update.message, MSG_UNAUTHORIZED_GROUP_COMMAND)

        if owner_id and user_id != owner_id and cfg.get("notify_unauthorized_to_owner", True):
            owner_alert = (
                "⚠️ <b>Alerta: Intento de Análisis de Red Restringido</b>\n\n"
                f"👤 <b>Usuario:</b> {html.escape(full_name)} ({html.escape(username_str)})\n"
                f"🆔 <b>ID de Telegram:</b> <code>{user_id}</code>\n"
                f"💬 <b>Origen:</b> {html.escape(chat_title)} (<code>{chat_id}</code>)\n"
                f"📝 <b>Comando:</b> <code>{html.escape(msg_text)}</code>\n"
                f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
                f"<i>La solicitud fue bloqueada automáticamente.</i>"
            )
            try:
                await context.bot.send_message(chat_id=owner_id, text=owner_alert, parse_mode='HTML')
            except Exception as e:
                logger.error(f"No se pudo notificar al owner sobre intento de analisis_red: {e}")

        return

    if cfg.get("commands_locked_for_users", False) and not is_owner:
        await _reply(
            update.message,
            "🔒 <b>Comandos Temporalmente Desactivados:</b>\n"
            "El Administrador ha deshabilitado temporalmente la ejecución de comandos para usuarios y grupos.\n\n"
            "<i>Por favor contacte al administrador si requiere asistencia técnica.</i>"
        )
        return

    duration = 60
    force_debug = False
    is_live = False

    if context.args:
        for arg in context.args:
            arg_clean = arg.strip().lower()
            if arg_clean in ("vivo", "live", "captura", "raw", "scan"):
                is_live = True
            elif arg_clean.isdigit():
                val = int(arg_clean)
                if 5 <= val <= 600:
                    duration = val
                    is_live = True
            elif arg_clean in ("debug", "completo", "full"):
                force_debug = True

    if not is_live:
        try:
            from monitor.net_radar_engine import generate_markdown_report_from_db
            summary_text, md_file, _ = generate_markdown_report_from_db()

            await update.message.reply_text(summary_text, parse_mode='HTML')

            if md_file and md_file.exists():
                with open(md_file, "rb") as f:
                    await context.bot.send_document(
                        chat_id=chat_id,
                        document=f,
                        filename=md_file.name,
                        caption="📄 Reporte estructurado NET Radar (Tráfico y Actualizaciones)"
                    )

            if is_allowed_group and not is_owner and owner_id:
                try:
                    group_title = update.effective_chat.title or "Grupo Autorizado"
                    await context.bot.send_message(
                        chat_id=owner_id,
                        text=f"📋 <b>Copia de Auditoría: NET Radar ejecutado en {html.escape(group_title)}</b>\n\n" + summary_text,
                        parse_mode='HTML'
                    )
                    if md_file and md_file.exists():
                        with open(md_file, "rb") as f:
                            await context.bot.send_document(
                                chat_id=owner_id,
                                document=f,
                                filename=md_file.name,
                                caption=f"📄 Reporte NET Radar ({group_title})"
                            )
                except Exception as e:
                    logger.error(f"Error enviando copia de auditoría al Owner: {e}")
            return
        except Exception as e_fast:
            logger.error(f"Error generando reporte rápido NET Radar desde BD: {e_fast}", exc_info=True)

    wait_msg = await update.message.reply_text(
        f"⏳ <b>Iniciando captura de tráfico en tiempo real ({duration}s) con tcpdump y análisis profundo con tshark...</b>\n\n"
        "<i>Analizando protocolos, calculando volumen TX/RX, detectando tráfico sospechoso, tormentas de broadcast y midiendo latencias. Por favor espere.</i>",
        parse_mode='HTML'
    )

    try:
        from monitor.network_analyzer import execute_network_analysis
        result = await execute_network_analysis(duration_seconds=duration)

        try:
            await wait_msg.delete()
        except Exception:
            pass

        await update.message.reply_text(result["summary_text"], parse_mode='HTML')

        md_file = result.get("md_report") or result.get("txt_report")
        html_file = result.get("html_report")

        if md_file and md_file.exists():
            with open(md_file, "rb") as f:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=f,
                    filename=md_file.name,
                    caption="📄 Reporte estructurado de análisis de red"
                )
        if html_file and html_file.exists():
            with open(html_file, "rb") as f:
                await context.bot.send_document(
                    chat_id=chat_id,
                    document=f,
                    filename=html_file.name,
                    caption="🌐 Reporte interactivo de análisis de red (HTML)"
                )

        if is_allowed_group and not is_owner and owner_id:
            try:
                group_title = update.effective_chat.title or "Grupo Autorizado"
                await context.bot.send_message(
                    chat_id=owner_id,
                    text=f"📋 <b>Copia de Auditoría: Reporte de Red ejecutado en {html.escape(group_title)}</b>\n\n" + result["summary_text"],
                    parse_mode='HTML'
                )
                if md_file and md_file.exists():
                    with open(md_file, "rb") as f:
                        await context.bot.send_document(
                            chat_id=owner_id,
                            document=f,
                            filename=md_file.name,
                            caption=f"📄 Reporte de análisis de red ({group_title})"
                        )
                if html_file and html_file.exists():
                    with open(html_file, "rb") as f:
                        await context.bot.send_document(
                            chat_id=owner_id,
                            document=f,
                            filename=html_file.name,
                            caption=f"🌐 Reporte HTML ({group_title})"
                        )
            except Exception as e:
                logger.error(f"Error enviando copia de auditoría al Owner: {e}")

    except Exception as e:
        logger.error(f"Error ejecutando análisis de red: {e}", exc_info=True)
        try:
            await wait_msg.edit_text(f"❌ <b>Error durante el escaneo de red:</b> <code>{html.escape(str(e))}</code>", parse_mode='HTML')
        except Exception:
            await _reply(update.message, f"❌ Error ejecutando análisis de red: {e}")


async def cmd_ip(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando para consultar el Hostname, interfaces de red y direcciones IP del servidor host."""
    if not is_authorized(update):
        if update.message and update.effective_chat and update.effective_chat.type == "private":
            await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    hostname = platform.node() or "N/A"
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    ifaces_data = []
    primary_ip = None

    if psutil:
        try:
            for iface, addrs in psutil.net_if_addrs().items():
                if iface.lower() in ("lo", "docker0"):
                    continue
                for a in addrs:
                    if a.family == socket.AF_INET and not a.address.startswith("127."):
                        ifaces_data.append({
                            "iface": iface,
                            "ip": a.address,
                            "netmask": a.netmask or "N/A",
                            "broadcast": a.broadcast or "N/A"
                        })
                        if not primary_ip and not a.address.startswith("169.254."):
                            primary_ip = a.address
        except Exception as e:
            logger.error(f"Error obteniendo interfaces con psutil: {e}")

    if not ifaces_data:
        try:
            res = subprocess.run(["ip", "-4", "addr", "show"], capture_output=True, text=True, timeout=2.0)
            current_iface = None
            for line in res.stdout.splitlines():
                m_iface = re.match(r"^\d+:\s+([a-zA-Z0-9_\-]+):", line)
                if m_iface:
                    current_iface = m_iface.group(1)
                    continue
                m_inet = re.search(r"inet\s+([0-9.]+)/(\d+)", line)
                if m_inet and current_iface and current_iface not in ("lo", "docker0"):
                    ip_val = m_inet.group(1)
                    prefix_val = m_inet.group(2)
                    if not ip_val.startswith("127."):
                        ifaces_data.append({
                            "iface": current_iface,
                            "ip": ip_val,
                            "netmask": f"/{prefix_val}",
                            "broadcast": "N/A"
                        })
                        if not primary_ip and not ip_val.startswith("169.254."):
                            primary_ip = ip_val
        except Exception as err:
            logger.error(f"Error en fallback nativo de red ip addr: {err}")

    if not ifaces_data:
        try:
            out = subprocess.check_output(["hostname", "-I"], text=True, timeout=2.0).strip()
            for piece in out.split():
                if piece and ":" not in piece and not piece.startswith("127."):
                    ifaces_data.append({
                        "iface": "eth",
                        "ip": piece,
                        "netmask": "255.255.255.0",
                        "broadcast": "N/A"
                    })
                    if not primary_ip:
                        primary_ip = piece
        except Exception:
            pass

    gw_info = "N/A"
    try:
        res = subprocess.run(["ip", "route"], capture_output=True, text=True, timeout=2.0)
        for line in res.stdout.splitlines():
            if line.startswith("default via"):
                parts = line.split()
                if len(parts) >= 5:
                    gw_info = f"<code>{parts[2]}</code> (vía <i>{parts[4]}</i>)"
                else:
                    gw_info = f"<code>{parts[2]}</code>"
                break
    except Exception:
        pass

    dns_servers = []
    try:
        with open("/etc/resolv.conf", "r") as rf:
            for line in rf:
                if line.strip().startswith("nameserver"):
                    parts = line.split()
                    if len(parts) > 1 and parts[1] not in ("127.0.0.1", "::1"):
                        dns_servers.append(parts[1])
    except Exception:
        pass
    dns_str = ", ".join(f"<code>{d}</code>" for d in dns_servers[:3]) if dns_servers else "N/A"

    ifaces_text_list = []
    for item in ifaces_data:
        ifaces_text_list.append(
            f"🔹 <b>{item['iface']}:</b> <code>{item['ip']}</code>\n"
            f"   └ <i>Máscara:</i> <code>{item['netmask']}</code>"
        )
    ifaces_formatted = "\n\n".join(ifaces_text_list) if ifaces_text_list else "<i>No se detectaron interfaces activas.</i>"

    portal_url = f"http://{primary_ip}/" if primary_ip else "http://localhost/"
    domain_url = "http://monitoreo-vs.local/"

    msg = (
        "🌐 <b>INFORMACIÓN DE RED DEL SERVIDOR</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🖥️ <b>Equipo:</b> <code>{hostname}</code>\n"
        f"⏰ <b>Hora Servidor:</b> <code>{now_str}</code>\n\n"
        "📡 <b>Direcciones IP Asignadas:</b>\n"
        f"{ifaces_formatted}\n\n"
        f"🚪 <b>Gateway (Puerta de Enlace):</b> {gw_info}\n"
        f"🧭 <b>DNS Primarios:</b> {dns_str}\n\n"
        "🔗 <b>Enlaces de Acceso al Portal:</b>\n"
        f" • IP Directa: <a href=\"{portal_url}\">{portal_url}</a>\n"
        f" • Dominio Local: <a href=\"{domain_url}\">{domain_url}</a>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━"
    )

    if update.message:
        await _reply(update.message, msg)


async def cmd_cluster(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el estado del clúster Master/Slave y el token de autenticación (Exclusivo Owner en privado)."""
    if not await require_private_chat(update, context):
        return

    if not update.effective_user or not update.message:
        return

    cfg = _get_active_config()
    owner_id = cfg.get("owner_id", IMMUTABLE_OWNER_ID)
    user_id = update.effective_user.id
    if user_id != owner_id:
        await _reply(update.message, MSG_UNAUTHORIZED_ADMIN_COMMAND)
        return

    role = cfg.get("node_role", "master").upper()
    master_url = cfg.get("master_api_url", "http://192.168.1.250")
    token = cfg.get("cluster_token", "No configurado")
    last_sync = cfg.get("cluster_last_sync_at", "N/A")
    status = cfg.get("cluster_last_sync_status", "N/A")

    role_emoji = "👑" if role == "MASTER" else "🔄"
    text = (
        f"{role_emoji} <b>Arquitectura de Clúster (Master / Slave)</b>\n\n"
        f"🏷️ <b>Rol del Nodo:</b> <code>{role}</code>\n"
        f"🌐 <b>Servidor Master:</b> <code>{html.escape(master_url)}</code>\n"
        f"📊 <b>Estado de Enlace:</b> <code>{html.escape(status)}</code>\n"
        f"⏰ <b>Última Sincronía:</b> <code>{html.escape(str(last_sync))}</code>\n\n"
        f"🔑 <b>Token Secreto de Clúster (toque para copiar):</b>\n"
        f"<code>{html.escape(token)}</code>\n\n"
        f"<i>Copie este token en el servidor Esclavo para autorizar la sincronización de telemetría sin colisiones en pfSense.</i>"
    )
    await _reply(update.message, text)


async def cmd_discovery(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Reporte de Auto-Discovery y Detección Anti-Rogue."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) as total FROM discovered_devices")
                total = cur.fetchone()["total"]
                cur.execute("SELECT COUNT(*) as pending FROM discovered_devices WHERE classification_status = 'pendiente'")
                pending = cur.fetchone()["pending"]
                cur.execute("SELECT COUNT(*) as auth FROM discovered_devices WHERE is_authorized = 1")
                auth_count = cur.fetchone()["auth"]
                cur.execute("SELECT COUNT(*) as rogue FROM discovered_devices WHERE classification_status = 'rogue'")
                rogue = cur.fetchone()["rogue"]

                cur.execute("""
                    SELECT ip_address, mac_address, vendor, device_type, classification_status, is_authorized, last_seen
                    FROM discovered_devices
                    ORDER BY last_seen DESC
                    LIMIT 8
                """)
                recent_devices = cur.fetchall()
        finally:
            conn.close()

        text = (
            "🛰️ <b>Auto-Discovery de Red & Anti-Rogue</b>\n\n"
            f"📊 <b>Resumen de Dispositivos:</b>\n"
            f"• Total en inventario: <b>{total}</b>\n"
            f"• ✅ Autorizados: <b>{auth_count}</b>\n"
            f"• ⏳ Pendientes de clasificar: <b>{pending}</b>\n"
            f"• 🚫 Intrusos (Rogue): <b>{rogue}</b>\n\n"
            "🔍 <b>Últimos Dispositivos Vistos:</b>\n"
        )

        if not recent_devices:
            text += "<i>No hay dispositivos registrados aún.</i>\n"
        else:
            for d in recent_devices:
                status_icon = "🚫" if d["classification_status"] == "rogue" else ("✅" if d["is_authorized"] else "⏳")
                vendor = d["vendor"] or "Desconocido"
                text += f"{status_icon} <code>{d['ip_address']}</code> - {d['mac_address']} ({html.escape(vendor)})\n"

        text += "\n<i>Gestione subredes y autorizaciones en el portal web: /admin/discovery</i>"
        await _reply(update.message, text)
    except Exception as e:
        logger.error(f"Error en cmd_discovery: {e}")
        await _reply(update.message, f"❌ Error consultando auto-discovery: {html.escape(str(e))}")


async def cmd_snmp(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando de Telemetría y Monitoreo SNMP (Fase 2)."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    args = context.args or []
    subcmd = args[0].lower() if args else "status"

    user_id = update.effective_user.id if update.effective_user else 0
    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)
    chat_id = update.effective_chat.id if update.effective_chat else 0

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            if subcmd in ("status", "resumen", "list"):
                with conn.cursor() as cur:
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
                await _reply(update.message, text)

            elif subcmd in ("detail", "detalle", "info"):
                if len(args) < 2:
                    await _reply(update.message, "⚠️ Uso: <code>/snmp detail &lt;ip_o_nombre&gt;</code>")
                    return

                target = args[1].strip()
                with conn.cursor() as cur:
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
                        await _reply(update.message, f"❌ Dispositivo <code>{html.escape(target)}</code> no encontrado en el inventario SNMP.")
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

                await _reply(update.message, text)

            elif subcmd in ("interfaces", "puertos", "ports"):
                if len(args) < 2:
                    await _reply(update.message, "⚠️ Uso: <code>/snmp interfaces &lt;ip_o_nombre&gt;</code>")
                    return

                target = args[1].strip()
                with conn.cursor() as cur:
                    cur.execute("SELECT id, name, ip_address FROM snmp_devices WHERE ip_address = %s OR name = %s OR id = %s LIMIT 1", (target, target, target if target.isdigit() else 0))
                    dev = cur.fetchone()
                    if not dev:
                        await _reply(update.message, f"❌ Dispositivo <code>{html.escape(target)}</code> no encontrado.")
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

                await _reply(update.message, text)

            elif subcmd in ("poll", "sondear", "test"):
                if not is_admin:
                    await _reply(update.message, "⛔ <b>Acceso Restringido:</b> Solo el Administrador puede forzar sondeos SNMP manuales.")
                    return

                if len(args) < 2:
                    await _reply(update.message, "⚠️ Uso: <code>/snmp poll &lt;ip_o_id&gt;</code>")
                    return

                target = args[1].strip()
                with conn.cursor() as cur:
                    cur.execute("SELECT id, name, ip_address FROM snmp_devices WHERE ip_address = %s OR name = %s OR id = %s LIMIT 1", (target, target, target if target.isdigit() else 0))
                    dev = cur.fetchone()

                if not dev:
                    await _reply(update.message, f"❌ Dispositivo <code>{html.escape(target)}</code> no encontrado.")
                    return

                await _reply(update.message, f"⏳ <i>Ejecutando sondeo SNMP inmediato para {html.escape(dev['name'])} (<code>{dev['ip_address']}</code>)...</i>")

                cmd = f"/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/snmp_poller.py --poll --device {dev['id']}"
                proc = await asyncio.create_subprocess_shell(
                    cmd,
                    stdout=asyncio.subprocess.PIPE,
                    stderr=asyncio.subprocess.PIPE
                )
                stdout, stderr = await proc.communicate()

                if proc.returncode == 0:
                    await _reply(update.message, f"✅ <b>Sondeo SNMP completado con éxito</b> para <b>{html.escape(dev['name'])}</b>.")
                else:
                    await _reply(update.message, f"⚠️ <b>Sondeo SNMP finalizado con observaciones para {html.escape(dev['name'])}.</b>")

            elif subcmd in ("activate", "activar"):
                if not is_admin:
                    await _reply(update.message, "⛔ <b>Acceso Restringido:</b> Solo el Administrador puede ejecutar activación remota SNMP.")
                    return

                if chat_id < 0:
                    await _reply(update.message, "🔒 <b>Por razones estrictas de seguridad, la activación remota de equipos solo puede gestionarse en privado.</b>")
                    return

                text = (
                    "⚡ <b>Activación Remota SNMP (SSH / Telnet / pfSense)</b>\n\n"
                    "Para configurar SNMP remotamente en un equipo Cisco o pfSense de forma segura con verificación en 2 pasos, ingrese al portal web administrativo:\n\n"
                    "🌐 <b>Ruta:</b> <code>/admin/snmp</code> → Botón <b>⚡ Activación Remota</b>\n\n"
                    "<i>El portal permite seleccionar el método (SSH, Telnet Legacy o API pfSense), ingresar credenciales cifradas y ver en tiempo real el log de comandos aplicados.</i>"
                )
                await _reply(update.message, text)

            else:
                await _reply(
                    update.message,
                    "⚠️ Subcomando no reconocido. Use:\n"
                    "• <code>/snmp</code>\n"
                    "• <code>/snmp detail &lt;ip&gt;</code>\n"
                    "• <code>/snmp interfaces &lt;ip&gt;</code>\n"
                    + ("• <code>/snmp poll &lt;ip&gt;</code>\n" if is_admin else "")
                )

        finally:
            conn.close()

    except Exception as e:
        logger.error(f"Error en cmd_snmp: {e}")
        await _reply(update.message, f"❌ Error procesando comando SNMP: {html.escape(str(e))}")


async def cmd_traps(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra los últimos SNMP Traps recibidos en tiempo real por el receptor push."""
    if not update.message:
        return

    args = context.args or []
    filter_ip = args[0].strip() if args else None

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                sql = "SELECT id, source_ip, trap_oid, trap_type, severity, processed, received_at FROM snmp_traps_received WHERE 1=1"
                params = []
                if filter_ip:
                    sql += " AND source_ip = %s"
                    params.append(filter_ip)
                sql += " ORDER BY id DESC LIMIT 8"
                cur.execute(sql, tuple(params))
                traps = cur.fetchall()

                cur.execute("SELECT COUNT(*) as total, SUM(CASE WHEN severity IN ('critical', 'emergency') THEN 1 ELSE 0 END) as crit FROM snmp_traps_received")
                stats = cur.fetchone() or {"total": 0, "crit": 0}
        finally:
            conn.close()

        lines = [
            "🛰️ <b>SNMP TRAPS RECIBIDOS (TELEMETRÍA PUSH)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Total Histórico:</b> <code>{stats.get('total', 0)}</code> | <b>Críticos:</b> 🔴 <code>{stats.get('crit', 0) or 0}</code>",
            f"• <b>Puerto de Escucha:</b> <code>UDP 162</code> (Activo)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ÚLTIMOS TRAPS DETECTADOS:</b>"
        ]

        if not traps:
            lines.append("<i>No se han recibido traps SNMP aún o no coinciden con el filtro.</i>")
        else:
            for t in traps:
                sev = t.get("severity", "info")
                sev_emoji = "🚨" if sev == "emergency" else "🔴" if sev == "critical" else "⚠️" if sev == "warning" else "ℹ️"
                dt_str = t["received_at"].strftime("%d/%m %H:%M:%S") if t.get("received_at") else "N/A"
                proc_str = "✅" if t.get("processed") else "⏳"
                lines.append(
                    f"{sev_emoji} <b>{html.escape(t.get('trap_type') or 'genericTrap')}</b> ({proc_str})\n"
                    f"   └ 📍 <code>{t['source_ip']}</code> | ⏰ {dt_str}\n"
                    f"   └ OID: <code>{t['trap_oid']}</code>"
                )

        lines.append("\n💡 <i>Filtro por equipo: <code>/traps &lt;ip&gt;</code></i>")
        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_traps: {e}")
        await _reply(update.message, f"❌ Error consultando traps SNMP: {html.escape(str(e))}")


async def cmd_syslog(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Consulta y filtra eventos recientes recibidos en la consola Syslog centralizada."""
    if not update.message:
        return

    args = context.args or []
    query_text = None
    device_ip = None

    if len(args) >= 2 and args[0].lower() in ("device", "ip", "equipo"):
        device_ip = args[1].strip()
    elif args:
        query_text = " ".join(args).strip()

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                sql = "SELECT id, source_ip, hostname, severity, program, message, received_at FROM syslog_events WHERE 1=1"
                params = []
                if device_ip:
                    sql += " AND source_ip = %s"
                    params.append(device_ip)
                if query_text:
                    sql += " AND (message LIKE %s OR program LIKE %s OR hostname LIKE %s)"
                    term = f"%{query_text}%"
                    params.extend([term, term, term])
                sql += " ORDER BY id DESC LIMIT 8"
                cur.execute(sql, tuple(params))
                events = cur.fetchall()

                cur.execute("SELECT COUNT(*) as total, COUNT(DISTINCT source_ip) as sources FROM syslog_events")
                stats = cur.fetchone() or {"total": 0, "sources": 0}
        finally:
            conn.close()

        lines = [
            "📋 <b>CONSOLA CENTRALIZADA DE SYSLOG (LIVE)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Total Eventos:</b> <code>{stats.get('total', 0)}</code> | <b>Fuentes Activas:</b> <code>{stats.get('sources', 0)}</code>",
            f"• <b>Puerto de Escucha:</b> <code>UDP 514</code> (RFC 3164 / RFC 5424)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ÚLTIMOS EVENTOS CAPTURADOS:</b>"
        ]

        if not events:
            lines.append("<i>No se encontraron eventos en syslog coincidentes.</i>")
        else:
            sev_names = {0: "EMERG", 1: "ALERT", 2: "CRIT", 3: "ERR", 4: "WARN", 5: "NOTICE", 6: "INFO", 7: "DEBUG"}
            for ev in events:
                s_lvl = ev.get("severity", 6)
                s_name = sev_names.get(s_lvl, str(s_lvl))
                s_emoji = "🚨" if s_lvl <= 1 else "🔴" if s_lvl == 2 else "⚠️" if s_lvl == 3 else "ℹ️"
                dt_str = ev["received_at"].strftime("%H:%M:%S") if ev.get("received_at") else ""
                clean_msg = html.escape((ev.get("message") or "")[:90])
                prog = html.escape(ev.get("program") or "syslog")
                lines.append(
                    f"{s_emoji} <b>[{s_name}]</b> <code>{prog}</code> ({dt_str})\n"
                    f"   └ 📍 <code>{ev['source_ip']}</code>: <i>{clean_msg}</i>"
                )

        lines.append("\n💡 <i>Uso: <code>/syslog &lt;patrón&gt;</code> o <code>/syslog device &lt;ip&gt;</code></i>")
        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_syslog: {e}")
        await _reply(update.message, f"❌ Error consultando syslog: {html.escape(str(e))}")


async def cmd_netflow(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el análisis de tráfico y Top Talkers capturados vía NetFlow v5."""
    if not update.message:
        return

    args = context.args or []
    filter_type = args[0].lower() if args else "top"

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) as total_flows, SUM(bytes) as total_bytes, SUM(packets) as total_pkts FROM netflow_records")
                flow_stats = cur.fetchone() or {"total_flows": 0, "total_bytes": 0, "total_pkts": 0}

                cur.execute("SELECT MAX(window_start) as latest_win FROM netflow_top_talkers")
                win_row = cur.fetchone()
                latest_win = win_row["latest_win"] if win_row else None

                talkers = []
                if latest_win:
                    if filter_type in ("src", "source", "emisores"):
                        cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s AND rank_type = 'src_ip' ORDER BY bytes DESC LIMIT 6", (latest_win,))
                    elif filter_type in ("dst", "dest", "receptores"):
                        cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s AND rank_type = 'dst_ip' ORDER BY bytes DESC LIMIT 6", (latest_win,))
                    elif filter_type in ("proto", "protocolo"):
                        cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s AND rank_type = 'protocol' ORDER BY bytes DESC LIMIT 5", (latest_win,))
                    else:
                        cur.execute("SELECT rank_type, rank_value, bytes, packets, percentage FROM netflow_top_talkers WHERE window_start = %s ORDER BY bytes DESC LIMIT 8", (latest_win,))
                    talkers = cur.fetchall()
        finally:
            conn.close()

        total_mb = round((flow_stats.get("total_bytes") or 0) / 1048576, 2)

        lines = [
            "🌊 <b>ANÁLISIS DE TRÁFICO NETFLOW V5 & TOP TALKERS</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Flujos Totales:</b> <code>{flow_stats.get('total_flows', 0)}</code> ventanas (1m)",
            f"• <b>Volumen Acumulado:</b> <code>{total_mb} MB</code> ({flow_stats.get('total_pkts', 0)} paquetes)",
            f"• <b>Puerto Colector:</b> <code>UDP 2055</code> (Activo)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"<b>RANKING TOP TALKERS ({filter_type.upper()}):</b>"
        ]

        if not talkers:
            lines.append("<i>No hay datos de flujos materializados todavía en la última ventana de 5m.</i>")
        else:
            for tk in talkers:
                rtype = tk.get("rank_type")
                rval = tk.get("rank_value")
                b_mb = round((tk.get("bytes") or 0) / 1048576, 2)
                pct = tk.get("percentage") or 0.0
                type_icon = "📤" if rtype == "src_ip" else ("📥" if rtype == "dst_ip" else "⚡")
                lines.append(f"{type_icon} <b>{html.escape(str(rval))}</b>: <code>{b_mb} MB</code> ({pct}%) | {tk.get('packets')} pkts")

        lines.append("\n💡 <i>Filtros: <code>/netflow src</code> | <code>/netflow dst</code> | <code>/netflow proto</code></i>")
        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_netflow: {e}")
        await _reply(update.message, f"❌ Error consultando NetFlow: {html.escape(str(e))}")


async def cmd_topologia(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra un resumen de la topología de red física y lógica descubierta (Fase 7)."""
    if not update.message:
        return

    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) as total_devs FROM snmp_devices WHERE is_active = 1")
                snmp_row = cur.fetchone()
                snmp_cnt = snmp_row["total_devs"] if snmp_row else 0

                cur.execute("SELECT COUNT(*) as total_links, SUM(CASE WHEN link_status = 'up' THEN 1 ELSE 0 END) as up_links FROM network_topology_links")
                link_stats = cur.fetchone() or {"total_links": 0, "up_links": 0}

                cur.execute("""
                    SELECT l.link_type, l.link_status, s.name as src_name, COALESCE(t.name, l.target_hostname) as tgt_name
                    FROM network_topology_links l
                    JOIN snmp_devices s ON l.source_device_id = s.id
                    LEFT JOIN snmp_devices t ON l.target_device_id = t.id
                    ORDER BY l.link_status ASC, l.id ASC LIMIT 6
                """)
                sample_links = cur.fetchall()
        finally:
            conn.close()

        total_links = link_stats.get("total_links") or 0
        up_links = link_stats.get("up_links") or 0

        lines = [
            "🗺️ <b>TOPOLOGÍA VISUAL DE RED (VALLE SECO)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Dispositivos SNMP:</b> <code>{snmp_cnt}</code> equipos",
            f"• <b>Enlaces Descubiertos:</b> <code>{total_links}</code>",
            f"• <b>Enlaces Operativos (UP):</b> <code>{up_links}</code>",
            f"• <b>Protocolos:</b> <code>CDP / LLDP / FDB Inferred</code>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ENLACES PRINCIPALES:</b>"
        ]

        if not sample_links:
            lines.append("<i>No hay enlaces registrados en la topología.</i>")
        else:
            for lk in sample_links:
                status_icon = "🟢" if lk.get("link_status") == "up" else "🔴"
                ltype = (lk.get("link_type") or "manual").upper()
                s_name = lk.get("src_name", "Core")
                t_name = lk.get("tgt_name", "Equipo")
                lines.append(f"{status_icon} <b>{html.escape(s_name)}</b> ──[{ltype}]──> <b>{html.escape(t_name)}</b>")

        lines.append("\n🌐 <i>Visualiza el grafo interactivo completo en: <b>Portal Web -> Topología Visual</b></i>")
        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_topologia: {e}")
        await _reply(update.message, f"❌ Error consultando topología: {html.escape(str(e))}")


async def cmd_wol(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Envía Magic Packets Wake-on-LAN o lista equipos registrados (Fase 7)."""
    if not update.message:
        return

    cfg = _get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    user_id = update.effective_user.id if update.effective_user else 0
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    args = context.args or []

    try:
        from monitor.wol_sender import WolManager
        manager = WolManager()

        if not args:
            devices = manager.list_devices()
            lines = [
                "⚡ <b>DISPOSITIVOS WAKE-ON-LAN (ENCENDIDO REMOTO)</b>",
                "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
            ]
            if not devices:
                lines.append("<i>No hay dispositivos registrados para Wake-on-LAN.</i>")
                lines.append("<i>Usa el portal web para registrar nuevos equipos.</i>")
            else:
                for d in devices:
                    last_w = d["last_woken_at"].strftime("%Y-%m-%d %H:%M") if d.get("last_woken_at") else "Nunca"
                    lines.append(f"• <b>[ID: {d['id']:02d}]</b> {html.escape(d['name'])}")
                    lines.append(f"  MAC: <code>{d['mac_address']}</code> | IP: <code>{d.get('ip_address') or 'N/A'}</code>")
                    lines.append(f"  Último encendido: <i>{last_w}</i>")
                    lines.append("")

            if is_admin:
                lines.append("💡 <i>Para encender un equipo: <code>/wol &lt;id|mac|nombre&gt;</code></i>")
            await _reply(update.message, "\n".join(lines))
            return

        if not is_admin:
            await _reply(update.message, "⛔ <i>Acción denegada: Sólo administradores pueden transmitir Magic Packets Wake-on-LAN.</i>")
            return

        target = " ".join(args)
        res = manager.wake(target)

        if res.get("status") == "ok":
            msg = (
                f"⚡ <b>MAGIC PACKET TRANSMITIDO CON ÉXITO</b>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"• <b>Equipo:</b> {html.escape(res['name'])}\n"
                f"• <b>MAC Destino:</b> <code>{res['mac']}</code>\n"
                f"• <b>Transmisión:</b> Broadcast UDP 9 vía <code>{res['broadcast']}</code>\n"
                f"• <b>Hora:</b> <code>{res['woken_at']}</code>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                f"<i>La orden de encendido fue enviada a la red física de Valle Seco.</i>"
            )
        else:
            msg = f"❌ Error enviando Wake-on-LAN: {html.escape(res.get('message', 'Fallo de red'))}"

        await _reply(update.message, msg)

    except Exception as e:
        logger.error(f"Error en cmd_wol: {e}")
        await _reply(update.message, f"❌ Error en comando WoL: {html.escape(str(e))}")


def register_network_routes(application: Application) -> None:
    """Registra los manejadores de telemetría y diagnóstico de red en la aplicación de Telegram."""
    application.add_handler(CommandHandler(["analisis_red", "red", "escaner_red", "network_scan", "radar"], cmd_analisis_red))
    application.add_handler(CommandHandler(["ip", "mi_ip", "ip_servidor", "server_ip", "my_ip", "red_ip", "ifconfig"], cmd_ip))
    application.add_handler(CommandHandler(["cluster", "nodo", "rol_nodo", "token_cluster"], cmd_cluster))
    application.add_handler(CommandHandler(["discovery", "antirogue", "antirrogue", "descubrimiento"], cmd_discovery))
    application.add_handler(CommandHandler(["snmp", "telemetria", "interfaces"], cmd_snmp))
    application.add_handler(CommandHandler(["traps", "trap", "snmptraps", "snmp_traps"], cmd_traps))
    application.add_handler(CommandHandler(["syslog", "syslogs", "logs_red"], cmd_syslog))
    application.add_handler(CommandHandler(["netflow", "flows", "flujos", "toptalkers"], cmd_netflow))
    application.add_handler(CommandHandler(["topologia", "topology", "grafo", "mapared"], cmd_topologia))
    application.add_handler(CommandHandler(["wol", "wake", "wakeonlan", "encender"], cmd_wol))
    logger.info("Rutas de Red y Telemetría registradas exitosamente.")
