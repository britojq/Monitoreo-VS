"""
# ==============================================================================
# 📦 EXPORTADOR DE EXCEPCIONES DE DOMINIO E INFRAESTRUCTURA: exceptions/__init__.py
# Ubicación: /opt/sonar/monitor/domain/exceptions/__init__.py
# ==============================================================================
"""

from monitor.domain.exceptions.domain_exceptions import (
    CheckExecutionException,
    ClusterSyncException,
    DatabaseConnectionException,
    DatabaseException,
    DatabaseQueryException,
    DecryptionException,
    DomainException,
    EntityNotFoundException,
    HardwareVaultException,
    InfrastructureException,
    InvalidConfigurationException,
    NetworkCaptureException,
    NotificationDispatchException,
    SnmpConnectionException,
    SnmpException,
    SnmpTimeoutException,
    SonarBaseException,
)

__all__ = [
    "CheckExecutionException",
    "ClusterSyncException",
    "DatabaseConnectionException",
    "DatabaseException",
    "DatabaseQueryException",
    "DecryptionException",
    "DomainException",
    "EntityNotFoundException",
    "HardwareVaultException",
    "InfrastructureException",
    "InvalidConfigurationException",
    "NetworkCaptureException",
    "NotificationDispatchException",
    "SnmpConnectionException",
    "SnmpException",
    "SnmpTimeoutException",
    "SonarBaseException",
]
