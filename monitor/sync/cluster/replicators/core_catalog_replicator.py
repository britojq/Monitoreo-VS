"""
# ==============================================================================
# 🏢 REPLICADOR DE CATÁLOGO TRONCAL: core_catalog_replicator.py
# Sincroniza Sedes, Servicios, Proxies y Equipos de Red troncales.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicators/core_catalog_replicator.py
# ==============================================================================
"""
from typing import Any, Dict

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt


class CoreCatalogReplicator(TableReplicatorProtocol):
    """Replicador atómico para monitored_sites, monitored_services, corporate_proxies y network_devices."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        """Sincroniza el catálogo base de red respetando la Regla de Oro #8."""
        cfg = data.get("config", data)
        total_synced = 0

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
                total_synced += len(st_records)

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
                total_synced += len(srv_records)

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
            if pxy_records:
                cursor.executemany(pxy_sql, pxy_records)
                total_synced += len(pxy_records)

        # 4. Dispositivos de Red (con blindaje de paridad Regla #8: Router ID 1 vs CENCARATIT ID 11519)
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
                11519: 1,
                11502: 8, 11503: 9, 11504: 10, 11505: 11, 11506: 12,
                11507: 13, 11508: 14, 11509: 15, 11510: 16, 11511: 17,
                11512: 18, 11513: 19, 11514: 20, 11515: 21, 11516: 22,
                11517: 23, 11518: 24,
            }
            net_dev_records = []
            for nd in cfg_net_devs:
                if "id" in nd and "name" in nd:
                    dev_id = nd["id"]
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
                total_synced += len(net_dev_records)

        return total_synced
