"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS FASE 2: test_phase2_command_registry.py
# Validación de Whitelist de Comandos (SEC-003) y Gestión de Flags MariaDB (SEC-010).
# ==============================================================================
"""

import pytest
from pathlib import Path

from bot_core.security.command_registry import (
    COMMAND_REGISTRY,
    is_command_allowed,
    validate_command,
)
from bot_core.utils.system_helpers import _exec_system_command
from bot_core.services.config_manager import (
    clear_auto_rollback_notice,
    get_auto_rollback_notice,
    set_auto_rollback_notice,
)


class TestPhase2CommandRegistry:
    """Pruebas de la lista blanca de comandos y prevención de inyección (SEC-003)."""

    def test_safe_ping_allowed(self) -> None:
        """Verifica que el comando ping legítimo con argumentos seguros sea aceptado."""
        cmd = ["ping", "-c", "4", "192.168.1.1"]
        is_valid, reason = validate_command(cmd)
        assert is_valid is True
        assert reason == "OK"
        assert is_command_allowed(cmd) is True

    def test_unauthorized_binary_rejected(self) -> None:
        """Verifica que ejecutables no autorizados sean inmediatamente rechazados."""
        cmd = ["rm", "-rf", "/opt/sonar"]
        is_valid, reason = validate_command(cmd)
        assert is_valid is False
        assert "no autorizado en lista blanca" in reason
        assert is_command_allowed(cmd) is False

    def test_dangerous_shell_injection_semicolon_rejected(self) -> None:
        """Verifica el rechazo de inyecciones con punto y coma (;)."""
        cmd = ["ping", "-c", "4", "192.168.1.1; cat /etc/shadow"]
        is_valid, reason = validate_command(cmd)
        assert is_valid is False
        assert "caracteres prohibidos" in reason

    def test_dangerous_shell_injection_pipe_rejected(self) -> None:
        """Verifica el rechazo de inyecciones con tubería (|)."""
        cmd = ["ping", "-c", "4", "192.168.1.1|id"]
        is_valid, reason = validate_command(cmd)
        assert is_valid is False
        assert "caracteres prohibidos" in reason

    def test_dangerous_shell_injection_substitution_rejected(self) -> None:
        """Verifica el rechazo de sustitución de comandos con $()."""
        cmd = ["ping", "-c", "4", "$(whoami).local"]
        is_valid, reason = validate_command(cmd)
        assert is_valid is False
        assert "caracteres prohibidos" in reason

    def test_dangerous_shell_injection_backticks_rejected(self) -> None:
        """Verifica el rechazo de backticks (`)."""
        cmd = ["ping", "-c", "4", "`id`"]
        is_valid, reason = validate_command(cmd)
        assert is_valid is False
        assert "caracteres prohibidos" in reason

    def test_systemctl_restricts_unauthorized_subcommands(self) -> None:
        """Verifica que systemctl solo permita subcomandos de solo lectura (status, is-active)."""
        # status permitido
        ok_cmd = ["systemctl", "status", "monitoreo-vs.service"]
        is_valid, _ = validate_command(ok_cmd)
        assert is_valid is True

        # restart o stop prohibidos a través de este helper
        bad_cmd = ["systemctl", "restart", "monitoreo-vs.service"]
        is_valid, reason = validate_command(bad_cmd)
        assert is_valid is False
        assert "Subcomando 'restart' no permitido" in reason

    def test_exec_system_command_blocks_unauthorized_execution(self) -> None:
        """Verifica que _exec_system_command intercepte y aborte comandos no autorizados."""
        res = _exec_system_command(["cat", "/etc/passwd"])
        assert res["success"] is False
        assert res["returncode"] == -1
        assert "Seguridad: Comando no autorizado" in res["output"]


class TestPhase2AutoRollbackFlagMariaDB:
    """Pruebas de gestión de banderas en MariaDB SSOT y eliminación de /tmp (SEC-010)."""

    def test_auto_rollback_notice_lifecycle_in_mariadb(self) -> None:
        """Valida inserción, consulta y limpieza de bandera de rollback en MariaDB SSOT."""
        # Asegurar estado limpio inicial
        clear_auto_rollback_notice()

        # Registrar notificación
        ok = set_auto_rollback_notice(
            checkpoint_id="CP_TEST_SEC010",
            description="Recuperación de prueba automatizada",
            dt="2026-11-04 12:30:00",
        )
        assert ok is True

        # Consultar notificación
        notice = get_auto_rollback_notice()
        assert notice is not None
        assert notice.get("checkpoint_id") == "CP_TEST_SEC010"
        assert notice.get("description") == "Recuperación de prueba automatizada"

        # Limpiar notificación
        cleared = clear_auto_rollback_notice()
        assert cleared is True

        # Verificar que ya no existe
        notice_after = get_auto_rollback_notice()
        assert notice_after is None

        # Comprobar que NINGÚN archivo plano en /tmp fue creado
        tmp_flag = Path("/tmp/.auto_rollback_occurred")
        assert not tmp_flag.exists(), "ERROR: Se creó archivo en /tmp violando la Regla #7"
