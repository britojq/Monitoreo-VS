"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS SOLID: MONITOR ENGINE Y SNMP POLLER DECONSTRUIDOS
# Arquitectura SOLID - Verificación de SRP, OCP, Estrategias y Criptografía AES.
# Ubicación: /opt/sonar/tests/unit/test_monitor_engine_solid.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import base64
import json
import os
import time
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock, patch
import pytest
from cryptography.hazmat.primitives import padding
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

from monitor.application.lock_manager import LockManager
from monitor.application.orchestrator import (
    MonitoringOrchestrator,
    execute_monitoring,
    get_default_orchestrator,
)
from monitor.application.snapshot_manager import (
    get_latest_snapshot,
    save_snapshot_payload,
)
from monitor.application.strategies.contracts import (
    MonitoringResultDto,
    MonitoringStrategyProtocol,
)
from monitor.application.strategies.cleanup_strategy import SystemCleanupStrategy
from monitor.application.strategies.full_strategy import FullMonitoringStrategy
from monitor.application.strategies.network_strategy import NetworkAnalysisStrategy
from monitor.application.strategies.outages_strategy import OutagesMonitoringStrategy
from monitor.application.strategies.registry import (
    MonitoringStrategyRegistry,
    get_default_strategy_registry,
)
from monitor.application.strategies.services_strategy import ServicesMonitoringStrategy
from monitor.application.strategies.sites_strategy import SitesMonitoringStrategy
from monitor.formatters.service_report_compiler import compile_services_from_snapshot
from monitor.formatters.site_report_compiler import compile_sedes_from_snapshot
from monitor.infrastructure.laravel_encryptor import (
    decrypt_laravel_string,
    get_laravel_app_key,
)
from monitor.infrastructure.shm_cache import ShmCache
import monitor.monitor_engine as monitor_engine
import monitor.snmp_poller as snmp_poller


class TestLockManager:
    """Valida el control de concurrencia y ciclo de vida de archivos lock."""

    def test_acquire_and_release(self, tmp_path: Path) -> None:
        lock_file = tmp_path / "test.lock"
        lm = LockManager(lock_file=lock_file, expiration_seconds=10.0)

        assert not lm.is_locked()
        assert lm.acquire() is True
        assert lm.is_locked() is True
        assert lm.acquire() is False  # Segundo intento falla

        lm.release()
        assert not lm.is_locked()

    def test_context_manager(self, tmp_path: Path) -> None:
        lock_file = tmp_path / "test_ctx.lock"
        lm = LockManager(lock_file=lock_file, expiration_seconds=10.0)

        with lm.context() as acquired:
            assert acquired is True
            assert lm.is_locked() is True

        assert not lm.is_locked()

    def test_expired_lock_auto_cleanup(self, tmp_path: Path) -> None:
        lock_file = tmp_path / "expired.lock"
        lock_file.write_text("1234\n0\n", encoding="utf-8")
        # Forzar mtime en el pasado (hace 100 segundos)
        past_time = time.time() - 100
        os.utime(lock_file, (past_time, past_time))

        lm = LockManager(lock_file=lock_file, expiration_seconds=10.0)
        assert lm.is_locked() is False
        assert not lock_file.exists()


class TestSnapshotManager:
    """Valida la recuperación y persistencia de snapshots en MariaDB."""

    def test_get_latest_snapshot_success(self) -> None:
        payload = {"services": [{"letter": "A", "name": "Web", "is_up": True}]}
        mock_row = {
            "payload_json": json.dumps(payload),
            "created_at": "2026-10-30 10:00:00",
        }
        with patch("monitor.application.snapshot_manager.db_cursor") as mock_cur_ctx:
            mock_cur = MagicMock()
            mock_cur.fetchone.return_value = mock_row
            mock_cur_ctx.return_value.__enter__.return_value = mock_cur

            data, dt_str = get_latest_snapshot()
            assert data == payload
            assert dt_str == "2026-10-30 10:00:00"

    def test_get_latest_snapshot_empty(self) -> None:
        with patch("monitor.application.snapshot_manager.db_cursor") as mock_cur_ctx:
            mock_cur = MagicMock()
            mock_cur.fetchone.return_value = None
            mock_cur_ctx.return_value.__enter__.return_value = mock_cur

            data, dt_str = get_latest_snapshot()
            assert data is None
            assert dt_str is None


class TestCompilers:
    """Valida los formateadores desacoplados de reportes."""

    def test_compile_services_from_snapshot(self) -> None:
        snapshot = {
            "services": [
                {"letter": "A", "name": "Portal Web", "is_up": True, "scope": "corporativo"},
                {"letter": "B", "name": "Servidor DNS", "is_up": False, "scope": "regional"},
            ]
        }
        report, vars_dict, logs = compile_services_from_snapshot(snapshot, "2026-10-30 11:00:00")
        assert "Portal Web" in report
        assert "Servidor DNS" in report
        assert "✅ - Portal Web" in report
        assert "❌ - Servidor DNS" in report
        assert len(logs) == 2

    def test_compile_sedes_from_snapshot(self) -> None:
        snapshot = {
            "sites": [
                {
                    "letter": "A",
                    "name": "Sede Valle Seco",
                    "is_up": True,
                    "devices": [{"name": "Switch Core", "is_up": True}],
                }
            ]
        }
        report, vars_dict, logs = compile_sedes_from_snapshot(snapshot, "2026-10-30 11:00:00")
        assert "Sede Valle Seco" in report
        assert "Switch Core" in report
        assert "✅ - Sede Valle Seco" in report


class TestMonitoringStrategiesOCP:
    """Valida el Patrón Strategy y el cumplimiento de OCP."""

    def test_strategies_implement_protocol(self) -> None:
        strategies = [
            ServicesMonitoringStrategy(),
            SitesMonitoringStrategy(),
            OutagesMonitoringStrategy(),
            FullMonitoringStrategy(),
            NetworkAnalysisStrategy(),
            SystemCleanupStrategy(),
        ]
        for s in strategies:
            assert isinstance(s, MonitoringStrategyProtocol)

    def test_registry_resolves_correct_strategy(self) -> None:
        registry = get_default_strategy_registry()

        assert isinstance(registry.get_strategy("servicios"), ServicesMonitoringStrategy)
        assert isinstance(registry.get_strategy("sedes"), SitesMonitoringStrategy)
        assert isinstance(registry.get_strategy("caidas"), OutagesMonitoringStrategy)
        assert isinstance(registry.get_strategy("analisis_red"), NetworkAnalysisStrategy)
        assert isinstance(registry.get_strategy("limpiar"), SystemCleanupStrategy)
        assert isinstance(registry.get_strategy("completo"), FullMonitoringStrategy)
        # Fallback a estrategia completa ante cualquier objetivo no registrado
        assert isinstance(registry.get_strategy("desconocido_xyz"), FullMonitoringStrategy)


class TestLaravelEncryptor:
    """Valida el descifrador AES-256-CBC compatible con Laravel."""

    def test_encrypt_and_decrypt_roundtrip(self) -> None:
        key = get_laravel_app_key()
        assert len(key) == 32  # 256 bits

        # Simular cifrado al estilo Laravel
        plaintext = "MiComunidadSNMP123"
        iv = os.urandom(16)
        padder = padding.PKCS7(128).padder()
        padded_data = padder.update(plaintext.encode("utf-8")) + padder.finalize()

        cipher = Cipher(algorithms.AES(key), modes.CBC(iv))
        encryptor = cipher.encryptor()
        ciphertext = encryptor.update(padded_data) + encryptor.finalize()

        payload = {
            "iv": base64.b64encode(iv).decode("utf-8"),
            "value": base64.b64encode(ciphertext).decode("utf-8"),
            "mac": "dummy_mac",
            "tag": "",
        }
        encrypted_str = base64.b64encode(json.dumps(payload).encode("utf-8")).decode("utf-8")

        decrypted = decrypt_laravel_string(encrypted_str)
        assert decrypted == plaintext

    def test_decrypt_empty_string(self) -> None:
        assert decrypt_laravel_string("") == ""


class TestFacadesAndOrchestrator:
    """Valida la integridad de las fachadas y la ejecución del orquestador."""

    def test_monitor_engine_facade_exports(self) -> None:
        assert hasattr(monitor_engine, "execute_monitoring")
        assert hasattr(monitor_engine, "get_latest_snapshot")
        assert hasattr(monitor_engine, "compile_services_from_snapshot")
        assert hasattr(monitor_engine, "compile_sedes_from_snapshot")
        assert hasattr(monitor_engine, "main")

    def test_snmp_poller_facade_exports(self) -> None:
        assert hasattr(snmp_poller, "decrypt_laravel_string")
        assert hasattr(snmp_poller, "poll_device_oids")
        assert hasattr(snmp_poller, "discover_interfaces")
        assert hasattr(snmp_poller, "poll_interface_metrics")
        assert hasattr(snmp_poller, "run_snmp_poll")
        assert hasattr(snmp_poller, "main")

    def test_orchestrator_skips_when_locked(self, tmp_path: Path) -> None:
        import asyncio
        lock_file = tmp_path / "locked.lock"
        lock_file.write_text("9999\n", encoding="utf-8")
        lm = LockManager(lock_file=lock_file, expiration_seconds=60.0)

        orchestrator = MonitoringOrchestrator(lock_manager=lm)
        res = asyncio.run(orchestrator.run(target="servicios"))
        assert res["skipped"] is True
        assert res["report_servicios"] is None
