"""
# ==============================================================================
# 📝 ESCRITURA Y PERSISTENCIA DE LOGS: log_writer.py
# Arquitectura SOLID - Principio de Responsabilidad Única (SRP).
# Centraliza la escritura de bitácoras consolidadas en /tmp y systemd-journald.
# Ubicación: /opt/sonar/monitor/infrastructure/log_writer.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import List, Optional

from monitor.config_parser import get_formatted_datetime

logger = logging.getLogger("monitor.infrastructure.log_writer")

DEFAULT_LOG_DIR = Path("/tmp/monitor")
DEFAULT_LOG_FILENAME = "servicelog.txt"


def write_consolidated_log(
    logs: List[str],
    log_dir: Optional[Path] = None,
    filename: str = DEFAULT_LOG_FILENAME,
) -> Path:
    """
    Escribe la bitácora consolidada de chequeos con cabecera formal y marcas de tiempo.
    Retorna la ruta absoluta del archivo generado.
    """
    target_dir = log_dir or DEFAULT_LOG_DIR
    target_dir.mkdir(parents=True, exist_ok=True)
    log_file = target_dir / filename

    fecha_str, hora_str = get_formatted_datetime()

    header = (
        "################################################\n"
        "##  REGISTROS DE CHEQUEO DE SISTEMA S.O.N.A.R.\n"
        f"##  FECHA DE EJECUCION: {fecha_str}\n"
        f"##  HORA DE EJECUCION: {hora_str}\n"
        "################################################\n\n"
    )

    content = header + "\n\n".join(logs) + "\n"
    log_file.write_text(content, encoding="utf-8", errors="ignore")
    return log_file
