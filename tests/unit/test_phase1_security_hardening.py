"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS: FASE 1 - BLINDAJE CRIPTOGRÁFICO Y TRANSPORTE DE CLÚSTER
# Verificación de SEC-001 (CWE-316) y SEC-002 (CWE-319)
# ==============================================================================
"""

import hashlib
import hmac
import time
import unittest
from unittest.mock import patch, MagicMock

import bot
from bot_core.contracts.security_provider import HardwareVaultSecurityProvider, get_default_security_provider
from monitor.sync.cluster.master_client import MasterClusterClient
import monitor.shield.secure_core as secure_core


class TestPhase1SecurityHardening(unittest.TestCase):
    """Verifica la eliminación de variables globales en memoria y el firmado HMAC de transporte."""

    def test_sec_001_no_static_token_in_bot_module_dict(self):
        """Verifica que IMMUTABLE_BOT_TOKEN no sea una variable estática en bot.__dict__."""
        # En el dict crudo del módulo no debe haber asignación estática de string
        raw_dict = bot.__dict__
        # Si existe, no debe ser un string almacenado directamente, sino resolverse dinámicamente
        self.assertNotIn("IMMUTABLE_BOT_TOKEN", raw_dict, "IMMUTABLE_BOT_TOKEN no debe residir estáticamente en bot.__dict__")
        
        # Pero mediante getattr dinámico (__getattr__) debe resolver el valor
        token = getattr(bot, "IMMUTABLE_BOT_TOKEN")
        self.assertIsInstance(token, str)
        self.assertEqual(token, bot.get_bot_token())

    def test_sec_001_no_static_token_in_secure_core_dict(self):
        """Verifica que secure_core no almacene IMMUTABLE_BOT_TOKEN en su __dict__ global."""
        raw_dict = secure_core.__dict__
        self.assertNotIn("IMMUTABLE_BOT_TOKEN", raw_dict, "IMMUTABLE_BOT_TOKEN no debe residir estáticamente en secure_core.__dict__")
        
        # Debe resolverse dinámicamente mediante __getattr__
        token = getattr(secure_core, "IMMUTABLE_BOT_TOKEN")
        self.assertIsInstance(token, str)
        self.assertEqual(token, secure_core.get_core_bot_token())

    def test_sec_001_security_provider_fetches_token_dynamically(self):
        """Verifica que HardwareVaultSecurityProvider invoque dinámicamente la función get_core_bot_token."""
        provider = HardwareVaultSecurityProvider()
        with patch("monitor.shield.secure_core.get_core_bot_token", return_value="dynamic_test_token_123"):
            token = provider.get_bot_token()
            self.assertEqual(token, "dynamic_test_token_123")

    def test_sec_002_master_cluster_client_generates_hmac_and_timestamp(self):
        """Verifica que MasterClusterClient genere cabeceras X-Cluster-Timestamp y X-Cluster-Signature válidas."""
        client = MasterClusterClient(master_url="http://192.168.1.250", cluster_token="secret_token_xyz")
        
        fixed_time = 1730700000
        with patch("time.time", return_value=fixed_time):
            # El cálculo de firma esperado para GET vacío:
            expected_sig = hmac.new(
                b"secret_token_xyz",
                f"{fixed_time}:".encode("utf-8"),
                hashlib.sha256
            ).hexdigest()

            # Mock httpx AsyncClient
            with patch("httpx.AsyncClient") as mock_client_cls:
                mock_client = MagicMock()
                mock_response = MagicMock()
                mock_response.status_code = 200
                mock_response.json.return_value = {"success": True, "snapshot": {"status": "ok"}}
                
                # Asynchronous mock get
                import asyncio
                async def mock_get(url, headers):
                    self.assertIn("X-Cluster-Token", headers)
                    self.assertEqual(headers["X-Cluster-Token"], "secret_token_xyz")
                    self.assertIn("X-Cluster-Timestamp", headers)
                    self.assertEqual(headers["X-Cluster-Timestamp"], str(fixed_time))
                    self.assertIn("X-Cluster-Signature", headers)
                    self.assertEqual(headers["X-Cluster-Signature"], expected_sig)
                    return mock_response

                mock_client.get = mock_get
                mock_client.__aenter__.return_value = mock_client
                mock_client_cls.return_value = mock_client

                success, data = asyncio.run(client.fetch_telemetry())
                self.assertTrue(success)
                self.assertIsNotNone(data)


if __name__ == "__main__":
    unittest.main()
