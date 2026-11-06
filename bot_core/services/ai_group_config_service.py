"""
==============================================================================
⚙️ SERVICIO DE CONFIGURACIÓN DE IA EN GRUPO: ai_group_config_service.py
Principio de Responsabilidad Única (SRP) y Fuente Única de Verdad (SSOT).
Gestiona la persistencia y lectura de bot_settings para el asistente en grupos.
==============================================================================
"""

from __future__ import annotations

import logging
import time
from typing import Optional

from bot_core.contracts.ai_contracts import AiGroupConfigProtocol

logger = logging.getLogger("bot.services.ai_group_config")


class MariaDbAiGroupConfigService(AiGroupConfigProtocol):
    """Gestor de persistencia del switch de IA en Grupo Corporativo en MariaDB SSOT."""

    def is_group_ai_enabled(self) -> bool:
        """Consulta en MariaDB SSOT si la Línea 2 de IA está habilitada para el grupo corporativo."""
        try:
            from monitor.infrastructure.monitor_db import get_db_connection
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        "SELECT setting_value FROM bot_settings WHERE setting_key = 'telegram_group_ai_enabled' LIMIT 1"
                    )
                    row = cur.fetchone()
                    if row:
                        val = str(row.get("setting_value") or "").strip().lower()
                        return val in ("1", "true", "yes", "on")
            finally:
                conn.close()
        except Exception as exc:
            logger.error("Error consultando telegram_group_ai_enabled en MariaDB: %s", exc)
        return False

    def set_group_ai_enabled(self, enabled: bool, updated_by: str = "Owner [Telegram]") -> bool:
        """Actualiza y persiste el estado de activación de la IA en Grupo en MariaDB SSOT."""
        try:
            from monitor.infrastructure.monitor_db import get_db_connection
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    now_str = time.strftime("%Y-%m-%d %H:%M:%S")
                    val = "1" if enabled else "0"
                    cur.execute(
                        """
                        INSERT INTO bot_settings (setting_key, setting_value, setting_group, title, description, type, created_at, updated_at)
                        VALUES ('telegram_group_ai_enabled', %s, 'ai', 'Asistente IA en Grupo Corporativo de Telegram', 
                                'Habilita o deshabilita la atención del asistente de IA en el grupo corporativo.', 'boolean', %s, %s)
                        ON DUPLICATE KEY UPDATE setting_value = %s, updated_at = %s
                        """,
                        (val, now_str, now_str, val, now_str)
                    )
                    cur.execute(
                        """
                        INSERT INTO bot_settings (setting_key, setting_value, setting_group, title, description, type, created_at, updated_at)
                        VALUES ('telegram_group_ai_updated_at', %s, 'ai', 'Fecha de Actualización de IA en Grupo', 'text', 'text', %s, %s)
                        ON DUPLICATE KEY UPDATE setting_value = %s, updated_at = %s
                        """,
                        (now_str, now_str, now_str, now_str, now_str)
                    )
                    cur.execute(
                        """
                        INSERT INTO bot_settings (setting_key, setting_value, setting_group, title, description, type, created_at, updated_at)
                        VALUES ('telegram_group_ai_updated_by', %s, 'ai', 'Usuario que Actualizó IA en Grupo', 'text', 'text', %s, %s)
                        ON DUPLICATE KEY UPDATE setting_value = %s, updated_at = %s
                        """,
                        (updated_by, now_str, now_str, updated_by, now_str)
                    )
                    conn.commit()
                    return True
            finally:
                conn.close()
        except Exception as exc:
            logger.error("Error actualizando telegram_group_ai_enabled en MariaDB: %s", exc)
            return False
