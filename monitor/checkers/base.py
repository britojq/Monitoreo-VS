"""
# ==============================================================================
# 🧩 BASE PARA ESTRATEGIAS DE CHEQUEO DE SERVICIOS: base.py
# Principio Abierto/Cerrado (O) y Sustitución de Liskov (L)
# Define el contrato abstracto y la estructura de resultado tipada
# ==============================================================================
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import TYPE_CHECKING

if TYPE_CHECKING:
    from monitor.config_parser import ServiceConfig


@dataclass(frozen=True)
class CheckResult:
    """Resultado inmutable y tipado del chequeo de un servicio."""
    is_ok: bool
    code: str = ""
    detail: str = ""


class BaseServiceChecker(ABC):
    """Contrato base abstracto para cualquier verificador de servicio corporativo."""

    @abstractmethod
    async def check(self, svc: ServiceConfig) -> CheckResult:
        """
        Ejecuta la verificación asíncrona sobre la configuración del servicio.
        Retorna un CheckResult uniforme.
        """
        pass
