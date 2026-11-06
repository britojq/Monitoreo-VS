"""
# ==============================================================================
# 🔍 REPLICADOR SNMP Y DESCUBRIMIENTO DE RED: snmp_discovery_replicator.py
# Sincroniza subredes, dispositivos descubiertos, dispositivos SNMP, OIDs, interfaces y métricas.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicators/snmp_discovery_replicator.py
# ==============================================================================
"""
import json
from typing import Any, Dict

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt


class SnmpDiscoveryReplicator(TableReplicatorProtocol):
    """Replicador atómico para discovery_subnets, discovered_devices, snmp_devices, interfaces y métricas."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        """Replica el conjunto de datos especificado hacia el destino local o remoto.

        Args:
            cursor: Parámetro operativo de entrada.
            data: Parámetro operativo de entrada.
        """
        cfg = data.get("config", {})
        total_synced = 0

        # 1. Subredes de descubrimiento
        disc_subnets = cfg.get("discovery_subnets", [])
        if disc_subnets:
            subnet_sql = """
                INSERT INTO discovery_subnets 
                (id, subnet, site_id, scan_method, scan_interval_minutes, is_active, last_scan_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                site_id=VALUES(site_id), scan_method=VALUES(scan_method),
                scan_interval_minutes=VALUES(scan_interval_minutes),
                is_active=VALUES(is_active), last_scan_at=VALUES(last_scan_at), updated_at=VALUES(updated_at)
            """
            s_records = [
                (
                    s["id"], s["subnet"], s.get("site_id"), s.get("scan_method", "arp_sweep"),
                    s.get("scan_interval_minutes", 15), 1 if s.get("is_active") else 0,
                    clean_mysql_dt(s.get("last_scan_at")), clean_mysql_dt(s.get("created_at")),
                    clean_mysql_dt(s.get("updated_at") or s.get("created_at"))
                )
                for s in disc_subnets if "id" in s and "subnet" in s
            ]
            if s_records:
                cursor.executemany(subnet_sql, s_records)
                total_synced += len(s_records)

        # 2. Dispositivos descubiertos
        disc_devs = cfg.get("discovered_devices", [])
        if disc_devs:
            dd_sql = """
                INSERT INTO discovered_devices 
                (id, mac_address, ip_address, hostname, vendor, oui_prefix, device_type, os_detected,
                 open_ports, site_id, classification_status, classified_by, classified_at,
                 linked_network_device_id, first_seen, last_seen, seen_count, is_active,
                 discovery_method, is_authorized, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                ip_address=VALUES(ip_address), hostname=VALUES(hostname), vendor=VALUES(vendor),
                oui_prefix=VALUES(oui_prefix), device_type=VALUES(device_type), os_detected=VALUES(os_detected),
                open_ports=VALUES(open_ports), site_id=VALUES(site_id),
                classification_status=VALUES(classification_status), classified_by=VALUES(classified_by),
                classified_at=VALUES(classified_at), linked_network_device_id=VALUES(linked_network_device_id),
                last_seen=VALUES(last_seen), seen_count=VALUES(seen_count), is_active=VALUES(is_active),
                discovery_method=VALUES(discovery_method), is_authorized=VALUES(is_authorized),
                notes=VALUES(notes), updated_at=VALUES(updated_at)
            """
            dd_records = []
            for d in disc_devs:
                if "id" in d and "mac_address" in d and "ip_address" in d:
                    ports = d.get("open_ports")
                    if isinstance(ports, (dict, list)):
                        ports = json.dumps(ports)
                    dd_records.append((
                        d["id"], d["mac_address"], d["ip_address"], d.get("hostname"),
                        d.get("vendor"), d.get("oui_prefix"), d.get("device_type", "unknown"),
                        d.get("os_detected"), ports, d.get("site_id"),
                        d.get("classification_status", "pendiente"), d.get("classified_by"),
                        clean_mysql_dt(d.get("classified_at")), d.get("linked_network_device_id"),
                        clean_mysql_dt(d.get("first_seen")), clean_mysql_dt(d.get("last_seen")),
                        d.get("seen_count", 1), 1 if d.get("is_active", True) else 0,
                        d.get("discovery_method", "arp_sweep"), 1 if d.get("is_authorized") else 0,
                        d.get("notes"), clean_mysql_dt(d.get("created_at")),
                        clean_mysql_dt(d.get("updated_at") or d.get("created_at"))
                    ))
            if dd_records:
                cursor.executemany(dd_sql, dd_records)
                total_synced += len(dd_records)

        # 3. OIDs SNMP
        snmp_oids = cfg.get("snmp_oids", [])
        if snmp_oids:
            oid_sql = """
                INSERT INTO snmp_oids 
                (id, name, oid, mib, vendor, data_type, unit, is_standard, is_counter_wrap, description, is_active, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                name=VALUES(name), oid=VALUES(oid), mib=VALUES(mib), vendor=VALUES(vendor),
                data_type=VALUES(data_type), unit=VALUES(unit), is_standard=VALUES(is_standard),
                is_counter_wrap=VALUES(is_counter_wrap), description=VALUES(description),
                is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """
            oid_records = [
                (
                    o["id"], o["name"], o["oid"], o.get("mib"), o.get("vendor"),
                    o.get("data_type", "string"), o.get("unit"), 1 if o.get("is_standard") else 0,
                    1 if o.get("is_counter_wrap") else 0, o.get("description"),
                    1 if o.get("is_active", True) else 0, clean_mysql_dt(o.get("created_at")),
                    clean_mysql_dt(o.get("updated_at") or o.get("created_at"))
                )
                for o in snmp_oids if "id" in o and "name" in o
            ]
            if oid_records:
                cursor.executemany(oid_sql, oid_records)
                total_synced += len(oid_records)

        # 4. Dispositivos SNMP
        snmp_devs = cfg.get("snmp_devices", [])
        if snmp_devs:
            dev_sql = """
                INSERT INTO snmp_devices 
                (id, name, ip_address, snmp_version, snmp_port, snmp_timeout_seconds, snmp_retries,
                 device_type, vendor, model, firmware_version, serial_number, sys_name, sys_description,
                 sys_object_id, sys_uptime, sys_location, sys_contact, site_id, discovered_device_id,
                 network_device_id, poll_interval_seconds, is_active, last_poll_at, last_poll_status,
                 consecutive_failures, ssh_enabled, ssh_username, ssh_port, custom_oids, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                name=VALUES(name), snmp_version=VALUES(snmp_version), snmp_port=VALUES(snmp_port),
                snmp_timeout_seconds=VALUES(snmp_timeout_seconds), snmp_retries=VALUES(snmp_retries),
                device_type=VALUES(device_type), vendor=VALUES(vendor), model=VALUES(model),
                firmware_version=VALUES(firmware_version), serial_number=VALUES(serial_number),
                sys_name=VALUES(sys_name), sys_description=VALUES(sys_description),
                sys_object_id=VALUES(sys_object_id), sys_uptime=VALUES(sys_uptime),
                sys_location=VALUES(sys_location), sys_contact=VALUES(sys_contact),
                site_id=VALUES(site_id), discovered_device_id=VALUES(discovered_device_id),
                network_device_id=VALUES(network_device_id), poll_interval_seconds=VALUES(poll_interval_seconds),
                is_active=VALUES(is_active), last_poll_at=VALUES(last_poll_at),
                last_poll_status=VALUES(last_poll_status), consecutive_failures=VALUES(consecutive_failures),
                ssh_enabled=VALUES(ssh_enabled), ssh_username=VALUES(ssh_username),
                ssh_port=VALUES(ssh_port), custom_oids=VALUES(custom_oids), notes=VALUES(notes),
                updated_at=VALUES(updated_at)
            """
            dev_records = [
                (
                    d["id"], d["name"], d["ip_address"], d.get("snmp_version", "v2c"),
                    d.get("snmp_port", 161), d.get("snmp_timeout_seconds", 5), d.get("snmp_retries", 2),
                    d.get("device_type", "unknown"), d.get("vendor"), d.get("model"),
                    d.get("firmware_version"), d.get("serial_number"), d.get("sys_name"),
                    d.get("sys_description"), d.get("sys_object_id"), d.get("sys_uptime"),
                    d.get("sys_location"), d.get("sys_contact"), d.get("site_id"),
                    d.get("discovered_device_id"), d.get("network_device_id"),
                    d.get("poll_interval_seconds", 60), 1 if d.get("is_active") else 0,
                    clean_mysql_dt(d.get("last_poll_at")), d.get("last_poll_status"),
                    d.get("consecutive_failures", 0), 1 if d.get("ssh_enabled") else 0,
                    d.get("ssh_username"), d.get("ssh_port", 22),
                    json.dumps(d.get("custom_oids")) if isinstance(d.get("custom_oids"), (dict, list)) else d.get("custom_oids"),
                    d.get("notes"), clean_mysql_dt(d.get("created_at")),
                    clean_mysql_dt(d.get("updated_at") or d.get("created_at"))
                )
                for d in snmp_devs if "id" in d and "name" in d
            ]
            if dev_records:
                cursor.executemany(dev_sql, dev_records)
                total_synced += len(dev_records)

        # 5. Interfaces SNMP
        snmp_ifs = cfg.get("snmp_interfaces", [])
        if snmp_ifs:
            if_sql = """
                INSERT INTO snmp_interfaces
                (id, snmp_device_id, if_index, if_name, if_description, if_alias, if_type, if_speed,
                 if_high_speed, if_physical_address, if_admin_status, if_oper_status, is_monitored,
                 last_in_octets, last_out_octets, last_polled_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                if_name=VALUES(if_name), if_description=VALUES(if_description), if_alias=VALUES(if_alias),
                if_type=VALUES(if_type), if_speed=VALUES(if_speed), if_high_speed=VALUES(if_high_speed),
                if_physical_address=VALUES(if_physical_address), if_admin_status=VALUES(if_admin_status),
                if_oper_status=VALUES(if_oper_status), is_monitored=VALUES(is_monitored),
                last_in_octets=VALUES(last_in_octets), last_out_octets=VALUES(last_out_octets),
                last_polled_at=VALUES(last_polled_at), updated_at=VALUES(updated_at)
            """
            if_records = [
                (
                    i["id"], i["snmp_device_id"], i["if_index"], i.get("if_name"),
                    i.get("if_description"), i.get("if_alias"), i.get("if_type"),
                    i.get("if_speed"), i.get("if_high_speed"), i.get("if_physical_address"),
                    i.get("if_admin_status"), i.get("if_oper_status"), 1 if i.get("is_monitored") else 0,
                    i.get("last_in_octets"), i.get("last_out_octets"), clean_mysql_dt(i.get("last_polled_at")),
                    clean_mysql_dt(i.get("created_at")), clean_mysql_dt(i.get("updated_at") or i.get("created_at"))
                )
                for i in snmp_ifs if "id" in i and "snmp_device_id" in i
            ]
            if if_records:
                cursor.executemany(if_sql, if_records)
                total_synced += len(if_records)

        # 6. Históricos y Métricas SNMP
        snmp_met = data.get("snmp_metrics_history", [])
        if snmp_met:
            met_sql = """
                INSERT IGNORE INTO snmp_metrics_history
                (id, snmp_device_id, snmp_oid_id, metric_value, metric_value_raw, collected_at)
                VALUES (%s, %s, %s, %s, %s, %s)
            """
            m_records = [
                (
                    m["id"], m["snmp_device_id"], m["snmp_oid_id"], m.get("metric_value"),
                    m.get("metric_value_raw"), clean_mysql_dt(m.get("collected_at"))
                )
                for m in snmp_met if "id" in m
            ]
            if m_records:
                cursor.executemany(met_sql, m_records)
                total_synced += len(m_records)

        snmp_if_met = data.get("snmp_interface_metrics", [])
        if snmp_if_met:
            if_met_sql = """
                INSERT IGNORE INTO snmp_interface_metrics
                (id, snmp_interface_id, in_octets, out_octets, in_unicast_pkts, out_unicast_pkts,
                 in_discards, out_discards, in_errors, out_errors, in_bps, out_bps,
                 in_utilization_pct, out_utilization_pct, collected_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            ifm_records = [
                (
                    im["id"], im["snmp_interface_id"], im.get("in_octets"), im.get("out_octets"),
                    im.get("in_unicast_pkts"), im.get("out_unicast_pkts"), im.get("in_discards"),
                    im.get("out_discards"), im.get("in_errors"), im.get("out_errors"),
                    im.get("in_bps"), im.get("out_bps"), im.get("in_utilization_pct"),
                    im.get("out_utilization_pct"), clean_mysql_dt(im.get("collected_at"))
                )
                for im in snmp_if_met if "id" in im
            ]
            if ifm_records:
                cursor.executemany(if_met_sql, ifm_records)
                total_synced += len(ifm_records)

        # 7. Rollups horarios
        metric_rollups = data.get("snmp_metric_hourly_rollups", [])
        if metric_rollups:
            m_roll_sql = """
                INSERT INTO snmp_metric_hourly_rollups
                (id, snmp_device_id, snmp_oid_id, hour_timestamp, avg_value, min_value, max_value, samples_count, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                avg_value=VALUES(avg_value), min_value=VALUES(min_value), max_value=VALUES(max_value),
                samples_count=VALUES(samples_count), updated_at=VALUES(updated_at)
            """
            m_roll_records = [
                (
                    mr["id"], mr["snmp_device_id"], mr["snmp_oid_id"], clean_mysql_dt(mr["hour_timestamp"]),
                    mr.get("avg_value", 0), mr.get("min_value", 0), mr.get("max_value", 0),
                    mr.get("samples_count", 0), clean_mysql_dt(mr.get("created_at")),
                    clean_mysql_dt(mr.get("updated_at") or mr.get("created_at"))
                )
                for mr in metric_rollups if "id" in mr and "snmp_device_id" in mr and "snmp_oid_id" in mr
            ]
            if m_roll_records:
                cursor.executemany(m_roll_sql, m_roll_records)
                total_synced += len(m_roll_records)

        iface_rollups = data.get("snmp_interface_hourly_rollups", [])
        if iface_rollups:
            i_roll_sql = """
                INSERT INTO snmp_interface_hourly_rollups
                (id, snmp_interface_id, hour_timestamp, avg_in_bps, max_in_bps, avg_out_bps, max_out_bps,
                 avg_in_util_pct, max_in_util_pct, avg_out_util_pct, max_out_util_pct, samples_count, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                avg_in_bps=VALUES(avg_in_bps), max_in_bps=VALUES(max_in_bps), avg_out_bps=VALUES(avg_out_bps),
                max_out_bps=VALUES(max_out_bps), avg_in_util_pct=VALUES(avg_in_util_pct), max_in_util_pct=VALUES(max_in_util_pct),
                avg_out_util_pct=VALUES(avg_out_util_pct), max_out_util_pct=VALUES(max_out_util_pct),
                samples_count=VALUES(samples_count), updated_at=VALUES(updated_at)
            """
            i_roll_records = [
                (
                    ir["id"], ir["snmp_interface_id"], clean_mysql_dt(ir["hour_timestamp"]),
                    ir.get("avg_in_bps", 0), ir.get("max_in_bps", 0), ir.get("avg_out_bps", 0), ir.get("max_out_bps", 0),
                    ir.get("avg_in_util_pct", 0), ir.get("max_in_util_pct", 0), ir.get("avg_out_util_pct", 0), ir.get("max_out_util_pct", 0),
                    ir.get("samples_count", 0), clean_mysql_dt(ir.get("created_at")),
                    clean_mysql_dt(ir.get("updated_at") or ir.get("created_at"))
                )
                for ir in iface_rollups if "id" in ir and "snmp_interface_id" in ir
            ]
            if i_roll_records:
                cursor.executemany(i_roll_sql, i_roll_records)
                total_synced += len(i_roll_records)

        return total_synced
