"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS FASE 4: test_phase4_architecture_cleanup.py
# Desacoplamiento de bot.py (__all__) y Reubicación de Proxy Cache (ARCH-001 & ARCH-002).
# ==============================================================================
"""

import ast
from pathlib import Path

import pytest

import bot
import bot_core.services.proxy_cache as core_proxy
import monitor.infrastructure.proxy_cache as infra_proxy
import monitor.proxy_cache as monitor_proxy

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestPhase4ArchitectureCleanup:
    """Pruebas de delimitación de fachada y desacoplamiento estructural."""

    def test_bot_facade_defines_explicit_all(self) -> None:
        """Verifica que bot.py defina una tupla/lista explícita __all__ (ARCH-001)."""
        assert hasattr(bot, "__all__"), "bot.py debe definir explícitamente __all__"
        all_exports = getattr(bot, "__all__")
        assert isinstance(all_exports, (tuple, list))
        assert len(all_exports) >= 20

        # Verificar presencia de símbolos indispensables
        required_symbols = [
            "main",
            "CONFIG",
            "COMMANDS",
            "get_bot_token",
            "get_owner_id",
            "register_all_handlers",
            "load_config",
            "save_config",
            "sanitizar_pii",
            "check_ai_rate_limit",
            "ProxyInflightGuard",
        ]
        for sym in required_symbols:
            assert sym in all_exports, f"El símbolo '{sym}' debe estar en __all__ de bot.py"

    def test_bot_py_ast_has_no_direct_imports_from_monitor_proxy_cache(self) -> None:
        """Verifica que el AST de bot.py no tenga importaciones directas de monitor.proxy_cache (ARCH-002)."""
        bot_file = BASE_DIR / "bot.py"
        tree = ast.parse(bot_file.read_text(encoding="utf-8"))

        for node in ast.walk(tree):
            if isinstance(node, ast.ImportFrom):
                assert node.module != "monitor.proxy_cache", (
                    "bot.py no debe importar directamente de monitor.proxy_cache (violación ARCH-002)"
                )
                assert node.module != "monitor.infrastructure.proxy_cache", (
                    "bot.py no debe importar de monitor.infrastructure.proxy_cache"
                )

    def test_connection_service_ast_has_no_imports_from_monitor_proxy_cache(self) -> None:
        """Verifica que connection_service.py no tenga importaciones circulares hacia monitor.proxy_cache (ARCH-002)."""
        conn_file = BASE_DIR / "bot_core" / "services" / "connection_service.py"
        tree = ast.parse(conn_file.read_text(encoding="utf-8"))

        for node in ast.walk(tree):
            if isinstance(node, ast.ImportFrom):
                assert node.module != "monitor.proxy_cache", (
                    "connection_service.py no debe importar de monitor.proxy_cache (acoplamiento inverso)"
                )

    def test_proxy_cache_canonical_identity_and_backward_compatibility(self) -> None:
        """Verifica que ProxyInflightGuard en monitor sea idéntico al de bot_core (ARCH-002)."""
        # La clase canónica reside en bot_core.services.proxy_cache
        assert hasattr(core_proxy, "ProxyInflightGuard")
        assert hasattr(core_proxy, "get_cached_proxy_result")
        assert hasattr(core_proxy, "save_cached_proxy_result")

        # monitor.infrastructure.proxy_cache debe delegar directamente
        assert infra_proxy.ProxyInflightGuard is core_proxy.ProxyInflightGuard
        assert infra_proxy.get_cached_proxy_result is core_proxy.get_cached_proxy_result

        # Fachada en monitor.proxy_cache debe ser idéntica
        assert monitor_proxy.ProxyInflightGuard is core_proxy.ProxyInflightGuard

    def test_proxy_inflight_guard_instantiation_and_normalization(self) -> None:
        """Verifica el funcionamiento operativo de ProxyInflightGuard en bot_core."""
        guard = core_proxy.ProxyInflightGuard("http://user:pass@192.168.0.119:8080", "https://api.telegram.org")
        assert guard.proxy_key == "192.168.0.119:8080"
        assert guard.acquired is False
