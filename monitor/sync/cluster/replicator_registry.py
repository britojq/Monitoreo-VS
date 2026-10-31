"""
# ==============================================================================
# 🗂️ REGISTRO POLIMÓRFICO DE REPLICADORES: replicator_registry.py
# Principio Abierto/Cerrado (OCP) - Despacho desacoplado sin cascadas if/elif.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicator_registry.py
# ==============================================================================
"""
import logging
from typing import Any, Dict, List, Optional

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.dtos import ReplicationBatchDto

logger = logging.getLogger("monitor.sync.cluster.registry")


class ReplicatorRegistry:
    """Registro desacoplado para orquestar la replicación de tablas de clúster."""

    def __init__(self) -> None:
        self._replicators: Dict[str, TableReplicatorProtocol] = {}

    def register(self, domain: str, replicator: TableReplicatorProtocol) -> None:
        """Registra un replicador para un dominio o categoría de datos."""
        self._replicators[domain] = replicator

    def get(self, domain: str) -> Optional[TableReplicatorProtocol]:
        """Obtiene el replicador correspondiente a un dominio."""
        return self._replicators.get(domain)

    def execute_domain(self, domain: str, cursor: Any, data: Dict[str, Any]) -> ReplicationBatchDto:
        """Ejecuta un replicador específico de forma aislada."""
        replicator = self._replicators.get(domain)
        if not replicator:
            return ReplicationBatchDto(domain=domain, records_processed=0, success=False, error_message="No replicator registered")

        try:
            count = replicator.replicate(cursor, data)
            return ReplicationBatchDto(domain=domain, records_processed=count, success=True)
        except Exception as e:
            logger.error(f"Error replicando dominio {domain}: {e}", exc_info=True)
            return ReplicationBatchDto(domain=domain, records_processed=0, success=False, error_message=str(e))

    def execute_all(self, cursor: Any, data: Dict[str, Any]) -> List[ReplicationBatchDto]:
        """Ejecuta todos los replicadores registrados en orden."""
        results: List[ReplicationBatchDto] = []
        for domain, replicator in self._replicators.items():
            try:
                count = replicator.replicate(cursor, data)
                results.append(ReplicationBatchDto(domain=domain, records_processed=count, success=True))
            except Exception as e:
                logger.error(f"Fallo en replicación de {domain}: {e}", exc_info=True)
                results.append(ReplicationBatchDto(domain=domain, records_processed=0, success=False, error_message=str(e)))
        return results
