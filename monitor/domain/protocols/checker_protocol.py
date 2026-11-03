"""
# ==============================================================================
# 🧩 CONTRATO DE ESTRATEGIAS DE CHEQUEO: checker_protocol.py
# Principio de Inversión de Dependencias (D) y Segregación de Interfaces (I).
# ==============================================================================
"""

from __future__ import annotations

from typing import Protocol, runtime_checkable
from monitor.domain.entities.service import ServiceEntity
from monitor.application.dtos.check_result_dto import CheckResultDTO


@runtime_checkable
class ServiceCheckerProtocol(Protocol):
    """Protocolo formal para cualquier verificador de protocolos de red."""

    async def check(self, service: ServiceEntity) -> CheckResultDTO:
        """Ejecuta la verificación asíncrona de un servicio.

        Args:
            service: Entidad ServiceEntity con los metadatos y configuración del servicio.

        Returns:
            CheckResultDTO: Resultado inmutable y fuertemente tipado con estado, latencia y detalles.

        Raises:
            CheckExecutionException: Si ocurre un fallo no recuperable durante la verificación.
        """
        ...
