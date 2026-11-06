"""
# ==============================================================================
# 📦 DTOs Y MODELOS DE DOMINIO INMUTABLES PARA ACTAS ITAM: models.py
# Ubicación: monitor/actas/models.py
# License: GNU AGPLv3
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import List, Optional


@dataclass(frozen=True)
class CustodianData:
    """Datos inmutables del custodio o funcionario responsable."""
    name: str
    cedula: str
    department: str
    role: Optional[str] = None


@dataclass(frozen=True)
class PeripheralData:
    """Datos inmutables de periféricos vinculados a un activo."""
    type: str
    label: str
    brand: str
    model: str
    serial: str
    notes: str


@dataclass(frozen=True)
class AssetData:
    """Datos inmutables del equipo o activo principal."""
    serial: str
    name: str
    brand: str
    model: str
    type: str = "desktop"
    ip_address: Optional[str] = None
    mac_address: Optional[str] = None
    operating_system: Optional[str] = None
    notes: Optional[str] = None
    exists_id: Optional[int] = None


@dataclass(frozen=True)
class PageActaConfig:
    """Configuración inmutable de un acta digitalizada."""
    page: int
    code: str
    pdf_name: str
    date: str
    movement_type: str  # 'assignment' o 'return'
    custodian: CustodianData
    asset: AssetData
    peripherals: List[PeripheralData] = field(default_factory=list)
    observations: str = ""
