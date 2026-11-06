"""
==============================================================================
🧪 PRUEBAS DE SEGURIDAD Y ARQUITECTURA FASE 5 (F-11, F-12, F-13)
Valida robustez transaccional InnoDB, erradicación de defaults en CLI
y configuración dinámica desacoplada de identidad de Administrador.
==============================================================================
"""

import os
import unittest
from unittest.mock import MagicMock, patch

from monitor.shield.vault_key_deriver import (
    IMMUTABLE_OWNER_ID,
    get_resolved_owner_id,
)
from monitor.shield.secure_core import get_core_owner_id
from monitor.cli.snmp_activator import parse_args


class TestPhase5SecurityHardening(unittest.TestCase):

    def test_f12_snmp_activator_cli_requires_credentials_and_community(self):
        """Valida que snmp_activator.py no contenga defaults inseguros (F-12)."""
        import sys
        
        # Test 1: Fallo al omitir credenciales obligatorias
        test_args = ["snmp_activator.py", "--ip", "192.168.1.1"]
        with patch.object(sys, "argv", test_args):
            with self.assertRaises(SystemExit) as cm:
                parse_args()
            self.assertEqual(cm.exception.code, 2)

        # Test 2: Éxito cuando se suministran todos los argumentos obligatorios
        test_valid_args = [
            "snmp_activator.py",
            "--ip", "192.168.1.1",
            "--user", "noc_operator",
            "--password", "SuperSecretPass123!",
            "--community", "SecureComm2026",
        ]
        with patch.object(sys, "argv", test_valid_args):
            args = parse_args()
            self.assertEqual(args.user, "noc_operator")
            self.assertEqual(args.password, "SuperSecretPass123!")
            self.assertEqual(args.community, "SecureComm2026")

    def test_f13_vault_key_deriver_dynamic_owner_id(self):
        """Valida resolución dinámica desacoplada de ID de Administrador (F-13)."""
        # Test 1: Fallback canónico cuando no hay variables de entorno
        with patch.dict(os.environ, {}, clear=True):
            resolved = get_resolved_owner_id()
            self.assertEqual(resolved, 38914901)
            self.assertEqual(IMMUTABLE_OWNER_ID, 38914901)

        # Test 2: Sobrescritura dinámica mediante TELEGRAM_OWNER_ID
        with patch.dict(os.environ, {"TELEGRAM_OWNER_ID": "99887766"}, clear=True):
            self.assertEqual(get_resolved_owner_id(), 99887766)
            self.assertEqual(get_core_owner_id(), 99887766)

    def test_f11_master_sync_action_uses_atomic_transaction_and_restores_fk(self):
        """Valida que master_sync_action ejecute en atomic_transaction y restaure FK=1 (F-11)."""
        import asyncio
        from unittest.mock import AsyncMock
        from monitor.sync.cluster.master_sync_action import SyncFromMasterAction

        mock_client = MagicMock()
        mock_client.fetch_telemetry = AsyncMock(return_value=(
            True,
            {
                "success": True,
                "snapshot": {"status": "ok", "summary": {}},
                "config": {},
            }
        ))

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        executed_statements = []
        def track_execute(query, *args):
            executed_statements.append(query.strip())
        mock_cursor.execute.side_effect = track_execute

        action = SyncFromMasterAction(client=mock_client)
        with patch("monitor.sync.cluster.master_sync_action.atomic_transaction") as mock_atomic:
            mock_atomic.return_value.__enter__.return_value = mock_conn

            res = asyncio.run(action.execute())
            self.assertTrue(res)

            # Validar que SET FOREIGN_KEY_CHECKS = 0 se ejecutó
            self.assertIn("SET FOREIGN_KEY_CHECKS = 0", executed_statements)
            # Validar que SET FOREIGN_KEY_CHECKS = 1 se ejecutó al final
            self.assertIn("SET FOREIGN_KEY_CHECKS = 1", executed_statements)
            # Validar orden: 0 primero, 1 después
            idx_zero = executed_statements.index("SET FOREIGN_KEY_CHECKS = 0")
            idx_one = executed_statements.index("SET FOREIGN_KEY_CHECKS = 1")
            self.assertLess(idx_zero, idx_one)
