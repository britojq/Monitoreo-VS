"""
# ==============================================================================
# 📊 FACHADA DELEGADORA DE MONITOREO: monitor_engine.py (@IA_ValleSeco_bot)
# Arquitectura SOLID - Fachada ultradelgada de delegación y retrocompatibilidad.
# Ubicación: /opt/sonar/monitor/monitor_engine.py
# License: GNU Affero General Public License v3.0
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

from monitor.application.orchestrator import execute_monitoring
from monitor.application.snapshot_manager import get_latest_snapshot
from monitor.cli.monitor_cli import get_default_telegram_chats, main
from monitor.formatters.service_report_compiler import compile_services_from_snapshot
from monitor.formatters.site_report_compiler import compile_sedes_from_snapshot
from monitor.infrastructure.log_writer import write_consolidated_log
from monitor.monitor_servicios import run_services_check

__all__ = [
    "execute_monitoring",
    "get_latest_snapshot",
    "compile_services_from_snapshot",
    "compile_sedes_from_snapshot",
    "get_default_telegram_chats",
    "write_consolidated_log",
    "run_services_check",
    "main",
]

if __name__ == "__main__":
    main()
