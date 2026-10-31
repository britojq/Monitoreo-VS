"""
# ==============================================================================
# ⚡ REPLICADOR DE ENERGÍA Y UPS: ups_replicator.py
# Sincroniza dispositivos UPS, telemetría y eventos de suministro eléctrico.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicators/ups_replicator.py
# ==============================================================================
"""
from typing import Any, Dict

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt


class UpsReplicator(TableReplicatorProtocol):
    """Replicador atómico para ups_devices, ups_telemetry_histories y ups_power_events."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        cfg = data.get("config", {})
        ups_devices = cfg.get("ups_devices", [])
        total_synced = 0

        if ups_devices:
            ups_sql = """
                INSERT INTO ups_devices
                (id, name, model, serial_number, serial_port, baud_rate,
                 rating_voltage, rating_current, rating_battery_voltage, rating_frequency, firmware_version,
                 input_voltage, input_fault_voltage, output_voltage, load_percent, frequency,
                 battery_voltage, battery_percent, temperature_c,
                 is_online, is_on_battery, is_battery_low, is_bypass, is_ups_failed, beeper_on,
                 telegram_alert_enabled, telegram_alert_target, last_alert_state, outage_since, last_seen_at,
                 created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                name=VALUES(name), model=VALUES(model), serial_number=VALUES(serial_number),
                serial_port=VALUES(serial_port), baud_rate=VALUES(baud_rate),
                rating_voltage=VALUES(rating_voltage), rating_current=VALUES(rating_current),
                rating_battery_voltage=VALUES(rating_battery_voltage), rating_frequency=VALUES(rating_frequency),
                firmware_version=VALUES(firmware_version), input_voltage=VALUES(input_voltage),
                input_fault_voltage=VALUES(input_fault_voltage), output_voltage=VALUES(output_voltage),
                load_percent=VALUES(load_percent), frequency=VALUES(frequency),
                battery_voltage=VALUES(battery_voltage), battery_percent=VALUES(battery_percent),
                temperature_c=VALUES(temperature_c), is_online=VALUES(is_online),
                is_on_battery=VALUES(is_on_battery), is_battery_low=VALUES(is_battery_low),
                is_bypass=VALUES(is_bypass), is_ups_failed=VALUES(is_ups_failed),
                beeper_on=VALUES(beeper_on), telegram_alert_enabled=VALUES(telegram_alert_enabled),
                telegram_alert_target=VALUES(telegram_alert_target), last_alert_state=VALUES(last_alert_state),
                outage_since=VALUES(outage_since), last_seen_at=VALUES(last_seen_at), updated_at=VALUES(updated_at)
            """
            ups_records = [
                (
                    u["id"], u.get("name", "UPS ZTG LV6KL"), u.get("model", "ZTG LV6KL 6kVA"),
                    u.get("serial_number"), u.get("serial_port", "/dev/ttyS0"), u.get("baud_rate", 2400),
                    u.get("rating_voltage", 208.0), u.get("rating_current", 28.0),
                    u.get("rating_battery_voltage", 192.0), u.get("rating_frequency", 60.0),
                    u.get("firmware_version", "R1.01.55"), u.get("input_voltage"),
                    u.get("input_fault_voltage"), u.get("output_voltage"),
                    u.get("load_percent"), u.get("frequency"),
                    u.get("battery_voltage"), u.get("battery_percent"), u.get("temperature_c"),
                    1 if u.get("is_online") else 0, 1 if u.get("is_on_battery") else 0,
                    1 if u.get("is_battery_low") else 0, 1 if u.get("is_bypass") else 0,
                    1 if u.get("is_ups_failed") else 0, 1 if u.get("beeper_on") else 0,
                    1 if u.get("telegram_alert_enabled") else 0, u.get("telegram_alert_target", "owner"),
                    u.get("last_alert_state"), clean_mysql_dt(u.get("outage_since")),
                    clean_mysql_dt(u.get("last_seen_at")), clean_mysql_dt(u.get("created_at")),
                    clean_mysql_dt(u.get("updated_at") or u.get("created_at"))
                )
                for u in ups_devices if "id" in u
            ]
            if ups_records:
                cursor.executemany(ups_sql, ups_records)
                total_synced += len(ups_records)

        ups_history = data.get("ups_telemetry_histories", [])
        if ups_history:
            ups_h_sql = """
                INSERT IGNORE INTO ups_telemetry_histories
                (id, ups_device_id, input_voltage, output_voltage, load_percent,
                 battery_percent, battery_voltage, temperature_c,
                 is_on_battery, is_battery_low, is_bypass, recorded_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            ups_h_records = [
                (
                    uh["id"], uh["ups_device_id"], uh.get("input_voltage"), uh.get("output_voltage"),
                    uh.get("load_percent"), uh.get("battery_percent"), uh.get("battery_voltage"),
                    uh.get("temperature_c"), 1 if uh.get("is_on_battery") else 0,
                    1 if uh.get("is_battery_low") else 0, 1 if uh.get("is_bypass") else 0,
                    clean_mysql_dt(uh.get("recorded_at"))
                )
                for uh in ups_history if "id" in uh and "ups_device_id" in uh
            ]
            if ups_h_records:
                cursor.executemany(ups_h_sql, ups_h_records)
                total_synced += len(ups_h_records)

        ups_p_events = data.get("ups_power_events", [])
        if ups_p_events:
            ups_pe_sql = """
                INSERT INTO ups_power_events
                (id, ups_device_id, event_type, started_at, ended_at, duration_seconds,
                 input_voltage, status, notified_group, notified_target, description, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                ended_at=VALUES(ended_at), duration_seconds=VALUES(duration_seconds),
                event_type=VALUES(event_type), status=VALUES(status),
                description=VALUES(description), updated_at=VALUES(updated_at)
            """
            ups_pe_records = [
                (
                    pe["id"], pe.get("ups_device_id", 1), pe.get("event_type", "fluctuation"),
                    clean_mysql_dt(pe.get("started_at")), clean_mysql_dt(pe.get("ended_at")),
                    pe.get("duration_seconds", 0), pe.get("input_voltage"),
                    pe.get("status", "resolved"), 1 if pe.get("notified_group") else 0,
                    pe.get("notified_target", "owner"), pe.get("description"),
                    clean_mysql_dt(pe.get("created_at")),
                    clean_mysql_dt(pe.get("updated_at") or pe.get("created_at"))
                )
                for pe in ups_p_events if "id" in pe and "started_at" in pe
            ]
            if ups_pe_records:
                cursor.executemany(ups_pe_sql, ups_pe_records)
                total_synced += len(ups_pe_records)

        return total_synced
