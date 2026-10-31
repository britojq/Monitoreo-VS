"""
# ==============================================================================
# 📊 REPLICADOR DE HISTORIALES Y AUDITORÍA: history_replicator.py
# Sincroniza telemetría histórica de servicios, sedes, proxies y bitácora audit_logs.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicators/history_replicator.py
# ==============================================================================
"""
import json
from typing import Any, Dict

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt


class HistoryReplicator(TableReplicatorProtocol):
    """Replicador atómico para historiales de chequeo y bitácora forense audit_logs."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        snapshot_payload = data.get("snapshot", {})
        all_services = snapshot_payload.get("services", [])
        all_sites = snapshot_payload.get("sites", [])
        all_proxies = snapshot_payload.get("proxies", [])
        all_net_devices = snapshot_payload.get("network_devices", [])
        total_synced = 0

        if all_services:
            hist_sql = """
                INSERT INTO service_check_histories 
                (monitored_service_id, is_up, latency_ms, http_code, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            hist_records = [
                (
                    s["id"], 1 if s.get("is_up") else 0, float(s.get("latency_ms", 0.0) or 0.0),
                    str(s.get("http_code") or "")[:10], str(s.get("status") or "")[:255]
                )
                for s in all_services if "id" in s and s["id"]
            ]
            if hist_records:
                cursor.executemany(hist_sql, hist_records)
                total_synced += len(hist_records)
            cursor.execute("DELETE FROM service_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

        if all_sites:
            site_hist_sql = """
                INSERT INTO site_check_histories 
                (monitored_site_id, is_up, latency_ms, packet_loss_pct, jitter_ms, min_rtt_ms, max_rtt_ms, mdev_ms, devices_online, devices_total, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            site_hist_records = []
            for st in all_sites:
                if "id" in st and st["id"]:
                    devs = st.get("devices", [])
                    devs_online = sum(1 for d in devs if d.get("is_up"))
                    status_msg = "Enlace Operativo" if st.get("is_up") else "Enlace Caído / Timeout"
                    site_hist_records.append((
                        st["id"], 1 if st.get("is_up") else 0, float(st.get("latency_ms", 0.0) or 0.0),
                        float(st.get("packet_loss_pct", 0.0) or 0.0), float(st.get("jitter_ms", 0.0) or 0.0),
                        float(st.get("min_rtt_ms", 0.0) or 0.0), float(st.get("max_rtt_ms", 0.0) or 0.0),
                        float(st.get("mdev_ms", 0.0) or 0.0), devs_online, len(devs), status_msg
                    ))
            if site_hist_records:
                cursor.executemany(site_hist_sql, site_hist_records)
                total_synced += len(site_hist_records)
            cursor.execute("DELETE FROM site_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

        if all_proxies:
            proxy_hist_sql = """
                INSERT INTO proxy_check_histories 
                (monitored_proxy_id, is_up, latency_ms, http_code, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            proxy_hist_records = [
                (
                    p["id"], 1 if p.get("is_up") else 0, float(p.get("latency_ms", 0.0) or 0.0),
                    "200" if p.get("is_up") else None,
                    "Proxy Operativo / Respondiendo" if p.get("is_up") else "Proxy Inaccesible / Falló Túnel"
                )
                for p in all_proxies if "id" in p and p["id"]
            ]
            if proxy_hist_records:
                cursor.executemany(proxy_hist_sql, proxy_hist_records)
                total_synced += len(proxy_hist_records)
            cursor.execute("DELETE FROM proxy_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

        if all_net_devices:
            net_hist_sql = """
                INSERT INTO network_device_check_histories 
                (monitored_network_device_id, is_up, latency_ms, status_message, checked_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, NOW(), NOW(), NOW())
            """
            legacy_dev_map = {11519: 1, 11502: 8, 11503: 9, 11504: 10, 11505: 11, 11506: 12, 11507: 13, 11508: 14, 11509: 15, 11510: 16, 11511: 17, 11512: 18, 11513: 19, 11514: 20, 11515: 21, 11516: 22, 11517: 23, 11518: 24}
            net_hist_records = []
            for nd in all_net_devices:
                if "id" in nd and nd["id"]:
                    dev_id = nd["id"]
                    if dev_id == 11519 and (nd.get("ip") == "192.168.1.1" or "ROUTER" in str(nd.get("name", "")).upper()):
                        dev_id = 1
                    elif dev_id in legacy_dev_map and dev_id != 11519:
                        dev_id = legacy_dev_map[dev_id]
                    net_hist_records.append((
                        dev_id, 1 if nd.get("is_up") else 0, float(nd.get("latency_ms", 0.0) or 0.0),
                        "Dispositivo Operativo / Enlace Activo" if nd.get("is_up") else "Dispositivo Caído / Inalcanzable"
                    ))
            if net_hist_records:
                cursor.executemany(net_hist_sql, net_hist_records)
                total_synced += len(net_hist_records)
            cursor.execute("DELETE FROM network_device_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

        # Bitácora de auditoría forense (audit_logs)
        audit_logs = data.get("audit_logs", [])
        if audit_logs:
            audit_sql = """
                INSERT IGNORE INTO audit_logs 
                (id, user_id, user_name, user_email, user_role, event, module, auditable_type,
                 auditable_id, entity_name, entity_label, description, old_values, new_values,
                 changed_fields, ip_address, user_agent, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            audit_records = [
                (
                    al["id"], al.get("user_id"), al.get("user_name"), al.get("user_email"),
                    al.get("user_role"), al.get("event"), al.get("module"), al.get("auditable_type"),
                    al.get("auditable_id"), al.get("entity_name"), al.get("entity_label"),
                    al.get("description"),
                    json.dumps(al.get("old_values")) if isinstance(al.get("old_values"), (dict, list)) else al.get("old_values"),
                    json.dumps(al.get("new_values")) if isinstance(al.get("new_values"), (dict, list)) else al.get("new_values"),
                    json.dumps(al.get("changed_fields")) if isinstance(al.get("changed_fields"), (dict, list)) else al.get("changed_fields"),
                    al.get("ip_address"), al.get("user_agent"),
                    clean_mysql_dt(al.get("created_at")),
                    clean_mysql_dt(al.get("updated_at") or al.get("created_at"))
                )
                for al in audit_logs if "id" in al and "event" in al
            ]
            if audit_records:
                cursor.executemany(audit_sql, audit_records)
                total_synced += len(audit_records)

        return total_synced
