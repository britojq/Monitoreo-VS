"""
# ==============================================================================
# 🏢 CASO DE USO: CHEQUEO DE TODAS LAS SEDES Y ENLACES
# check_all_sites_use_case.py
# Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import time
from typing import List, Tuple

from monitor.application.dtos.check_result_dto import (
    EquipmentCheckResultDTO,
    SiteCheckResultDTO,
)
from monitor.checker_base import check_ping
from monitor.domain.entities.site import EquipmentEntity, SiteEntity
from monitor.domain.protocols.database_protocol import (
    SiteRepositoryProtocol,
    TelemetryRepositoryProtocol,
)

logger = logging.getLogger("application.use_case.check_sites")


class CheckAllSitesUseCase:
    """Caso de uso orquestador para el chequeo concurrente de sedes físicas."""

    def __init__(
        self,
        site_repo: SiteRepositoryProtocol,
        telemetry_repo: TelemetryRepositoryProtocol | None = None,
    ) -> None:
        self._site_repo = site_repo
        self._telemetry_repo = telemetry_repo

    async def _verify_equipment(self, eq: EquipmentEntity) -> EquipmentCheckResultDTO:
        """Verifica un equipo específico dentro de una sede."""
        is_ok, _, detail = await check_ping(eq.ip_host)
        state_label = "ACTIVO" if is_ok else "APAGADO"
        log_text = (
            f"----------------------------------------\n"
            f"EQUIPO: [{eq.site_letter}{eq.num}] {eq.name} (IP: {eq.ip_host})\n"
            f"ESTADO: {state_label} | DETALLE: {detail}\n"
            f"----------------------------------------"
        )
        return EquipmentCheckResultDTO(
            site_letter=eq.site_letter,
            num=eq.num,
            name=eq.name,
            ip_host=eq.ip_host,
            is_ok=is_ok,
            state_label=state_label,
            detail=detail,
            log_text=log_text,
        )

    async def _verify_site(self, site: SiteEntity) -> SiteCheckResultDTO:
        """Verifica la IP de una sede física y todos sus equipos asociados."""
        site_ok, _, site_detail = await check_ping(site.ip_host)
        state_label = "ACTIVO" if site_ok else "APAGADO"
        log_text = (
            f"----------------------------------------\n"
            f"SEDE: [{site.letter}] {site.name} (IP: {site.ip_host})\n"
            f"ESTADO: {state_label} | DETALLE: {site_detail}\n"
            f"----------------------------------------"
        )

        # Chequeo concurrente de equipos de esta sede
        eq_tasks = [self._verify_equipment(eq) for eq in site.equipment]
        eq_results = await asyncio.gather(*eq_tasks) if eq_tasks else []

        return SiteCheckResultDTO(
            site_letter=site.letter,
            site_name=site.name,
            ip_host=site.ip_host,
            is_ok=site_ok,
            state_label=state_label,
            detail=site_detail,
            log_text=log_text,
            equipment_results=tuple(eq_results),
        )

    async def execute(self) -> Tuple[List[SiteCheckResultDTO], List[str], float]:
        """
        Ejecuta la verificación concurrente de todas las sedes activas.
        Retorna: (lista_resultados_sede, lista_logs_formateados, tiempo_total_segundos).
        """
        t0 = time.perf_counter()
        sites = self._site_repo.get_all_active()

        site_tasks = [self._verify_site(site) for site in sites]
        results = await asyncio.gather(*site_tasks) if site_tasks else []

        logs: List[str] = []
        for res in results:
            logs.append(res.log_text)
            for eq_res in res.equipment_results:
                logs.append(eq_res.log_text)

        elapsed = time.perf_counter() - t0
        return list(results), logs, elapsed
