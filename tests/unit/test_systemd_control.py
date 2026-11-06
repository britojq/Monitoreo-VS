"""
Pruebas unitarias para monitor/gitops/services/systemd_control.py (F-04).
Verifica que las invocaciones a systemd se realicen mediante argv estructurado
sin invocar shells inseguras ni os.system.
"""

from unittest.mock import AsyncMock, patch
import pytest

from monitor.gitops.services.systemd_control import SystemdController


@pytest.mark.anyio
async def test_restart_service_delayed_uses_subprocess_exec():
    mock_proc = AsyncMock()
    mock_proc.wait = AsyncMock(return_value=0)

    with patch("asyncio.sleep", new_callable=AsyncMock) as mock_sleep, \
         patch("asyncio.create_subprocess_exec", new_callable=AsyncMock, return_value=mock_proc) as mock_exec, \
         patch("os.system") as mock_os_system:

        await SystemdController.restart_service_delayed(delay_seconds=0.01)

        mock_sleep.assert_awaited_once_with(0.01)
        mock_exec.assert_awaited_once()

        # Verificar que los argumentos pasados son una lista estricta (argv)
        called_args = mock_exec.await_args[0]
        assert "systemd-run" in called_args
        assert "--no-block" in called_args
        assert "systemctl" in called_args
        assert "restart" in called_args
        assert "tg-admin-bot.service" in called_args

        # F-04: os.system no debe ser llamado bajo ninguna circunstancia
        mock_os_system.assert_not_called()


@pytest.mark.anyio
async def test_get_system_services_status_executes_systemctl():
    mock_proc = AsyncMock()
    mock_proc.communicate = AsyncMock(return_value=(b"active\n", b""))

    with patch("asyncio.create_subprocess_exec", new_callable=AsyncMock, return_value=mock_proc) as mock_exec:
        status = await SystemdController.get_system_services_status()

        assert "tg-admin-bot" in status
        assert status["tg-admin-bot"] == "active"
        assert mock_exec.await_count == 4
