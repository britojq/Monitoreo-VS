"""
# ==============================================================================
# 🔒 MOTOR DE INSPECCIÓN Y MONITOREO SSL/TLS: ssl_checker.py (@IA_ValleSeco_bot)
# Monitoreo de vigencia, cadena y renovaciones de certificados SSL/TLS
# Ubicación: /scripts/telegram-admin-bot/monitor/ssl_checker.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import fnmatch
import json
import logging
import os
import re
import socket
import ssl
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import urlparse

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import pymysql
from cryptography import x509
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import ec, rsa
from cryptography.x509.oid import ExtensionOID, NameOID

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [ssl.checker] %(message)s",
    handlers=[
        logging.FileHandler(LOG_DIR / "ssl_checker.log", encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("ssl.checker")


# Conector centralizado a base de datos
from monitor.monitor_db import get_db_connection


def create_permissive_ssl_context() -> ssl.SSLContext:
    """
    Crea un contexto SSL permisivo y compatible con servidores legacy y corporativos
    (TLS 1.0+, ciphers antiguos, certificados autofirmados o de CAs internas).
    """
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    try:
        import warnings
        with warnings.catch_warnings():
            warnings.simplefilter("ignore", DeprecationWarning)
            ctx.minimum_version = ssl.TLSVersion.TLSv1
    except Exception:
        pass
    return ctx



def match_hostname(hostname: str, san_entries: List[str], subject_cn: Optional[str]) -> bool:
    """
    Verifica si el hostname coincide con el CN o con cualquiera de las entradas SAN,
    incluyendo soporte para comodines (wildcard).
    """
    hostname = hostname.lower().strip()
    candidates = []
    if subject_cn:
        candidates.append(subject_cn.lower().strip())
    for san in san_entries:
        if san:
            candidates.append(san.lower().strip())

    for pattern in candidates:
        if pattern == hostname:
            return True
        if pattern.startswith("*."):
            base_pattern = pattern[2:]
            # e.g. *.empresa.com.ve matches sub.empresa.com.ve
            if fnmatch.fnmatch(hostname, pattern):
                return True
            # Also matches base domain if allowed
            if hostname == base_pattern:
                return True
    return False


def extract_cert_info(hostname: str, port: int = 443, timeout: float = 5.0) -> Dict[str, Any]:
    """
    Conecta al servicio mediante TLS/SSL con SNI y extrae la información integral del certificado:
    - Subject (CN, O, OU, C, ST, L)
    - Issuer (CN, O, C)
    - Fechas de vigencia (valid_from, valid_to, days_remaining)
    - Número de serie, algoritmos y longitud de clave
    - SAN entries (Subject Alternative Names)
    - Detección de autofirmado, comodín (wildcard) y EV
    - Huellas digitales SHA-256 y SHA-1
    - Certificado PEM
    """
    hostname = hostname.strip()
    result = {
        "success": False,
        "domain": hostname,
        "port": port,
        "subject_cn": None,
        "subject_org": None,
        "subject_ou": None,
        "subject_country": None,
        "subject_state": None,
        "subject_locality": None,
        "issuer_cn": None,
        "issuer_org": None,
        "issuer_country": None,
        "serial_number": None,
        "signature_algorithm": None,
        "public_key_algorithm": None,
        "public_key_bits": None,
        "version": None,
        "valid_from": None,
        "valid_to": None,
        "days_remaining": 0,
        "is_self_signed": False,
        "is_wildcard": False,
        "is_ev": False,
        "san_entries": [],
        "fingerprint_sha256": None,
        "fingerprint_sha1": None,
        "pem_certificate": None,
        "tls_version": None,
        "cipher_suite": None,
        "hostname_mismatch": False,
        "status": "error",
        "error_message": None,
    }

    ctx = create_permissive_ssl_context()

    try:
        # Resolver socket directo
        with socket.create_connection((hostname, port), timeout=timeout) as sock:
            with ctx.wrap_socket(sock, server_hostname=hostname) as ssock:
                der_cert = ssock.getpeercert(binary_form=True)
                if not der_cert:
                    result["error_message"] = "No se recibió certificado del servidor remoto."
                    return result

                result["tls_version"] = ssock.version()
                cipher = ssock.cipher()
                if cipher:
                    result["cipher_suite"] = f"{cipher[0]} ({cipher[1]})"

                # Convertir a PEM
                pem_cert = ssl.DER_cert_to_PEM_cert(der_cert)
                result["pem_certificate"] = pem_cert

                # Cargar con cryptography
                cert = x509.load_der_x509_certificate(der_cert, default_backend())

                # Subject
                for attr in cert.subject:
                    oid = attr.oid
                    if oid == NameOID.COMMON_NAME:
                        result["subject_cn"] = str(attr.value)
                    elif oid == NameOID.ORGANIZATION_NAME:
                        result["subject_org"] = str(attr.value)
                    elif oid == NameOID.ORGANIZATIONAL_UNIT_NAME:
                        result["subject_ou"] = str(attr.value)
                    elif oid == NameOID.COUNTRY_NAME:
                        result["subject_country"] = str(attr.value)
                    elif oid == NameOID.STATE_OR_PROVINCE_NAME:
                        result["subject_state"] = str(attr.value)
                    elif oid == NameOID.LOCALITY_NAME:
                        result["subject_locality"] = str(attr.value)

                # Issuer
                for attr in cert.issuer:
                    oid = attr.oid
                    if oid == NameOID.COMMON_NAME:
                        result["issuer_cn"] = str(attr.value)
                    elif oid == NameOID.ORGANIZATION_NAME:
                        result["issuer_org"] = str(attr.value)
                    elif oid == NameOID.COUNTRY_NAME:
                        result["issuer_country"] = str(attr.value)

                # Serial Number formateado en HEX
                serial_int = cert.serial_number
                serial_hex = f"{serial_int:X}"
                if len(serial_hex) % 2 != 0:
                    serial_hex = "0" + serial_hex
                result["serial_number"] = ":".join(serial_hex[i:i+2] for i in range(0, len(serial_hex), 2))

                # Algoritmos y clave
                try:
                    result["signature_algorithm"] = cert.signature_algorithm_oid._name
                except Exception:
                    result["signature_algorithm"] = str(cert.signature_algorithm_oid.dotted_string)

                pubkey = cert.public_key()
                if isinstance(pubkey, rsa.RSAPublicKey):
                    result["public_key_algorithm"] = "RSA"
                    result["public_key_bits"] = pubkey.key_size
                elif isinstance(pubkey, ec.EllipticCurvePublicKey):
                    result["public_key_algorithm"] = f"EC ({pubkey.curve.name})"
                    result["public_key_bits"] = pubkey.key_size
                else:
                    result["public_key_algorithm"] = type(pubkey).__name__
                    result["public_key_bits"] = getattr(pubkey, "key_size", None)

                # Versión
                result["version"] = getattr(cert.version, "value", 3)

                # Fechas
                try:
                    valid_from_dt = cert.not_valid_before_utc
                except AttributeError:
                    valid_from_dt = cert.not_valid_before

                try:
                    valid_to_dt = cert.not_valid_after_utc
                except AttributeError:
                    valid_to_dt = cert.not_valid_after

                if valid_from_dt:
                    result["valid_from"] = valid_from_dt.strftime("%Y-%m-%d %H:%M:%S")
                if valid_to_dt:
                    result["valid_to"] = valid_to_dt.strftime("%Y-%m-%d %H:%M:%S")

                # Días restantes
                now_utc = datetime.now(timezone.utc)
                if valid_to_dt:
                    if valid_to_dt.tzinfo is None:
                        valid_to_aware = valid_to_dt.replace(tzinfo=timezone.utc)
                    else:
                        valid_to_aware = valid_to_dt
                    delta = valid_to_aware - now_utc
                    result["days_remaining"] = delta.days

                # SAN (Subject Alternative Names)
                san_list = []
                try:
                    ext = cert.extensions.get_extension_for_oid(ExtensionOID.SUBJECT_ALTERNATIVE_NAME)
                    san_ext = ext.value
                    for name in san_ext.get_values_for_type(x509.DNSName):
                        san_list.append(name)
                    for ip in san_ext.get_values_for_type(x509.IPAddress):
                        san_list.append(str(ip))
                except Exception:
                    pass
                result["san_entries"] = san_list

                # Autofirmado: Subject y Issuer idénticos
                result["is_self_signed"] = bool(cert.issuer == cert.subject)

                # Comodín (Wildcard)
                is_wild = False
                if result["subject_cn"] and result["subject_cn"].startswith("*."):
                    is_wild = True
                for s in san_list:
                    if s.startswith("*."):
                        is_wild = True
                        break
                result["is_wildcard"] = is_wild

                # Huellas digitales
                result["fingerprint_sha256"] = cert.fingerprint(hashes.SHA256()).hex().upper()
                result["fingerprint_sha1"] = cert.fingerprint(hashes.SHA1()).hex().upper()

                # Extended Validation (EV)
                is_ev = False
                try:
                    policies_ext = cert.extensions.get_extension_for_oid(ExtensionOID.CERTIFICATE_POLICIES)
                    for policy in policies_ext.value:
                        p_str = str(policy.policy_identifier.dotted_string)
                        # OIDs conocidos de EV de CAs globales o indicadores
                        if any(ev_kw in p_str for ev_kw in ["2.16.840.1.114412", "2.16.840.1.114413", "1.3.6.1.4.1.6449", "2.16.840.1.114028"]):
                            is_ev = True
                            break
                except Exception:
                    pass
                result["is_ev"] = is_ev

                # Validación de correspondencia de Hostname
                is_matched = match_hostname(hostname, san_list, result["subject_cn"])
                result["hostname_mismatch"] = not is_matched

                # Determinar status final
                if result["days_remaining"] < 0:
                    result["status"] = "expired"
                elif result["hostname_mismatch"]:
                    result["status"] = "hostname_mismatch"
                elif result["days_remaining"] <= 30:
                    result["status"] = "expiring_soon"
                else:
                    result["status"] = "success"

                result["success"] = True

    except Exception as e:
        result["error_message"] = str(e)
        result["status"] = "error"

    return result


def auto_discover_https_services(conn) -> int:
    """
    Inspecciona la tabla monitored_services e identifica todos los servicios
    que posean protocolo HTTPS o puerto 443 explícito, asegurando su registro en ssl_certificates.
    """
    added_count = 0
    with conn.cursor() as cursor:
        cursor.execute("""
            SELECT id, name, type, web_url, host_ip, port
            FROM monitored_services
            WHERE is_active = 1
        """)
        services = cursor.fetchall()

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
                # Comprobar si ya existe
                cursor.execute("SELECT id FROM ssl_certificates WHERE domain = %s", (domain,))
                exists = cursor.fetchone()
                if not exists:
                    cursor.execute("""
                        INSERT INTO ssl_certificates (
                            service_id, domain, port, is_active, notes, created_at, updated_at
                        ) VALUES (%s, %s, %s, 1, %s, NOW(), NOW())
                    """, (s["id"], domain, detected_port, f"Descubierto automáticamente desde servicio: {s['name']}"))
                    added_count += 1
                    logger.info(f"✨ [AUTO-DISCOVERY] Registrado nuevo dominio SSL: {domain}:{detected_port} (Servicio: {s['name']})")
                else:
                    # Asegurar vinculación del service_id si estaba nulo
                    cursor.execute("""
                        UPDATE ssl_certificates 
                        SET service_id = %s 
                        WHERE id = %s AND service_id IS NULL
                    """, (s["id"], exists["id"]))

    return added_count



def check_and_update_certificate(conn, cert_record: Dict[str, Any], timeout: float = 5.0) -> Dict[str, Any]:
    """
    Ejecuta la inspección SSL para un registro dado, compara contra estados previos,
    detecta renovaciones o discrepancias, genera eventos en el historial y actualiza MariaDB.
    """
    cert_id = cert_record["id"]
    domain = cert_record["domain"]
    port = cert_record.get("port") or 443
    warn_threshold = cert_record.get("alert_threshold_warning") or 30
    crit_threshold = cert_record.get("alert_threshold_critical") or 7

    logger.info(f"🔍 Inspeccionando SSL: {domain}:{port}...")
    info = extract_cert_info(domain, port=port, timeout=timeout)

    prev_fingerprint = cert_record.get("fingerprint_sha256")
    prev_valid_to = cert_record.get("valid_to")
    prev_status = cert_record.get("last_check_status")
    consecutive_errors = cert_record.get("consecutive_errors") or 0
    renewal_count = cert_record.get("renewal_count") or 0

    events_to_record = []

    with conn.cursor() as cursor:
        if info["success"]:
            new_fp = info["fingerprint_sha256"]
            new_valid_to = info["valid_to"]
            days_remaining = info["days_remaining"]

            # 1. ¿Primer descubrimiento / inspección exitosa inicial?
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
            # 2. ¿Renovación detectada (huella distinta o fecha extendida)?
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
            # 3. ¿Recuperación tras error previo?
            elif prev_status == "error" or consecutive_errors > 0:
                events_to_record.append({
                    "event_type": "recovered",
                    "previous_fingerprint": prev_fingerprint,
                    "new_fingerprint": new_fp,
                    "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                    "new_valid_to": new_valid_to,
                    "days_remaining_at_event": days_remaining,
                    "error_message": "Servidor SSL recuperado. Handshake TLS completado con éxito.",
                })

            # 4. Avisos de expiración
            if days_remaining < 0:
                # Comprobar si ya se registró evento de expirado recientemente
                cursor.execute("""
                    SELECT id FROM ssl_certificate_history 
                    WHERE ssl_certificate_id = %s AND event_type = 'expired'
                      AND occurred_at >= NOW() - INTERVAL 1 DAY
                """, (cert_id,))
                if not cursor.fetchone():
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
                # Aviso de vencimiento si no se registró hoy
                cursor.execute("""
                    SELECT id FROM ssl_certificate_history 
                    WHERE ssl_certificate_id = %s AND event_type = 'expiration_warning'
                      AND occurred_at >= NOW() - INTERVAL 1 DAY
                """, (cert_id,))
                if not cursor.fetchone():
                    events_to_record.append({
                        "event_type": "expiration_warning",
                        "previous_fingerprint": prev_fingerprint,
                        "new_fingerprint": new_fp,
                        "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                        "new_valid_to": new_valid_to,
                        "days_remaining_at_event": days_remaining,
                        "error_message": f"Advertencia: el certificado expirará en {days_remaining} días.",
                    })

            # 5. Discrepancia de hostname
            if info["hostname_mismatch"]:
                cursor.execute("""
                    SELECT id FROM ssl_certificate_history 
                    WHERE ssl_certificate_id = %s AND event_type = 'hostname_mismatch'
                      AND occurred_at >= NOW() - INTERVAL 1 DAY
                """, (cert_id,))
                if not cursor.fetchone():
                    events_to_record.append({
                        "event_type": "hostname_mismatch",
                        "previous_fingerprint": prev_fingerprint,
                        "new_fingerprint": new_fp,
                        "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                        "new_valid_to": new_valid_to,
                        "days_remaining_at_event": days_remaining,
                        "error_message": f"Discrepancia: El dominio '{domain}' no coincide con CN ({info['subject_cn']}) ni con SANs ({info['san_entries']}).",
                    })

            # Actualizar registro principal
            cursor.execute("""
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
                info["subject_cn"],
                info["subject_org"],
                info["subject_ou"],
                info["subject_country"],
                info["subject_state"],
                info["subject_locality"],
                info["issuer_cn"],
                info["issuer_org"],
                info["issuer_country"],
                info["serial_number"],
                info["signature_algorithm"],
                info["public_key_algorithm"],
                info["public_key_bits"],
                info["version"],
                info["valid_from"],
                info["valid_to"],
                info["days_remaining"],
                1 if info["is_self_signed"] else 0,
                1 if info["is_wildcard"] else 0,
                1 if info["is_ev"] else 0,
                json.dumps(info["san_entries"]),
                info["fingerprint_sha256"],
                info["fingerprint_sha1"],
                info["pem_certificate"],
                info["status"],
                renewal_count,
                cert_id,
            ))

        else:
            # Fallo de conexión
            consecutive_errors += 1
            events_to_record.append({
                "event_type": "error",
                "previous_fingerprint": prev_fingerprint,
                "new_fingerprint": None,
                "previous_valid_to": str(prev_valid_to) if prev_valid_to else None,
                "new_valid_to": None,
                "days_remaining_at_event": cert_record.get("days_remaining", 0),
                "error_message": info["error_message"] or "Fallo desconocido al conectar con servidor TLS.",
            })

            cursor.execute("""
                UPDATE ssl_certificates SET
                    last_checked_at = NOW(),
                    last_check_status = 'error',
                    consecutive_errors = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (consecutive_errors, cert_id))

        # Insertar eventos de historial generados
        for ev in events_to_record:
            cursor.execute("""
                INSERT INTO ssl_certificate_history (
                    ssl_certificate_id, event_type, previous_fingerprint,
                    new_fingerprint, previous_valid_to, new_valid_to,
                    days_remaining_at_event, error_message, occurred_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW())
            """, (
                cert_id,
                ev["event_type"],
                ev["previous_fingerprint"],
                ev["new_fingerprint"],
                ev["previous_valid_to"],
                ev["new_valid_to"],
                ev["days_remaining_at_event"],
                ev["error_message"],
            ))

    return info


def run_all_ssl_checks(auto_discover: bool = True, timeout: float = 5.0) -> List[Dict[str, Any]]:
    """Ejecuta la rutina completa de verificación de certificados activos en MariaDB."""
    conn = get_db_connection()
    results = []
    try:
        if auto_discover:
            newly_added = auto_discover_https_services(conn)
            if newly_added > 0:
                logger.info(f"Descubiertos {newly_added} nuevos servicios HTTPS.")

        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT id, service_id, domain, port, valid_to, days_remaining,
                       fingerprint_sha256, last_check_status, consecutive_errors,
                       renewal_count, alert_threshold_warning, alert_threshold_critical
                FROM ssl_certificates
                WHERE is_active = 1
                ORDER BY domain ASC
            """)
            certs = cursor.fetchall()

        for cert in certs:
            res = check_and_update_certificate(conn, cert, timeout=timeout)
            results.append(res)

    finally:
        conn.close()

    return results


def print_cli_table(results: List[Dict[str, Any]]):
    """Imprime una tabla elegante en consola con el resumen de inspección."""
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


def main():
    parser = argparse.ArgumentParser(description="Motor de Inspección SSL/TLS - Monitor Valle Seco")
    parser.add_argument("--check-all", action="store_true", help="Inspeccionar todos los certificados registrados")
    parser.add_argument("--check", type=str, help="Inspeccionar un dominio específico")
    parser.add_argument("--port", type=int, default=443, help="Puerto TLS (por defecto 443)")
    parser.add_argument("--auto-discover", action="store_true", help="Descubrir servicios HTTPS desde monitored_services")
    parser.add_argument("--list", action="store_true", help="Listar certificados en base de datos")
    parser.add_argument("--expiring", type=int, nargs="?", const=30, help="Listar certificados que expiran en N días")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON")
    parser.add_argument("--timeout", type=float, default=5.0, help="Timeout de conexión en segundos")

    args = parser.parse_args()

    if args.auto_discover:
        conn = get_db_connection()
        try:
            count = auto_discover_https_services(conn)
            print(f"Descubrimiento completado: {count} nuevos dominios SSL registrados.")
        finally:
            conn.close()
        return

    if args.check:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM ssl_certificates WHERE domain = %s", (args.check,))
                cert = cursor.fetchone()
                if not cert:
                    # Crear registro si no existe
                    cursor.execute("""
                        INSERT INTO ssl_certificates (domain, port, is_active, created_at, updated_at)
                        VALUES (%s, %s, 1, NOW(), NOW())
                    """, (args.check, args.port))
                    cursor.execute("SELECT * FROM ssl_certificates WHERE domain = %s", (args.check,))
                    cert = cursor.fetchone()

            res = check_and_update_certificate(conn, cert, timeout=args.timeout)
            if args.json:
                print(json.dumps(res, indent=2, default=str))
            else:
                print_cli_table([res])
        finally:
            conn.close()
        return

    if args.expiring is not None:
        conn = get_db_connection()
        try:
            days_limit = args.expiring
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT domain, port, subject_cn, issuer_cn, valid_to, days_remaining, last_check_status
                    FROM ssl_certificates
                    WHERE is_active = 1 AND days_remaining <= %s
                    ORDER BY days_remaining ASC
                """, (days_limit,))
                expiring = cursor.fetchall()
            if args.json:
                print(json.dumps(expiring, indent=2, default=str))
            else:
                print(f"Certificados por expirar en los próximos {days_limit} días ({len(expiring)} encontrados):")
                print("-" * 75)
                for c in expiring:
                    print(f"• {c['domain']}:{c['port']} -> {c['days_remaining']} días restantes (Vence: {c['valid_to']}) [{c['last_check_status']}]")
        finally:
            conn.close()
        return

    if args.list:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT domain, port, subject_cn, issuer_cn, valid_to, days_remaining, last_check_status
                    FROM ssl_certificates
                    WHERE is_active = 1
                    ORDER BY days_remaining ASC
                """)
                certs = cursor.fetchall()
            if args.json:
                print(json.dumps(certs, indent=2, default=str))
            else:
                print(f"Listado de certificados SSL registrados ({len(certs)} total):")
                for c in certs:
                    print(f"• {c['domain']}:{c['port']} | Estado: {c['last_check_status']} | Días: {c['days_remaining']} | Vence: {c['valid_to']}")
        finally:
            conn.close()
        return

    # Comportamiento por defecto o --check-all
    results = run_all_ssl_checks(auto_discover=True, timeout=args.timeout)
    if args.json:
        print(json.dumps(results, indent=2, default=str))
    else:
        print_cli_table(results)


if __name__ == "__main__":
    main()
