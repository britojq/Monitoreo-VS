"""
# ==============================================================================
# 📊 DTO DE RESULTADO DE CHEQUEO: check_result_dto.py
# Estructuras inmutables de transporte de datos para resultados de verificación.
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class CheckResultDTO:
    """Resultado inmutable y tipado del chequeo de un servicio o host."""
    is_ok: bool
    code: str = ""
    detail: str = ""
    response_time_ms: float = 0.0


@dataclass(frozen=True)
class SiteCheckResultDTO:
    """Resultado inmutable de la verificación de una sede física y sus equipos."""
    site_letter: str
    site_name: str
    ip_host: str
    is_ok: bool
    state_label: str
    detail: str
    log_text: str
    equipment_results: tuple[EquipmentCheckResultDTO, ...] = ()


@dataclass(frozen=True)
class EquipmentCheckResultDTO:
    """Resultado inmutable de la verificación de un equipo específico en una sede."""
    site_letter: str
    num: int
    name: str
    ip_host: str
    is_ok: bool
    state_label: str
    detail: str
    log_text: str


@dataclass(frozen=True)
class ServiceCheckResultDTO:
    """Resultado inmutable de la verificación de un servicio corporativo."""
    name: str
    protocol: str
    target: str
    port: Optional[int]
    is_ok: bool
    state_label: str
    code: str
    detail: str
    log_text: str
    response_time_ms: float = 0.0
