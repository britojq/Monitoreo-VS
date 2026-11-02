"""
# ==============================================================================
# 🚨 EXCEPCIONES DE DOMINIO E INFRAESTRUCTURA: domain_exceptions.py
# Jerarquía estricta de excepciones tipadas para el ecosistema S.O.N.A.R.
# Arquitectura SOLID - Principio de Responsabilidad Única y Tipado Fuerte.
# Ubicación: /opt/sonar/monitor/domain/exceptions/domain_exceptions.py
# ==============================================================================
"""

from __future__ import annotations

from typing import Any, Optional


class SonarBaseException(Exception):
    """Excepción raíz de la que heredan todos los errores tipados del sistema S.O.N.A.R."""

    def __init__(self, message: str = "", *args: Any, **kwargs: Any) -> None:
        super().__init__(message, *args)
        self.message: str = message

    def __str__(self) -> str:
        return self.message or self.__class__.__name__

    def __repr__(self) -> str:
        return f"{self.__class__.__name__}(message={self.message!r})"


# ==============================================================================
# CAPA DE DOMINIO Y LÓGICA DE NEGOCIO
# ==============================================================================

class DomainException(SonarBaseException):
    """Excepción base para cualquier error en la capa de dominio o lógica de negocio."""
    pass


class EntityNotFoundException(DomainException):
    """Lanzada cuando una entidad esperada (sede, dispositivo, servicio) no existe en SSOT."""
    pass


class CheckExecutionException(DomainException):
    """Lanzada ante un error irrecuperable durante la ejecución de un chequeo o sondeo."""
    pass


class InvalidConfigurationException(DomainException):
    """Lanzada cuando la configuración del sistema, parámetros o umbrales son inválidos."""
    pass


class NotificationDispatchException(DomainException):
    """Lanzada ante fallos en el despacho de alertas a Telegram o canales de notificación."""
    pass


# ==============================================================================
# CAPA DE INFRAESTRUCTURA Y HARDWARE
# ==============================================================================

class InfrastructureException(SonarBaseException):
    """Excepción base para errores de comunicación, red, persistencia o hardware."""
    pass


class DatabaseException(InfrastructureException):
    """Excepción base para operaciones de persistencia en MariaDB (SSOT)."""
    pass


class DatabaseConnectionException(DatabaseException, DomainException):
    """Lanzada cuando la conexión a MariaDB falla o las credenciales no son válidas."""
    pass


class DatabaseQueryException(DatabaseException):
    """Lanzada cuando una consulta o sentencia SQL falla durante su ejecución."""
    pass


class DecryptionException(InfrastructureException):
    """Lanzada ante fallos en el descifrado AES o claves criptográficas no válidas."""
    pass


class SnmpException(InfrastructureException):
    """Excepción base para operaciones del protocolo SNMP."""
    pass


class SnmpConnectionException(SnmpException):
    """Lanzada cuando no es posible establecer enlace de red SNMP con el agente."""
    pass


class SnmpTimeoutException(SnmpException):
    """Lanzada cuando una petición SNMP supera el tiempo máximo de espera sin respuesta."""
    pass


class ClusterSyncException(InfrastructureException):
    """Lanzada ante fallos en la replicación o sincronización de nodos del clúster."""
    pass


class NetworkCaptureException(InfrastructureException):
    """Lanzada cuando falla la captura de paquetes tcpdump, tshark o inspección PCAP."""
    pass


class HardwareVaultException(InfrastructureException):
    """Lanzada ante fallos al acceder o derivar llaves criptográficas de anclaje de hardware."""
    pass


class VaultError(HardwareVaultException):
    """Excepción base para fallos operativos en el subsistema de bóveda criptográfica."""
    pass


class VaultWriteError(VaultError):
    """Lanzada cuando la escritura o sellado del anclaje de hardware falla."""
    pass


class VaultReadError(VaultError):
    """Lanzada cuando la lectura o desenrollado de llaves en memoria falla."""
    pass


class VaultKeyNotFoundError(VaultError):
    """Lanzada cuando la llave maestra o los secretos del vault no están aprovisionados."""
    pass


class VaultIntegrityError(VaultError):
    """Lanzada ante discrepancias de firma HMAC o corrupción de datos en el anclaje."""
    pass


class DatabaseTransactionException(DatabaseException):
    """Lanzada cuando una transacción atómica falla y debe ser revertida (rollback)."""
    pass


class DatabaseConcurrencyError(DatabaseTransactionException):
    """Lanzada ante conflictos de concurrencia o tiempos de espera en bloqueos pesimistas."""
    pass


class SnmpDeviceValidationError(SnmpException, DomainException):
    """Lanzada cuando la configuración o parámetros de un dispositivo SNMP no son válidos."""
    pass


class SubprocessSecurityException(InfrastructureException):
    """Lanzada cuando un argumento de subproceso contiene patrones peligrosos o metacaracteres."""
    pass

