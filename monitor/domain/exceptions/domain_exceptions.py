"""
# ==============================================================================
# 🚨 EXCEPCIONES DE DOMINIO: domain_exceptions.py
# Jerarquía estricta de excepciones de negocio y dominio para el sistema de monitoreo.
# ==============================================================================
"""

from __future__ import annotations


class DomainException(Exception):
    """Excepción base para cualquier error de la capa de dominio."""
    def __init__(self, message: str) -> None:
        super().__init__(message)
        self.message = message


class EntityNotFoundException(DomainException):
    """Lanzada cuando una entidad esperada (sede, dispositivo, servicio) no existe."""
    pass


class CheckExecutionException(DomainException):
    """Lanzada ante un error irrecuperable durante la ejecución de un chequeo."""
    pass


class DatabaseConnectionException(DomainException):
    """Lanzada cuando la conexión a MariaDB (SSOT) falla o las credenciales no son válidas."""
    pass


class NotificationDispatchException(DomainException):
    """Lanzada ante fallos en el despacho de alertas a Telegram o canales de notificación."""
    pass


class InvalidConfigurationException(DomainException):
    """Lanzada cuando la configuración del sistema o parámetros son inválidos."""
    pass
