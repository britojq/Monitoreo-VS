"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS: FASE 1 - PRIVILEGIOS DE SO Y SHELL SEGURO
# Verificación de SEC-001, SEC-002 y SEC-006 (Auditoría v5.51.70)
# ==============================================================================
"""

import inspect
from pathlib import Path
import unittest
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from bot_core.contracts.service_lifecycle import ServiceLifecyclePort
from monitor.gitops.services.systemd_control import SystemdController


class TestPhase1OsSecurityAudit(unittest.TestCase):
    """Verifica la eliminación de chmod 777, os.system y el desacoplamiento seguro con auditoría."""

    def test_sec_001_no_chmod_777_in_gitops_actions(self):
        """Verifica que execute_update_action.py no contenga llamadas a chmod 777."""
        action_file = Path("/opt/sonar/monitor/gitops/actions/execute_update_action.py")
        content = action_file.read_text(encoding="utf-8")
        # El código activo no debe contener "777"
        lines_with_777 = [line for line in content.splitlines() if "777" in line and not line.strip().startswith("#")]
        self.assertEqual(lines_with_777, [], f"Se encontraron líneas con 777 activo: {lines_with_777}")
        self.assertIn("SEC-001", content)
        self.assertIn("775", content)
        self.assertIn("664", content)

    def test_sec_001_no_chmod_777_in_caches_or_tools(self):
        """Verifica que proxy_cache y web_screenshot no utilicen permisos universales 0o777."""
        proxy_cache = Path("/opt/sonar/bot_core/services/proxy_cache.py").read_text(encoding="utf-8")
        screenshot = Path("/opt/sonar/monitor/tools/web_screenshot.py").read_text(encoding="utf-8")
        deploy_pipe = Path("/opt/sonar/tools/deploy_pipeline.sh").read_text(encoding="utf-8")

        self.assertNotIn("0o777", proxy_cache)
        self.assertNotIn("0o777", screenshot)
        self.assertNotIn("chmod -R 777", deploy_pipe)

    def test_sec_002_conversation_token_migration_no_os_system(self):
        """Verifica que conversation.py no use os.system ni importe os para reiniciar servicios."""
        conv_file = Path("/opt/sonar/bot_core/handlers/token_migration/conversation.py")
        content = conv_file.read_text(encoding="utf-8")
        active_calls = [
            line for line in content.splitlines()
            if "os.system" in line and not line.strip().startswith("#")
        ]
        self.assertEqual(active_calls, [], f"Se encontraron llamadas activas a os.system: {active_calls}")
        self.assertIn("SystemdController.restart_service_delayed", content)

    def test_sec_006_emergency_handler_uses_audited_controller(self):
        """Verifica que emergency_handler.py despache la parada mediante SystemdController con auditoría."""
        em_file = Path("/opt/sonar/bot_core/handlers/emergency/emergency_handler.py")
        content = em_file.read_text(encoding="utf-8")
        self.assertIn("SystemdController.stop_service_delayed", content)
        self.assertIn("emergency_panel_stop_confirmed", content)

    def test_service_lifecycle_contract_conformance(self):
        """Verifica que SystemdController implemente formalmente ServiceLifecyclePort."""
        controller = SystemdController()
        self.assertTrue(isinstance(controller, ServiceLifecyclePort))
        self.assertTrue(inspect.iscoroutinefunction(controller.restart_service))
        self.assertTrue(inspect.iscoroutinefunction(controller.stop_service))


@pytest.mark.anyio
async def test_sec_006_stop_service_delayed_records_audit_and_calls_exec():
    """Verifica que stop_service_delayed registre en audit_logs y ejecute subprocess_exec sin shell."""
    mock_proc = AsyncMock()
    mock_proc.wait = AsyncMock(return_value=0)

    mock_cursor = MagicMock()
    mock_conn = MagicMock()
    mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

    with patch("asyncio.sleep", new_callable=AsyncMock) as mock_sleep, \
         patch("asyncio.create_subprocess_exec", new_callable=AsyncMock, return_value=mock_proc) as mock_exec, \
         patch("monitor.infrastructure.monitor_db.get_db_connection", return_value=mock_conn):

        result = await SystemdController.stop_service_delayed(
            delay_seconds=0.01,
            service="tg-admin-bot.service",
            actor_id=38914901,
            reason="emergency_test_stop"
        )

        assert result is True
        mock_sleep.assert_awaited_once_with(0.01)
        mock_exec.assert_awaited_once()

        # Validar argv estructurado
        called_args = mock_exec.await_args[0]
        assert "systemctl" in called_args
        assert "stop" in called_args
        assert "tg-admin-bot.service" in called_args

        # Validar inserción en audit_logs
        mock_cursor.execute.assert_called_once()
        sql, params = mock_cursor.execute.call_args[0]
        assert "INSERT INTO audit_logs" in sql
        assert params[0] == 38914901
        assert params[4] == "service_stop"
        assert params[5] == "systemd"
        mock_conn.commit.assert_called_once()
