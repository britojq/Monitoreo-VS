"""
Parser X.509 para extracción de metadatos, extensiones SAN y correspondencia de hostnames.
"""
from datetime import datetime, timezone
import fnmatch
import logging
import ssl
from typing import Any, Dict, List, Optional

from cryptography import x509
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import ec, rsa
from cryptography.x509.oid import ExtensionOID, NameOID

from monitor.checkers.ssl.socket_handshake import perform_ssl_handshake

logger = logging.getLogger("monitor.checkers.ssl.x509_parser")


def match_hostname(hostname: str, san_entries: List[str], subject_cn: Optional[str]) -> bool:
    """Verifica si el hostname coincide con CN o entradas SAN, con soporte wildcard."""
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
            if fnmatch.fnmatch(hostname, pattern):
                return True
            if hostname == base_pattern:
                return True
    return False


def extract_cert_info(hostname: str, port: int = 443, timeout: float = 5.0) -> Dict[str, Any]:
    """Extrae metadatos completos del certificado SSL/TLS remoto."""
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

    der_cert, tls_ver, cipher, err = perform_ssl_handshake(hostname, port, timeout)
    if err or not der_cert:
        result["error_message"] = err or "Error desconocido en handshake SSL."
        return result

    try:
        result["tls_version"] = tls_ver
        if cipher:
            result["cipher_suite"] = f"{cipher[0]} ({cipher[1]})"

        pem_cert = ssl.DER_cert_to_PEM_cert(der_cert)
        result["pem_certificate"] = pem_cert
        cert = x509.load_der_x509_certificate(der_cert, default_backend())

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

        for attr in cert.issuer:
            oid = attr.oid
            if oid == NameOID.COMMON_NAME:
                result["issuer_cn"] = str(attr.value)
            elif oid == NameOID.ORGANIZATION_NAME:
                result["issuer_org"] = str(attr.value)
            elif oid == NameOID.COUNTRY_NAME:
                result["issuer_country"] = str(attr.value)

        serial_int = cert.serial_number
        serial_hex = f"{serial_int:X}"
        if len(serial_hex) % 2 != 0:
            serial_hex = "0" + serial_hex
        result["serial_number"] = ":".join(serial_hex[i:i+2] for i in range(0, len(serial_hex), 2))

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

        result["version"] = getattr(cert.version, "value", 3)

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

        now_utc = datetime.now(timezone.utc)
        if valid_to_dt:
            if valid_to_dt.tzinfo is None:
                valid_to_aware = valid_to_dt.replace(tzinfo=timezone.utc)
            else:
                valid_to_aware = valid_to_dt
            delta = valid_to_aware - now_utc
            result["days_remaining"] = delta.days

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

        result["is_self_signed"] = bool(cert.issuer == cert.subject)

        is_wild = False
        if result["subject_cn"] and result["subject_cn"].startswith("*."):
            is_wild = True
        for s in san_list:
            if s.startswith("*."):
                is_wild = True
                break
        result["is_wildcard"] = is_wild

        result["fingerprint_sha256"] = cert.fingerprint(hashes.SHA256()).hex().upper()
        result["fingerprint_sha1"] = cert.fingerprint(hashes.SHA1()).hex().upper()

        is_ev = False
        try:
            policies_ext = cert.extensions.get_extension_for_oid(ExtensionOID.CERTIFICATE_POLICIES)
            for policy in policies_ext.value:
                p_str = str(policy.policy_identifier.dotted_string)
                if any(ev_kw in p_str for ev_kw in ["2.16.840.1.114412", "2.16.840.1.114413", "1.3.6.1.4.1.6449", "2.16.840.1.114028"]):
                    is_ev = True
                    break
        except Exception:
            pass
        result["is_ev"] = is_ev

        is_matched = match_hostname(hostname, san_list, result["subject_cn"])
        result["hostname_mismatch"] = not is_matched

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
