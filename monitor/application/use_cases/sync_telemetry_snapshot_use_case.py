"""
# ==============================================================================
# 📸 CASO DE USO: SINCRONIZACIÓN Y PERSISTENCIA DE SNAPSHOT
# sync_telemetry_snapshot_use_case.py
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

import logging
from typing import Any, Dict

from monitor.application.dtos.telemetry_dto import SnapshotDTO
from monitor.domain.protocols.database_protocol import (
    SnapshotRepositoryProtocol,
    TelemetryRepositoryProtocol,
)

logger = logging.getLogger("application.use_case.sync_snapshot")


class SyncTelemetrySnapshotUseCase:
    """Caso de uso orquestador para capturar y persistir snapshots del clúster."""

    def __init__(
        self,
        snapshot_repo: SnapshotRepositoryProtocol,
        telemetry_repo: TelemetryRepositoryProtocol | None = None,
    ) -> None:
        self._snapshot_repo = snapshot_repo
        self._telemetry_repo = telemetry_repo

    def execute(
        self,
        total_sites: int,
        up_sites: int,
        down_sites: int,
        total_services: int,
        up_services: int,
        down_services: int,
        total_devices: int,
        up_devices: int,
        down_devices: int,
        payload_json: Dict[str, Any],
    ) -> int:
        """
        Construye y guarda el snapshot global en MariaDB.
        Retorna el ID del registro insertado en monitoring_snapshots.
        """
        snapshot = SnapshotDTO(
            total_sites=total_sites,
            up_sites=up_sites,
            down_sites=down_sites,
            total_services=total_services,
            up_services=up_services,
            down_services=down_services,
            total_devices=total_devices,
            up_devices=up_devices,
            down_devices=down_devices,
            payload_json=payload_json,
        )
        snapshot_id = self._snapshot_repo.save_snapshot(snapshot)
        logger.info(f"Snapshot consolidado guardado exitosamente con ID #{snapshot_id}")
        return snapshot_id
