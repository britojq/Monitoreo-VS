"""
# ==============================================================================
# 📦 EXPORTADOR DE EXCEPCIONES DE DOMINIO: exceptions/__init__.py
# ==============================================================================
"""

from monitor.domain.exceptions.domain_exceptions import (
    DomainException,
    EntityNotFoundException,
    CheckExecutionException,
    DatabaseConnectionException,
    NotificationDispatchException,
    InvalidConfigurationException,
)

__all__ = [
    "DomainException",
    "EntityNotFoundException",
    "CheckExecutionException",
    "DatabaseConnectionException",
    "NotificationDispatchException",
    "InvalidConfigurationException",
]
