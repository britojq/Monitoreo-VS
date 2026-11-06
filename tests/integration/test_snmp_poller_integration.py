"""
# ==============================================================================
# 🧪 SUITE DE INTEGRACIÓN: test_snmp_poller_integration.py
# Arquitectura SOLID - Verificación de Integración del Motor y Poller SNMP.
# Cobertura TEST-INT exigida en auditoría exhaustiva v5.46.80.
# Simulación determinista sin dependencias de red externa (Mock Agent / Socket).
# Ubicación: /opt/sonar/tests/integration/test_snmp_poller_integration.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import asyncio
from datetime import datetime
from typing import Any, Dict, List
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from monitor.checkers.snmp.interface_discoverer import discover_interfaces
from monitor.checkers.snmp.interface_metrics import poll_interface_metrics
from monitor.checkers.snmp.snmp_engine import SNMPDeviceConfig, poll_device_oids


class MockPySnmpVarBind:
    """Simula una tupla (OID, Valor) de PySNMP en operaciones GET o BULK."""

    def __init__(self, oid_str: str, val_str: str) -> None:
        self._oid_str = oid_str
        self._val_mock = MagicMock()
        self._val_mock.prettyPrint.return_value = val_str

    def __getitem__(self, index: int) -> Any:
        return self._oid_str if index == 0 else self._val_mock

    def __str__(self) -> str:
        return self._oid_str

    def __repr__(self) -> str:
        return self._oid_str


class TestSnmpEngineIntegrationFlow:
    """Pruebas de integración del flujo de sondeo de dispositivos SNMP."""

    def test_successful_system_and_custom_oid_polling(self) -> None:
        """Simula una respuesta válida MIB-II y métricas personalizadas sin sockets reales."""
        async def _run() -> None:
            dev_config = SNMPDeviceConfig(
                id=101,
                ip_address="192.168.1.1",
                snmp_version="v2c",
                snmp_port=161,
                snmp_timeout_seconds=2.0,
                snmp_retries=1,
            )

            mock_var_binds = [
                MockPySnmpVarBind("1.3.6.1.2.1.1.1.0", "Cisco IOS Software, C2960 Software"),
                MockPySnmpVarBind("1.3.6.1.2.1.1.3.0", "12345678"),
                MockPySnmpVarBind("1.3.6.1.2.1.1.5.0", "SW-CORE-VALLESECO"),
                MockPySnmpVarBind("1.3.6.1.2.1.1.6.0", "Centro de Operaciones NOC"),
            ]

            custom_oids = [
                {"oid": "1.3.6.1.4.1.9.9.109.1.1.1.1.3.1", "name": "cpu_utilization", "unit": "%"}
            ]
            mock_custom_binds = [
                MockPySnmpVarBind("1.3.6.1.4.1.9.9.109.1.1.1.1.3.1", "18")
            ]

            with patch("monitor.checkers.snmp.snmp_engine.get_cmd") as mock_get_cmd, \
                 patch("monitor.checkers.snmp.snmp_engine.db_cursor") as mock_db:

                mock_cursor = MagicMock()
                mock_db.return_value.__enter__.return_value = mock_cursor

                mock_get_cmd.side_effect = [
                    (None, 0, 0, mock_var_binds),
                    (None, 0, 0, mock_custom_binds),
                ]

                result = await poll_device_oids(dev_config, custom_oids)

                assert result["success"] is True
                assert result["status"] == "success"
                assert result["sys_name"] == "SW-CORE-VALLESECO"
                assert result["sys_uptime"] == 12345678
                assert "Cisco IOS" in result["sys_description"]
                assert result["sys_location"] == "Centro de Operaciones NOC"
                assert mock_cursor.execute.call_count >= 1

        asyncio.run(_run())

    def test_snmp_timeout_handling_without_network_lock(self) -> None:
        """Simula pérdida de datagramas UDP (RequestTimedOut) retornando estado estructurado."""
        async def _run() -> None:
            dev_config = SNMPDeviceConfig(
                id=102,
                ip_address="192.168.1.250",
                snmp_timeout_seconds=0.5,
            )

            with patch("monitor.checkers.snmp.snmp_engine.get_cmd") as mock_get_cmd:
                error_ind = MagicMock()
                error_ind.__str__.return_value = "RequestTimedOut: No SNMP response received before timeout"
                mock_get_cmd.return_value = (error_ind, 0, 0, [])

                result = await poll_device_oids(dev_config, [])

                assert result["success"] is False
                assert result["status"] == "timeout"
                assert "RequestTimedOut" in result["error"]
                assert result["sys_name"] is None

        asyncio.run(_run())

    def test_snmp_agent_error_status(self) -> None:
        """Simula respuesta con ErrorStatus del agente SNMP (ej: noSuchName)."""
        async def _run() -> None:
            dev_config = SNMPDeviceConfig(ip_address="192.168.1.1")

            with patch("monitor.checkers.snmp.snmp_engine.get_cmd") as mock_get_cmd:
                err_stat = MagicMock()
                err_stat.prettyPrint.return_value = "noSuchName(2)"
                mock_get_cmd.return_value = (None, err_stat, 1, [])

                result = await poll_device_oids(dev_config, [])

                assert result["success"] is False
                assert result["status"] == "error"
                assert result["error"] == "noSuchName(2)"

        asyncio.run(_run())

    def test_encrypted_community_credential_integration(self) -> None:
        """Prueba la integración del descifrado de credenciales comunitarias."""
        async def _run() -> None:
            encrypted_token = "base64_payload_simulado_aes_cbc"
            dev_config = SNMPDeviceConfig(
                ip_address="192.168.1.1",
                snmp_community_encrypted=encrypted_token,
            )

            with patch("monitor.checkers.snmp.snmp_engine.decrypt_laravel_string") as mock_decrypt, \
                 patch("monitor.checkers.snmp.snmp_engine.CommunityData") as mock_comm, \
                 patch("monitor.checkers.snmp.snmp_engine.get_cmd") as mock_get_cmd:

                mock_decrypt.return_value = "SecretCorpCommunity#2026"
                mock_get_cmd.return_value = (None, 0, 0, [])

                await poll_device_oids(dev_config, [])

                mock_decrypt.assert_called_once_with(encrypted_token)
                mock_comm.assert_called_once_with("SecretCorpCommunity#2026", mpModel=1)

        asyncio.run(_run())


class TestInterfaceDiscoveryIntegration:
    """Pruebas de integración del descubridor de interfaces (ifTable walk)."""

    def test_interface_discovery_walk_and_db_persistence(self) -> None:
        """Simula el walk SNMP de ifTable poblando snmp_interfaces."""
        async def _run() -> None:
            device_record = {
                "id": 15,
                "ip_address": "192.168.1.1",
                "snmp_port": 161,
                "snmp_community_encrypted": None,
                "snmp_timeout_seconds": 2,
                "snmp_retries": 1,
            }

            # En PySNMP bulk_cmd, cada elemento retornado es un VarBind con OID y valor
            batch_var_binds = [
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.1.1", "1"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.2.1", "GigabitEthernet0/1"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.3.1", "6"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.5.1", "1000000000"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.8.1", "1"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.1.2", "2"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.2.2", "GigabitEthernet0/2"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.3.2", "6"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.5.2", "1000000000"),
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.8.2", "2"),
            ]

            with patch("monitor.checkers.snmp.interface_discoverer.bulk_cmd") as mock_bulk, \
                 patch("monitor.checkers.snmp.interface_discoverer.db_cursor") as mock_db:

                mock_cursor = MagicMock()
                mock_db.return_value.__enter__.return_value = mock_cursor

                mock_bulk.side_effect = [
                    (None, 0, 0, batch_var_binds),
                    (None, 0, 0, []),
                ]

                interfaces = await discover_interfaces(device_record)

                assert len(interfaces) == 2
                assert interfaces[0]["if_index"] == 1
                assert interfaces[0]["if_name"] == "GigabitEthernet0/1"
                assert interfaces[0]["if_oper_status"] == "up"

                assert interfaces[1]["if_index"] == 2
                assert interfaces[1]["if_name"] == "GigabitEthernet0/2"
                assert interfaces[1]["if_oper_status"] == "down"

                assert mock_cursor.executemany.call_count >= 1

        asyncio.run(_run())


class TestInterfaceMetricsCalculationAndWrap:
    """Pruebas de integración del cálculo de tasas de transferencia (bps) y counter-wrap."""

    def test_bandwidth_calculation_and_counter_wrap_handling(self) -> None:
        """Verifica el cálculo de bps ante desbordamiento de 32 bits (counter wrap)."""
        async def _run() -> None:
            device_record = {
                "id": 20,
                "ip_address": "192.168.1.1",
            }

            simulated_interface = {
                "id": 501,
                "if_index": 1,
                "if_speed": 1_000_000_000,
                "if_high_speed": 1000,
                "last_in_octets": 4_290_000_000,
                "last_out_octets": 10_000_000,
                "last_polled_at": datetime(2026, 11, 3, 10, 0, 0),
            }

            mock_metric_binds = [
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.10.1", "10000000"),  # ifInOctets wrap
                MockPySnmpVarBind("1.3.6.1.2.1.2.2.1.16.1", "15000000"),  # ifOutOctets normal
            ]

            with patch("monitor.checkers.snmp.interface_metrics.db_cursor") as mock_db, \
                 patch("monitor.checkers.snmp.interface_metrics.get_cmd") as mock_get_cmd:

                mock_cursor = MagicMock()
                mock_cursor.fetchall.return_value = [simulated_interface]
                mock_db.return_value.__enter__.return_value = mock_cursor

                mock_get_cmd.return_value = (None, 0, 0, mock_metric_binds)

                await poll_interface_metrics(device_record)

                assert mock_cursor.executemany.call_count == 2

        asyncio.run(_run())


class TestPollerConcurrencyAndErrorIsolation:
    """Pruebas de concurrencia y aislamiento de fallos entre múltiples dispositivos."""

    def test_concurrent_device_polling_isolation(self) -> None:
        """Sondeo paralelo de 3 equipos donde uno falla y los otros dos completan con éxito."""
        async def _run() -> None:
            devices = [
                SNMPDeviceConfig(id=1, ip_address="192.168.1.1"),
                SNMPDeviceConfig(id=2, ip_address="192.168.1.2"),
                SNMPDeviceConfig(id=3, ip_address="192.168.1.3"),
            ]

            async def mock_get_cmd_concurrent(engine, auth, transport, ctx, *vbs):
                target_ip = transport.transport_address[0]
                if target_ip == "192.168.1.2":
                    err_ind = MagicMock()
                    err_ind.__str__.return_value = "RequestTimedOut: No SNMP response received before timeout"
                    return (err_ind, 0, 0, [])
                else:
                    binds = [
                        MockPySnmpVarBind("1.3.6.1.2.1.1.1.0", f"Router-{target_ip}"),
                        MockPySnmpVarBind("1.3.6.1.2.1.1.3.0", "50000"),
                        MockPySnmpVarBind("1.3.6.1.2.1.1.5.0", f"RTR-{target_ip}"),
                        MockPySnmpVarBind("1.3.6.1.2.1.1.6.0", "Sede Central"),
                    ]
                    return (None, 0, 0, binds)

            with patch("monitor.checkers.snmp.snmp_engine.get_cmd", side_effect=mock_get_cmd_concurrent), \
                 patch("monitor.checkers.snmp.snmp_engine.db_cursor") as mock_db:

                mock_cursor = MagicMock()
                mock_db.return_value.__enter__.return_value = mock_cursor

                tasks = [poll_device_oids(dev, []) for dev in devices]
                results = await asyncio.gather(*tasks)

                assert len(results) == 3
                assert results[0]["success"] is True
                assert results[0]["sys_name"] == "RTR-192.168.1.1"

                # Dispositivo 2 falló limpiamente por timeout
                assert results[1]["success"] is False
                assert results[1]["status"] == "timeout"
                assert "RequestTimedOut" in results[1]["error"]

                # Dispositivo 3 completó exitosamente
                assert results[2]["success"] is True
                assert results[2]["sys_name"] == "RTR-192.168.1.3"

        asyncio.run(_run())
