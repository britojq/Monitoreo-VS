"""
# ==============================================================================
# ⚙️ ENTIDAD DE SERVICIO CORPORATIVO: service.py
# Modelado inmutable de servicios de infraestructura (Web, DNS, LDAP, Ping, etc.).
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class ServiceEntity:
    """Entidad inmutable que representa un servicio monitoreado."""
    id: Optional[int]
    name: str
    protocol: str
    target: str
    port: Optional[int] = None
    is_active: bool = True
    timeout_seconds: float = 5.0
    expected_status_code: Optional[int] = 200
    path: Optional[str] = None
    description: Optional[str] = None

    @property
    def normalized_protocol(self) -> str:
        """Protocolo normalizado en minúsculas."""
        return self.protocol.strip().lower()
