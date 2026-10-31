"""
# ==============================================================================
# 🏛️ CONTRATO DE REPLICACIÓN DE TABLAS CLÚSTER: contracts.py
# Principio de Inversión de Dependencias (DIP) y Segregación de Interfaces (ISP).
# Ubicación: /opt/sonar/monitor/sync/cluster/contracts.py
# ==============================================================================
"""
from typing import Any, Dict, Protocol, runtime_checkable


@runtime_checkable
class TableReplicatorProtocol(Protocol):
    """Contrato formal para cualquier replicador de entidad o tabla de clúster."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        """
        Persiste el lote de datos recibido del Master en MariaDB local.
        Retorna la cantidad total de registros procesados.
        """
        ...
