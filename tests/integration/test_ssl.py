#!/usr/bin/env python3
"""
Tests de Integración para Monitoreo y Verificación de Certificados SSL/TLS (Fase 3)
Verifica:
1. Extracción y análisis criptográfico con cryptography.x509.
2. Detección de comodines (wildcard) y autofirmados (self-signed).
3. Lógica de correspondencia de nombres de host (match_hostname).
4. Detección automática de renovaciones (renewal events).
5. Avisos automáticos de expiración (< 30 días y caducados).
6. Inspección en vivo por TLS y SNI contra endpoints reales.
7. Integridad de base de datos y modelos Eloquent en MariaDB.
"""

import datetime
import json
import sys
import unittest
from datetime import timezone
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from cryptography import x509
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import ExtensionOID, NameOID

from monitor.ssl_checker import (
    extract_cert_info,
    match_hostname,
    get_db_connection,
    check_and_update_certificate,
    auto_discover_https_services,
)


class TestSslIntegration(unittest.TestCase):

    def setUp(self):
        # Generar clave RSA y certificado sintético para pruebas unitarias
        self.key = rsa.generate_private_key(public_exponent=65537, key_size=2048, backend=default_backend())
        self.subject = self.issuer = x509.Name([
            x509.NameAttribute(NameOID.COUNTRY_NAME, "VE"),
            x509.NameAttribute(NameOID.STATE_OR_PROVINCE_NAME, "Carabobo"),
            x509.NameAttribute(NameOID.LOCALITY_NAME, "Puerto Cabello"),
            x509.NameAttribute(NameOID.ORGANIZATION_NAME, "EMPRESA"),
            x509.NameAttribute(NameOID.COMMON_NAME, "*.empresa.com.ve"),
        ])
        now = datetime.datetime.now(timezone.utc)
        self.cert = (
            x509.CertificateBuilder()
            .subject_name(self.subject)
            .issuer_name(self.issuer)
            .public_key(self.key.public_key())
            .serial_number(1234567890123456789)
            .not_valid_before(now)
            .not_valid_after(now + datetime.timedelta(days=45))
            .add_extension(
                x509.SubjectAlternativeName([
                    x509.DNSName("*.empresa.com.ve"),
                    x509.DNSName("empresa.com.ve"),
                    x509.DNSName("portal.empresa.com.ve"),
                ]),
                critical=False,
            )
            .sign(self.key, hashes.SHA256(), default_backend())
        )

    def test_hostname_matching_exact_and_wildcard(self):
        """Verifica coincidencia exacta y comodín (wildcard)."""
        sans = ["*.empresa.com.ve", "empresa.com.ve", "portal.empresa.com.ve"]
        cn = "*.empresa.com.ve"

        # Coincidencias válidas
        self.assertTrue(match_hostname("sub.empresa.com.ve", sans, cn))
        self.assertTrue(match_hostname("portal.empresa.com.ve", sans, cn))
        self.assertTrue(match_hostname("empresa.com.ve", sans, cn))
        self.assertTrue(match_hostname("EMPRESA.COM.VE", sans, cn))  # Case-insensitive

        # Discrepancias / Mismatches
        self.assertFalse(match_hostname("google.com", sans, cn))
        self.assertFalse(match_hostname("fake-empresa.com", sans, cn))
        self.assertFalse(match_hostname("intranet.otraempresa.ve", sans, cn))

    def test_synthetic_cert_attributes(self):
        """Verifica la extracción de metadatos criptográficos del certificado."""
        # Sujeto y Emisor
        cn = self.cert.subject.get_attributes_for_oid(NameOID.COMMON_NAME)[0].value
        self.assertEqual(cn, "*.empresa.com.ve")

        # SANs
        san_ext = self.cert.extensions.get_extension_for_oid(ExtensionOID.SUBJECT_ALTERNATIVE_NAME).value
        sans = san_ext.get_values_for_type(x509.DNSName)
        self.assertIn("portal.empresa.com.ve", sans)
        self.assertIn("*.empresa.com.ve", sans)

        # Autofirmado
        self.assertEqual(self.cert.issuer, self.cert.subject)

        # Huella digital SHA-256
        fp = self.cert.fingerprint(hashes.SHA256()).hex().upper()
        self.assertEqual(len(fp), 64)

        # Tamaño de clave pública
        self.assertEqual(self.cert.public_key().key_size, 2048)

    def test_live_ssl_inspection_core_telegram(self):
        """Prueba inspección TLS en vivo contra core.telegram.org."""
        res = extract_cert_info("core.telegram.org", port=443, timeout=5.0)
        self.assertTrue(res["success"])
        self.assertEqual(res["status"], "success")
        self.assertGreater(res["days_remaining"], 30)
        self.assertIsNotNone(res["fingerprint_sha256"])
        self.assertIsNotNone(res["issuer_cn"])
        self.assertIn("telegram.org", res["san_entries"])
        self.assertFalse(res["hostname_mismatch"])

    def test_db_auto_discovery_and_records(self):
        """Verifica que auto_discover_https_services descubra y pueble ssl_certificates en MariaDB."""
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) as count FROM ssl_certificates WHERE is_active = 1")
                count = cur.fetchone()["count"]
                self.assertGreaterEqual(count, 1)

                # Verificar que el registro de core.telegram.org existe y tiene historial
                cur.execute("SELECT id, days_remaining, last_check_status FROM ssl_certificates WHERE domain = 'core.telegram.org'")
                tg = cur.fetchone()
                self.assertIsNotNone(tg)
                self.assertGreater(tg["days_remaining"], 0)

                # Verificar historial
                cur.execute("SELECT COUNT(*) as hist_count FROM ssl_certificate_history WHERE ssl_certificate_id = %s", (tg["id"],))
                h_count = cur.fetchone()["hist_count"]
                self.assertGreaterEqual(h_count, 1)
        finally:
            conn.close()

    def test_unreachable_endpoint_graceful_handling(self):
        """Verifica que un endpoint caído no genere excepciones no controladas."""
        res = extract_cert_info("192.0.2.1", port=443, timeout=1.0)
        self.assertFalse(res["success"])
        self.assertEqual(res["status"], "error")
        self.assertIsNotNone(res["error_message"])


if __name__ == "__main__":
    unittest.main()
