"""
Pruebas unitarias de modularización y paridad de fachadas para monitor.tools.
Verifica que las herramientas modularizadas conserven 100% retrocompatibilidad
y cumplan con las Reglas de Oro (#2 de denominación de IA).
"""

import unittest
from unittest.mock import patch, MagicMock

import monitor.tools.diagnostico_produccion as tool_diag
import monitor.diagnostico_produccion as facade_diag

import monitor.tools.system_cleaner as tool_cleaner
import monitor.system_cleaner as facade_cleaner

import monitor.tools.system_optimizer as tool_optimizer
import monitor.system_optimizer as facade_optimizer

import monitor.tools.web_screenshot as tool_screenshot
import monitor.web_screenshot as facade_screenshot

import monitor.tools.wol_sender as tool_wol
import monitor.wol_sender as facade_wol


class TestMonitorToolsModularization(unittest.TestCase):
    """Verificación de paridad arquitectónica y contratos de extensión en monitor.tools."""

    def test_diagnostico_produccion_parity(self) -> None:
        """Verifica que la fachada re-exporte la función main y credenciales de clúster."""
        self.assertIs(facade_diag.main, tool_diag.main)
        self.assertIs(facade_diag.get_cluster_credentials, tool_diag.get_cluster_credentials)

    def test_system_cleaner_parity_and_methods(self) -> None:
        """Verifica que system_cleaner provea run_system_cleanup y coincidencia de métodos."""
        self.assertIs(facade_cleaner.get_disk_status, tool_cleaner.get_disk_status)
        self.assertIs(facade_cleaner.execute_clean_task, tool_cleaner.execute_clean_task)
        self.assertIs(facade_cleaner.build_cleaner_dashboard, tool_cleaner.build_cleaner_dashboard)
        self.assertTrue(hasattr(tool_cleaner, "run_system_cleanup"))
        self.assertIs(facade_cleaner.run_system_cleanup, tool_cleaner.run_system_cleanup)

    def test_system_optimizer_parity_and_golden_rule_2(self) -> None:
        """Verifica paridad de optimizador y cumplimiento estricto de Regla de Oro #2."""
        self.assertIs(facade_optimizer.get_memory_info, tool_optimizer.get_memory_info)
        self.assertIs(facade_optimizer.get_system_health, tool_optimizer.get_system_health)
        self.assertIs(facade_optimizer.execute_optimize_plasma, tool_optimizer.execute_optimize_plasma)

        # Regla de Oro #2: Prohibido mencionar motor local de IA
        sanitized = tool_optimizer.SANITIZED_PROCESS_NAMES
        self.assertNotIn("ollama", sanitized.values())
        self.assertEqual(sanitized.get("ollama"), "Servicio local de IA")
        self.assertEqual(sanitized.get("ollama_llama_server"), "Motor local de IA (Inferencia)")

    def test_web_screenshot_parity_and_aliases(self) -> None:
        """Verifica que capture_web_dashboard y take_dashboard_screenshot existan en fachada y módulo."""
        self.assertIs(facade_screenshot.capture_web_dashboard, tool_screenshot.capture_web_dashboard)
        self.assertIs(facade_screenshot.take_dashboard_screenshot, tool_screenshot.take_dashboard_screenshot)
        self.assertIs(tool_screenshot.take_dashboard_screenshot, tool_screenshot.capture_web_dashboard)

    def test_wol_sender_parity_and_functions(self) -> None:
        """Verifica paridad de WoL, normalización de MAC y generación de Magic Packet."""
        self.assertIs(facade_wol.normalize_mac, tool_wol.normalize_mac)
        self.assertIs(facade_wol.create_magic_packet, tool_wol.create_magic_packet)
        self.assertIs(facade_wol.send_magic_packet, tool_wol.send_magic_packet)
        self.assertIs(facade_wol.WolManager, tool_wol.WolManager)

        # Prueba de normalización y magic packet
        mac = "ec:b1:d7:36:9e:82"
        normalized = tool_wol.normalize_mac(mac)
        self.assertEqual(normalized, "EC:B1:D7:36:9E:82")

        packet = tool_wol.create_magic_packet(mac)
        self.assertEqual(len(packet), 102)
        self.assertTrue(packet.startswith(b"\xff" * 6))
