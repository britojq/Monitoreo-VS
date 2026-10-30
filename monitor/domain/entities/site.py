"""
# ==============================================================================
# 🏢 ENTIDADES DE SEDE Y EQUIPO: site.py
# Modelado inmutable de sedes operativas y equipos de comunicaciones asociados.
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import List, Optional


@dataclass(frozen=True)
class EquipmentEntity:
    """Entidad inmutable que representa un equipo de telecomunicaciones en una sede."""
    id: Optional[int]
    name: str
    ip_host: str
    site_letter: str
    num: int
    is_active: bool = True
    equipment_type: str = "GENERIC"
    snmp_community: Optional[str] = None
    mac_address: Optional[str] = None

    @property
    def identifier(self) -> str:
        """Identificador alfanumérico del equipo dentro de la sede (ej. A1, B2)."""
        return f"{self.site_letter}{self.num}"


@dataclass(frozen=True)
class SiteEntity:
    """Entidad inmutable que representa una sede física (CIAU, Oficina, etc.)."""
    id: Optional[int]
    name: str
    letter: str
    ip_host: str
    is_active: bool = True
    subnet: Optional[str] = None
    gateway: Optional[str] = None
    equipment: tuple[EquipmentEntity, ...] = field(default_factory=tuple)

    @property
    def display_name(self) -> str:
        """Nombre visible con su identificador de letra."""
        return f"[{self.letter}] {self.name}"
