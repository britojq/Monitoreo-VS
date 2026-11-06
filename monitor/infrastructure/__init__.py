"""
# ==============================================================================
# 🏗️ CAPA DE INFRAESTRUCTURA: __init__.py
# Exportaciones canónicas de servicios, conectores y contexto MariaDB.
# Ubicación: /opt/sonar/monitor/infrastructure/__init__.py
# ==============================================================================
"""

from __future__ import annotations

from monitor.infrastructure.db_context import (
    atomic_cursor,
    atomic_transaction,
    db_connection,
    db_cursor,
    execute_query,
    execute_scalar,
    execute_statement,
    test_db_connectivity,
)

__all__ = [
    "atomic_cursor",
    "atomic_transaction",
    "db_connection",
    "db_cursor",
    "execute_query",
    "execute_scalar",
    "execute_statement",
    "test_db_connectivity",
]
