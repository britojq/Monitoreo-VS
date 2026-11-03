"""
# ==============================================================================
# 🧪 SUITE DE PRUEBAS UNITARIAS: test_snmp_engine_solid.py
# Arquitectura SOLID - Validación del Motor SNMP (snmp_engine.py).
# Cumplimiento OWASP A03:2021 (Inyección ASN.1 / IP) y cobertura unitaria TEST-M1.
# Ubicación: /opt/sonar/tests/unit/test_snmp_engine_solid.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

import asyncio
from dataclasses import FrozenInstanceError
from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from monitor.checkers.snmp.snmp_engine import SNMPDeviceConfig, poll_device_oids
from monitor.domain.exceptions import SnmpDeviceValidationError


class MockVarBind:
    """Mock para simular tuplas de VarBind retornadas por PySNMP."""

    def __init__(self, oid_str: str, val_str: str) -> None:
        self._oid = oid_str
        self._val = MagicMock()
        self._val.prettyPrint.return_value = val_str

    def __getitem__(self, idx: int) -> Any:
        return self._oid if idx == 0 else self._val


class TestSNMPDeviceConfigValidation:
    """Pruebas de validación estricta de inputs de red (OWASP A03:2021)."""

    def test_valid_ipv4_and_default_parameters(self) -> None:
        cfg = SNMPDeviceConfig(ip_address="192.168.1.1")
        assert cfg.ip_address == "192.168.1.1"
        assert cfg.snmp_version == "v2c"
        assert cfg.snmp_port == 161
        assert cfg.snmp_timeout_seconds == 5.0
        assert cfg.snmp_retries == 2
        assert cfg.id is None

    def test_valid_ipv6_configuration(self) -> None:
        cfg = SNMPDeviceConfig(ip_address="2001:db8::1", snmp_port=1161, snmp_version="v3")
        assert cfg.ip_address == "2001:db8::1"
        assert cfg.snmp_port == 1161
        assert cfg.snmp_version == "v3"

    def test_empty_or_non_string_ip_raises_validation_error(self) -> None:
        with pytest.raises(SnmpDeviceValidationError, match="requerida"):
            SNMPDeviceConfig(ip_address="")

        with pytest.raises(SnmpDeviceValidationError, match="requerida"):
            SNMPDeviceConfig(ip_address=None)  # type: ignore

    def test_malformed_ip_address_raises_validation_error(self) -> None:
        with pytest.raises(SnmpDeviceValidationError, match="inválida"):
            SNMPDeviceConfig(ip_address="999.999.999.999")

        with pytest.raises(SnmpDeviceValidationError, match="inválida"):
            SNMPDeviceConfig(ip_address="hostname_sin_ip.local")

        with pytest.raises(SnmpDeviceValidationError, match="inválida"):
            SNMPDeviceConfig(ip_address="192.168.1.1; cat /etc/passwd")

    def test_invalid_snmp_port_raises_validation_error(self) -> None:
        with pytest.raises(SnmpDeviceValidationError, match="fuera de rango"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_port=0)

        with pytest.raises(SnmpDeviceValidationError, match="fuera de rango"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_port=65536)

        with pytest.raises(SnmpDeviceValidationError, match="fuera de rango"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_port=-1)

    def test_unsupported_snmp_version_raises_validation_error(self) -> None:
        with pytest.raises(SnmpDeviceValidationError, match="no soportada"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_version="v4")

        with pytest.raises(SnmpDeviceValidationError, match="no soportada"):
            SNMPDeviceConfig(ip_address="192.168.1.1", snmp_version="snmpv2")

    def test_config_immutability(self) -> None:
        cfg = SNMPDeviceConfig(ip_address="192.168.1.1", id=10)
        with pytest.raises((FrozenInstanceError, AttributeError)):
            cfg.ip_address = "192.168.1.2"  # type: ignore


class TestPollDeviceOidsFlow:
    """Pruebas de la función orquestadora asíncrona de sondeo SNMP."""

    def test_poll_with_invalid_dict_returns_validation_error(self) -> None:
        result = asyncio.run(
            poll_device_oids(
                device={"id": 99, "ip_address": "ip_invalida"},
                oids=[],
            )
        )
        assert result["status"] == "validation_error"
        assert result["success"] is False
        assert result["device_id"] == 99
        assert "inválida" in result["error"].lower()

    @patch("monitor.checkers.snmp.snmp_engine.db_cursor")
    @patch("monitor.checkers.snmp.snmp_engine.UdpTransportTarget.create")
    @patch("monitor.checkers.snmp.snmp_engine.get_cmd")
    def test_poll_successful_with_mocked_snmp(
        self,
        mock_get_cmd: MagicMock,
        mock_udp_create: MagicMock,
        mock_db: MagicMock,
    ) -> None:
        # Configurar transporte mock
        mock_udp_create.return_value = MagicMock()

        sys_var_binds = [
            MockVarBind("1.3.6.1.2.1.1.1.0", "Cisco Catalyst 3750 Switch"),
            MockVarBind("1.3.6.1.2.1.1.3.0", "12345678"),
            MockVarBind("1.3.6.1.2.1.1.5.0", "SW-CORE-VALLE-SECO"),
            MockVarBind("1.3.6.1.2.1.1.6.0", "Data Center Valle Seco"),
        ]

        metric_var_binds = [
            MockVarBind("1.3.6.1.4.1.9.9.109.1.1.1.1.3.1", "42.5"),
        ]

        # Simular primera llamada (sys info) y segunda llamada (métricas)
        mock_get_cmd.side_effect = [
            (None, None, 0, sys_var_binds),
            (None, None, 0, metric_var_binds),
        ]

        cfg = SNMPDeviceConfig(ip_address="192.168.1.1", id=1)
        oids = [{"id": 101, "oid": "1.3.6.1.4.1.9.9.109.1.1.1.1.3.1", "name": "cpu_util", "unit": "%"}]

        result = asyncio.run(poll_device_oids(cfg, oids))

        assert result["success"] is True
        assert result["status"] == "success"
        assert result["sys_name"] == "SW-CORE-VALLE-SECO"
        assert result["sys_uptime"] == 12345678
        assert result["sys_description"] == "Cisco Catalyst 3750 Switch"
        assert result["sys_location"] == "Data Center Valle Seco"
        assert len(result["metrics"]) == 1
        assert result["metrics"][0]["name"] == "cpu_util"
        assert result["metrics"][0]["val_num"] == 42.5

    @patch("monitor.checkers.snmp.snmp_engine.UdpTransportTarget.create")
    @patch("monitor.checkers.snmp.snmp_engine.get_cmd")
    def test_poll_handles_timeout_gracefully(
        self,
        mock_get_cmd: MagicMock,
        mock_udp_create: MagicMock,
    ) -> None:
        mock_udp_create.return_value = MagicMock()
        mock_get_cmd.return_value = ("Request timed out", None, 0, [])

        cfg = SNMPDeviceConfig(ip_address="192.168.1.99", id=2)
        result = asyncio.run(poll_device_oids(cfg, []))

        assert result["success"] is False
        assert result["status"] == "timeout"
        assert "timed out" in result["error"].lower()

    @patch("monitor.checkers.snmp.snmp_engine.UdpTransportTarget.create")
    @patch("monitor.checkers.snmp.snmp_engine.get_cmd")
    def test_invalid_asn1_oid_is_skipped(
        self,
        mock_get_cmd: MagicMock,
        mock_udp_create: MagicMock,
    ) -> None:
        mock_udp_create.return_value = MagicMock()
        mock_get_cmd.return_value = (None, None, 0, [])

        cfg = SNMPDeviceConfig(ip_address="192.168.1.1", id=3)
        # OID malicioso o sintácticamente inválido
        oids = [
            {"id": 1, "oid": "1.3.6.1.invalid_char"},
            {"id": 2, "oid": "1.3.6.1; rm -rf /"},
        ]

        result = asyncio.run(poll_device_oids(cfg, oids))
        assert result["success"] is True
        # Los OIDs inválidos son omitidos sin quebrar el sondeo
        assert len(result["metrics"]) == 0
