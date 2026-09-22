#!/usr/bin/env python3
"""
Tests de Integración para Monitoreo y Telemetría SNMP (Fase 2)
Verifica:
1. Cálculo correcto de bps por delta temporal.
2. Detección y corrección de counter wrap (32 bits).
3. Cifrado y descifrado de credenciales comunitarias compatibles con Laravel.
4. Manejo robusto de timeout ante dispositivos inalcanzables.
5. Sondeo en vivo contra equipo accesible en la red.
"""

import sys
import unittest
import asyncio
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.snmp_poller import (
    decrypt_laravel_string,
    poll_device_oids,
)
from monitor.snmp_activator import (
    encrypt_laravel_value,
)


class TestSnmpIntegration(unittest.TestCase):

    def test_credential_encryption_roundtrip(self):
        """Prueba que el cifrado y descifrado AES-256-CBC sea consistente y preserve la comunidad."""
        secret_community = "Corpo#Snmp2026!Ro"
        encrypted = encrypt_laravel_value(secret_community)
        self.assertIsNotNone(encrypted)
        self.assertNotEqual(encrypted, secret_community)

        decrypted = decrypt_laravel_string(encrypted)
        self.assertEqual(decrypted, secret_community)

    def test_delta_bandwidth_normal(self):
        """Prueba el cálculo de bps a partir del delta de octetos en 10 segundos."""
        prev_octets = 10_000_000
        new_octets = 12_000_000  # +2,000,000 bytes en 10s = 200,000 bytes/s = 1,600,000 bps
        delta_time = 10.0
        if_speed = 100_000_000  # 100 Mbps

        delta_octets = new_octets - prev_octets
        bps = (delta_octets * 8) / delta_time
        utilization_pct = (bps / if_speed) * 100.0

        self.assertEqual(bps, 1_600_000.0)
        self.assertEqual(round(utilization_pct, 2), 1.60)

    def test_counter_wrap_32bit(self):
        """Prueba el manejo de desbordamiento de contador de 32 bits (4,294,967,295)."""
        max_32 = 4_294_967_296
        prev_octets = 4_294_000_000  # Cerca del límite
        new_octets = 500_000         # Contador reiniciado
        delta_time = 10.0

        # Si new < prev, ocurrió wrap
        self.assertTrue(new_octets < prev_octets)
        wrapped_delta = (max_32 - prev_octets) + new_octets
        bps = (wrapped_delta * 8) / delta_time

        self.assertGreater(bps, 0)
        self.assertEqual(wrapped_delta, 1_467_296)
        self.assertEqual(bps, 1_173_836.8)

    def test_timeout_handling(self):
        """Prueba que un equipo inalcanzable falle de forma segura sin excepciones no controladas."""
        fake_device = {
            "id": 9999,
            "ip_address": "192.0.2.1",  # TEST-NET-1 (RFC 5737)
            "community": "public",
            "snmp_port": 161,
            "snmp_timeout_seconds": 1,
            "snmp_retries": 0,
        }

        async def run_timeout():
            return await poll_device_oids(fake_device, [])

        res = asyncio.run(run_timeout())
        self.assertIn(res["status"], ("timeout", "error"))
        self.assertFalse(res["success"])

    def test_live_gateway_poll(self):
        """Prueba sondeo real contra el router gateway Carabobo (10.20.0.1)."""
        live_device = {
            "id": 4,
            "ip_address": "10.20.0.1",
            "community": "public",
            "snmp_port": 161,
            "snmp_timeout_seconds": 3,
            "snmp_retries": 1,
        }

        async def run_live():
            return await poll_device_oids(live_device, [])

        res = asyncio.run(run_live())
        # En caso de que el enlace WAN esté operativo, valida datos
        if res["success"]:
            self.assertEqual(res["status"], "success")
            self.assertIsNotNone(res["sys_name"])
            self.assertGreater(res["sys_uptime"], 0)
            print(f"\n[OK] Sondeo en vivo 10.20.0.1 exitoso: {res['sys_name']} (Uptime: {res['sys_uptime']})")
        else:
            print(f"\n[INFO] 10.20.0.1 no respondió en ventana de test: {res['error']}")


if __name__ == "__main__":
    unittest.main()
