"""
# ==============================================================================
# 🌐 REPLICADOR DE TELEMETRÍA Y TOPOLOGÍA: telemetry_topology_replicator.py
# Sincroniza NetRadar, Topología de Red, Syslog, NetFlow, WoL y Ciclo de Vida.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicators/telemetry_topology_replicator.py
# ==============================================================================
"""
import json
from typing import Any, Dict

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt


class TelemetryTopologyReplicator(TableReplicatorProtocol):
    """Replicador atómico para NetRadar, Netflow, Syslog, Traps, Topología, WOL y Hardware Lifecycle."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        total_synced = 0

        # 1. Hosts de NetRadar
        nr_hosts = data.get("net_radar_hosts", [])
        if nr_hosts:
            nr_host_sql = """
                INSERT INTO net_radar_hosts
                (id, ip, mac, hostname, vendor, os_detected, bytes_in, bytes_out, total_bytes, packet_count,
                 is_local, update_status, last_update_type, last_update_target, update_bytes,
                 last_update_at, first_seen_at, last_seen_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                mac=VALUES(mac), hostname=VALUES(hostname), vendor=VALUES(vendor),
                os_detected=VALUES(os_detected), bytes_in=VALUES(bytes_in), bytes_out=VALUES(bytes_out),
                total_bytes=VALUES(total_bytes), packet_count=VALUES(packet_count),
                is_local=VALUES(is_local), update_status=VALUES(update_status),
                last_update_type=VALUES(last_update_type), last_update_target=VALUES(last_update_target),
                update_bytes=VALUES(update_bytes), last_update_at=VALUES(last_update_at),
                last_seen_at=VALUES(last_seen_at), updated_at=VALUES(updated_at)
            """
            nr_host_records = [
                (
                    h["id"], h["ip"], h.get("mac"), h.get("hostname"), h.get("vendor"),
                    h.get("os_detected", "Desconocido"), h.get("bytes_in", 0), h.get("bytes_out", 0),
                    h.get("total_bytes", 0), h.get("packet_count", 0), 1 if h.get("is_local", True) else 0,
                    h.get("update_status", "none"), h.get("last_update_type"), h.get("last_update_target"),
                    h.get("update_bytes", 0), clean_mysql_dt(h.get("last_update_at")), clean_mysql_dt(h.get("first_seen_at")),
                    clean_mysql_dt(h.get("last_seen_at")), clean_mysql_dt(h.get("created_at")), clean_mysql_dt(h.get("updated_at") or h.get("created_at"))
                )
                for h in nr_hosts if "id" in h and "ip" in h
            ]
            if nr_host_records:
                cursor.executemany(nr_host_sql, nr_host_records)
                total_synced += len(nr_host_records)

        # 2. Eventos NetRadar
        nr_events = data.get("net_radar_events", [])
        if nr_events:
            nr_ev_sql = """
                INSERT IGNORE INTO net_radar_events
                (id, host_ip, event_type, severity, target_domain, bytes_transferred, description, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """
            nr_ev_records = [
                (
                    e["id"], e["host_ip"], e["event_type"], e.get("severity", "info"),
                    e.get("target_domain"), e.get("bytes_transferred", 0), e.get("description", ""),
                    clean_mysql_dt(e.get("created_at"))
                )
                for e in nr_events if "id" in e and "host_ip" in e
            ]
            if nr_ev_records:
                cursor.executemany(nr_ev_sql, nr_ev_records)
                total_synced += len(nr_ev_records)

        # 3. Snapshots NetRadar
        nr_snaps = data.get("net_radar_snapshots", [])
        if nr_snaps:
            nr_snap_sql = """
                INSERT IGNORE INTO net_radar_snapshots
                (id, total_hosts, active_hosts, windows_updating_hosts, linux_updating_hosts,
                 total_bytes_in, total_bytes_out, top_protocols, top_talkers, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            nr_snap_records = []
            for s in nr_snaps:
                if "id" in s:
                    top_p = s.get("top_protocols")
                    if isinstance(top_p, (dict, list)):
                        top_p = json.dumps(top_p)
                    top_t = s.get("top_talkers")
                    if isinstance(top_t, (dict, list)):
                        top_t = json.dumps(top_t)
                    nr_snap_records.append((
                        s["id"], s.get("total_hosts", 0), s.get("active_hosts", 0),
                        s.get("windows_updating_hosts", 0), s.get("linux_updating_hosts", 0),
                        s.get("total_bytes_in", 0), s.get("total_bytes_out", 0),
                        top_p, top_t, clean_mysql_dt(s.get("created_at"))
                    ))
            if nr_snap_records:
                cursor.executemany(nr_snap_sql, nr_snap_records)
                total_synced += len(nr_snap_records)

        # 4. Trampas SNMP Recibidas
        snmp_traps = data.get("snmp_traps_received", [])
        if snmp_traps:
            trap_sql = """
                INSERT IGNORE INTO snmp_traps_received
                (id, snmp_device_id, source_ip, trap_oid, trap_type, varbinds, severity, processed, alert_id, received_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            trap_records = [
                (
                    t["id"], t.get("snmp_device_id"), t["source_ip"], t["trap_oid"],
                    t.get("trap_type"), json.dumps(t.get("varbinds")) if isinstance(t.get("varbinds"), (dict, list)) else t.get("varbinds"),
                    t.get("severity", "info"), 1 if t.get("processed") else 0, t.get("alert_id"), clean_mysql_dt(t.get("received_at"))
                )
                for t in snmp_traps if "id" in t and "source_ip" in t and "trap_oid" in t
            ]
            if trap_records:
                cursor.executemany(trap_sql, trap_records)
                total_synced += len(trap_records)

        # 5. Eventos Syslog
        sys_events = data.get("syslog_events", [])
        if sys_events:
            sys_sql = """
                INSERT IGNORE INTO syslog_events
                (id, source_ip, hostname, facility, severity, program, message, raw_message, received_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            sys_records = [
                (
                    se["id"], se["source_ip"], se.get("hostname"), se.get("facility"),
                    se.get("severity"), se.get("program"), se.get("message", ""),
                    se.get("raw_message"), clean_mysql_dt(se.get("received_at"))
                )
                for se in sys_events if "id" in se and "source_ip" in se
            ]
            if sys_records:
                cursor.executemany(sys_sql, sys_records)
                total_synced += len(sys_records)

        # 6. Netflow Top Talkers
        top_talkers = data.get("netflow_top_talkers", [])
        if top_talkers:
            tt_sql = """
                INSERT IGNORE INTO netflow_top_talkers
                (id, window_start, window_end, rank_type, rank_value, bytes, packets, percentage)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """
            tt_records = [
                (
                    tt["id"], clean_mysql_dt(tt.get("window_start")), clean_mysql_dt(tt.get("window_end")),
                    tt["rank_type"], tt["rank_value"], tt.get("bytes", 0),
                    tt.get("packets", 0), tt.get("percentage")
                )
                for tt in top_talkers if "id" in tt and "rank_type" in tt and "rank_value" in tt
            ]
            if tt_records:
                cursor.executemany(tt_sql, tt_records)
                total_synced += len(tt_records)

        # 7. Enlaces de Topología de Red
        topo_links = data.get("network_topology_links", [])
        if topo_links:
            topo_sql = """
                INSERT INTO network_topology_links
                (id, source_device_id, source_interface_id, target_device_id, target_mac,
                 target_hostname, link_type, link_status, last_seen_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                link_status=VALUES(link_status), last_seen_at=VALUES(last_seen_at), updated_at=VALUES(updated_at)
            """
            topo_records = [
                (
                    tl["id"], tl["source_device_id"], tl.get("source_interface_id"),
                    tl.get("target_device_id"), tl.get("target_mac"), tl.get("target_hostname"),
                    tl.get("link_type", "manual"), tl.get("link_status", "unknown"),
                    clean_mysql_dt(tl.get("last_seen_at")), clean_mysql_dt(tl.get("created_at")),
                    clean_mysql_dt(tl.get("updated_at") or tl.get("created_at"))
                )
                for tl in topo_links if "id" in tl and "source_device_id" in tl
            ]
            if topo_records:
                cursor.executemany(topo_sql, topo_records)
                total_synced += len(topo_records)

        # 8. Anomalías Predictivas
        pred_anomalies = data.get("predictive_anomalies", [])
        if pred_anomalies:
            pa_sql = """
                INSERT INTO predictive_anomalies
                (id, entity_type, entity_id, anomaly_type, metric_name, confidence,
                 description, predicted_impact, detected_at, acknowledged)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                confidence=VALUES(confidence), description=VALUES(description),
                predicted_impact=VALUES(predicted_impact), acknowledged=VALUES(acknowledged)
            """
            pa_records = [
                (
                    pa["id"], pa["entity_type"], pa["entity_id"], pa.get("anomaly_type", "outlier"),
                    pa.get("metric_name"), pa.get("confidence"), pa.get("description"),
                    pa.get("predicted_impact"), clean_mysql_dt(pa.get("detected_at")), 1 if pa.get("acknowledged") else 0
                )
                for pa in pred_anomalies if "id" in pa and "entity_type" in pa
            ]
            if pa_records:
                cursor.executemany(pa_sql, pa_records)
                total_synced += len(pa_records)

        # 9. Dispositivos WoL
        entities = data.get("entities", {})
        wol_devs = entities.get("wol_devices", [])
        if wol_devs:
            wol_sql = """
                INSERT INTO wol_devices
                (id, name, mac_address, ip_address, broadcast_address, site_id,
                 discovered_device_id, is_enabled, last_woken_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                name=VALUES(name), ip_address=VALUES(ip_address), broadcast_address=VALUES(broadcast_address),
                is_enabled=VALUES(is_enabled), last_woken_at=VALUES(last_woken_at), updated_at=VALUES(updated_at)
            """
            wol_records = [
                (
                    w["id"], w["name"], w["mac_address"], w.get("ip_address"),
                    w.get("broadcast_address", "255.255.255.255"), w.get("site_id"),
                    w.get("discovered_device_id"), 1 if w.get("is_enabled", True) else 0,
                    clean_mysql_dt(w.get("last_woken_at")), clean_mysql_dt(w.get("created_at")),
                    clean_mysql_dt(w.get("updated_at") or w.get("created_at"))
                )
                for w in wol_devs if "id" in w and "mac_address" in w
            ]
            if wol_records:
                cursor.executemany(wol_sql, wol_records)
                total_synced += len(wol_records)

        # 10. Ciclo de Vida de Hardware
        hw_lifecycle = entities.get("hardware_lifecycle", [])
        if hw_lifecycle:
            hw_sql = """
                INSERT INTO hardware_lifecycle
                (id, entity_type, entity_id, serial_number, purchase_date, warranty_end_date,
                 eol_date, eos_date, battery_last_replaced, disk_health_status,
                 firmware_version, firmware_latest, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                serial_number=VALUES(serial_number), warranty_end_date=VALUES(warranty_end_date),
                eol_date=VALUES(eol_date), eos_date=VALUES(eos_date),
                battery_last_replaced=VALUES(battery_last_replaced),
                disk_health_status=VALUES(disk_health_status), firmware_version=VALUES(firmware_version),
                notes=VALUES(notes), updated_at=VALUES(updated_at)
            """
            hw_records = [
                (
                    h["id"], h["entity_type"], h["entity_id"], h.get("serial_number"),
                    clean_mysql_dt(h.get("purchase_date"), date_only=True), clean_mysql_dt(h.get("warranty_end_date"), date_only=True),
                    clean_mysql_dt(h.get("eol_date"), date_only=True), clean_mysql_dt(h.get("eos_date"), date_only=True),
                    clean_mysql_dt(h.get("battery_last_replaced"), date_only=True), h.get("disk_health_status", "unknown"),
                    h.get("firmware_version"), h.get("firmware_latest"), h.get("notes"),
                    clean_mysql_dt(h.get("created_at")), clean_mysql_dt(h.get("updated_at") or h.get("created_at"))
                )
                for h in hw_lifecycle if "id" in h and "entity_type" in h
            ]
            if hw_records:
                cursor.executemany(hw_sql, hw_records)
                total_synced += len(hw_records)

        return total_synced
