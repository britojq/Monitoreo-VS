"""
Orquestador de persistencia, detección de cambios y ejecución de respaldos WAN/LAN.
"""
import asyncio
from datetime import datetime
import logging
from typing import Any, Dict, List, Optional

from monitor.checkers.backup.cisco_ssh_client import (
    calculate_hash,
    fetch_cisco_config_ssh,
    normalize_config,
)
from monitor.checkers.backup.pfsense_ssh_client import fetch_pfsense_config_ssh
from monitor.checkers.backup.unified_diff_engine import generate_unified_diff
from monitor.infrastructure.db_context import db_cursor
from monitor.infrastructure.laravel_encryptor import decrypt_laravel_string

logger = logging.getLogger("monitor.checkers.backup.orchestrator")


def save_device_backup(
    raw_config: str,
    device_name: str,
    device_ip: str,
    device_type: str = "cisco_switch",
    network_device_id: Optional[int] = None,
    snmp_device_id: Optional[int] = None,
    captured_by: str = "cron",
    notes: Optional[str] = None
) -> Dict[str, Any]:
    """Procesa, normaliza, compara y persiste la configuración del dispositivo."""
    norm_config = normalize_config(raw_config, device_type)
    new_hash = calculate_hash(norm_config)
    size_bytes = len(norm_config.encode("utf-8"))
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    with db_cursor() as cur:
        query_parts = []
        params = []
        if snmp_device_id:
            query_parts.append("snmp_device_id = %s")
            params.append(snmp_device_id)
        elif network_device_id:
            query_parts.append("network_device_id = %s")
            params.append(network_device_id)
        else:
            query_parts.append("device_ip = %s")
            params.append(device_ip)

        where_clause = " OR ".join(query_parts)
        cur.execute(
            f"""
            SELECT id, config_hash, config_text, captured_at, status
            FROM device_configurations
            WHERE ({where_clause}) AND status = 'success'
            ORDER BY captured_at DESC
            LIMIT 1
            """,
            tuple(params)
        )
        last_backup = cur.fetchone()

        if not last_backup:
            cur.execute(
                """
                INSERT INTO device_configurations
                (network_device_id, snmp_device_id, device_name, device_ip, device_type,
                 config_text, config_hash, config_size_bytes, captured_at, captured_by, status, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'success', %s, %s, %s)
                """,
                (
                    network_device_id, snmp_device_id, device_name, device_ip, device_type,
                    norm_config, new_hash, size_bytes, now, captured_by, notes, now, now
                )
            )
            new_id = cur.lastrowid

            cur.execute(
                """
                INSERT INTO config_change_logs
                (device_configuration_id, previous_config_id, network_device_id, snmp_device_id,
                 change_type, diff_summary, diff_unified, lines_added, lines_removed, detected_at, alerted, created_at, updated_at)
                VALUES (%s, NULL, %s, %s, 'initial', %s, NULL, %s, 0, %s, 0, %s, %s)
                """,
                (
                    new_id, network_device_id, snmp_device_id,
                    f"Línea base inicial registrada ({size_bytes} bytes)",
                    norm_config.count("\n"), now, now, now
                )
            )
            logger.info(f"💾 Línea base creada para {device_name} ({device_ip}) - ID #{new_id}")
            return {
                "action": "initial_baseline",
                "changed": False,
                "configuration_id": new_id,
                "hash": new_hash,
                "size_bytes": size_bytes,
                "message": "Línea base inicial establecida con éxito."
            }

        if last_backup["config_hash"] == new_hash:
            logger.info(f"✅ Sin cambios en {device_name} ({device_ip}) - Hash: {new_hash[:12]}...")
            return {
                "action": "no_change",
                "changed": False,
                "configuration_id": last_backup["id"],
                "hash": new_hash,
                "size_bytes": size_bytes,
                "message": "La configuración no ha sufrido modificaciones."
            }

        prev_id = last_backup["id"]
        prev_text = last_backup["config_text"] or ""
        prev_date = last_backup["captured_at"].strftime("%d/%m/%Y %H:%M") if last_backup.get("captured_at") else "Previa"

        diff_unified, lines_added, lines_removed = generate_unified_diff(
            prev_text,
            norm_config,
            label_old=f"{device_name} ({prev_date})",
            label_new=f"{device_name} ({now})"
        )

        cur.execute(
            f"""
            SELECT id, captured_at FROM device_configurations
            WHERE ({where_clause}) AND config_hash = %s AND id != %s
            LIMIT 1
            """,
            tuple(params) + (new_hash, prev_id)
        )
        reverted_row = cur.fetchone()
        change_type = "reverted" if reverted_row else "modified"
        diff_summary = (
            f"Reversión a configuración del {reverted_row['captured_at']} (+{lines_added}/-{lines_removed})"
            if reverted_row else
            f"Configuración modificada: +{lines_added} líneas añadidas, -{lines_removed} líneas eliminadas"
        )

        cur.execute(
            """
            INSERT INTO device_configurations
            (network_device_id, snmp_device_id, device_name, device_ip, device_type,
             config_text, config_hash, config_size_bytes, captured_at, captured_by, status, notes, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'success', %s, %s, %s)
            """,
            (
                network_device_id, snmp_device_id, device_name, device_ip, device_type,
                norm_config, new_hash, size_bytes, now, captured_by, notes, now, now
            )
        )
        new_id = cur.lastrowid

        cur.execute(
            """
            INSERT INTO config_change_logs
            (device_configuration_id, previous_config_id, network_device_id, snmp_device_id,
             change_type, diff_summary, diff_unified, lines_added, lines_removed, detected_at, alerted, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 0, %s, %s)
            """,
            (
                new_id, prev_id, network_device_id, snmp_device_id,
                change_type, diff_summary, diff_unified, lines_added, lines_removed, now, now, now
            )
        )
        log_id = cur.lastrowid
        logger.warning(f"⚠️ CAMBIO DETECTADO en {device_name} ({device_ip}): {diff_summary} (Log #{log_id})")

        return {
            "action": change_type,
            "changed": True,
            "configuration_id": new_id,
            "previous_id": prev_id,
            "change_log_id": log_id,
            "hash": new_hash,
            "lines_added": lines_added,
            "lines_removed": lines_removed,
            "diff_summary": diff_summary,
            "diff_unified": diff_unified,
            "message": diff_summary
        }


def record_backup_failure(
    device_name: str,
    device_ip: str,
    device_type: str,
    error_message: str,
    network_device_id: Optional[int] = None,
    snmp_device_id: Optional[int] = None,
    captured_by: str = "cron"
) -> None:
    """Registra un intento fallido de respaldo en MariaDB."""
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    with db_cursor() as cur:
        cur.execute(
            """
            INSERT INTO device_configurations
            (network_device_id, snmp_device_id, device_name, device_ip, device_type,
             config_text, config_hash, config_size_bytes, captured_at, captured_by, status, error_message, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, '', '', 0, %s, %s, 'failed', %s, %s, %s)
            """,
            (
                network_device_id, snmp_device_id, device_name, device_ip, device_type,
                now, captured_by, error_message[:500], now, now
            )
        )


async def backup_target_device(target: Dict[str, Any], captured_by: str = "cron") -> Dict[str, Any]:
    """Ejecuta el respaldo de un dispositivo según credenciales y tipo."""
    ip = target.get("ip_address") or target.get("ip")
    name = target.get("name") or ip
    dev_type = target.get("device_type", "cisco_switch")
    snmp_id = target.get("snmp_device_id") or (target.get("id") if "snmp_version" in target else None)
    net_id = target.get("network_device_id") or (target.get("id") if "access_type" in target else None)

    ssh_user = target.get("ssh_username")
    raw_pass_enc = target.get("ssh_password_encrypted")
    raw_sec_enc = target.get("ssh_enable_secret_encrypted")

    ssh_pass = target.get("ssh_password_decrypted") or (decrypt_laravel_string(raw_pass_enc) if raw_pass_enc else "") or target.get("ssh_password") or ""
    ssh_secret = target.get("ssh_enable_secret_decrypted") or (decrypt_laravel_string(raw_sec_enc) if raw_sec_enc else "") or target.get("ssh_enable_secret") or None
    ssh_port = int(target.get("access_port") or target.get("ssh_port") or 22)

    if not ssh_user or not ssh_pass:
        err = f"Dispositivo '{name}' ({ip}) no cuenta con credenciales SSH configuradas."
        logger.warning(f"⚠️ {err}")
        record_backup_failure(name, ip, dev_type, err, net_id, snmp_id, captured_by)
        return {"action": "failed", "changed": False, "error": err, "device": name}

    if "cisco" in dev_type.lower() or dev_type in ("router", "switch", "cisco_switch", "cisco_router"):
        ok, cfg_text, err = await fetch_cisco_config_ssh(ip, ssh_user, ssh_pass, port=ssh_port, secret=ssh_secret)
        if ok:
            return save_device_backup(
                raw_config=cfg_text,
                device_name=name,
                device_ip=ip,
                device_type="cisco_switch" if "switch" in dev_type.lower() else "cisco_router",
                network_device_id=net_id,
                snmp_device_id=snmp_id,
                captured_by=captured_by
            )
        else:
            record_backup_failure(name, ip, dev_type, err, net_id, snmp_id, captured_by)
            return {"action": "failed", "changed": False, "error": err, "device": name}

    elif "pfsense" in dev_type.lower() or dev_type == "firewall":
        ok, cfg_text, err = await fetch_pfsense_config_ssh(ip, ssh_user, ssh_pass, port=ssh_port)
        if ok:
            return save_device_backup(
                raw_config=cfg_text,
                device_name=name,
                device_ip=ip,
                device_type="pfsense",
                network_device_id=net_id,
                snmp_device_id=snmp_id,
                captured_by=captured_by
            )
        else:
            record_backup_failure(name, ip, dev_type, err, net_id, snmp_id, captured_by)
            return {"action": "failed", "changed": False, "error": err, "device": name}

    return {"action": "skipped", "changed": False, "error": "Tipo no soportado", "device": name}


def backup_network_device_by_id(net_id: int, captured_by: str = "manual") -> Dict[str, Any]:
    with db_cursor() as cur:
        cur.execute(
            "SELECT id, name, ip, model, access_type, access_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
            "FROM monitored_network_devices WHERE id = %s",
            (net_id,)
        )
        dev = cur.fetchone()
    if not dev:
        return {"action": "failed", "error": f"Dispositivo #{net_id} no encontrado."}

    dev_type = "cisco_router" if "router" in ((dev.get("name") or "") + " " + (dev.get("model") or "")).lower() else "cisco_switch"
    dev["device_type"] = dev_type
    dev["network_device_id"] = dev["id"]
    return asyncio.run(backup_target_device(dev, captured_by=captured_by))


def backup_snmp_device_by_id(snmp_id: int, captured_by: str = "manual") -> Dict[str, Any]:
    with db_cursor() as cur:
        cur.execute(
            "SELECT id, name, ip_address, device_type, ssh_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
            "FROM snmp_devices WHERE id = %s",
            (snmp_id,)
        )
        dev = cur.fetchone()
    if not dev:
        return {"action": "failed", "error": f"Dispositivo SNMP #{snmp_id} no encontrado."}

    dev["snmp_device_id"] = dev["id"]
    return asyncio.run(backup_target_device(dev, captured_by=captured_by))


def backup_all_configured_devices(captured_by: str = "cron") -> List[Dict[str, Any]]:
    results = []
    with db_cursor() as cur:
        cur.execute(
            "SELECT id, name, ip, model, access_type, access_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
            "FROM monitored_network_devices WHERE is_active = 1 AND ssh_username IS NOT NULL AND ssh_username != ''"
        )
        net_devices = cur.fetchall()

        cur.execute(
            "SELECT id, name, ip_address, device_type, ssh_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
            "FROM snmp_devices WHERE is_active = 1 AND ssh_username IS NOT NULL AND ssh_username != ''"
        )
        snmp_devices = cur.fetchall()

    for d in net_devices:
        dev_type = "cisco_router" if "router" in ((d.get("name") or "") + " " + (d.get("model") or "")).lower() else "cisco_switch"
        d["device_type"] = dev_type
        d["network_device_id"] = d["id"]
        res = asyncio.run(backup_target_device(d, captured_by=captured_by))
        results.append(res)

    for d in snmp_devices:
        d["snmp_device_id"] = d["id"]
        res = asyncio.run(backup_target_device(d, captured_by=captured_by))
        results.append(res)

    return results


def get_backups_summary() -> Dict[str, Any]:
    with db_cursor() as cur:
        cur.execute("SELECT COUNT(DISTINCT device_ip) as total_devices FROM device_configurations WHERE status = 'success'")
        tot_dev = cur.fetchone()["total_devices"]

        cur.execute("SELECT COUNT(*) as total_backups FROM device_configurations WHERE status = 'success'")
        tot_bkups = cur.fetchone()["total_backups"]

        cur.execute("SELECT COUNT(*) as backups_today FROM device_configurations WHERE DATE(captured_at) = CURDATE()")
        bkups_today = cur.fetchone()["backups_today"]

        cur.execute("SELECT COUNT(*) as total_changes FROM config_change_logs WHERE change_type = 'modified'")
        tot_changes = cur.fetchone()["total_changes"]

        cur.execute("SELECT IFNULL(SUM(config_size_bytes), 0) as total_bytes FROM device_configurations")
        tot_bytes = cur.fetchone()["total_bytes"]

    return {
        "total_devices": tot_dev,
        "total_backups": tot_bkups,
        "backups_today": bkups_today,
        "total_changes": tot_changes,
        "total_size_bytes": tot_bytes,
        "total_size_formatted": f"{round(tot_bytes / 1024, 1)} KB" if tot_bytes < 1048576 else f"{round(tot_bytes / 1048576, 2)} MB"
    }


def list_latest_backups(limit: int = 30) -> List[Dict[str, Any]]:
    with db_cursor() as cur:
        cur.execute(
            f"""
            SELECT c.id, c.device_name, c.device_ip, c.device_type, c.config_hash,
                   c.config_size_bytes, c.captured_at, c.captured_by, c.status, c.notes,
                   l.change_type, l.diff_summary, l.lines_added, l.lines_removed
            FROM device_configurations c
            LEFT JOIN config_change_logs l ON l.device_configuration_id = c.id
            ORDER BY c.captured_at DESC
            LIMIT {limit}
            """
        )
        return cur.fetchall()
