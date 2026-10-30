"""
# ==============================================================================
# ⚙️ CASO DE USO: CHEQUEO DE TODOS LOS SERVICIOS CORPORATIVOS
# check_all_services_use_case.py
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import time
from typing import List, Tuple

from monitor.application.dtos.check_result_dto import ServiceCheckResultDTO
from monitor.checkers import CheckerRegistry
from monitor.config_parser import ServiceConfig
from monitor.domain.entities.service import ServiceEntity
from monitor.domain.protocols.database_protocol import (
    ServiceRepositoryProtocol,
    TelemetryRepositoryProtocol,
)

logger = logging.getLogger("application.use_case.check_services")


class CheckAllServicesUseCase:
    """Caso de uso orquestador para el chequeo concurrente de servicios corporativos."""

    def __init__(
        self,
        service_repo: ServiceRepositoryProtocol,
        telemetry_repo: TelemetryRepositoryProtocol | None = None,
    ) -> None:
        self._service_repo = service_repo
        self._telemetry_repo = telemetry_repo

    async def _verify_service(self, service: ServiceEntity) -> ServiceCheckResultDTO:
        """Verifica un servicio individual utilizando la estrategia registrada."""
        t0 = time.perf_counter()
        # Adaptar ServiceEntity a ServiceConfig para el registry
        raw_ip = service.target.split("/")[2].split(":")[0] if "://" in service.target else service.target.split(":")[0]
        cfg = ServiceConfig(
            letter=f"S{service.id}" if service.id else "S",
            name=service.name,
            service_type=service.protocol,
            ip_host=raw_ip,
            port=service.port,
            web_url=service.target if "://" in service.target else "",
        )

        checker = CheckerRegistry.get(service.protocol)
        res = await checker.check(cfg)
        elapsed_ms = (time.perf_counter() - t0) * 1000.0

        is_ok = res.is_ok
        state_label = "ACTIVO" if is_ok else "APAGADO"
        log_text = (
            f"----------------------------------------\n"
            f"SERVICIO: [{cfg.letter}] {service.name} ({service.protocol})\n"
            f"ESTADO: {state_label} | DETALLE: {res.detail}\n"
            f"----------------------------------------"
        )

        return ServiceCheckResultDTO(
            name=service.name,
            protocol=service.protocol,
            target=service.target,
            port=service.port,
            is_ok=is_ok,
            state_label=state_label,
            code=res.code,
            detail=res.detail,
            log_text=log_text,
            response_time_ms=elapsed_ms,
        )

    async def execute(self) -> Tuple[List[ServiceCheckResultDTO], List[str], float]:
        """
        Ejecuta la verificación concurrente de todos los servicios activos.
        Retorna: (lista_resultados_servicios, lista_logs_formateados, tiempo_total_segundos).
        """
        t0 = time.perf_counter()
        services = self._service_repo.get_all_active()

        tasks = [self._verify_service(s) for s in services]
        results = await asyncio.gather(*tasks) if tasks else []

        logs = [r.log_text for r in results]
        elapsed = time.perf_counter() - t0
        return list(results), logs, elapsed
