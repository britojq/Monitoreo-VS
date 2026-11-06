"""
# ==============================================================================
# 🛡️ TERMINAL SHIELD GUI CONTRACTS: contracts.py
# Protocolo tipado para adaptadores de diálogo visual y advertencias en pantalla
# Ubicación: /opt/sonar/monitor/shield/ui/contracts.py
# ==============================================================================
"""

from __future__ import annotations

from typing import Protocol


class GuiDialogProtocol(Protocol):
    """Contrato formal para lanzamiento de diálogos de advertencia y contención gráfica."""

    def launch(self, mode: str, attempt: int = 1, seconds: int = 10, dry_run: bool = False) -> bool:
        """Despliega una advertencia visual en el entorno de escritorio activo."""
        ...
