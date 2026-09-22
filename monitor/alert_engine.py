"""
# ==============================================================================
# 🚨 MOTOR DE ALERTAS, ESCALACIÓN Y CORRELACIÓN: alert_engine.py (@IA_ValleSeco_bot)
# Evaluación continua de reglas, correlación topológica, supresión de tormentas,
# ventanas de mantenimiento y escalación jerárquica de incidentes.
# Ubicación: /scripts/telegram-admin-bot/monitor/alert_engine.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import hashlib
import html
import json
import logging
import os
import re
import sys
import time
from datetime import datetime, timezone, timedelta
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import pymysql

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [alert.engine] %(message)s",
    handlers=[
        logging.FileHandler(LOG_DIR / "alert_engine.log", encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("alert.engine")


def load_env() -> Dict[str, str]:
    """Carga variables desde el archivo .env del portal web."""
    env_paths = [
        Path("/var/www/monitoreo/.env"),
        BASE_DIR / "web_portal" / ".env",
    ]
    env_vars = {}
    for p in env_paths:
        if p.exists():
            try:
                for line in p.read_text(encoding="utf-8").splitlines():
                    line = line.strip()
                    if line and not line.startswith("#") and "=" in line:
                        k, v = line.split("=", 1)
                        env_vars[k.strip()] = v.strip().strip('"').strip("'")
                break
            except Exception:
                pass
    return env_vars


ENV = load_env()


def get_db_connection():
    """Establece conexión con la base de datos MariaDB monitoreo_vs."""
    return pymysql.connect(
        host=ENV.get("DB_HOST", "127.0.0.1"),
        port=int(ENV.get("DB_PORT", 3306)),
        user=ENV.get("DB_USERNAME", "monitoreo_user"),
        password=ENV.get("DB_PASSWORD", "VsMonit#2026!SecureKey"),
        database=ENV.get("DB_DATABASE", "monitoreo_vs"),
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def compute_fingerprint(entity_type: str, entity_id: int | str, condition_type: str) -> str:
    """Genera hash SHA-256 único para deduplicación y control de tormentas."""
    raw = f"{entity_type}:{entity_id}:{condition_type}".encode("utf-8")
    return hashlib.sha256(raw).hexdigest()


class AlertEngine:
    """Motor central de evaluación de alertas, correlación, supresión y escalación."""

    def __init__(self):
        self.db = get_db_connection()

    def _ensure_db(self):
        try:
            self.db.ping()
        except Exception:
            self.db = get_db_connection()

    # --------------------------------------------------------------------------
    # 1. Ventanas de mantenimiento
    # --------------------------------------------------------------------------
    def is_in_maintenance_window(self, entity_type: str, entity_id: int, severity: str) -> bool:
        """Verifica si la entidad se encuentra bajo una ventana de mantenimiento activa."""
        self._ensure_db()
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        sql = """
            SELECT id, suppress_severities FROM maintenance_windows
            WHERE is_active = 1
              AND starts_at <= %s AND ends_at >= %s
              AND (entity_type = 'all' OR (entity_type = %s AND (entity_id IS NULL OR entity_id = %s)))
            LIMIT 1
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (now, now, entity_type, entity_id))
            row = cur.fetchone()
            if not row:
                return False

            suppress = row.get("suppress_severities")
            if not suppress:
                # Si es NULL o vacío, suprime todas las severidades
                return True
            try:
                if isinstance(suppress, str):
                    suppress = json.loads(suppress)
                if isinstance(suppress, list) and len(suppress) > 0:
                    return severity in suppress
                return True
            except Exception:
                return True

    # --------------------------------------------------------------------------
    # 2. Supresión de tormentas y flapping
    # --------------------------------------------------------------------------
    def check_storm_suppression(
        self,
        fingerprint: str,
        max_per_hour: int = 5,
        cooldown_minutes: int = 30
    ) -> bool:
        """
        Evalúa si la alerta debe ser suprimida por tormenta (flapping).
        Retorna True si debe suprimirse, False si puede procesarse.
        """
        self._ensure_db()
        now = datetime.now()
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")

        with self.db.cursor() as cur:
            cur.execute("SELECT * FROM alert_storm_suppression WHERE fingerprint = %s", (fingerprint,))
            record = cur.fetchone()

            if not record:
                # Primer registro de esta alerta
                cur.execute(
                    """
                    INSERT INTO alert_storm_suppression 
                    (fingerprint, alert_count, first_alert_at, last_alert_at, suppressed_count, next_allowed_at)
                    VALUES (%s, 1, %s, %s, 0, NULL)
                    """,
                    (fingerprint, now_str, now_str)
                )
                return False

            # Si ya hay un bloqueo activo (next_allowed_at en el futuro)
            next_allowed = record.get("next_allowed_at")
            if next_allowed and next_allowed > now:
                cur.execute(
                    """
                    UPDATE alert_storm_suppression 
                    SET suppressed_count = suppressed_count + 1, last_alert_at = %s
                    WHERE fingerprint = %s
                    """,
                    (now_str, fingerprint)
                )
                return True

            # Si pasó más de 1 hora desde first_alert_at, reiniciar ventana
            first_alert = record.get("first_alert_at") or now
            if (now - first_alert).total_seconds() > 3600:
                cur.execute(
                    """
                    UPDATE alert_storm_suppression
                    SET alert_count = 1, first_alert_at = %s, last_alert_at = %s, next_allowed_at = NULL
                    WHERE fingerprint = %s
                    """,
                    (now_str, now_str, fingerprint)
                )
                return False

            # Si está dentro de la hora y superó el máximo por hora permitido
            new_count = (record.get("alert_count") or 0) + 1
            if new_count > max_per_hour:
                cooldown_until = now + timedelta(minutes=cooldown_minutes)
                cooldown_str = cooldown_until.strftime("%Y-%m-%d %H:%M:%S")
                cur.execute(
                    """
                    UPDATE alert_storm_suppression
                    SET alert_count = %s, last_alert_at = %s, suppressed_count = suppressed_count + 1, next_allowed_at = %s
                    WHERE fingerprint = %s
                    """,
                    (new_count, now_str, cooldown_str, fingerprint)
                )
                logger.warning(
                    f"⛈️ Tormenta detectada en fingerprint {fingerprint[:8]}! Suprimiendo por {cooldown_minutes}m hasta {cooldown_str}."
                )
                return True

            # Incremento normal
            cur.execute(
                """
                UPDATE alert_storm_suppression
                SET alert_count = %s, last_alert_at = %s
                WHERE fingerprint = %s
                """,
                (new_count, now_str, fingerprint)
            )
            return False

    # --------------------------------------------------------------------------
    # 3. Correlación jerárquica padre-hijo
    # --------------------------------------------------------------------------
    def apply_correlation(
        self,
        child_type: str,
        child_id: int,
        severity: str
    ) -> Tuple[bool, Optional[int], Optional[int], str]:
        """
        Aplica reglas de correlación topológica.
        Retorna: (is_suppressed, correlation_group_id, parent_alert_id, final_severity)
        """
        self._ensure_db()
        with self.db.cursor() as cur:
            # Buscar si el elemento es miembro hijo de algún grupo de correlación activo
            sql = """
                SELECT g.id as group_id, g.parent_entity_type, g.parent_entity_id, g.suppression_strategy
                FROM alert_correlation_members m
                JOIN alert_correlation_groups g ON m.correlation_group_id = g.id
                WHERE m.child_entity_type = %s AND m.child_entity_id = %s AND g.is_active = 1
                LIMIT 1
            """
            cur.execute(sql, (child_type, child_id))
            group = cur.fetchone()

            if not group:
                return False, None, None, severity

            group_id = group["group_id"]
            p_type = group["parent_entity_type"]
            p_id = group["parent_entity_id"]
            strategy = group["suppression_strategy"]

            # Verificar si el padre tiene una alerta activa (firing)
            cur.execute(
                """
                SELECT id, severity FROM alerts 
                WHERE entity_type = %s AND entity_id = %s AND status = 'firing'
                ORDER BY id DESC LIMIT 1
                """,
                (p_type, p_id)
            )
            parent_alert = cur.fetchone()

            if not parent_alert:
                return False, group_id, None, severity

            parent_alert_id = parent_alert["id"]

            if strategy == "suppress_if_parent_down" or strategy == "suppress_all":
                logger.info(
                    f"🔗 Correlación aplicada: Suprimiendo alerta de hijo ({child_type} #{child_id}) "
                    f"porque el padre ({p_type} #{p_id}) tiene alerta activa #{parent_alert_id}."
                )
                return True, group_id, parent_alert_id, severity

            elif strategy == "reduce_severity":
                severity_map = {
                    "emergency": "critical",
                    "critical": "warning",
                    "warning": "info",
                    "info": "info"
                }
                new_sev = severity_map.get(severity, "info")
                logger.info(
                    f"🔗 Correlación aplicada: Reduciendo severidad de {severity} a {new_sev} para "
                    f"hijo ({child_type} #{child_id}) por alerta activa en padre #{parent_alert_id}."
                )
                return False, group_id, parent_alert_id, new_sev

        return False, None, None, severity

    # --------------------------------------------------------------------------
    # 4. Creación y disparo de alertas
    # --------------------------------------------------------------------------
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
        message: Optional[str] = None
    ) -> Optional[int]:
        """
        Crea o actualiza una alerta en la tabla alerts respetando
        mantenimiento, tormentas y correlación jerárquica.
        """
        self._ensure_db()
        fingerprint = compute_fingerprint(entity_type, entity_id, condition_type)

        # 1. Verificar si ya existe una alerta activa para este elemento y condición
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

            # 2. Verificar ventana de mantenimiento
            in_maintenance = self.is_in_maintenance_window(entity_type, entity_id, severity)

            # 3. Verificar tormenta de alertas
            is_storm = self.check_storm_suppression(
                fingerprint,
                max_per_hour=rule.get("max_alerts_per_hour", 5),
                cooldown_minutes=rule.get("cooldown_minutes", 30)
            )

            # 4. Verificar correlación topológica
            is_corr_suppressed, group_id, parent_alert_id, final_severity = self.apply_correlation(
                entity_type, entity_id, severity
            )

            # Determinar estado
            if in_maintenance or is_storm or is_corr_suppressed:
                initial_status = "suppressed"
            else:
                initial_status = "firing"

            now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

            if existing:
                alert_id = existing["id"]
                # Si ya estaba reconocida o firing, actualizar datos de disparo si corresponde
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
                        alert_id
                    )
                )
                return alert_id

            # Insertar nueva alerta
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
                    now
                )
            )
            new_id = cur.lastrowid
            logger.info(
                f"🚨 Alerta #{new_id} DISPARADA: [{final_severity.upper()}] {entity_name} ({entity_type}) - "
                f"{condition_type} - Status: {initial_status}"
            )
            return new_id

    # --------------------------------------------------------------------------
    # 5. Auto-resolución de alertas
    # --------------------------------------------------------------------------
    def auto_resolve_cleared_alerts(self, active_keys_still_firing: set):
        """
        Auto-resuelve alertas activas cuya condición ya no se cumple, si la regla lo permite.
        active_keys_still_firing: set de tuplas (entity_type, entity_id, condition_type)
        """
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
                    # La condición ya se normalizó!
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

    # --------------------------------------------------------------------------
    # 6. Evaluación global de reglas
    # --------------------------------------------------------------------------
    def evaluate_all_rules(self):
        """Evalúa todas las reglas activas contra el estado actual de la infraestructura."""
        self._ensure_db()
        logger.info("⚡ Iniciando ciclo de evaluación de reglas de alertas...")
        firing_keys = set()

        with self.db.cursor() as cur:
            cur.execute("SELECT * FROM alert_rules WHERE is_active = 1")
            rules = cur.fetchall()

        for rule in rules:
            e_type = rule["entity_type"]
            c_type = rule["condition_type"]
            threshold = float(rule["threshold_value"]) if rule["threshold_value"] is not None else None
            comparison = rule["comparison"]
            severity = rule["severity"]

            # Reglas para Servicios
            if e_type == "service":
                self._eval_services(rule, c_type, threshold, comparison, severity, firing_keys)

            # Reglas para Sedes WAN
            elif e_type == "site":
                self._eval_sites(rule, c_type, threshold, comparison, severity, firing_keys)

            # Reglas para Certificados SSL/TLS
            elif e_type == "ssl_certificate":
                self._eval_ssl(rule, c_type, threshold, comparison, severity, firing_keys)

            # Reglas para Dispositivos SNMP
            elif e_type == "snmp_device":
                self._eval_snmp_devices(rule, c_type, threshold, comparison, severity, firing_keys)

            # Reglas para Interfaces
            elif e_type == "interface":
                self._eval_interfaces(rule, c_type, severity, firing_keys)

            # Reglas para Auto-Discovery
            elif e_type == "discovered_device":
                self._eval_discovered(rule, c_type, threshold, comparison, severity, firing_keys)

        # Auto-resolver las que ya no están disparadas
        self.auto_resolve_cleared_alerts(firing_keys)
        logger.info(f"✨ Evaluación completada. Incidentes activos detectados: {len(firing_keys)}")

    def _eval_services(self, rule, c_type, threshold, comparison, severity, firing_keys):
        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT s.id, s.name, s.web_url, s.is_active,
                       h.is_up, h.latency_ms, h.status_message
                FROM monitored_services s
                LEFT JOIN (
                    SELECT monitored_service_id, is_up, latency_ms, status_message
                    FROM service_check_histories
                    WHERE id IN (SELECT MAX(id) FROM service_check_histories GROUP BY monitored_service_id)
                ) h ON s.id = h.monitored_service_id
                WHERE s.is_active = 1
                """
            )
            services = cur.fetchall()

        for s in services:
            sid = s["id"]
            sname = s["name"]
            is_up = s.get("is_up")
            lat = float(s.get("latency_ms") or 0.0)

            if c_type == "is_down":
                if is_up is not None and is_up == 0:
                    firing_keys.add(("service", sid, c_type))
                    self.trigger_alert(
                        rule, "service", sid, sname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"El servicio {sname} ({s['web_url'] or 'N/A'}) no responde o se encuentra en estado DOWN."
                    )

            elif c_type == "latency_high" and threshold is not None:
                if is_up == 1 and lat > threshold:
                    firing_keys.add(("service", sid, c_type))
                    self.trigger_alert(
                        rule, "service", sid, sname, c_type, severity,
                        value_at_trigger=lat,
                        threshold_value=threshold,
                        message=f"La latencia de {sname} es elevada ({lat:.1f}ms > {threshold}ms)."
                    )

    def _eval_sites(self, rule, c_type, threshold, comparison, severity, firing_keys):
        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT s.id, s.name, s.ip, s.is_active,
                       h.is_up, h.latency_ms, h.status_message, h.devices_online, h.devices_total
                FROM monitored_sites s
                LEFT JOIN (
                    SELECT monitored_site_id, is_up, latency_ms, status_message, devices_online, devices_total
                    FROM site_check_histories
                    WHERE id IN (SELECT MAX(id) FROM site_check_histories GROUP BY monitored_site_id)
                ) h ON s.id = h.monitored_site_id
                WHERE s.is_active = 1
                """
            )
            sites = cur.fetchall()

        for st in sites:
            sid = st["id"]
            sname = st["name"]
            is_up = st.get("is_up")
            lat = float(st.get("latency_ms") or 0.0)

            if c_type == "is_down":
                if is_up is not None and is_up == 0:
                    firing_keys.add(("site", sid, c_type))
                    self.trigger_alert(
                        rule, "site", sid, sname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"La sede WAN {sname} ({st['ip'] or 'N/A'}) no responde o enlace caído."
                    )

            elif c_type == "latency_high" and threshold is not None:
                if is_up == 1 and lat > threshold:
                    firing_keys.add(("site", sid, c_type))
                    self.trigger_alert(
                        rule, "site", sid, sname, c_type, severity,
                        value_at_trigger=lat,
                        threshold_value=threshold,
                        message=f"Latencia WAN elevada hacia {sname} ({lat:.1f}ms > {threshold}ms)."
                    )

    def _eval_ssl(self, rule, c_type, threshold, comparison, severity, firing_keys):
        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT id, domain, days_remaining, valid_to, is_active
                FROM ssl_certificates
                WHERE is_active = 1
                """
            )
            certs = cur.fetchall()

        now = datetime.now()
        for c in certs:
            cid = c["id"]
            domain = c["domain"]
            days = c["days_remaining"]
            valid_to = c["valid_to"]

            if c_type == "cert_expired":
                if (valid_to and valid_to < now) or (days is not None and days <= 0):
                    firing_keys.add(("ssl_certificate", cid, c_type))
                    self.trigger_alert(
                        rule, "ssl_certificate", cid, domain, c_type, severity,
                        value_at_trigger=float(days or 0),
                        message=f"El certificado SSL para {domain} ha EXPIRADO."
                    )

            elif c_type == "cert_expiring" and threshold is not None:
                if days is not None and 0 < days <= threshold:
                    firing_keys.add(("ssl_certificate", cid, c_type))
                    self.trigger_alert(
                        rule, "ssl_certificate", cid, domain, c_type, severity,
                        value_at_trigger=float(days),
                        threshold_value=threshold,
                        message=f"El certificado SSL para {domain} vence en {days} días (umbral: {int(threshold)}d)."
                    )

    def _eval_snmp_devices(self, rule, c_type, threshold, comparison, severity, firing_keys):
        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT id, name, ip_address, last_poll_status, consecutive_failures
                FROM snmp_devices
                WHERE is_active = 1
                """
            )
            devices = cur.fetchall()

        for d in devices:
            did = d["id"]
            dname = d["name"] or d["ip_address"]
            poll_status = d["last_poll_status"] or "unknown"

            if c_type == "snmp_unreachable":
                if poll_status in ("timeout", "auth_error", "error") or (d.get("consecutive_failures") or 0) >= 3:
                    firing_keys.add(("snmp_device", did, c_type))
                    self.trigger_alert(
                        rule, "snmp_device", did, dname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"Dispositivo SNMP {dname} inalcanzable (estado: {poll_status})."
                    )

            elif c_type == "cpu_high" and threshold is not None:
                # Consultar métrica de CPU más reciente
                with self.db.cursor() as cur:
                    cur.execute(
                        """
                        SELECT m.metric_value
                        FROM snmp_metrics_history m
                        JOIN snmp_oids o ON m.snmp_oid_id = o.id
                        WHERE m.snmp_device_id = %s AND (o.name LIKE '%%cpu%%' OR o.name LIKE '%%CPU%%')
                        ORDER BY m.id DESC LIMIT 1
                        """,
                        (did,)
                    )
                    cpu_row = cur.fetchone()
                    if cpu_row and cpu_row["metric_value"] is not None:
                        cpu_val = float(cpu_row["metric_value"])
                        if cpu_val > threshold:
                            firing_keys.add(("snmp_device", did, c_type))
                            self.trigger_alert(
                                rule, "snmp_device", did, dname, c_type, severity,
                                value_at_trigger=cpu_val,
                                threshold_value=threshold,
                                message=f"Uso de CPU elevado en {dname}: {cpu_val:.1f}% (> {threshold}%)."
                            )

            elif c_type == "temperature_high" and threshold is not None:
                with self.db.cursor() as cur:
                    cur.execute(
                        """
                        SELECT m.metric_value
                        FROM snmp_metrics_history m
                        JOIN snmp_oids o ON m.snmp_oid_id = o.id
                        WHERE m.snmp_device_id = %s AND (o.name LIKE '%%temp%%' OR o.name LIKE '%%Temp%%')
                        ORDER BY m.id DESC LIMIT 1
                        """,
                        (did,)
                    )
                    temp_row = cur.fetchone()
                    if temp_row and temp_row["metric_value"] is not None:
                        temp_val = float(temp_row["metric_value"])
                        if temp_val > threshold:
                            firing_keys.add(("snmp_device", did, c_type))
                            self.trigger_alert(
                                rule, "snmp_device", did, dname, c_type, severity,
                                value_at_trigger=temp_val,
                                threshold_value=threshold,
                                message=f"Temperatura de chasis elevada en {dname}: {temp_val:.1f}°C (> {threshold}°C)."
                            )

    def _eval_interfaces(self, rule, c_type, severity, firing_keys):
        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT i.id, i.if_name, i.if_oper_status, d.id as device_id, d.name as device_name
                FROM snmp_interfaces i
                JOIN snmp_devices d ON i.snmp_device_id = d.id
                WHERE i.is_monitored = 1 AND d.is_active = 1
                """
            )
            interfaces = cur.fetchall()

        for iface in interfaces:
            iid = iface["id"]
            iname = f"{iface['device_name']} - {iface['if_name'] or 'if#'+str(iid)}"
            oper = str(iface["if_oper_status"] or "").lower()

            if c_type == "interface_down":
                if oper in ("down", "2", "lowerlayerdown"):
                    firing_keys.add(("interface", iid, c_type))
                    self.trigger_alert(
                        rule, "interface", iid, iname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"La interfaz de red {iname} se encuentra CAÍDA (operState: down)."
                    )

    def _eval_discovered(self, rule, c_type, threshold, comparison, severity, firing_keys):
        with self.db.cursor() as cur:
            if c_type == "new_device":
                cur.execute(
                    """
                    SELECT id, ip_address, mac_address, hostname, vendor
                    FROM discovered_devices
                    WHERE classification_status = 'rogue'
                      AND first_seen >= NOW() - INTERVAL 1 HOUR
                    """
                )
                rogues = cur.fetchall()
                for r in rogues:
                    rid = r["id"]
                    rname = f"{r['ip_address']} ({r['mac_address'] or 'Sin MAC'})"
                    firing_keys.add(("discovered_device", rid, c_type))
                    self.trigger_alert(
                        rule, "discovered_device", rid, rname, c_type, severity,
                        message=f"Dispositivo no autorizado detectado en red: {rname} ({r.get('vendor') or 'Genérico'})."
                    )

            elif c_type == "device_disappeared":
                cur.execute(
                    """
                    SELECT id, ip_address, mac_address, hostname, vendor, last_seen
                    FROM discovered_devices
                    WHERE is_authorized = 1
                      AND last_seen < NOW() - INTERVAL 1 HOUR
                    """
                )
                disappeared = cur.fetchall()
                for d in disappeared:
                    did = d["id"]
                    dname = f"{d['ip_address']} ({d['mac_address'] or 'Sin MAC'})"
                    firing_keys.add(("discovered_device", did, c_type))
                    self.trigger_alert(
                        rule, "discovered_device", did, dname, c_type, severity,
                        message=f"Dispositivo autorizado desaparecido de la red por más de 1 hora: {dname}."
                    )

    # --------------------------------------------------------------------------
    # 7. Motor de Escalación Jerárquica y Despacho Seguro
    # --------------------------------------------------------------------------
    async def process_escalations(self):
        """
        Evalúa alertas activas y despacha notificaciones según alert_escalation_levels.
        REGLA DE ORO #1: Estrictamente prohíbe el envío a grupos o IDs negativos.
        """
        self._ensure_db()
        from monitor.telegram_dispatcher import TelegramDispatcher

        dispatcher = TelegramDispatcher()
        now = datetime.now()
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")

        with self.db.cursor() as cur:
            # Buscar alertas firing o acknowledged que no estén suprimidas por correlación
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

                # Obtener niveles de escalación para la regla
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

                    # ¿Es momento de disparar este nivel?
                    if elapsed_minutes < delay_min:
                        continue

                    # Verificar si este nivel ya fue notificado
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

                    # Determinar destinatario
                    # REGLA DE ORO #1: Chat ID estricto privado del Administrador
                    target_chat = "38914901"
                    if lvl.get("target_external"):
                        target_chat = str(lvl["target_external"]).strip()

                    # Validación de seguridad de Golden Rule #1
                    try:
                        cid = int(target_chat)
                        if cid < 0:
                            logger.error(f"🚨 REGLA DE ORO #1: Intento bloqueado de despachar a grupo ({cid}). Redirigiendo a Owner 38914901.")
                            target_chat = "38914901"
                    except ValueError:
                        target_chat = "38914901"

                    # Formatear mensaje corporativo profesional y teclado interactivo
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

                    # Extraer URL o dato clave de enlace si aplica
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

                    # Limpiar URL redundante de detail si ya está en url_line
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

                    portal_url = "http://10.20.23.221/admin/alerts"
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

                    # Despachar vía Telegram
                    success, resp_info = await dispatcher.send_text(
                        target_chat,
                        msg_text,
                        parse_mode="HTML",
                        reply_markup=reply_markup
                    )

                    # Registrar en alert_notifications
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

    # --------------------------------------------------------------------------
    # 8. Comandos de Gestión Directa (ACK, Resolve, Silence)
    # --------------------------------------------------------------------------
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
            # Si no existe en la tabla users (ej: Telegram ID como 38914901), resolver al primer admin
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


def format_table(rows: List[Dict[str, Any]]) -> str:
    """Genera tabla ANSI formateada para consola."""
    if not rows:
        return "No hay alertas activas en este momento."

    headers = ["ID", "Severidad", "Estado", "Entidad", "Condición", "Nivel", "Disparada"]
    col_w = [6, 12, 14, 25, 20, 7, 19]

    sep = "+" + "+".join("-" * (w + 2) for w in col_w) + "+"
    hdr = "| " + " | ".join(h.ljust(col_w[i]) for i, h in enumerate(headers)) + " |"

    lines = [sep, hdr, sep]
    for r in rows:
        fired_str = r["fired_at"].strftime("%Y-%m-%d %H:%M") if isinstance(r["fired_at"], datetime) else str(r["fired_at"] or "")
        vals = [
            str(r["id"]),
            r["severity"].upper(),
            r["status"].upper(),
            (r["entity_name"] or "")[:25],
            (r["condition_type"] or "")[:20],
            str(r["current_escalation_level"]),
            fired_str
        ]
        line = "| " + " | ".join(vals[i].ljust(col_w[i]) for i in range(len(headers))) + " |"
        lines.append(line)
    lines.append(sep)
    return "\n".join(lines)


async def main_cli():
    parser = argparse.ArgumentParser(description="Motor de Alertas y Correlación")
    parser.add_argument("--evaluate", action="store_true", help="Ejecutar ciclo de evaluación de reglas")
    parser.add_argument("--escalate", action="store_true", help="Procesar cola de escalación y avisos")
    parser.add_argument("--list", action="store_true", help="Listar alertas activas")
    parser.add_argument("--ack", type=int, help="Reconocer alerta por ID")
    parser.add_argument("--resolve", type=int, help="Resolver manualmente alerta por ID")
    parser.add_argument("--silence", type=int, help="Silenciar alerta por ID")
    parser.add_argument("--minutes", type=int, default=60, help="Minutos para silenciar (default: 60)")
    parser.add_argument("--notes", type=str, default="Acción ejecutada vía CLI", help="Notas de auditoría")
    args = parser.parse_args()

    engine = AlertEngine()

    if args.evaluate:
        engine.evaluate_all_rules()

    if args.escalate:
        await engine.process_escalations()

    if args.ack:
        ok = engine.acknowledge_alert(args.ack, user_id=1, notes=args.notes)
        print(f"[{'OK' if ok else 'ERROR'}] Alerta #{args.ack} {'reconocida' if ok else 'no encontrada o ya procesada'}.")

    if args.resolve:
        ok = engine.resolve_alert(args.resolve, notes=args.notes)
        print(f"[{'OK' if ok else 'ERROR'}] Alerta #{args.resolve} {'resuelta' if ok else 'no encontrada o ya cerrada'}.")

    if args.silence:
        ok = engine.silence_alert(args.silence, minutes=args.minutes, user_id=1)
        print(f"[{'OK' if ok else 'ERROR'}] Alerta #{args.silence} silenciada por {args.minutes} min.")

    if args.list or (not args.evaluate and not args.escalate and not args.ack and not args.resolve and not args.silence):
        engine._ensure_db()
        with engine.db.cursor() as cur:
            cur.execute(
                """
                SELECT id, severity, status, entity_name, condition_type, current_escalation_level, fired_at
                FROM alerts
                WHERE status IN ('firing', 'acknowledged', 'suppressed')
                ORDER BY FIELD(severity, 'emergency', 'critical', 'warning', 'info'), fired_at DESC
                """
            )
            rows = cur.fetchall()
            print("\n🚨 ALERTAS ACTIVAS DEL SISTEMA:")
            print(format_table(rows))
            print(f"Total activas: {len(rows)}\n")


if __name__ == "__main__":
    asyncio.run(main_cli())
