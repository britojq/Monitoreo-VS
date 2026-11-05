"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS FASE 4: test_phase4_resilience_and_strategy.py
# Valida la subsanación integral de ARC-01, ARC-03 y ARC-04.
# ==============================================================================
"""

import asyncio
from unittest.mock import AsyncMock, MagicMock, patch
import pytest

from monitor.application.dtos.telemetry_dto import ServiceTelemetryDTO
from monitor.checkers.base import CheckResult
from monitor.checkers.registry import CheckerRegistry
from monitor.infrastructure.database.mariadb_telemetry_repository import MariaDBTelemetryRepository
from monitor.sync.actions.run_full_scan_action import RunFullScanAction
from monitor.sync.evaluators.service_evaluator import evaluate_service


class TestArc04TransactionRollback:
    """ARC-04: Verificación de rollback estricto ante fallos en transacciones MariaDB."""

    def test_record_service_history_calls_rollback_on_error(self):
        """Verifica que conn.rollback() sea invocado cuando cur.execute falla."""
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.execute.side_effect = Exception("Simulated DB Error")
        mock_conn.cursor.return_value.__enter__.return_value = mock_cursor

        mock_pool = MagicMock()
        mock_pool.get_connection.return_value = mock_conn

        repo = MariaDBTelemetryRepository(pool=mock_pool)
        telemetry = ServiceTelemetryDTO(
            service_id=10,
            service_name="Web Intranet",
            protocol="HTTP",
            target="http://intranet.local",
            is_up=True,
            response_time_ms=15.0,
            status_code="200",
            error_message="OK",
        )

        repo.record_service_history(telemetry)

        mock_conn.rollback.assert_called_once()
        mock_conn.close.assert_called_once()


class TestArc03StrategyPatternEvaluator:
    """ARC-03: Verificación de patrón Strategy en service_evaluator."""

    @pytest.mark.anyio
    async def test_evaluate_service_delegates_to_registered_strategy(self):
        """Verifica que evaluate_service delegue en CheckerRegistry sin cascada de ifs."""
        mock_checker = MagicMock()
        mock_checker.check = AsyncMock(return_value=CheckResult(is_ok=True, code="200", detail="OK Mocked"))

        CheckerRegistry.register("CUSTOM_PROTO", mock_checker)

        service_data = {
            "id": 99,
            "letter": "C99",
            "name": "Custom Protocol Service",
            "type": "CUSTOM_PROTO",
            "host_ip": "192.168.1.10",
            "port": 8080,
        }

        res = await evaluate_service(service_data)

        assert res["id"] == 99
        assert res["type"] == "CUSTOM_PROTO"
        assert res["is_up"] is True
        assert res["status"] == "ACTIVO"
        mock_checker.check.assert_called_once()


class TestArc01ContentionReduction:
    """ARC-01: Verificación de reducción de contención de bloqueos DDL/DML."""

    def test_auto_healing_runs_only_once(self):
        """Verifica que _heal_services_once ejecute las consultas UPDATE una sola vez."""
        mock_cursor = MagicMock()
        mock_conn = MagicMock()

        # Reset flag
        RunFullScanAction._auto_healed = False

        # Primera llamada: debe ejecutar los dos UPDATEs
        RunFullScanAction._heal_services_once(mock_cursor, mock_conn)
        assert mock_cursor.execute.call_count == 2
        assert RunFullScanAction._auto_healed is True

        # Segunda llamada en caliente: no debe ejecutar más consultas UPDATE
        RunFullScanAction._heal_services_once(mock_cursor, mock_conn)
        assert mock_cursor.execute.call_count == 2
