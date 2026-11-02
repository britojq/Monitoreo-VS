"""
# ==============================================================================
# 🌉 IMPLEMENTACIÓN CANÓNICA DEL PUENTE LARAVEL-MARIADB: laravel_mariadb_bridge.py
# Arquitectura SOLID - Principio de Inversión de Dependencias (DIP).
# Implementa el contrato LaravelBridge utilizando MariaDB como Única Fuente de Verdad (SSOT).
# Ubicación: /opt/sonar/monitor/infrastructure/bridges/laravel_mariadb_bridge.py
# License: GNU Affero General Public License v3.0
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import json
import logging
import re
from typing import Any, Dict, Optional

from monitor.contracts.laravel_bridge import LaravelBridge
from monitor.domain.exceptions import DatabaseQueryException, DatabaseTransactionException
from monitor.infrastructure.db_context import atomic_transaction, execute_query
from monitor.infrastructure.laravel_encryptor import decrypt_laravel_string

logger = logging.getLogger("monitor.infrastructure.bridges.laravel_mariadb")


class LaravelMariaDbBridge(LaravelBridge):
    """
    Puente de interoperabilidad desacoplado entre el motor en segundo plano de Python
    y los modelos/configuraciones de Laravel almacenados en MariaDB (SSOT).
    """

    def read_config(self, key: str, default: Optional[str] = None) -> Optional[str]:
        """
        Lee una variable operativa desde la tabla bot_settings en MariaDB SSOT.

        Args:
            key: Nombre de la clave a consultar.
            default: Valor por defecto si no existe.

        Returns:
            Optional[str]: Valor de la configuración o default.
        """
        if not key or not isinstance(key, str):
            return default

        try:
            rows = execute_query(
                "SELECT value FROM bot_settings WHERE `key` = %s LIMIT 1",
                (key.strip(),),
            )
            if not rows:
                return default

            raw_val = rows[0].get("value")
            if raw_val is None:
                return default

            # Si es un string JSON o valor primitivo, retornar como texto
            if isinstance(raw_val, (dict, list)):
                return json.dumps(raw_val)
            return str(raw_val)
        except Exception as e:
            logger.warning(f"Error consultando clave '{key}' en bot_settings: {e}")
            return default

    def decrypt_payload(self, encrypted_payload: str) -> str:
        """
        Descifra un string protegido con Illuminate\\Support\\Facades\\Crypt de Laravel.

        Args:
            encrypted_payload: String codificado en base64 con el payload cifrado.

        Returns:
            str: Contenido descifrado o cadena vacía si falla la desencriptación.
        """
        return decrypt_laravel_string(encrypted_payload)

    def write_telemetry(self, table: str, data: Dict[str, Any]) -> bool:
        """
        Inserta un registro de telemetría de forma atómica en MariaDB SSOT.

        Args:
            table: Nombre de la tabla destino.
            data: Columnas y valores del registro.

        Returns:
            bool: True si la escritura se confirmó con éxito.

        Raises:
            DatabaseTransactionException: Si los nombres de campos son inválidos
                o la inserción atómica falla.
        """
        if not table or not re.match(r"^[a-zA-Z0-9_]+$", table):
            raise DatabaseTransactionException(f"Nombre de tabla inválido para telemetría: {table}")

        if not data:
            return True

        for col in data.keys():
            if not re.match(r"^[a-zA-Z0-9_]+$", col):
                raise DatabaseTransactionException(f"Nombre de columna inválido para telemetría: {col}")

        cols = [f"`{col}`" for col in data.keys()]
        placeholders = ["%s"] * len(data)
        sql = f"INSERT INTO `{table}` ({', '.join(cols)}) VALUES ({', '.join(placeholders)})"
        params = list(data.values())

        try:
            with atomic_transaction() as conn:
                with conn.cursor() as cur:
                    cur.execute(sql, params)
            logger.debug(f"Telemetría registrada exitosamente en {table}")
            return True
        except Exception as err:
            logger.error(f"Fallo al registrar telemetría en {table}: {err}")
            raise DatabaseTransactionException(f"Error de telemetría en {table}: {err}") from err
