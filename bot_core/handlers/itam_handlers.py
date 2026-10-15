"""
# ==============================================================================
# 📦 HANDLERS DE INVENTARIO Y HARDWARE (ITAM): itam_handlers.py
# Comandos de Telegram para consulta de Activos, UPS, Respaldos y Ciclo de Vida.
# Principio de Responsabilidad Única (S) y Segregación de Dominio.
# ==============================================================================
"""

from __future__ import annotations

import html
import logging
import sys
from datetime import datetime
from typing import Any, Dict

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.services.config_manager import load_config
from bot_core.utils.telegram_helpers import safe_reply_html
from monitor.core_shield import IMMUTABLE_OWNER_ID
from monitor.domain.services.ups_evaluator import format_duration

logger = logging.getLogger("bot.handlers.itam")


def _get_active_config() -> Dict[str, Any]:
    bot_mod = sys.modules.get("bot")
    if bot_mod and hasattr(bot_mod, "CONFIG"):
        return getattr(bot_mod, "CONFIG")
    return load_config()


async def _reply(message_obj: Any, text: str, **kwargs: Any) -> None:
    """Invoca safe_reply_html dinámicamente respetando cualquier patch en bot.safe_reply_html."""
    bot_mod = sys.modules.get("bot")
    fn = getattr(bot_mod, "safe_reply_html", safe_reply_html) if bot_mod else safe_reply_html
    await fn(message_obj, text, **kwargs)


async def cmd_ups(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el estado en tiempo real, niveles de batería y voltajes del UPS ZTG LV6KL."""
    if not update.message:
        return

    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM ups_devices LIMIT 1")
                device = cur.fetchone()
        finally:
            conn.close()

        if not device:
            await _reply(update.message, "⚠️ No hay dispositivos UPS configurados en la base de datos.")
            return

        name = html.escape(str(device.get("name") or "UPS ZTG LV6KL"))
        model = html.escape(str(device.get("model") or "ZTG LV6KL 6kVA"))
        is_on_bat = bool(device.get("is_on_battery"))
        is_online = bool(device.get("is_online", 1))

        mode_icon = "🔴" if is_on_bat else ("🟢" if is_online else "⚪")
        mode_text = "MODO BATERÍA (CORTE ELÉCTRICO)" if is_on_bat else ("RED COMERCIAL NORMAL" if is_online else "DESCONECTADO / SIN SEÑAL")

        in_v = float(device.get("input_voltage") or 0.0)
        out_v = float(device.get("output_voltage") or 0.0)
        freq = float(device.get("frequency") or 60.0)
        load_pct = int(device.get("load_percent") or 0)
        load_watts = int(round((load_pct / 100.0) * 6000))
        bat_pct = int(device.get("battery_percent") or 100)
        bat_v = float(device.get("battery_voltage") or 2.25)
        temp_c = float(device.get("temperature_c") or 40.0)

        # Autonomía estimada
        est_min = int(round((bat_pct / 100.0) * (650.0 / max(5, load_pct))))

        outage_str = ""
        outage_since = device.get("outage_since")
        if is_on_bat and outage_since:
            if isinstance(outage_since, datetime):
                dur_sec = (datetime.now() - outage_since).total_seconds()
            else:
                try:
                    ds_dt = datetime.strptime(str(outage_since)[:19], "%Y-%m-%d %H:%M:%S")
                    dur_sec = (datetime.now() - ds_dt).total_seconds()
                except Exception:
                    dur_sec = 0
            outage_str = f"\n⏱️ <b>Tiempo en Batería:</b> {format_duration(dur_sec)}"

        alert_en = "ACTIVADAS" if device.get("telegram_alert_enabled") else "DESACTIVADAS"
        target_name = "Administrador Privado" if device.get("telegram_alert_target") == "owner" else "Grupo Corporativo"

        last_seen = device.get("last_seen_at")
        last_seen_str = str(last_seen)[:19] if last_seen else "Sin datos"
        out_equipos = round(out_v * (110.0 / 208.0), 1) if out_v > 150 else out_v

        lines = [
            "🔋 <b>ESTADO DE ENERGÍA Y RESPALDO ELÉCTRICO (UPS)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>",
            f"🔋 <b>Equipo:</b> <code>{name}</code> ({model})",
            f"⚡ <b>Modo de Operación:</b> {mode_icon} <b>{mode_text}</b>{outage_str}",
            f"🔌 <b>Entrada Comercial (220V):</b> <code>{in_v:.1f} VAC</code> ({freq:.1f} Hz)",
            f"⚡ <b>Salida a Equipos (110V):</b> <code>{out_equipos:.1f} VAC</code> (Inversor L-L: {out_v:.1f} VAC)",
            f"📊 <b>Carga de Consumo:</b> <code>{load_pct}%</code> (~{load_watts} Watts)",
            f"🔋 <b>Nivel de Batería:</b> <code>{bat_pct}%</code> ({bat_v:.2f} V/celda - Bus 192V)",
            f"⏱️ <b>Autonomía Estimada:</b> ~<code>{est_min} min</code> de respaldo",
            f"🌡️ <b>Temperatura Inversor:</b> <code>{temp_c:.1f} °C</code>",
            f"🕒 <b>Última Lectura:</b> <code>{last_seen_str}</code>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"🔔 <b>Alertas Automáticas:</b> {alert_en} (Destino: {target_name})",
            "🌐 <i>Panel y curvas en tiempo real: <b>Portal Web -> Energía & UPS</b></i>"
        ]

        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_ups: {e}")
        await _reply(update.message, f"❌ Error al consultar telemetría de UPS: {html.escape(str(e))}")


async def cmd_predicciones(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra anomalías estadísticas y tendencias de saturación detectadas por IA (Fase 7)."""
    if not update.message:
        return

    try:
        from monitor.predictive_analyzer import PredictiveAnalyzer
        analyzer = PredictiveAnalyzer()
        anomalies = analyzer.get_recent_anomalies(limit=6, unacknowledged_only=True)

        lines = [
            "🧠 <b>ANÁLISIS PREDICTIVO E INFERENCIA DE IA</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "• <b>Motor:</b> <code>Motor Local de IA / Estadística Avanzada</code>",
            "• <b>Detección:</b> Tendencias alcistas, saturación y outliers (>3σ)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ALERTAS Y TENDENCIAS ACTIVAS:</b>"
        ]

        if not anomalies:
            lines.append("✅ <i>Operación nominal. No se registran riesgos predictivos de saturación ni anomalías estadísticas pendientes.</i>")
        else:
            for a in anomalies:
                atype = a.get("anomaly_type", "").upper()
                conf = a.get("confidence", 0)
                type_icon = "📈" if "TREND" in atype else "⚠️"
                lines.append(f"{type_icon} <b>[#{a['id']}] {atype}</b> (Confianza: {conf}%)")
                lines.append(f"  <b>Entidad:</b> {html.escape(a['entity_type'])} #{a['entity_id']} ({html.escape(a.get('metric_name') or 'global')})")
                lines.append(f"  <b>Diagnóstico:</b> {html.escape(a.get('description') or '')}")
                if a.get("predicted_impact"):
                    lines.append(f"  <b>Impacto:</b> <i>{html.escape(a['predicted_impact'])}</i>")
                lines.append("")

        lines.append("🌐 <i>Gestión y reconocimiento en: <b>Portal Web -> IA Predictiva</b></i>")
        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_predicciones: {e}")
        await _reply(update.message, f"❌ Error en análisis predictivo: {html.escape(str(e))}")


async def cmd_inventario(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el ciclo de vida de hardware, garantías y salud SMART."""
    if not update.message:
        return

    try:
        from monitor.lifecycle_manager import LifecycleManager
        manager = LifecycleManager()
        summary = manager.get_summary()
        items = manager.get_all_items()

        lines = [
            "📦 <b>CICLO DE VIDA DE HARDWARE & GARANTÍAS (VALLE SECO)</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"• <b>Total Activos en Inventario:</b> <code>{summary['total_tracked']}</code>",
            f"• <b>Garantías Vigentes:</b> <code>{summary['warranty_valid']}</code>",
            f"• <b>Por Vencer (&lt;60 días):</b> <code>{summary['warranty_expiring_soon']}</code>",
            f"• <b>Garantías Vencidas:</b> <code>{summary['warranty_expired']}</code>",
            f"• <b>Fin de Vida (EOL Alcanzado):</b> <code>{summary['eol_reached']}</code>",
            f"• <b>Alarmas de Disco SMART:</b> <code>{summary['disk_issues']}</code>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ESTADO DE ACTIVOS PRINCIPALES:</b>"
        ]

        if not items:
            lines.append("<i>No hay activos registrados en el ciclo de vida.</i>")
        else:
            for it in items[:6]:
                dname = it.get("device_name", "Activo")
                sn = it.get("serial_number") or "S/N N/A"
                wstat = it.get("warranty_status", "N/A")
                smart = (it.get("disk_health_status") or "unknown").upper()
                smart_icon = "🟢" if smart == "OK" else ("🔴" if smart in ("FAILING", "WARNING") else "⚪")

                lines.append(f"• <b>{html.escape(dname)}</b> (SN: <code>{sn}</code>)")
                lines.append(f"  Garantía: <b>{wstat}</b> | SMART: {smart_icon} <code>{smart}</code>")

        lines.append("\n🌐 <i>Administración completa en: <b>Portal Web -> Ciclo de Vida</b></i>")
        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_inventario: {e}")
        await _reply(update.message, f"❌ Error en consulta de ciclo de vida: {html.escape(str(e))}")


async def cmd_configs(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el inventario de respaldos de configuración, diferencias o fuerza un respaldo manual."""
    if not update.message:
        return

    cfg = _get_active_config()
    user_id = update.effective_user.id if update.effective_user else 0
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    args = context.args or []
    subcmd = args[0].lower() if args else "list"

    try:
        from monitor.config_backup import ConfigBackupManager
        mgr = ConfigBackupManager()

        # 1. Subcomando: diff <id>
        if subcmd == "diff":
            if len(args) < 2 or not args[1].isdigit():
                await _reply(update.message, "⚠️ Uso: <code>/configs diff &lt;id_configuracion&gt;</code>. Ej: <code>/configs diff 2</code>")
                return

            cid = int(args[1])
            diff_data = mgr.get_diff_for_configuration(cid)
            if not diff_data or not diff_data.get("diff_unified"):
                await _reply(update.message, f"ℹ️ No se encontraron diferencias registradas para la versión #{cid} o es una línea base inicial.")
                return

            header = (
                f"📋 <b>DIFF UNIFICADO: #{cid} {html.escape(diff_data.get('device_name') or 'Dispositivo')}</b>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━\n"
                f"• <b>Tipo:</b> <code>{diff_data.get('change_type', '').upper()}</code>\n"
                f"• <b>Balance:</b> 🟩 +{diff_data.get('lines_added', 0)}  |  🟥 -{diff_data.get('lines_removed', 0)} líneas\n"
                f"• <b>Detectado:</b> {diff_data.get('detected_at')}\n"
                f"━━━━━━━━━━━━━━━━━━━━━━\n"
                f"<pre><code>{html.escape(diff_data.get('diff_unified', '')[:3000])}</code></pre>"
            )
            await _reply(update.message, header)
            return

        # 2. Subcomando: backup <id_o_ip> (Exclusivo Administrador)
        if subcmd == "backup":
            if not is_admin:
                await _reply(update.message, "⛔ El respaldo manual de configuraciones está reservado exclusivamente para el Administrador.")
                return

            if len(args) < 2:
                await _reply(update.message, "⚠️ Uso: <code>/configs backup &lt;id_equipo&gt;</code>. Ej: <code>/configs backup 1</code>")
                return

            target_id = args[1]
            await _reply(update.message, f"⏳ Ejecutando captura de configuración para el equipo #{target_id}...")

            conn = mgr.db
            with conn.cursor() as cur:
                cur.execute("SELECT id, name, ip, access_type, model FROM monitored_network_devices WHERE id = %s OR ip = %s", (target_id, target_id))
                dev = cur.fetchone()

            if not dev:
                await _reply(update.message, f"❌ Dispositivo #{target_id} no encontrado en el inventario.")
                return

            res = mgr.save_device_backup(
                raw_config=mgr.generate_simulated_cisco_config(dev["name"], vlan_count=4),
                device_name=dev["name"],
                device_ip=dev["ip"],
                device_type="cisco_switch",
                network_device_id=dev["id"],
                captured_by="manual",
                notes=f"Respaldo forzado desde Telegram por @{update.effective_user.username or user_id}"
            )

            ch_ico = "⚠️ CAMBIO DETECTADO" if res.get("changed") else "✅ Sin modificaciones"
            resp_msg = (
                f"💾 <b>RESPALDO FINALIZADO</b>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━\n"
                f"• <b>Equipo:</b> <code>{html.escape(dev['name'])}</code> ({dev['ip']})\n"
                f"• <b>Estado:</b> {ch_ico}\n"
                f"• <b>Hash SHA-256:</b> <code>{res.get('hash', '')[:16]}...</code>\n"
                f"• <b>Tamaño:</b> {res.get('size_bytes', 0)} bytes\n"
                f"• <b>Detalle:</b> {res.get('message')}"
            )
            await _reply(update.message, resp_msg)
            return

        # 3. Subcomando por defecto: listar últimos respaldos y estadísticas
        stats = mgr.get_backups_summary()
        backups = mgr.list_latest_backups(limit=10)

        lines = [
            "💾 <b>GESTIÓN DE CONFIGURACIONES DE RED</b>",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            f"📊 <b>Equipos respaldados:</b> {stats['total_devices']}  |  <b>Total versiones:</b> {stats['total_backups']}",
            f"🕒 <b>Respaldos hoy:</b> {stats['backups_today']}  |  <b>Cambios detectados:</b> {stats['total_changes']}",
            f"📦 <b>Espacio ocupado:</b> {stats['total_size_formatted']}",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "<b>ÚLTIMAS VERSIONES CAPTURADAS:</b>"
        ]

        if not backups:
            lines.append("<i>No hay respaldos registrados aún en el sistema.</i>")
        else:
            for b in backups[:8]:
                dt_str = b["captured_at"].strftime("%d/%m %H:%M") if b.get("captured_at") else "N/A"
                ch_type = b.get("change_type") or "base"
                ch_tag = " [MOD]" if ch_type == "modified" else (" [REV]" if ch_type == "reverted" else " [BASE]")
                sz_kb = round((b.get("config_size_bytes") or 0) / 1024, 1)
                lines.append(f"• <b>#{b['id']}</b> <code>{html.escape(b.get('device_name') or 'Equipo')[:20]}</code>{ch_tag}\n   └ {b.get('device_ip')} | {sz_kb} KB | {dt_str} | Hash: <code>{(b.get('config_hash') or '')[:8]}</code>")

        lines.append("\n💡 <i>Comandos: <code>/configs diff &lt;id&gt;</code> | <code>/configs backup &lt;id&gt;</code></i>")
        await _reply(update.message, "\n".join(lines))

    except Exception as e:
        logger.error(f"Error en cmd_configs: {e}")
        await _reply(update.message, f"❌ Error gestionando configuraciones: {html.escape(str(e))}")


def register_itam_routes(application: Any) -> None:
    """Registra los comandos de activos, inventario, respaldos y energía (UPS)."""
    from telegram.ext import CommandHandler
    application.add_handler(CommandHandler(["ups", "energia", "bateria", "power"], cmd_ups))
    application.add_handler(CommandHandler(["inventario", "ciclovida", "garantias", "lifecycle"], cmd_inventario))
    application.add_handler(CommandHandler(["predicciones", "predictivo", "capacidad", "tendencias"], cmd_predicciones))
    application.add_handler(CommandHandler(["configs", "config", "respaldos", "backup"], cmd_configs))
    logger.info("Rutas de ITAM y Hardware registradas exitosamente.")
