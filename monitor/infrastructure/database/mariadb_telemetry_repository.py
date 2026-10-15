"""
# ==============================================================================
# 📈 REPOSITORIO MARIADB DE TELEMETRÍA E HISTÓRICOS: mariadb_telemetry_repository.py
# Implementación de TelemetryRepositoryProtocol sobre MariaDB (SSOT).
# Cumple estrictamente con la Regla de Oro #3 (Replicación e históricos).
# ==============================================================================
"""

from __future__ import annotations

import logging
from monitor.application.dtos.telemetry_dto import (
    DeviceTelemetryDTO,
    ServiceTelemetryDTO,
    SiteTelemetryDTO,
)
from monitor.domain.protocols.database_protocol import TelemetryRepositoryProtocol
from monitor.infrastructure.database.mariadb_connection import MariaDBConnectionPool

logger = logging.getLogger("infrastructure.repository.telemetry")


class MariaDBTelemetryRepository(TelemetryRepositoryProtocol):
    """Repositorio para poblar el histórico de telemetría en MariaDB."""

    def __init__(self, pool: type[MariaDBConnectionPool] = MariaDBConnectionPool) -> None:
        self._pool = pool

    def record_service_history(self, telemetry: ServiceTelemetryDTO) -> None:
        """Inserta un registro en service_check_histories."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    INSERT INTO service_check_histories
                    (monitored_service_id, is_up, latency_ms, http_code, status_message, checked_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                    """,
                    (
                        telemetry.service_id,
                        1 if telemetry.is_up else 0,
                        float(telemetry.response_time_ms or 0.0),
                        str(telemetry.status_code or "")[:10],
                        str(telemetry.error_message or ("Operacional" if telemetry.is_up else "Timeout"))[:255],
                    ),
                )
                conn.commit()
        except Exception as e:
            logger.error(f"Error insertando en service_check_histories: {e}")
        finally:
            conn.close()

    def record_site_history(self, telemetry: SiteTelemetryDTO) -> None:
        """Inserta un registro en site_check_histories."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                status_msg = "Enlace Operativo" if telemetry.is_up else "Enlace Caído / Timeout"
                cur.execute(
                    """
                    INSERT INTO site_check_histories
                    (monitored_site_id, is_up, latency_ms, packet_loss_pct, jitter_ms, min_rtt_ms, max_rtt_ms, mdev_ms, devices_online, devices_total, status_message, checked_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                    """,
                    (
                        telemetry.site_id,
                        1 if telemetry.is_up else 0,
                        0.0,
                        0.0 if telemetry.is_up else 100.0,
                        0.0,
                        0.0,
                        0.0,
                        0.0,
                        1 if telemetry.is_up else 0,
                        1,
                        status_msg,
                    ),
                )
                conn.commit()
        except Exception as e:
            logger.error(f"Error insertando en site_check_histories: {e}")
        finally:
            conn.close()

    def record_device_history(self, telemetry: DeviceTelemetryDTO) -> None:
        """Inserta un registro en network_device_check_histories."""
        conn = self._pool.get_connection()
        try:
            with conn.cursor() as cur:
                status_msg = "Dispositivo Operativo / Enlace Activo" if telemetry.is_up else "Dispositivo Caído / Inalcanzable"
                cur.execute(
                    """
                    INSERT INTO network_device_check_histories
                    (monitored_network_device_id, is_up, latency_ms, status_message, checked_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW(), NOW())
                    """,
                    (
                        telemetry.device_id,
                        1 if telemetry.is_up else 0,
                        float(telemetry.latency_ms or 0.0),
                        status_msg,
                    ),
                )
                conn.commit()
        except Exception as e:
            logger.error(f"Error insertando en network_device_check_histories: {e}")
        finally:
            conn.close()
