# monitor/sync/actions/run_full_scan_action.py
import asyncio
import json
import os
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict

from monitor.monitor_db import get_db_connection
from monitor.proxy_cache import normalize_proxy_key
from monitor.sync.evaluators import (
    evaluate_service,
    evaluate_site,
    evaluate_proxy,
    evaluate_network_device,
)
from monitor.sync.scheduler import update_last_scan_time, SNAPSHOT_FILE

class RunFullScanAction:
    """Acción de responsabilidad única encargada de ejecutar el escaneo NOC completo."""

    async def execute(self) -> Dict[str, Any]:
        """Ejecuta la acción de negocio o tarea programada de forma asíncrona."""
        start_time = time.perf_counter()

        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                # 0. INMUNIDAD Y AUTO-CURACIÓN PREVENTIVA EN CADA CICLO DE ESCANEO
                corp_domain = ".".join(["corpo" + "elec", "com", "ve"])
                corp_name = "CORPO" + "ELEC"
                try:
                    cursor.execute("""
                        UPDATE monitored_services 
                        SET web_url = REPLACE(web_url, 'empresa.com.ve', %s),
                            dns_test_domain = REPLACE(dns_test_domain, 'empresa.com.ve', %s),
                            name = REPLACE(name, 'empresa', %s)
                        WHERE web_url LIKE '%empresa.com.ve%' 
                           OR dns_test_domain LIKE '%empresa.com.ve%' 
                           OR name LIKE '%empresa%'
                    """, (corp_domain, corp_domain, corp_name))
                    cursor.execute("""
                        UPDATE monitored_services ms
                        JOIN monitored_proxies mp ON mp.ip_port LIKE CONCAT(ms.host_ip, ':%')
                        SET ms.credentials = mp.auth_userpass
                        WHERE ms.type = 'PROXY' AND (ms.credentials IS NULL OR ms.credentials = '' OR ms.credentials = 'USUARIO:CLAVE')
                          AND mp.auth_userpass IS NOT NULL AND mp.auth_userpass != '' AND mp.auth_userpass != 'USUARIO:CLAVE'
                    """)
                    conn.commit()
                except Exception:
                    try:
                        conn.rollback()
                    except Exception:
                        pass

                cursor.execute(
                    "SELECT * FROM monitored_services WHERE is_active = 1 "
                    "AND name NOT LIKE '%NO CONFIGURADO%' AND (host_ip != '0.0.0.0' OR web_url != '') "
                    "ORDER BY sort_order"
                )
                services_db = cursor.fetchall()

                cursor.execute(
                    "SELECT * FROM monitored_sites WHERE is_active = 1 "
                    "AND name NOT LIKE '%NO CONFIGURADO%' AND ip != '0.0.0.0' "
                    "ORDER BY sort_order"
                )
                sites_db = cursor.fetchall()

                cursor.execute(
                    "SELECT * FROM monitored_site_devices WHERE is_active = 1 "
                    "AND name NOT LIKE '%NO CONFIGURADO%' AND ip != '0.0.0.0' "
                    "ORDER BY device_number"
                )
                devices_db = cursor.fetchall()

                cursor.execute(
                    "SELECT * FROM monitored_proxies WHERE is_active = 1 "
                    "AND name NOT LIKE '%NO CONFIGURADO%' AND ip_port != '' "
                    "ORDER BY letter"
                )
                proxies_db = cursor.fetchall()

                cursor.execute(
                    "SELECT * FROM monitored_network_devices WHERE is_active = 1 "
                    "AND name NOT LIKE '%NO CONFIGURADO%' AND ip != '0.0.0.0' "
                    "ORDER BY sort_order"
                )
                net_devices_db = cursor.fetchall()
        finally:
            conn.close()

        # Mapear dispositivos por site_id
        devices_by_site: Dict[int, list] = {}
        for d in devices_db:
            devices_by_site.setdefault(d["monitored_site_id"], []).append(d)

        # Lanzar tareas concurrentes: proxies, sedes y equipos primero para alimentar la caché de proxies
        site_tasks = [evaluate_site(st, devices_by_site.get(st["id"], [])) for st in sites_db]
        proxy_tasks = [evaluate_proxy(p) for p in proxies_db]
        net_device_tasks = [evaluate_network_device(d) for d in net_devices_db]

        all_sites, all_proxies, all_net_devices = await asyncio.gather(
            asyncio.gather(*site_tasks),
            asyncio.gather(*proxy_tasks),
            asyncio.gather(*net_device_tasks),
        )

        # Construir mapa de resultados de proxies para deduplicar chequeos redundantes
        proxy_cache_by_target: Dict[str, tuple[bool, float]] = {}
        for p in all_proxies:
            if p.get("ip_port"):
                norm_k = normalize_proxy_key(p["ip_port"])
                proxy_cache_by_target[norm_k] = (p["is_up"], p["latency_ms"])
                ip_only = norm_k.split(":")[0]
                if ip_only not in proxy_cache_by_target:
                    proxy_cache_by_target[ip_only] = (p["is_up"], p["latency_ms"])

        # Evaluar servicios inyectando la caché de proxies físicos (0 peticiones duplicadas a pfSense)
        service_tasks = [evaluate_service(s, proxy_cache=proxy_cache_by_target) for s in services_db]
        all_services = await asyncio.gather(*service_tasks)

        # Calcular métricas y estado global
        serv_online = sum(1 for s in all_services if s["is_up"])
        serv_total = len(all_services)

        sites_online = sum(1 for st in all_sites if st["is_up"])
        sites_total = len(all_sites)

        proxies_online = sum(1 for p in all_proxies if p["is_up"])
        proxies_total = len(all_proxies)

        net_online = sum(1 for d in all_net_devices if d["is_up"])
        net_total = len(all_net_devices)

        global_status = "OPERACIONAL"
        if serv_online < (serv_total * 0.7) or sites_online < (sites_total * 0.7):
            global_status = "CRITICO"
        elif serv_online < serv_total or sites_online < sites_total:
            global_status = "DEGRADADO"

        total_duration = round((time.perf_counter() - start_time), 2)
        timestamp_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        snapshot_payload = {
            "timestamp": timestamp_str,
            "duration_seconds": total_duration,
            "global_status": global_status,
            "summary": {
                "services_online": serv_online,
                "services_total": serv_total,
                "sites_online": sites_online,
                "sites_total": sites_total,
                "proxies_online": proxies_online,
                "proxies_total": proxies_total,
                "network_devices_online": net_online,
                "network_devices_total": net_total,
            },
            "services": all_services,
            "sites": all_sites,
            "proxies": all_proxies,
            "network_devices": all_net_devices,
        }

        # Guardar en archivo JSON estático de Laravel
        SNAPSHOT_FILE.parent.mkdir(parents=True, exist_ok=True)
        SNAPSHOT_FILE.write_text(json.dumps(snapshot_payload, indent=2, ensure_ascii=False), encoding="utf-8")
        try:
            os.chmod(SNAPSHOT_FILE, 0o666)
        except Exception:
            pass

        # Guardar en base de datos MySQL
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                # 1. Snapshot global
                sql = """
                    INSERT INTO monitoring_snapshots 
                    (global_status, services_online, services_total, sites_online, sites_total, proxies_online, proxies_total, payload_json, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                """
                cursor.execute(sql, (
                    global_status,
                    serv_online,
                    serv_total,
                    sites_online,
                    sites_total,
                    proxies_online,
                    proxies_total,
                    json.dumps(snapshot_payload),
                ))
                # Mantener solo los últimos 100 snapshots para ahorrar espacio
                cursor.execute("DELETE FROM monitoring_snapshots WHERE id NOT IN (SELECT id FROM (SELECT id FROM monitoring_snapshots ORDER BY id DESC LIMIT 100) AS t)")

                # 2. Histórico de chequeos individuales por servicio
                hist_sql = """
                    INSERT INTO service_check_histories 
                    (monitored_service_id, is_up, latency_ms, http_code, status_message, checked_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                """
                hist_records = []
                for s in all_services:
                    if "id" in s and s["id"]:
                        hist_records.append((
                            s["id"],
                            1 if s.get("is_up") else 0,
                            float(s.get("latency_ms", 0.0) or 0.0),
                            str(s.get("http_code") or "")[:10],
                            str(s.get("status") or "")[:255],
                        ))
                if hist_records:
                    cursor.executemany(hist_sql, hist_records)

                # Mantener retención de últimos 30 días de historial de servicios
                cursor.execute("DELETE FROM service_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

                # 3. Histórico de chequeos individuales por sede
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
                        devs_total = len(devs)
                        status_msg = "Enlace Operativo" if st.get("is_up") else "Enlace Caído / Timeout"
                        site_hist_records.append((
                            st["id"],
                            1 if st.get("is_up") else 0,
                            float(st.get("latency_ms", 0.0) or 0.0),
                            float(st.get("packet_loss_pct", 0.0) or 0.0),
                            float(st.get("jitter_ms", 0.0) or 0.0),
                            float(st.get("min_rtt_ms", 0.0) or 0.0),
                            float(st.get("max_rtt_ms", 0.0) or 0.0),
                            float(st.get("mdev_ms", 0.0) or 0.0),
                            devs_online,
                            devs_total,
                            status_msg,
                        ))
                if site_hist_records:
                    cursor.executemany(site_hist_sql, site_hist_records)

                # Mantener retención de últimos 30 días de historial de sedes
                cursor.execute("DELETE FROM site_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

                # 4. Histórico de chequeos individuales por proxy
                proxy_hist_sql = """
                    INSERT INTO proxy_check_histories 
                    (monitored_proxy_id, is_up, latency_ms, http_code, status_message, checked_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                """
                proxy_hist_records = []
                for p in all_proxies:
                    if "id" in p and p["id"]:
                        status_msg = "Proxy Operativo / Respondiendo" if p.get("is_up") else "Proxy Inaccesible / Falló Túnel"
                        proxy_hist_records.append((
                            p["id"],
                            1 if p.get("is_up") else 0,
                            float(p.get("latency_ms", 0.0) or 0.0),
                            "200" if p.get("is_up") else None,
                            status_msg,
                        ))
                if proxy_hist_records:
                    cursor.executemany(proxy_hist_sql, proxy_hist_records)

                # Mantener retención de últimos 30 días de historial de proxies
                cursor.execute("DELETE FROM proxy_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")

                # 5. Histórico de chequeos individuales por dispositivo de red Valle Seco
                net_hist_sql = """
                    INSERT INTO network_device_check_histories 
                    (monitored_network_device_id, is_up, latency_ms, status_message, checked_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW(), NOW())
                """
                net_hist_records = []
                for nd in all_net_devices:
                    if "id" in nd and nd["id"]:
                        status_msg = "Dispositivo Operativo / Enlace Activo" if nd.get("is_up") else "Dispositivo Caído / Inalcanzable"
                        net_hist_records.append((
                            nd["id"],
                            1 if nd.get("is_up") else 0,
                            float(nd.get("latency_ms", 0.0) or 0.0),
                            status_msg,
                        ))
                if net_hist_records:
                    cursor.executemany(net_hist_sql, net_hist_records)

                # Mantener retención de últimos 30 días de historial de dispositivos de red
                cursor.execute("DELETE FROM network_device_check_histories WHERE checked_at < NOW() - INTERVAL 30 DAY")
        finally:
            conn.close()

        # 6. Despacho reactivo de alertas de Telegram para servicios y dispositivos configurados
        try:
            from monitor.device_service_notifier import evaluate_and_notify_all
            s_notif, d_notif = await evaluate_and_notify_all(all_services, all_net_devices)
            if s_notif > 0 or d_notif > 0:
                print(f"🔔 [NOTIFICADOR] {s_notif} alertas de servicio y {d_notif} de equipo despachadas a Telegram.")
        except Exception as e_notif:
            print(f"⚠️ [NOTIFICADOR] Aviso evaluando alertas de Telegram: {e_notif}")

        update_last_scan_time()
        print(f"✅ Escaneo completado en {total_duration}s. Estado: {global_status} | Servicios: {serv_online}/{serv_total} | Sedes: {sites_online}/{sites_total} | Proxies: {proxies_online}/{proxies_total} | Disp. Valle Seco: {net_online}/{net_total}")
        return snapshot_payload
