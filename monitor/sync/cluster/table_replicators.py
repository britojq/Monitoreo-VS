# monitor/sync/cluster/table_replicators.py
import json
from typing import Any, Dict
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt

class TableReplicationService:
    """Servicio modularizado para persistir entidades replicadas del Master en MariaDB."""

    @staticmethod
    def replicate_core_catalog(cursor: Any, cfg: Dict[str, Any]) -> None:
        """Sincroniza Sedes, Servicios, Proxies y Equipos de Red troncales."""
        # 1. Sedes
        cfg_sites = cfg.get("sites", [])
        if cfg_sites:
            st_sql = """
                INSERT INTO monitored_sites
                (id, letter, name, ip, phone_1, phone_2, phone_3, phone_4, phone_5, phone_6,
                 phone_7, phone_8, address, normal_state_msg, error_state_msg, is_active,
                 sort_order, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                letter=VALUES(letter), name=VALUES(name), ip=VALUES(ip),
                phone_1=VALUES(phone_1), phone_2=VALUES(phone_2), phone_3=VALUES(phone_3),
                phone_4=VALUES(phone_4), phone_5=VALUES(phone_5), phone_6=VALUES(phone_6),
                phone_7=VALUES(phone_7), phone_8=VALUES(phone_8), address=VALUES(address),
                normal_state_msg=VALUES(normal_state_msg), error_state_msg=VALUES(error_state_msg),
                is_active=VALUES(is_active), sort_order=VALUES(sort_order), updated_at=VALUES(updated_at)
            """
            st_records = [
                (
                    st["id"], st.get("letter"), st["name"], st.get("ip"), st.get("phone_1"),
                    st.get("phone_2"), st.get("phone_3"), st.get("phone_4"), st.get("phone_5"),
                    st.get("phone_6"), st.get("phone_7"), st.get("phone_8"), st.get("address"),
                    st.get("normal_state_msg"), st.get("error_state_msg"), 1 if st.get("is_active", True) else 0,
                    st.get("sort_order", 0), clean_mysql_dt(st.get("created_at")),
                    clean_mysql_dt(st.get("updated_at") or st.get("created_at"))
                )
                for st in cfg_sites if "id" in st and "name" in st
            ]
            if st_records:
                cursor.executemany(st_sql, st_records)

        # 2. Servicios
        cfg_services = cfg.get("services", [])
        if cfg_services:
            srv_sql = """
                INSERT INTO monitored_services
                (id, letter, name, type, scope, host_ip, web_url, port, credentials,
                 check_interface, dns_test_domain, normal_state_msg, error_state_msg,
                 is_active, sort_order, telegram_alert_enabled, telegram_alert_target,
                 last_alert_state, down_since, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                letter=VALUES(letter), name=VALUES(name), type=VALUES(type), scope=VALUES(scope),
                host_ip=VALUES(host_ip), web_url=VALUES(web_url), port=VALUES(port),
                credentials=VALUES(credentials), check_interface=VALUES(check_interface),
                dns_test_domain=VALUES(dns_test_domain), normal_state_msg=VALUES(normal_state_msg),
                error_state_msg=VALUES(error_state_msg), is_active=VALUES(is_active),
                sort_order=VALUES(sort_order), telegram_alert_enabled=VALUES(telegram_alert_enabled),
                telegram_alert_target=VALUES(telegram_alert_target), last_alert_state=VALUES(last_alert_state),
                down_since=VALUES(down_since), updated_at=VALUES(updated_at)
            """
            srv_records = [
                (
                    s["id"], s.get("letter"), s["name"], s.get("type", "WEB"), s.get("scope", "LOCAL"),
                    s.get("host_ip"), s.get("web_url"), s.get("port"), s.get("credentials"),
                    s.get("check_interface"), s.get("dns_test_domain"), s.get("normal_state_msg"),
                    s.get("error_state_msg"), 1 if s.get("is_active", True) else 0,
                    s.get("sort_order", 0), 1 if s.get("telegram_alert_enabled") else 0,
                    s.get("telegram_alert_target", "owner"), s.get("last_alert_state"),
                    clean_mysql_dt(s.get("down_since")), clean_mysql_dt(s.get("created_at")),
                    clean_mysql_dt(s.get("updated_at") or s.get("created_at"))
                )
                for s in cfg_services if "id" in s and "name" in s
            ]
            if srv_records:
                cursor.executemany(srv_sql, srv_records)

        # 3. Proxies
        cfg_proxies = cfg.get("proxies", [])
        if cfg_proxies:
            pxy_sql = """
                INSERT INTO monitored_proxies
                (id, letter, name, ip_port, auth_userpass, test_url, is_active, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                letter=VALUES(letter), name=VALUES(name), ip_port=VALUES(ip_port),
                auth_userpass=VALUES(auth_userpass), test_url=VALUES(test_url),
                is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """
            pxy_records = [
                (
                    p["id"], p.get("letter"), p["name"], p.get("ip_port"), p.get("auth_userpass"),
                    p.get("test_url"), 1 if p.get("is_active", True) else 0,
                    clean_mysql_dt(p.get("created_at")),
                    clean_mysql_dt(p.get("updated_at") or p.get("created_at"))
                )
                for p in cfg_proxies if "id" in p and "name" in p
            ]
            if ppy_records := pxy_records:
                cursor.executemany(pxy_sql, ppy_records)

        # 4. Dispositivos de Red Valle Seco (con blindaje de paridad ID 1 vs 11519)
        cfg_net_devs = cfg.get("network_devices", [])
        if cfg_net_devs:
            net_dev_sql = """
                INSERT INTO monitored_network_devices
                (id, monitored_site_id, device_number, name, ip, mac, vendor_data,
                 access_type, access_port, model, serial, ports, notes,
                 normal_state_msg, error_state_msg, is_active, sort_order,
                 telegram_alert_enabled, telegram_alert_target, last_alert_state,
                 down_since, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                monitored_site_id=VALUES(monitored_site_id), device_number=VALUES(device_number),
                name=VALUES(name), ip=VALUES(ip), mac=VALUES(mac), vendor_data=VALUES(vendor_data),
                access_type=VALUES(access_type), access_port=VALUES(access_port),
                model=VALUES(model), serial=VALUES(serial), ports=VALUES(ports), notes=VALUES(notes),
                normal_state_msg=VALUES(normal_state_msg), error_state_msg=VALUES(error_state_msg),
                is_active=VALUES(is_active), sort_order=VALUES(sort_order),
                telegram_alert_enabled=VALUES(telegram_alert_enabled),
                telegram_alert_target=VALUES(telegram_alert_target),
                last_alert_state=VALUES(last_alert_state), down_since=VALUES(down_since),
                updated_at=VALUES(updated_at)
            """
            legacy_dev_map = {
                11519: 1,  # Router Principal oficial
                11502: 8, 11503: 9, 11504: 10, 11505: 11, 11506: 12,
                11507: 13, 11508: 14, 11509: 15, 11510: 16, 11511: 17,
                11512: 18, 11513: 19, 11514: 20, 11515: 21, 11516: 22,
                11517: 23, 11518: 24,
            }
            net_dev_records = []
            for nd in cfg_net_devs:
                if "id" in nd and "name" in nd:
                    dev_id = nd["id"]
                    # Blindaje de paridad: Si el master envía el Router Principal con ID legado 11519,
                    # o IDs duplicados viejos, remapear a sus IDs canónicos para proteger CENCARATIT (ID 11519).
                    if dev_id == 11519 and (nd.get("ip") == "192.168.1.1" or "ROUTER" in str(nd.get("name", "")).upper()):
                        dev_id = 1
                    elif dev_id in legacy_dev_map and dev_id != 11519:
                        dev_id = legacy_dev_map[dev_id]

                    dev_num = nd.get("device_number")
                    if dev_num is not None:
                        try:
                            dev_num = int(dev_num)
                        except (ValueError, TypeError):
                            dev_num = None
                    if dev_id >= 104950 and nd.get("monitored_site_id") == 1 and dev_num == 1:
                        dev_num = None

                    net_dev_records.append((
                        dev_id, nd.get("monitored_site_id"), dev_num,
                        nd["name"], nd.get("ip"), nd.get("mac"), nd.get("vendor_data"),
                        nd.get("access_type", "SIN SOPORTE"), nd.get("access_port"),
                        nd.get("model"), nd.get("serial"), nd.get("ports"), nd.get("notes"),
                        nd.get("normal_state_msg"), nd.get("error_state_msg"),
                        1 if nd.get("is_active", True) else 0, nd.get("sort_order", 0),
                        1 if nd.get("telegram_alert_enabled") else 0,
                        nd.get("telegram_alert_target", "owner"), nd.get("last_alert_state"),
                        clean_mysql_dt(nd.get("down_since")), clean_mysql_dt(nd.get("created_at")),
                        clean_mysql_dt(nd.get("updated_at") or nd.get("created_at"))
                    ))
            if net_dev_records:
                cursor.executemany(net_dev_sql, net_dev_records)

    @staticmethod
    def replicate_histories(cursor: Any, data: Dict[str, Any]) -> None:
        """Sincroniza historiales individuales y bitácora de auditoría."""
        snapshot_payload = data.get("snapshot", {})
        all_services = snapshot_payload.get("services", [])
        all_sites = snapshot_payload.get("sites", [])
        all_proxies = snapshot_payload.get("proxies", [])
        all_net_devices = snapshot_payload.get("network_devices", [])

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

    @staticmethod
    def replicate_snmp_discovery(cursor: Any, data: Dict[str, Any]) -> None:
        """Sincroniza subredes de descubrimiento, dispositivos SNMP, OIDs y métricas."""
        cfg = data.get("config", {})

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

        # Históricos SNMP
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

        # Rollups horarios
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

    @staticmethod
    def replicate_security_and_alerts(cursor: Any, data: Dict[str, Any]) -> None:
        """Sincroniza certificados SSL, reglas de alerta, alarmas y respaldos de configuración."""
        cfg = data.get("config", {})

        # SSL Certificates
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

        # Reglas de alerta
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

        # Ventanas de mantenimiento
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

        # Alarmas activas
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

        # Respaldos de configuración
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

    @staticmethod
    def replicate_netradar_and_topology(cursor: Any, data: Dict[str, Any]) -> None:
        """Sincroniza NetRadar, Topología de Red, Syslog, NetFlow y WoL."""
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

    @staticmethod
    def replicate_ups(cursor: Any, data: Dict[str, Any]) -> None:
        """Sincroniza dispositivos UPS, telemetría y eventos de suministro eléctrico."""
        cfg = data.get("config", {})
        ups_devices = cfg.get("ups_devices", [])
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

    @staticmethod
    def replicate_itam(cursor: Any, data: Dict[str, Any]) -> None:
        """Sincroniza activos de inventario, componentes, periféricos, asignaciones y tickets."""
        cfg = data.get("config", {})

        inv_assets = cfg.get("inventory_assets", [])
        if inv_assets:
            inv_asset_sql = """
                INSERT IGNORE INTO inventory_assets
                (id, asset_tag, name, type, status, brand, model, serial_number, mac_address, ip_address,
                 monitored_site_id, location_detail, monitored_network_device_id, current_user_id,
                 custodian_name, custodian_id_number, custodian_department, operating_system, os_kernel,
                 purchase_date, warranty_expiration, last_glpi_sync_at, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                asset_tag=VALUES(asset_tag), name=VALUES(name), type=VALUES(type), status=VALUES(status),
                brand=VALUES(brand), model=VALUES(model), serial_number=VALUES(serial_number),
                mac_address=VALUES(mac_address), ip_address=VALUES(ip_address),
                monitored_site_id=VALUES(monitored_site_id), location_detail=VALUES(location_detail),
                monitored_network_device_id=VALUES(monitored_network_device_id), current_user_id=VALUES(current_user_id),
                custodian_name=VALUES(custodian_name), custodian_id_number=VALUES(custodian_id_number),
                custodian_department=VALUES(custodian_department), operating_system=VALUES(operating_system),
                os_kernel=VALUES(os_kernel), purchase_date=VALUES(purchase_date),
                warranty_expiration=VALUES(warranty_expiration), last_glpi_sync_at=VALUES(last_glpi_sync_at),
                notes=VALUES(notes), updated_at=VALUES(updated_at)
            """
            inv_asset_records = [
                (
                    ia["id"], ia.get("asset_tag"), ia["name"], ia.get("type", "desktop"),
                    ia.get("status", "in_stock"), ia.get("brand"), ia.get("model"),
                    ia.get("serial_number"), ia.get("mac_address"), ia.get("ip_address"),
                    ia.get("monitored_site_id"), ia.get("location_detail"),
                    ia.get("monitored_network_device_id"), ia.get("current_user_id"),
                    ia.get("custodian_name"), ia.get("custodian_id_number"),
                    ia.get("custodian_department"), ia.get("operating_system"),
                    ia.get("os_kernel"), clean_mysql_dt(ia.get("purchase_date"), date_only=True),
                    clean_mysql_dt(ia.get("warranty_expiration"), date_only=True),
                    clean_mysql_dt(ia.get("last_glpi_sync_at")), ia.get("notes"),
                    clean_mysql_dt(ia.get("created_at")),
                    clean_mysql_dt(ia.get("updated_at") or ia.get("created_at"))
                )
                for ia in inv_assets if "id" in ia and "name" in ia
            ]
            if inv_asset_records:
                cursor.executemany(inv_asset_sql, inv_asset_records)

        inv_comps = cfg.get("inventory_components", [])
        if inv_comps:
            inv_comp_sql = """
                INSERT IGNORE INTO inventory_components
                (id, inventory_asset_id, type, name, brand, model, capacity, serial_number, slot_or_bus, status, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                inventory_asset_id=VALUES(inventory_asset_id), type=VALUES(type), name=VALUES(name),
                brand=VALUES(brand), model=VALUES(model), capacity=VALUES(capacity),
                serial_number=VALUES(serial_number), slot_or_bus=VALUES(slot_or_bus),
                status=VALUES(status), updated_at=VALUES(updated_at)
            """
            inv_comp_records = [
                (
                    ic["id"], ic["inventory_asset_id"], ic["type"], ic["name"],
                    ic.get("brand"), ic.get("model"), ic.get("capacity"),
                    ic.get("serial_number"), ic.get("slot_or_bus"),
                    ic.get("status", "active"), clean_mysql_dt(ic.get("created_at")),
                    clean_mysql_dt(ic.get("updated_at") or ic.get("created_at"))
                )
                for ic in inv_comps if "id" in ic and "inventory_asset_id" in ic
            ]
            if inv_comp_records:
                cursor.executemany(inv_comp_sql, inv_comp_records)

        inv_periphs = cfg.get("inventory_peripherals", [])
        if inv_periphs:
            inv_peri_sql = """
                INSERT IGNORE INTO inventory_peripherals
                (id, asset_tag, type, brand, model, serial_number, status, monitored_site_id, current_asset_id, current_user_id, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                asset_tag=VALUES(asset_tag), type=VALUES(type), brand=VALUES(brand),
                model=VALUES(model), serial_number=VALUES(serial_number), status=VALUES(status),
                monitored_site_id=VALUES(monitored_site_id), current_asset_id=VALUES(current_asset_id),
                current_user_id=VALUES(current_user_id), notes=VALUES(notes), updated_at=VALUES(updated_at)
            """
            inv_peri_records = [
                (
                    ip["id"], ip.get("asset_tag"), ip["type"], ip.get("brand"),
                    ip.get("model"), ip.get("serial_number"), ip.get("status", "in_stock"),
                    ip.get("monitored_site_id"), ip.get("current_asset_id"),
                    ip.get("current_user_id"), ip.get("notes"),
                    clean_mysql_dt(ip.get("created_at")),
                    clean_mysql_dt(ip.get("updated_at") or ip.get("created_at"))
                )
                for ip in inv_periphs if "id" in ip and "type" in ip
            ]
            if inv_peri_records:
                cursor.executemany(inv_peri_sql, inv_peri_records)

        inv_assigns = cfg.get("inventory_assignments", [])
        if inv_assigns:
            inv_asg_sql = """
                INSERT INTO inventory_assignments
                (id, assignment_code, inventory_asset_id, user_id, custodian_name, custodian_id_number,
                 custodian_department, custodian_role, phone_extension, monitored_site_id,
                 checkpoint_name, gate_operator_name, departure_time, entry_time, control_number,
                 movement_type, provenance, provenance_other, peripherals_json, hardware_specs,
                 assigned_at, returned_at, document_pdf_path, status, delivered_by_user_id,
                 observations, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                assignment_code=VALUES(assignment_code), inventory_asset_id=VALUES(inventory_asset_id),
                user_id=VALUES(user_id), custodian_name=VALUES(custodian_name),
                custodian_id_number=VALUES(custodian_id_number), custodian_department=VALUES(custodian_department),
                custodian_role=VALUES(custodian_role), phone_extension=VALUES(phone_extension),
                monitored_site_id=VALUES(monitored_site_id), checkpoint_name=VALUES(checkpoint_name),
                gate_operator_name=VALUES(gate_operator_name), departure_time=VALUES(departure_time),
                entry_time=VALUES(entry_time), control_number=VALUES(control_number),
                movement_type=VALUES(movement_type), provenance=VALUES(provenance),
                provenance_other=VALUES(provenance_other), peripherals_json=VALUES(peripherals_json),
                hardware_specs=VALUES(hardware_specs), assigned_at=VALUES(assigned_at),
                returned_at=VALUES(returned_at), document_pdf_path=VALUES(document_pdf_path),
                status=VALUES(status), delivered_by_user_id=VALUES(delivered_by_user_id),
                observations=VALUES(observations), updated_at=VALUES(updated_at)
            """
            inv_asg_records = [
                (
                    ia["id"], ia["assignment_code"], ia["inventory_asset_id"],
                    ia.get("user_id"), ia["custodian_name"], ia.get("custodian_id_number"),
                    ia.get("custodian_department"), ia.get("custodian_role"),
                    ia.get("phone_extension"), ia.get("monitored_site_id"),
                    ia.get("checkpoint_name"), ia.get("gate_operator_name"),
                    ia.get("departure_time"), ia.get("entry_time"), ia.get("control_number"),
                    ia.get("movement_type", "assignment"), ia.get("provenance", "stock"),
                    ia.get("provenance_other"),
                    json.dumps(ia.get("peripherals_json")) if isinstance(ia.get("peripherals_json"), (list, dict)) else ia.get("peripherals_json"),
                    json.dumps(ia.get("hardware_specs")) if isinstance(ia.get("hardware_specs"), (list, dict)) else ia.get("hardware_specs"),
                    clean_mysql_dt(ia.get("assigned_at")), clean_mysql_dt(ia.get("returned_at")),
                    ia.get("document_pdf_path"), ia.get("status", "active"),
                    ia.get("delivered_by_user_id"), ia.get("observations"),
                    clean_mysql_dt(ia.get("created_at")),
                    clean_mysql_dt(ia.get("updated_at") or ia.get("created_at"))
                )
                for ia in inv_assigns if "id" in ia and "assignment_code" in ia
            ]
            if inv_asg_records:
                cursor.executemany(inv_asg_sql, inv_asg_records)

        inv_tickets = cfg.get("maintenance_tickets", [])
        if inv_tickets:
            inv_tkt_sql = """
                INSERT INTO maintenance_tickets
                (id, ticket_code, inventory_asset_id, type, status, priority, title, description,
                 scheduled_at, started_at, completed_at, technician_user_id, resolution_notes,
                 parts_replaced_json, warranty_applied, cost, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                ticket_code=VALUES(ticket_code), inventory_asset_id=VALUES(inventory_asset_id),
                type=VALUES(type), status=VALUES(status), priority=VALUES(priority),
                title=VALUES(title), description=VALUES(description),
                scheduled_at=VALUES(scheduled_at), started_at=VALUES(started_at),
                completed_at=VALUES(completed_at), technician_user_id=VALUES(technician_user_id),
                resolution_notes=VALUES(resolution_notes), parts_replaced_json=VALUES(parts_replaced_json),
                warranty_applied=VALUES(warranty_applied), cost=VALUES(cost), updated_at=VALUES(updated_at)
            """
            inv_tkt_records = [
                (
                    it["id"], it["ticket_code"], it["inventory_asset_id"],
                    it.get("type", "preventive"), it.get("status", "scheduled"),
                    it.get("priority", "medium"), it["title"], it.get("description"),
                    clean_mysql_dt(it.get("scheduled_at")), clean_mysql_dt(it.get("started_at")),
                    clean_mysql_dt(it.get("completed_at")), it.get("technician_user_id"),
                    it.get("resolution_notes"),
                    json.dumps(it.get("parts_replaced_json")) if isinstance(it.get("parts_replaced_json"), (list, dict)) else it.get("parts_replaced_json"),
                    1 if it.get("warranty_applied") else 0, float(it.get("cost", 0.0) or 0.0),
                    clean_mysql_dt(it.get("created_at")),
                    clean_mysql_dt(it.get("updated_at") or it.get("created_at"))
                )
                for it in inv_tickets if "id" in it and "ticket_code" in it
            ]
            if inv_tkt_records:
                cursor.executemany(inv_tkt_sql, inv_tkt_records)

        inv_hist = data.get("inventory_components_histories", [])
        if inv_hist:
            inv_h_sql = """
                INSERT IGNORE INTO inventory_components_histories
                (id, inventory_asset_id, action, component_type, old_value, new_value, reason, registered_by_user_id, recorded_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            inv_h_records = [
                (
                    ih["id"], ih["inventory_asset_id"], ih["action"], ih["component_type"],
                    json.dumps(ih.get("old_value")) if isinstance(ih.get("old_value"), (list, dict)) else ih.get("old_value"),
                    json.dumps(ih.get("new_value")) if isinstance(ih.get("new_value"), (list, dict)) else ih.get("new_value"),
                    ih.get("reason"), ih.get("registered_by_user_id"),
                    clean_mysql_dt(ih.get("recorded_at"))
                )
                for ih in inv_hist if "id" in ih and "inventory_asset_id" in ih
            ]
            if inv_h_records:
                cursor.executemany(inv_h_sql, inv_h_records)
