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
    DatabaseTransactionException,
    DatabaseConcurrencyError,
    DecryptionException,
    DomainException,
    EntityNotFoundException,
    HardwareVaultException,
    InfrastructureException,
    InvalidConfigurationException,
    NetworkCaptureException,
    NotificationDispatchException,
    SnmpConnectionException,
    SnmpDeviceValidationError,
    SnmpException,
    SnmpTimeoutException,
    SonarBaseException,
    SubprocessSecurityException,
    VaultError,
    VaultIntegrityError,
    VaultKeyNotFoundError,
    VaultReadError,
    VaultWriteError,
)

__all__ = [
    "CheckExecutionException",
    "ClusterSyncException",
    "DatabaseConnectionException",
    "DatabaseException",
    "DatabaseQueryException",
    "DatabaseTransactionException",
    "DatabaseConcurrencyError",
    "DecryptionException",
    "DomainException",
    "EntityNotFoundException",
    "HardwareVaultException",
    "InfrastructureException",
    "InvalidConfigurationException",
    "NetworkCaptureException",
    "NotificationDispatchException",
    "SnmpConnectionException",
    "SnmpDeviceValidationError",
    "SnmpException",
    "SnmpTimeoutException",
    "SonarBaseException",
    "SubprocessSecurityException",
    "VaultError",
    "VaultIntegrityError",
    "VaultKeyNotFoundError",
    "VaultReadError",
    "VaultWriteError",
]
