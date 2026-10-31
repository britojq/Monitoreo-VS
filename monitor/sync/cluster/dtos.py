"""
# ==============================================================================
# 📦 DTOs INMUTABLES PARA SINCRONIZACIÓN DE CLÚSTER: dtos.py
# Principio de Responsabilidad Única y Transferencia Tipada sin Diccionarios Crudos.
# Ubicación: /opt/sonar/monitor/sync/cluster/dtos.py
# ==============================================================================
"""
from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class ReplicationBatchDto:
    """Representa el resultado de la replicación de una tabla o catálogo."""
    domain: str
    records_processed: int
    success: bool = True
    error_message: Optional[str] = None


@dataclass(frozen=True)
class ClusterSyncSummaryDto:
    """Resumen consolidado del ciclo de sincronización esclavo/master."""
    total_domains: int
    total_records: int
    elapsed_seconds: float
    status: str
