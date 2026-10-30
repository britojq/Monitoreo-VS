# monitor/alerts/services/escalation_processor.py
import html
import logging
import re
from datetime import datetime
from typing import Any

logger = logging.getLogger("alert.engine.escalation")

class EscalationProcessor:
    """Procesa alertas activas y despacha notificaciones según alert_escalation_levels."""

    @staticmethod
    async def process_escalations(db: Any) -> None:
        """
        Evalúa alertas activas y despacha notificaciones según alert_escalation_levels.
        REGLA DE ORO #1: Estrictamente prohíbe el envío a grupos o IDs negativos.
        """
        from monitor.telegram_dispatcher import TelegramDispatcher

        dispatcher = TelegramDispatcher()
        now = datetime.now()
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")

        with db.cursor() as cur:
            cur.execute(
                """
                SELECT a.id, a.alert_rule_id, a.entity_type, a.entity_id, a.entity_name,
                       a.condition_type, a.severity, a.status, a.current_escalation_level,
                       a.value_at_trigger, a.threshold_value, a.message, a.fired_at,
                       a.last_notified_at, a.notification_count
                FROM alerts a
                WHERE a.status = 'firing'
                  AND (a.silenced_until IS NULL OR a.silenced_until <= NOW())
                  AND a.is_correlated_suppressed = 0
                ORDER BY a.fired_at ASC
                """
            )
            alerts = cur.fetchall()

            for alert in alerts:
                aid = alert["id"]
                rule_id = alert["alert_rule_id"]
                if not rule_id:
                    continue

                cur.execute(
                    """
                    SELECT * FROM alert_escalation_levels
                    WHERE alert_rule_id = %s AND is_active = 1
                    ORDER BY level ASC
                    """,
                    (rule_id,)
                )
                levels = cur.fetchall()
                if not levels:
                    continue

                fired_at = alert["fired_at"] or now
                elapsed_minutes = (now - fired_at).total_seconds() / 60.0

                for lvl in levels:
                    level_num = lvl["level"]
                    delay_min = lvl["delay_minutes"] or 0
                    lvl_id = lvl["id"]

                    if elapsed_minutes < delay_min:
                        continue

                    cur.execute(
                        """
                        SELECT id FROM alert_notifications
                        WHERE alert_id = %s AND escalation_level_id = %s AND status = 'sent'
                        LIMIT 1
                        """,
                        (aid, lvl_id)
                    )
                    already_sent = cur.fetchone()
                    if already_sent:
                        continue

                    # REGLA DE ORO #1: Chat ID estricto privado del Administrador
                    target_chat = "38914901"
                    if lvl.get("target_external"):
                        target_chat = str(lvl["target_external"]).strip()

                    try:
                        cid = int(target_chat)
                        if cid < 0:
                            logger.error(f"🚨 REGLA DE ORO #1: Intento bloqueado de despachar a grupo ({cid}). Redirigiendo a Owner 38914901.")
                            target_chat = "38914901"
                    except ValueError:
                        target_chat = "38914901"

                    sev_map = {
                        "emergency": ("🆘", "EMERGENCIA"),
                        "critical": ("🚨", "CRÍTICA"),
                        "warning": ("⚠️", "ADVERTENCIA"),
                        "info": ("ℹ️", "INFORMACIÓN"),
                    }
                    ico, sev_label = sev_map.get(alert.get("severity", "critical"), ("🚨", "CRÍTICA"))

                    entity_types = {
                        "service": "Servicio Web",
                        "site": "Sede / Enlace WAN",
                        "snmp_device": "Dispositivo de Red",
                        "network_device": "Dispositivo de Red",
                        "snmp_interface": "Interfaz de Red",
                        "ssl_certificate": "Certificado SSL/TLS",
                        "discovered_device": "Dispositivo Descubierto",
                    }
                    etype = entity_types.get(alert.get("entity_type", ""), alert.get("entity_type") or "Recurso")

                    conditions = {
                        "is_down": "Servicio Caído / Sin Respuesta",
                        "service_latency": "Latencia Web Degradada",
                        "wan_packet_loss": "Pérdida Crítica de Paquetes WAN",
                        "wan_latency": "Latencia WAN Elevada",
                        "snmp_cpu": "Sobrecarga de CPU en Equipo",
                        "snmp_memory": "Saturación de Memoria RAM",
                        "snmp_temperature": "Temperatura Crítica en Equipo",
                        "snmp_interface_status": "Interfaz Desconectada (Down)",
                        "ssl_days_remaining": "Certificado SSL Próximo a Vencer / Expirado",
                        "rogue_device_detected": "Dispositivo Rogue No Autorizado",
                        "device_missing_hours": "Dispositivo Crítico Desconectado",
                    }
                    cond_label = conditions.get(alert.get("condition_type", ""), alert.get("condition_type") or "Anomalía")

                    dur_str = f"{int(elapsed_minutes)} minutos" if elapsed_minutes >= 1 else f"{int(elapsed_minutes * 60)} segundos"

                    fired_at = alert.get("fired_at")
                    if hasattr(fired_at, "strftime"):
                        fired_str = fired_at.strftime("%d/%m/%Y %I:%M %p")
                    elif isinstance(fired_at, str):
                        fired_str = fired_at
                    else:
                        fired_str = datetime.now().strftime("%d/%m/%Y %I:%M %p")

                    entity_name = alert.get("entity_name") or "Desconocido"
                    detail = alert.get("message") or "Superación de umbral crítico de telemetría."

                    url_line = ""
                    if alert.get("entity_type") == "service":
                        cur.execute("SELECT web_url FROM monitored_services WHERE id = %s", (alert.get("entity_id"),))
                        s_row = cur.fetchone()
                        if s_row and s_row.get("web_url"):
                            url_line = f"🌐 <b>URL:</b> <code>{html.escape(s_row['web_url'])}</code>\n"
                    elif alert.get("entity_type") == "ssl_certificate":
                        url_line = f"🌐 <b>Dominio:</b> <code>{html.escape(entity_name)}</code>\n"
                    elif alert.get("entity_type") == "site":
                        cur.execute("SELECT ip FROM monitored_sites WHERE id = %s", (alert.get("entity_id"),))
                        site_row = cur.fetchone()
                        if site_row and site_row.get("ip"):
                            url_line = f"🌐 <b>IP Sede:</b> <code>{html.escape(site_row['ip'])}</code>\n"
                    elif alert.get("entity_type") in ("snmp_device", "network_device"):
                        cur.execute("SELECT ip FROM snmp_devices WHERE id = %s", (alert.get("entity_id"),))
                        dev_row = cur.fetchone()
                        if dev_row and dev_row.get("ip"):
                            url_line = f"🌐 <b>IP Equipo:</b> <code>{html.escape(dev_row['ip'])}</code>\n"

                    if url_line:
                        detail = re.sub(r"\s*\([^)]*https?://[^)]*\)", "", detail).strip()

                    msg_text = (
                        f"{ico} <b>INCIDENTE DE INFRAESTRUCTURA</b> (Escalación L{level_num})\n"
                        f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                        f"📍 <b>Entidad:</b> <code>{html.escape(entity_name)}</code> ({etype})\n"
                        f"⚠️ <b>Condición:</b> {cond_label}\n"
                        f"🔴 <b>Severidad:</b> {sev_label}\n"
                        f"⏱️ <b>Tiempo activo:</b> {dur_str}\n"
                        f"🕒 <b>Detectado:</b> {fired_str}\n"
                        f"{url_line}"
                        f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                        f"<b>Detalle:</b> {html.escape(detail)}"
                    )

                    portal_url = "http://192.168.1.220/admin/alerts"
                    reply_markup = {
                        "inline_keyboard": [
                            [
                                {"text": "✅ Reconocer (ACK)", "callback_data": f"alert_act:ack:{aid}"},
                                {"text": "🔇 Silenciar 1h", "callback_data": f"alert_act:silence:{aid}:60"}
                            ],
                            [
                                {"text": "📋 Detalle", "callback_data": f"alert_act:detail:{aid}"},
                                {"text": "🏁 Resolver", "callback_data": f"alert_act:resolve:{aid}"}
                            ],
                            [
                                {"text": "🌐 Ver en Portal Web", "url": f"{portal_url}?search={aid}"}
                            ]
                        ]
                    }

                    success, resp_info = await dispatcher.send_text(
                        target_chat,
                        msg_text,
                        parse_mode="HTML",
                        reply_markup=reply_markup
                    )

                    cur.execute(
                        """
                        INSERT INTO alert_notifications
                        (alert_id, escalation_level_id, channel, target, message_sent, status, error_message, sent_at)
                        VALUES (%s, %s, 'telegram', %s, %s, %s, %s, %s)
                        """,
                        (
                            aid,
                            lvl_id,
                            target_chat,
                            msg_text,
                            "sent" if success else "failed",
                            None if success else resp_info,
                            now_str
                        )
                    )

                    if success:
                        cur.execute(
                            """
                            UPDATE alerts
                            SET current_escalation_level = %s,
                                last_notified_at = %s,
                                notification_count = notification_count + 1
                            WHERE id = %s
                            """,
                            (level_num, now_str, aid)
                        )
                        logger.info(f"📤 Notificación Nivel {level_num} despachada para Alerta #{aid} a {target_chat}")
                    else:
                        logger.warning(f"❌ Falló despacho de alerta #{aid}: {resp_info}")
