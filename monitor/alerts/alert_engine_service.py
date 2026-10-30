# monitor/alerts/alert_engine_service.py
import logging
from datetime import datetime
from typing import Any, Callable, Dict, List, Optional, Set, Tuple

from monitor.monitor_db import get_db_connection
from monitor.alerts.services.storm_suppressor import compute_fingerprint, StormSuppressor
from monitor.alerts.services.maintenance_guard import MaintenanceGuard
from monitor.alerts.services.alert_correlator import AlertCorrelator
from monitor.alerts.services.escalation_processor import EscalationProcessor
from monitor.alerts.evaluators import (
    ServiceRuleEvaluator,
    SiteRuleEvaluator,
    SslRuleEvaluator,
    SnmpDeviceRuleEvaluator,
    InterfaceRuleEvaluator,
    DiscoveredDeviceRuleEvaluator,
)

logger = logging.getLogger("alert.engine")

class AlertEngine:
    """Motor central modular de evaluación de alertas, correlación, supresión y escalación."""

    def __init__(self, db: Any = None):
        self.db = db or get_db_connection()

    def _ensure_db(self) -> None:
        try:
            self.db.ping()
        except Exception:
            self.db = get_db_connection()

    def is_in_maintenance_window(self, entity_type: str, entity_id: int, severity: str) -> bool:
        """Verifica si la entidad se encuentra bajo una ventana de mantenimiento activa."""
        self._ensure_db()
        return MaintenanceGuard.is_in_maintenance_window(self.db, entity_type, entity_id, severity)

    def check_storm_suppression(
        self,
        fingerprint: str,
        max_per_hour: int = 5,
        cooldown_minutes: int = 30,
    ) -> bool:
        """Evalúa si la alerta debe ser suprimida por tormenta (flapping)."""
        self._ensure_db()
        return StormSuppressor.check_storm_suppression(self.db, fingerprint, max_per_hour, cooldown_minutes)

    def apply_correlation(
        self,
        child_type: str,
        child_id: int,
        severity: str,
    ) -> Tuple[bool, Optional[int], Optional[int], str]:
        """Aplica reglas de correlación topológica."""
        self._ensure_db()
        return AlertCorrelator.apply_correlation(self.db, child_type, child_id, severity)

    def trigger_alert(
        self,
        rule: Dict[str, Any],
        entity_type: str,
        entity_id: int,
        entity_name: str,
        condition_type: str,
        severity: str,
        value_at_trigger: Optional[float] = None,
        threshold_value: Optional[float] = None,
        message: Optional[str] = None,
    ) -> Optional[int]:
        """Crea o actualiza una alerta respetando mantenimiento, tormentas y correlación."""
        self._ensure_db()
        fingerprint = compute_fingerprint(entity_type, entity_id, condition_type)

        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT id, status, current_escalation_level, last_notified_at, notification_count
                FROM alerts
                WHERE entity_type = %s AND entity_id = %s AND condition_type = %s
                  AND status IN ('firing', 'acknowledged', 'suppressed')
                ORDER BY id DESC LIMIT 1
                """,
                (entity_type, entity_id, condition_type)
            )
            existing = cur.fetchone()

            in_maintenance = self.is_in_maintenance_window(entity_type, entity_id, severity)
            is_storm = self.check_storm_suppression(
                fingerprint,
                max_per_hour=rule.get("max_alerts_per_hour", 5),
                cooldown_minutes=rule.get("cooldown_minutes", 30),
            )
            is_corr_suppressed, group_id, parent_alert_id, final_severity = self.apply_correlation(
                entity_type, entity_id, severity
            )

            initial_status = "suppressed" if (in_maintenance or is_storm or is_corr_suppressed) else "firing"
            now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

            if existing:
                alert_id = existing["id"]
                cur.execute(
                    """
                    UPDATE alerts
                    SET value_at_trigger = %s,
                        threshold_value = %s,
                        message = %s,
                        is_correlated_suppressed = %s,
                        parent_alert_id = %s,
                        correlation_group_id = %s,
                        severity = %s,
                        updated_at = %s
                    WHERE id = %s
                    """,
                    (
                        value_at_trigger,
                        threshold_value,
                        message,
                        1 if is_corr_suppressed else 0,
                        parent_alert_id,
                        group_id,
                        final_severity,
                        now,
                        alert_id,
                    )
                )
                return alert_id

            cur.execute(
                """
                INSERT INTO alerts
                (alert_rule_id, entity_type, entity_id, entity_name, condition_type, severity,
                 status, current_escalation_level, value_at_trigger, threshold_value, message,
                 correlation_group_id, is_correlated_suppressed, parent_alert_id, fired_at,
                 duration_seconds, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 1, %s, %s, %s, %s, %s, %s, %s, 0, %s, %s)
                """,
                (
                    rule.get("id"),
                    entity_type,
                    entity_id,
                    entity_name,
                    condition_type,
                    final_severity,
                    initial_status,
                    value_at_trigger,
                    threshold_value,
                    message,
                    group_id,
                    1 if is_corr_suppressed else 0,
                    parent_alert_id,
                    now,
                    now,
                    now,
                )
            )
            new_id = cur.lastrowid
            logger.info(
                f"🚨 Alerta #{new_id} DISPARADA: [{final_severity.upper()}] {entity_name} ({entity_type}) - "
                f"{condition_type} - Status: {initial_status}"
            )
            return new_id

    def auto_resolve_cleared_alerts(self, active_keys_still_firing: Set[Tuple[str, int, str]]) -> None:
        """Auto-resuelve alertas activas cuya condición ya no se cumple."""
        self._ensure_db()
        now = datetime.now()
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")

        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT a.id, a.entity_type, a.entity_id, a.condition_type, a.entity_name, a.fired_at,
                       r.auto_resolve
                FROM alerts a
                LEFT JOIN alert_rules r ON a.alert_rule_id = r.id
                WHERE a.status IN ('firing', 'acknowledged', 'suppressed')
                """
            )
            active_alerts = cur.fetchall()

            for a in active_alerts:
                key = (a["entity_type"], a["entity_id"], a["condition_type"])
                if key not in active_keys_still_firing:
                    auto_resolve_enabled = a.get("auto_resolve")
                    if auto_resolve_enabled is None or bool(auto_resolve_enabled):
                        fired_at = a["fired_at"] or now
                        duration = int((now - fired_at).total_seconds()) if isinstance(fired_at, datetime) else 0

                        cur.execute(
                            """
                            UPDATE alerts
                            SET status = 'auto_resolved',
                                resolved_by = 'auto',
                                resolved_at = %s,
                                duration_seconds = %s,
                                updated_at = %s
                            WHERE id = %s
                            """,
                            (now_str, duration, now_str, a["id"])
                        )
                        logger.info(
                            f"✅ Alerta #{a['id']} AUTO-RESUELTA: {a['entity_name']} ({a['condition_type']}) - Duración: {duration}s"
                        )

    def evaluate_all_rules(self) -> None:
        """Evalúa todas las reglas activas contra el estado actual de la infraestructura."""
        self._ensure_db()
        logger.info("⚡ Iniciando ciclo de evaluación de reglas de alertas...")
        firing_keys: Set[Tuple[str, int, str]] = set()

        with self.db.cursor() as cur:
            cur.execute("SELECT * FROM alert_rules WHERE is_active = 1")
            rules = cur.fetchall()

            for rule in rules:
                e_type = rule["entity_type"]
                c_type = rule["condition_type"]
                threshold = float(rule["threshold_value"]) if rule["threshold_value"] is not None else None
                comparison = rule["comparison"]
                severity = rule["severity"]

                if e_type == "service":
                    ServiceRuleEvaluator.evaluate(cur, rule, c_type, threshold, comparison, severity, firing_keys, self.trigger_alert)
                elif e_type == "site":
                    SiteRuleEvaluator.evaluate(cur, rule, c_type, threshold, comparison, severity, firing_keys, self.trigger_alert)
                elif e_type == "ssl_certificate":
                    SslRuleEvaluator.evaluate(cur, rule, c_type, threshold, comparison, severity, firing_keys, self.trigger_alert)
                elif e_type == "snmp_device":
                    SnmpDeviceRuleEvaluator.evaluate(cur, rule, c_type, threshold, comparison, severity, firing_keys, self.trigger_alert)
                elif e_type == "interface":
                    InterfaceRuleEvaluator.evaluate(cur, rule, c_type, severity, firing_keys, self.trigger_alert)
                elif e_type == "discovered_device":
                    DiscoveredDeviceRuleEvaluator.evaluate(cur, rule, c_type, threshold, comparison, severity, firing_keys, self.trigger_alert)

        self.auto_resolve_cleared_alerts(firing_keys)
        logger.info(f"✨ Evaluación completada. Incidentes activos detectados: {len(firing_keys)}")

    async def process_escalations(self) -> None:
        """Procesa niveles de escalación respetando Golden Rule #1."""
        self._ensure_db()
        await EscalationProcessor.process_escalations(self.db)

    def _resolve_db_user_id(self, user_id: Any) -> Optional[int]:
        """Resuelve un ID de usuario a un id válido en la tabla users para foreign keys."""
        if not user_id:
            return 1
        try:
            uid = int(user_id)
        except (ValueError, TypeError):
            return 1

        with self.db.cursor() as cur:
            cur.execute("SELECT id FROM users WHERE id = %s", (uid,))
            row = cur.fetchone()
            if row:
                return row["id"]
            cur.execute("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")
            admin_row = cur.fetchone()
            return admin_row["id"] if admin_row else 1

    def acknowledge_alert(self, alert_id: int, user_id: int = 1, notes: Optional[str] = None) -> bool:
        """Marca una alerta como reconocida por un usuario u operador."""
        self._ensure_db()
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        db_uid = self._resolve_db_user_id(user_id)
        with self.db.cursor() as cur:
            cur.execute(
                """
                UPDATE alerts
                SET status = 'acknowledged',
                    acknowledged_by = %s,
                    acknowledged_at = %s,
                    notes = CONCAT(IFNULL(notes, ''), '\n[ACK ', %s, '] ', IFNULL(%s, 'Reconocido por operador'))
                WHERE id = %s AND status = 'firing'
                """,
                (db_uid, now, now, notes, alert_id)
            )
            return cur.rowcount > 0

    def resolve_alert(self, alert_id: int, notes: Optional[str] = None) -> bool:
        """Resuelve manualmente una alerta."""
        self._ensure_db()
        now = datetime.now()
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")
        with self.db.cursor() as cur:
            cur.execute("SELECT fired_at FROM alerts WHERE id = %s", (alert_id,))
            row = cur.fetchone()
            if not row:
                return False

            fired_at = row["fired_at"] or now
            duration = int((now - fired_at).total_seconds()) if isinstance(fired_at, datetime) else 0

            cur.execute(
                """
                UPDATE alerts
                SET status = 'resolved',
                    resolved_by = 'manual',
                    resolved_at = %s,
                    duration_seconds = %s,
                    notes = CONCAT(IFNULL(notes, ''), '\n[RESUELTO ', %s, '] ', IFNULL(%s, 'Cerrado manualmente'))
                WHERE id = %s AND status IN ('firing', 'acknowledged', 'suppressed')
                """,
                (now_str, duration, now_str, notes, alert_id)
            )
            return cur.rowcount > 0

    def silence_alert(self, alert_id: int, minutes: int = 60, user_id: int = 1) -> bool:
        """Crea una ventana de mantenimiento temporal para silenciar la alerta."""
        self._ensure_db()
        now = datetime.now()
        ends = now + timedelta(minutes=minutes)
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")
        ends_str = ends.strftime("%Y-%m-%d %H:%M:%S")
        db_uid = self._resolve_db_user_id(user_id)

        with self.db.cursor() as cur:
            cur.execute("SELECT entity_type, entity_id, entity_name FROM alerts WHERE id = %s", (alert_id,))
            row = cur.fetchone()
            if not row:
                return False

            cur.execute(
                """
                INSERT INTO maintenance_windows
                (title, description, entity_type, entity_id, starts_at, ends_at, created_by, is_active, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 1, %s, %s)
                """,
                (
                    f"Silenciado temporal ({row['entity_name']})",
                    f"Silenciado por {minutes} minutos desde CLI/Telegram",
                    row["entity_type"],
                    row["entity_id"],
                    now_str,
                    ends_str,
                    db_uid,
                    now_str,
                    now_str
                )
            )

            cur.execute(
                """
                UPDATE alerts
                SET status = 'suppressed',
                    silenced_until = %s,
                    notes = CONCAT(IFNULL(notes, ''), '\n[SILENCIADO] Por ', %s, ' min hasta ', %s)
                WHERE id = %s
                """,
                (ends_str, minutes, ends_str, alert_id)
            )
            return True
