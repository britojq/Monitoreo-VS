"""
Pruebas unitarias para monitor/boot_alert.py
Verifica la detección de recuperación post-apagón, envío de alerta al grupo corporativo
y chequeo automático de servicios según las directrices operativas.
"""

import asyncio
from datetime import datetime
import json
import os
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

import monitor.boot_alert as ba


class TestBootAlert:
    @pytest.mark.anyio
    async def test_handle_start_clean_reboot(self):
        """Reinicio limpio: no envía alerta de corte al grupo, sólo notificación de arranque normal al owner."""
        mock_cfg = {
            "owner_id": "38914901",
            "bot_token": "fake-token",
            "node_role": "master",
            "allowed_group_ids": ["-1001383163558"],
        }

        mock_marker = MagicMock()
        mock_marker.exists.return_value = True
        mock_marker.read_text.return_value = "2026-09-29 10:00:00"

        mock_outage_marker = MagicMock()
        mock_outage_marker.exists.return_value = False

        mock_send = AsyncMock(return_value=True)
        with patch.object(ba, "load_config", return_value=mock_cfg):
            with patch.object(ba, "MARKER_FILE", mock_marker):
                with patch.object(ba, "OUTAGE_ALERT_MARKER", mock_outage_marker):
                    with patch.object(ba, "wait_for_network_and_telegram", return_value=True):
                        with patch.object(ba, "send_telegram_alert", mock_send):
                            ret = await ba.handle_start()

        assert ret == 0
        assert mock_send.call_count == 1
        # Envía sólo al Owner
        call_chat_ids = mock_send.call_args[0][1]
        assert call_chat_ids == ["38914901"]
        assert "Reinicio Limpio o Apagado Controlado" in mock_send.call_args[0][2]

    @pytest.mark.anyio
    async def test_handle_start_outage_recovery_flow(self):
        """Apagón con grupo previamente alertado: notifica al grupo recuperación, corre chequeo y actualiza BD."""
        mock_cfg = {
            "owner_id": "38914901",
            "bot_token": "fake-token",
            "node_role": "master",
            "allowed_group_ids": ["-1001383163558"],
        }

        mock_marker = MagicMock()
        mock_marker.exists.return_value = False

        mock_outage_marker = MagicMock()
        mock_outage_marker.exists.return_value = True

        mock_send = AsyncMock(return_value=True)
        mock_vars = {
            "SERVICIOS_CORPORATIVOS": "✅ - OTRS\n✅ - INTRANET",
            "SERVICIOS_REGIONALES": "✅ - LDAP CARABOBO",
        }
        mock_run_services = AsyncMock(return_value=("", mock_vars, [], 0.5))

        mock_conn = MagicMock()
        mock_cur = MagicMock()
        mock_conn.cursor.return_value.__enter__.return_value = mock_cur

        with patch.object(ba, "load_config", return_value=mock_cfg):
            with patch.object(ba, "MARKER_FILE", mock_marker):
                with patch.object(ba, "OUTAGE_ALERT_MARKER", mock_outage_marker):
                    with patch.object(ba, "wait_for_network_and_telegram", return_value=True):
                        with patch.object(ba, "send_telegram_alert", mock_send):
                            with patch("monitor.monitor_engine.run_services_check", mock_run_services):
                                with patch("monitor.monitor_db.get_db_connection", return_value=mock_conn):
                                    with patch.dict(os.environ, {"TESTING": "0", "APP_ENV": "production", "POST_BOOT_STABILIZATION_SECONDS": "0"}):
                                        ret = await ba.handle_start()

        assert ret == 0
        # Al menos 3 llamadas a send_telegram_alert:
        # 1. Alerta al Owner del encendido tras corte
        # 2. Alerta al Grupo de restablecimiento eléctrico
        # 3. Reporte de chequeo de servicios al Grupo
        assert mock_send.call_count >= 3

        # Verificar mensaje al grupo
        group_calls = [c for c in mock_send.call_args_list if "-1001383163558" in c[0][1]]
        assert len(group_calls) >= 2

        outage_recovery_msg = group_calls[0][0][2]
        assert "RECUPERACIÓN DEL SERVICIO ELÉCTRICO" in outage_recovery_msg
        assert "Servidor de Monitoreo:" in outage_recovery_msg
        assert "EN LINEA" in outage_recovery_msg
        assert "Hora de Encendido Servidor:" in outage_recovery_msg
        assert "Servicio eléctrico restablecido al 100% ." in outage_recovery_msg
        assert "El servidor de monitoreo ha iniciado tras la falla eléctrica reportada" in outage_recovery_msg
        assert "Se ejecutara una verificación automática de servicios corporativos" in outage_recovery_msg

        services_msg = group_calls[1][0][2]
        assert "ESTADO DE SERVICIOS TRAS RESTABLECIMIENTO ELÉCTRICO" in services_msg
        assert "Servicios Corporativos:" in services_msg
        assert "Chequeo automático completado tras estabilización de red" in services_msg

        # Verificar que se limpió el archivo marcador
        mock_outage_marker.unlink.assert_called_once()
        mock_conn.commit.assert_called()
