"""
Orquestador de inspección, auto-descubrimiento y sincronización MariaDB de certificados SSL.
"""
import json
import logging
from typing import Any, Dict, List
from urllib.parse import urlparse

from monitor.checkers.ssl.x509_parser import extract_cert_info
from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.ssl.orchestrator")


def auto_discover_https_services(conn=None) -> int:
    """Inspecciona monitored_services e identifica URLs HTTPS o puerto 443."""
    added_count = 0
    with db_cursor() as cur:
        cur.execute("""
            SELECT id, name, type, web_url, host_ip, port
            FROM monitored_services
            WHERE is_active = 1
        """)
        services = cur.fetchall()

        for s in services:
            url = (s.get("web_url") or "").strip()
            host_ip = (s.get("host_ip") or "").strip()
            explicit_port = s.get("port")
            domain = None
            detected_port = 443

            if url.lower().startswith("https://"):
                try:
                    parsed = urlparse(url)
                    domain = parsed.hostname
                    detected_port = parsed.port if parsed.port else 443
                except Exception:
                    pass
            elif explicit_port == 443 and host_ip and host_ip != "0.0.0.0":
                domain = host_ip
                detected_port = 443

            if domain:
                cur.execute("SELECT id FROM ssl_certificates WHERE domain = %s", (domain,))
                exists = cur.fetchone()
                if not exists:
                    cur.execute("""
                        INSERT INTO ssl_certificates (
                            service_id, domain, port, is_active, notes, created_at, updated_at
                        ) VALUES (%s, %s, %s, 1, %s, NOW(), NOW())
                    """, (s["id"], domain, detected_port, f"Descubierto automáticamente desde servicio: {s['name']}"))
                    added_count += 1
                    logger.info(f"✨ [AUTO-DISCOVERY] Registrado nuevo dominio SSL: {domain}:{detected_port}")
                else:
                    cur.execute("""
                        UPDATE ssl_certificates 
                        SET service_id = %s 
                        WHERE id = %s AND service_id IS NULL
                    """, (s["id"], exists["id"]))

    return added_count


def check_and_update_certificate(conn=None, cert_record: Dict[str, Any] = None, timeout: float = 5.0) -> Dict[str, Any]:
    """Inspecciona un certificado, compara contra historial y actualiza MariaDB."""
    cert_id = cert_record["id"]
    domain = cert_record["domain"]
    port = cert_record.get("port") or 443
    warn_threshold = cert_record.get("alert_threshold_warning") or 30

    logger.info(f"🔍 Inspeccionando SSL: {domain}:{port}...")
    info = extract_cert_info(domain, port=port, timeout=timeout)

    prev_fingerprint = cert_record.get("fingerprint_sha256")
    prev_valid_to = cert_record.get("valid_to")
    prev_status = cert_record.get("last_check_status")
    consecutive_errors = cert_record.get("consecutive_errors") or 0
    renewal_count = cert_record.get("renewal_count") or 0

    events_to_record = []

    with db_cursor() as cur:
        if info["success"]:
            new_fp = info["fingerprint_sha256"]
            new_valid_to = info["valid_to"]
            days_remaining = info["days_remaining"]

            if not prev_fingerprint:
                events_to_record.append({
                    "event_type": "initial_discovery",
                    "previous_fingerprint": None,
                    "new_fingerprint": new_fp,
                    "previous_valid_to": None,
                    "new_valid_to": new_valid_to,
                    "days_remaining_at_event": days_remaining,
                    "error_message": None,
                })
            elif prev_fingerprint and new_fp and prev_fingerprint != new_fp:
                renewal_count += 1
                events_to_record.append({
                    "event_type": "renewal",
                    "previous_fingerprint": prev_fingerprint,
                    "new_fingerprint": new_fp,
                    "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                    "new_valid_to": new_valid_to,
                    "days_remaining_at_event": days_remaining,
                    "error_message": f"Certificado renovado exitosamente. Nueva vigencia hasta {new_valid_to}",
                })
            elif prev_status == "error" or consecutive_errors > 0:
                events_to_record.append({
                    "event_type": "recovered",
                    "previous_fingerprint": prev_fingerprint,
                    "new_fingerprint": new_fp,
                    "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                    "new_valid_to": new_valid_to,
                    "days_remaining_at_event": days_remaining,
                    "error_message": "Servidor SSL recuperado.",
                })

            if days_remaining < 0:
                cur.execute("""
                    SELECT id FROM ssl_certificate_history 
                    WHERE ssl_certificate_id = %s AND event_type = 'expired'
                      AND occurred_at >= NOW() - INTERVAL 1 DAY
                """, (cert_id,))
                if not cur.fetchone():
                    events_to_record.append({
                        "event_type": "expired",
                        "previous_fingerprint": prev_fingerprint,
                        "new_fingerprint": new_fp,
                        "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                        "new_valid_to": new_valid_to,
                        "days_remaining_at_event": days_remaining,
                        "error_message": f"El certificado ha expirado hace {abs(days_remaining)} días.",
                    })
            elif days_remaining <= warn_threshold:
                cur.execute("""
                    SELECT id FROM ssl_certificate_history 
                    WHERE ssl_certificate_id = %s AND event_type = 'expiration_warning'
                      AND occurred_at >= NOW() - INTERVAL 1 DAY
                """, (cert_id,))
                if not cur.fetchone():
                    events_to_record.append({
                        "event_type": "expiration_warning",
                        "previous_fingerprint": prev_fingerprint,
                        "new_fingerprint": new_fp,
                        "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                        "new_valid_to": new_valid_to,
                        "days_remaining_at_event": days_remaining,
                        "error_message": f"Advertencia: el certificado expirará en {days_remaining} días.",
                    })

            if info["hostname_mismatch"]:
                cur.execute("""
                    SELECT id FROM ssl_certificate_history 
                    WHERE ssl_certificate_id = %s AND event_type = 'hostname_mismatch'
                      AND occurred_at >= NOW() - INTERVAL 1 DAY
                """, (cert_id,))
                if not cur.fetchone():
                    events_to_record.append({
                        "event_type": "hostname_mismatch",
                        "previous_fingerprint": prev_fingerprint,
                        "new_fingerprint": new_fp,
                        "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                        "new_valid_to": new_valid_to,
                        "days_remaining_at_event": days_remaining,
                        "error_message": f"Discrepancia: El dominio '{domain}' no coincide con CN.",
                    })

            cur.execute("""
                UPDATE ssl_certificates SET
                    subject_cn = %s,
                    subject_org = %s,
                    subject_ou = %s,
                    subject_country = %s,
                    subject_state = %s,
                    subject_locality = %s,
                    issuer_cn = %s,
                    issuer_org = %s,
                    issuer_country = %s,
                    serial_number = %s,
                    signature_algorithm = %s,
                    public_key_algorithm = %s,
                    public_key_bits = %s,
                    version = %s,
                    valid_from = %s,
                    valid_to = %s,
                    days_remaining = %s,
                    is_self_signed = %s,
                    is_wildcard = %s,
                    is_ev = %s,
                    san_entries = %s,
                    fingerprint_sha256 = %s,
                    fingerprint_sha1 = %s,
                    pem_certificate = %s,
                    last_checked_at = NOW(),
                    last_check_status = %s,
                    consecutive_errors = 0,
                    renewal_count = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (
                info["subject_cn"], info["subject_org"], info["subject_ou"],
                info["subject_country"], info["subject_state"], info["subject_locality"],
                info["issuer_cn"], info["issuer_org"], info["issuer_country"],
                info["serial_number"], info["signature_algorithm"], info["public_key_algorithm"],
                info["public_key_bits"], info["version"], info["valid_from"],
                info["valid_to"], info["days_remaining"], 1 if info["is_self_signed"] else 0,
                1 if info["is_wildcard"] else 0, 1 if info["is_ev"] else 0,
                json.dumps(info["san_entries"]), info["fingerprint_sha256"], info["fingerprint_sha1"],
                info["pem_certificate"], info["status"], renewal_count, cert_id,
            ))
        else:
            consecutive_errors += 1
            events_to_record.append({
                "event_type": "error",
                "previous_fingerprint": prev_fingerprint,
                "new_fingerprint": None,
                "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                "new_valid_to": None,
                "days_remaining_at_event": cert_record.get("days_remaining", 0),
                "error_message": info["error_message"] or "Fallo al conectar con servidor TLS.",
            })

            cur.execute("""
                UPDATE ssl_certificates SET
                    last_checked_at = NOW(),
                    last_check_status = 'error',
                    consecutive_errors = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (consecutive_errors, cert_id))

        for ev in events_to_record:
            cur.execute("""
                INSERT INTO ssl_certificate_history (
                    ssl_certificate_id, event_type, previous_fingerprint,
                    new_fingerprint, previous_valid_to, new_valid_to,
                    days_remaining_at_event, error_message, occurred_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW())
            """, (
                cert_id, ev["event_type"], ev["previous_fingerprint"],
                ev["new_fingerprint"], ev["previous_valid_to"], ev["new_valid_to"],
                ev["days_remaining_at_event"], ev["error_message"],
            ))

    return info


def run_all_ssl_checks(auto_discover: bool = True, timeout: float = 5.0) -> List[Dict[str, Any]]:
    """Ejecuta la rutina completa de verificación de certificados en MariaDB."""
    results = []
    if auto_discover:
        newly_added = auto_discover_https_services()
        if newly_added > 0:
            logger.info(f"Descubiertos {newly_added} nuevos servicios HTTPS.")

    with db_cursor() as cur:
        cur.execute("""
            SELECT id, service_id, domain, port, valid_to, days_remaining,
                   fingerprint_sha256, last_check_status, consecutive_errors,
                   renewal_count, alert_threshold_warning, alert_threshold_critical
            FROM ssl_certificates
            WHERE is_active = 1
            ORDER BY domain ASC
        """)
        certs = cur.fetchall()

    for cert in certs:
        res = check_and_update_certificate(cert_record=cert, timeout=timeout)
        results.append(res)

    return results


def print_cli_table(results: List[Dict[str, Any]]):
    """Imprime resumen en consola."""
    print("=" * 80)
    print("       MONITOREO AVANZADO DE CERTIFICADOS SSL/TLS - ATIT VALLE SECO      ")
    print("=" * 80)
    print(f"{'DOMINIO':<30} {'PUERTO':<7} {'ESTADO':<15} {'DÍAS':<8} {'VIGENCIA HASTA':<20}")
    print("-" * 80)
    for r in results:
        domain = r.get("domain", "N/A")[:29]
        port = str(r.get("port", 443))
        status = r.get("status", "error").upper()
        days = str(r.get("days_remaining", "N/A"))
        valid_to = str(r.get("valid_to", "N/A"))[:19]
        print(f"{domain:<30} {port:<7} {status:<15} {days:<8} {valid_to:<20}")
    print("=" * 80)
