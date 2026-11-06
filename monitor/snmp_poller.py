"""
# ==============================================================================
# 📡 FACHADA DELEGADORA SNMP: snmp_poller.py (@IA_ValleSeco_bot)
# Arquitectura SOLID - Fachada ultradelgada de delegación y retrocompatibilidad.
# Ubicación: /opt/sonar/monitor/snmp_poller.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

from monitor.application.snmp_orchestrator import (
    print_status,
    run_snmp_poll,
    snmp_walk_arp_table,
)
from monitor.checkers.snmp.interface_discoverer import discover_interfaces
from monitor.checkers.snmp.interface_metrics import poll_interface_metrics
from monitor.checkers.snmp.snmp_engine import poll_device_oids
from monitor.cli.snmp_cli import main
from monitor.infrastructure.laravel_encryptor import (
    decrypt_laravel_string,
    encrypt_laravel_string,
    encrypt_laravel_string_gcm,
    get_laravel_app_key,
)

__all__ = [
    "decrypt_laravel_string",
    "encrypt_laravel_string",
    "encrypt_laravel_string_gcm",
    "get_laravel_app_key",
    "poll_device_oids",
    "discover_interfaces",
    "poll_interface_metrics",
    "run_snmp_poll",
    "snmp_walk_arp_table",
    "print_status",
    "main",
]

if __name__ == "__main__":
    main()
