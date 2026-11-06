"""
Pruebas unitarias de modularización y contratos canónicos para monitor.tools.
Verifica que las herramientas canónicas provean la funcionalidad completa
y cumplan con las Reglas de Oro (#2 de denominación de IA).
"""

import unittest
from unittest.mock import patch, MagicMock

import monitor.tools.diagnostico_produccion as tool_diag
import monitor.tools.system_cleaner as tool_cleaner
import monitor.tools.system_optimizer as tool_optimizer
import monitor.tools.web_screenshot as tool_screenshot
import monitor.tools.wol_sender as tool_wol


class TestMonitorToolsModularization(unittest.TestCase):
    """Verificación de contratos de arquitectura canónica en monitor.tools."""

    def test_diagnostico_produccion_methods(self) -> None:
        """Verifica que tool_diag provea la función main y credenciales de clúster."""
        self.assertTrue(callable(tool_diag.main))
        self.assertTrue(callable(tool_diag.get_cluster_credentials))

    def test_system_cleaner_methods(self) -> None:
        """Verifica que system_cleaner provea run_system_cleanup y métodos requeridos."""
        self.assertTrue(callable(tool_cleaner.get_disk_status))
        self.assertTrue(callable(tool_cleaner.execute_clean_task))
        self.assertTrue(callable(tool_cleaner.build_cleaner_dashboard))
        self.assertTrue(hasattr(tool_cleaner, "run_system_cleanup"))
        self.assertTrue(callable(tool_cleaner.run_system_cleanup))

    def test_system_optimizer_methods_and_golden_rule_2(self) -> None:
        """Verifica métodos de optimizador y cumplimiento estricto de Regla de Oro #2."""
        self.assertTrue(callable(tool_optimizer.get_memory_info))
        self.assertTrue(callable(tool_optimizer.get_system_health))
        self.assertTrue(callable(tool_optimizer.execute_optimize_plasma))

        # Regla de Oro #2: Prohibido mencionar motor local de IA
        sanitized = tool_optimizer.SANITIZED_PROCESS_NAMES
        self.assertNotIn("ollama", sanitized.values())
        self.assertEqual(sanitized.get("ollama"), "Servicio local de IA")
        self.assertEqual(sanitized.get("ollama_llama_server"), "Motor local de IA (Inferencia)")

    def test_web_screenshot_methods_and_aliases(self) -> None:
        """Verifica que capture_web_dashboard y take_dashboard_screenshot existan en tool_screenshot."""
        self.assertTrue(callable(tool_screenshot.capture_web_dashboard))
        self.assertTrue(callable(tool_screenshot.take_dashboard_screenshot))
        self.assertIs(tool_screenshot.take_dashboard_screenshot, tool_screenshot.capture_web_dashboard)

    def test_wol_sender_functions_and_logic(self) -> None:
        """Verifica normalización de MAC y generación de Magic Packet en tool_wol."""
        self.assertTrue(callable(tool_wol.normalize_mac))
        self.assertTrue(callable(tool_wol.create_magic_packet))
        self.assertTrue(callable(tool_wol.send_magic_packet))
        self.assertTrue(hasattr(tool_wol, "WolManager"))

        # Prueba de normalización y magic packet
        mac = "ec:b1:d7:36:9e:82"
        normalized = tool_wol.normalize_mac(mac)
        self.assertEqual(normalized, "EC:B1:D7:36:9E:82")

        packet = tool_wol.create_magic_packet(mac)
        self.assertEqual(len(packet), 102)
        self.assertTrue(packet.startswith(b"\xff" * 6))
