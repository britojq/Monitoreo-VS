"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS: FASE 2 - SANEAMIENTO DE ENTRADAS Y BLINDAJE DE RENDERIZADO
# Verificación de SEC-007 y SEC-008 (Auditoría v5.51.70)
# ==============================================================================
"""

from pathlib import Path
import unittest
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from monitor.infrastructure.validators import (
    validate_hostname,
    validate_ipv4,
    validate_network_interface,
    validate_oid,
    validate_snmp_target,
)
from monitor.tools.system_cleaner import (
    ALLOWED_CLEAN_ACTIONS,
    ALLOWED_SYSTEM_BINARIES,
    execute_clean_task,
    run_cmd_async,
)
from monitor.checker_base import check_cups, check_dhcp, check_dns, check_ping


class TestPhase2InputSanitization(unittest.TestCase):
    """Verifica el whitelisting estricto de formatos de red y saneamiento de renderizado."""

    def test_sec_008_validate_ipv4_strict_whitelist(self):
        """Valida que validate_ipv4 admita únicamente direcciones IPv4 legítimas."""
        valid_ips = ["192.168.1.1", "192.168.1.254", "172.16.0.1", "255.255.255.255", "8.8.8.8"]
        for ip in valid_ips:
            self.assertTrue(validate_ipv4(ip), f"Fallo al validar IP legítima: {ip}")

        invalid_ips = [
            "256.1.1.1", "192.168.1.1; rm -rf /", "192.168.1.1 | cat /etc/passwd",
            "192.168.1.1`reboot`", "abc.def.ghi.jkl", "10.20.23", "192.168.1.1.5",
            "", None, 12345, "192.168.1.-1", "192.168.1.01"
        ]
        for ip in invalid_ips:
            self.assertFalse(validate_ipv4(ip), f"Se aceptó erróneamente IP inválida: {ip}")

    def test_sec_008_validate_oid_strict_whitelist(self):
        """Valida que validate_oid admita únicamente sintaxis estándar de OIDs SNMP."""
        valid_oids = ["1.3.6.1.2.1.1.1.0", "1.3.6.1.4.1.9.9.23", "2.16.840.1"]
        for oid in valid_oids:
            self.assertTrue(validate_oid(oid), f"Fallo al validar OID legítimo: {oid}")

        invalid_oids = [
            "1.3.6.1.a", "1.3.6.1; reboot", "1.3.6.1.2.", ".1.3.6.1",
            "3.1.2", "sysDescr.0", "", None, 123
        ]
        for oid in invalid_oids:
            self.assertFalse(validate_oid(oid), f"Se aceptó erróneamente OID inválido: {oid}")

    def test_sec_008_validate_hostname_and_target(self):
        """Valida que validate_hostname y validate_snmp_target rechacen inyecciones."""
        self.assertTrue(validate_hostname("router-principal.valleseco.local"))
        self.assertTrue(validate_hostname("switch-01"))
        self.assertFalse(validate_hostname("host; echo hack"))
        self.assertFalse(validate_hostname("-invalid-host"))
        self.assertFalse(validate_hostname("host name with spaces"))

        self.assertTrue(validate_snmp_target("192.168.1.1"))
        self.assertTrue(validate_snmp_target("15"))  # ID numérico de dispositivo
        self.assertTrue(validate_snmp_target("switch-core.local"))
        self.assertFalse(validate_snmp_target("192.168.1.1; reboot"))
        self.assertFalse(validate_snmp_target("15 OR 1=1"))
        self.assertFalse(validate_snmp_target(""))

    def test_sec_008_validate_network_interface(self):
        """Valida que validate_network_interface rechace nombres de interfaz anómalos."""
        self.assertTrue(validate_network_interface("eth0"))
        self.assertTrue(validate_network_interface("ens33"))
        self.assertTrue(validate_network_interface("wlan0"))
        self.assertTrue(validate_network_interface("br-lan"))
        self.assertFalse(validate_network_interface("eth0; rm -rf /"))
        self.assertFalse(validate_network_interface(""))
        self.assertFalse(validate_network_interface(None))

    def test_sec_007_blade_templates_sanitized_against_raw_addslashes(self):
        """Verifica que las plantillas críticas utilicen directivas seguras @js o @json."""
        board_blade = Path("/opt/sonar/web_portal/resources/views/partials/monitoring_board.blade.php").read_text(encoding="utf-8")
        sites_blade = Path("/opt/sonar/web_portal/resources/views/admin/sites/index.blade.php").read_text(encoding="utf-8")
        bot_tpl_blade = Path("/opt/sonar/web_portal/resources/views/admin/bot_templates/index.blade.php").read_text(encoding="utf-8")

        self.assertNotIn("openSshTerminal('{{ $dev->ip }}'", board_blade)
        self.assertNotIn("openTelnetTerminal('{{ $dev->ip }}'", board_blade)
        self.assertIn("@js($dev->ip)", board_blade)
        self.assertIn("@js($site->name)", sites_blade)
        self.assertIn("@json($currentTemplate", bot_tpl_blade)


@pytest.mark.anyio
async def test_sec_008_system_cleaner_blocks_unauthorized_binaries_and_actions():
    """Verifica que run_cmd_async bloquee binarios no autorizados y execute_clean_task acciones inválidas."""
    rc, msg = await run_cmd_async(["cat", "/etc/shadow"])
    assert rc == 1
    assert "no autorizado" in msg

    rc_sub, msg_sub = await run_cmd_async(["sudo", "rm", "-rf", "/tmp/test"])
    assert rc_sub == 1
    assert "no autorizado" in msg_sub

    res = await execute_clean_task("malicious_unauthorized_action")
    assert "no autorizada" in res


@pytest.mark.anyio
async def test_sec_008_checker_base_rejects_invalid_inputs_before_subprocess():
    """Verifica que checker_base rechace parámetros anómalos de inmediato."""
    is_ok, code, detail = await check_ping("192.168.1.1; reboot")
    assert is_ok is False
    assert "inválido" in detail

    is_ok_dns, _, detail_dns = await check_dns("8.8.8.8; evil", "test.local")
    assert is_ok_dns is False
    assert "inválido" in detail_dns

    is_ok_dhcp, _, detail_dhcp = await check_dhcp("eth0; rm -rf /")
    assert is_ok_dhcp is False
    assert "inválido" in detail_dhcp

    is_ok_cups, _, detail_cups = await check_cups("192.168.1.1; touch /tmp/pwn:631")
    assert is_ok_cups is False
    assert "inválido" in detail_cups
