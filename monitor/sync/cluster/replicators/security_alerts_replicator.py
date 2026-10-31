"""
# ==============================================================================
# 🛡️ REPLICADOR DE SEGURIDAD Y ALERTAS: security_alerts_replicator.py
# Sincroniza certificados SSL, reglas de alerta, alarmas y respaldos de configuración WAN/LAN.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicators/security_alerts_replicator.py
# ==============================================================================
"""
import json
from typing import Any, Dict

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt


class SecurityAlertsReplicator(TableReplicatorProtocol):
    """Replicador atómico para certificados SSL, reglas de alerta, ventanas de mantenimiento y respaldos."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        cfg = data.get("config", {})
        total_synced = 0

        # 1. Certificados SSL
        ssl_certs = cfg.get("ssl_certificates", [])
        if ssl_certs:
            master_cert_map = {sc["domain"]: sc["id"] for sc in ssl_certs if "id" in sc and "domain" in sc}
            cursor.execute("SELECT id, domain FROM ssl_certificates")
            local_certs = cursor.fetchall()
            mismatched_ids = [
                lc["id"] for lc in local_certs 
                if lc["domain"] in master_cert_map and lc["id"] != master_cert_map[lc["domain"]]
            ]
            if mismatched_ids:
                format_strings = ','.join(['%s'] * len(mismatched_ids))
                cursor.execute(f"DELETE FROM ssl_certificate_history WHERE ssl_certificate_id IN ({format_strings})", tuple(mismatched_ids))
                cursor.execute(f"DELETE FROM ssl_certificates WHERE id IN ({format_strings})", tuple(mismatched_ids))

            ssl_sql = """
                INSERT INTO ssl_certificates
                (id, service_id, domain, port, subject_cn, subject_org, subject_ou, subject_country,
                 subject_state, subject_locality, issuer_cn, issuer_org, issuer_country, serial_number,
                 signature_algorithm, public_key_algorithm, public_key_bits, version, valid_from, valid_to,
                 days_remaining, is_self_signed, is_wildcard, is_ev, san_entries, fingerprint_sha256,
                 fingerprint_sha1, alert_threshold_warning, alert_threshold_critical, last_checked_at,
                 last_check_status, consecutive_errors, renewal_count, notes, is_active, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                service_id=VALUES(service_id), domain=VALUES(domain), port=VALUES(port),
                subject_cn=VALUES(subject_cn), subject_org=VALUES(subject_org), issuer_cn=VALUES(issuer_cn),
                issuer_org=VALUES(issuer_org), serial_number=VALUES(serial_number),
                signature_algorithm=VALUES(signature_algorithm), public_key_algorithm=VALUES(public_key_algorithm),
                public_key_bits=VALUES(public_key_bits), version=VALUES(version),
                valid_from=VALUES(valid_from), valid_to=VALUES(valid_to), days_remaining=VALUES(days_remaining),
                is_self_signed=VALUES(is_self_signed), is_wildcard=VALUES(is_wildcard), is_ev=VALUES(is_ev),
                san_entries=VALUES(san_entries), fingerprint_sha256=VALUES(fingerprint_sha256),
                fingerprint_sha1=VALUES(fingerprint_sha1), last_checked_at=VALUES(last_checked_at),
                last_check_status=VALUES(last_check_status), consecutive_errors=VALUES(consecutive_errors),
                renewal_count=VALUES(renewal_count), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """
            ssl_records = []
            for sc in ssl_certs:
                sans = sc.get("san_entries")
                if isinstance(sans, (dict, list)):
                    sans = json.dumps(sans)
                ssl_records.append((
                    sc["id"], sc.get("service_id"), sc["domain"], sc.get("port", 443),
                    sc.get("subject_cn"), sc.get("subject_org"), sc.get("subject_ou"), sc.get("subject_country"),
                    sc.get("subject_state"), sc.get("subject_locality"), sc.get("issuer_cn"), sc.get("issuer_org"),
                    sc.get("issuer_country"), sc.get("serial_number"), sc.get("signature_algorithm"),
                    sc.get("public_key_algorithm"), sc.get("public_key_bits"), sc.get("version"),
                    clean_mysql_dt(sc.get("valid_from")), clean_mysql_dt(sc.get("valid_to")), sc.get("days_remaining", 0),
                    1 if sc.get("is_self_signed") else 0, 1 if sc.get("is_wildcard") else 0,
                    1 if sc.get("is_ev") else 0, sans, sc.get("fingerprint_sha256"),
                    sc.get("fingerprint_sha1"), sc.get("alert_threshold_warning", 30),
                    sc.get("alert_threshold_critical", 7), clean_mysql_dt(sc.get("last_checked_at")),
                    sc.get("last_check_status"), sc.get("consecutive_errors", 0),
                    sc.get("renewal_count", 0), sc.get("notes"), 1 if sc.get("is_active", True) else 0,
                    clean_mysql_dt(sc.get("created_at")), clean_mysql_dt(sc.get("updated_at") or sc.get("created_at"))
                ))
            if ssl_records:
                cursor.executemany(ssl_sql, ssl_records)
                total_synced += len(ssl_records)

        ssl_hist = data.get("ssl_certificate_history", [])
        if ssl_hist:
            ssl_h_sql = """
                INSERT IGNORE INTO ssl_certificate_history
                (id, ssl_certificate_id, event_type, previous_fingerprint, new_fingerprint,
                 previous_valid_to, new_valid_to, days_remaining_at_event, error_message, occurred_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            ssl_h_records = [
                (
                    sh["id"], sh["ssl_certificate_id"], sh["event_type"], sh.get("previous_fingerprint"),
                    sh.get("new_fingerprint"), clean_mysql_dt(sh.get("previous_valid_to")), clean_mysql_dt(sh.get("new_valid_to")),
                    sh.get("days_remaining_at_event"), sh.get("error_message"), clean_mysql_dt(sh.get("occurred_at"))
                )
                for sh in ssl_hist if "id" in sh and "ssl_certificate_id" in sh
            ]
            if ssl_h_records:
                cursor.executemany(ssl_h_sql, ssl_h_records)
                total_synced += len(ssl_h_records)

        # 2. Reglas de alerta
        alert_rules = cfg.get("alert_rules", [])
        if alert_rules:
            ar_sql = """
                INSERT INTO alert_rules
                (id, name, description, entity_type, entity_id, condition_type, threshold_value,
                 comparison, duration_seconds, severity, cooldown_minutes, max_alerts_per_hour,
                 auto_resolve, is_active, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                name=VALUES(name), description=VALUES(description), entity_type=VALUES(entity_type),
                entity_id=VALUES(entity_id), condition_type=VALUES(condition_type),
                threshold_value=VALUES(threshold_value), comparison=VALUES(comparison),
                duration_seconds=VALUES(duration_seconds), severity=VALUES(severity),
                cooldown_minutes=VALUES(cooldown_minutes), max_alerts_per_hour=VALUES(max_alerts_per_hour),
                auto_resolve=VALUES(auto_resolve), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """
            ar_records = [
                (
                    r["id"], r["name"], r.get("description"), r["entity_type"], r.get("entity_id"),
                    r["condition_type"], r.get("threshold_value"), r.get("comparison"),
                    r.get("duration_seconds", 0), r["severity"], r.get("cooldown_minutes", 30),
                    r.get("max_alerts_per_hour", 5), 1 if r.get("auto_resolve", True) else 0,
                    1 if r.get("is_active", True) else 0, clean_mysql_dt(r.get("created_at")),
                    clean_mysql_dt(r.get("updated_at") or r.get("created_at"))
                )
                for r in alert_rules if "id" in r and "name" in r
            ]
            if ar_records:
                cursor.executemany(ar_sql, ar_records)
                total_synced += len(ar_records)

        # 3. Ventanas de mantenimiento
        maints = cfg.get("maintenance_windows", [])
        if maints:
            mw_sql = """
                INSERT INTO maintenance_windows
                (id, title, description, entity_type, entity_id, suppress_severities,
                 starts_at, ends_at, created_by, is_active, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                title=VALUES(title), description=VALUES(description), starts_at=VALUES(starts_at),
                ends_at=VALUES(ends_at), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """
            mw_records = []
            for m in maints:
                sups = m.get("suppress_severities")
                if isinstance(sups, (list, dict)):
                    sups = json.dumps(sups)
                mw_records.append((
                    m["id"], m["title"], m.get("description"), m.get("entity_type", "all"),
                    m.get("entity_id"), sups, clean_mysql_dt(m.get("starts_at")), clean_mysql_dt(m.get("ends_at")),
                    m.get("created_by"), 1 if m.get("is_active", True) else 0,
                    clean_mysql_dt(m.get("created_at")), clean_mysql_dt(m.get("updated_at") or m.get("created_at"))
                ))
            if mw_records:
                cursor.executemany(mw_sql, mw_records)
                total_synced += len(mw_records)

        # 4. Alarmas activas
        alerts_list = data.get("alerts", [])
        if alerts_list:
            al_sql = """
                INSERT INTO alerts
                (id, alert_rule_id, entity_type, entity_id, entity_name, condition_type,
                 severity, status, current_escalation_level, value_at_trigger, threshold_value,
                 message, correlation_group_id, is_correlated_suppressed, parent_alert_id,
                 fired_at, acknowledged_at, resolved_at, acknowledged_by, resolved_by,
                 last_notified_at, notification_count, duration_seconds, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                severity=VALUES(severity), status=VALUES(status),
                current_escalation_level=VALUES(current_escalation_level),
                value_at_trigger=VALUES(value_at_trigger), message=VALUES(message),
                acknowledged_at=VALUES(acknowledged_at), resolved_at=VALUES(resolved_at),
                duration_seconds=VALUES(duration_seconds), notes=VALUES(notes),
                updated_at=VALUES(updated_at)
            """
            al_records = [
                (
                    a["id"], a.get("alert_rule_id"), a["entity_type"], a["entity_id"],
                    a.get("entity_name"), a["condition_type"], a["severity"], a.get("status", "firing"),
                    a.get("current_escalation_level", 1), a.get("value_at_trigger"),
                    a.get("threshold_value"), a.get("message"), a.get("correlation_group_id"),
                    1 if a.get("is_correlated_suppressed") else 0, a.get("parent_alert_id"),
                    clean_mysql_dt(a.get("fired_at")), clean_mysql_dt(a.get("acknowledged_at")), clean_mysql_dt(a.get("resolved_at")),
                    a.get("acknowledged_by"), a.get("resolved_by"), clean_mysql_dt(a.get("last_notified_at")),
                    a.get("notification_count", 0), a.get("duration_seconds", 0),
                    a.get("notes"), clean_mysql_dt(a.get("created_at")), clean_mysql_dt(a.get("updated_at") or a.get("created_at"))
                )
                for a in alerts_list if "id" in a and "entity_type" in a
            ]
            if al_records:
                cursor.executemany(al_sql, al_records)
                total_synced += len(al_records)

        # 5. Respaldos de configuración de red
        configs_list = data.get("device_configurations", [])
        if configs_list:
            cfg_sql = """
                INSERT INTO device_configurations
                (id, network_device_id, snmp_device_id, device_name, device_ip,
                 device_type, config_text, config_hash, config_size_bytes, captured_at,
                 captured_by, status, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, '', %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                config_hash=VALUES(config_hash), config_size_bytes=VALUES(config_size_bytes),
                captured_at=VALUES(captured_at), status=VALUES(status), updated_at=VALUES(updated_at)
            """
            cfg_records = [
                (
                    c["id"], c.get("network_device_id"), c.get("snmp_device_id"),
                    c.get("device_name"), c.get("device_ip"), c.get("device_type", "other"),
                    c.get("config_hash", ""), c.get("config_size_bytes", 0),
                    clean_mysql_dt(c.get("captured_at")), c.get("captured_by", "cron"),
                    c.get("status", "success"), c.get("notes"),
                    clean_mysql_dt(c.get("created_at")), clean_mysql_dt(c.get("updated_at") or c.get("created_at"))
                )
                for c in configs_list if "id" in c
            ]
            if cfg_records:
                cursor.executemany(cfg_sql, cfg_records)
                total_synced += len(cfg_records)

        logs_list = data.get("config_change_logs", [])
        if logs_list:
            log_sql = """
                INSERT INTO config_change_logs
                (id, device_configuration_id, previous_config_id, network_device_id,
                 snmp_device_id, change_type, diff_summary, diff_unified, lines_added,
                 lines_removed, detected_at, alerted, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                diff_summary=VALUES(diff_summary), detected_at=VALUES(detected_at), updated_at=VALUES(updated_at)
            """
            log_records = [
                (
                    l["id"], l.get("device_configuration_id"), l.get("previous_config_id"),
                    l.get("network_device_id"), l.get("snmp_device_id"),
                    l.get("change_type", "modified"), l.get("diff_summary"),
                    l.get("diff_unified"), l.get("lines_added", 0),
                    l.get("lines_removed", 0), clean_mysql_dt(l.get("detected_at")),
                    1 if l.get("alerted") else 0,
                    clean_mysql_dt(l.get("created_at")), clean_mysql_dt(l.get("updated_at") or l.get("created_at"))
                )
                for l in logs_list if "id" in l
            ]
            if log_records:
                cursor.executemany(log_sql, log_records)
                total_synced += len(log_records)

        return total_synced
