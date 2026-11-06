"""
# ==============================================================================
# 🔋 UPS PERSISTENCE ACTION: persistence_action.py
# Escritura transaccional de telemetría, eventos e histórico de UPS en MariaDB
# Ubicación: /opt/sonar/monitor/ups/daemon/persistence_action.py
# ==============================================================================
"""

from __future__ import annotations

import logging
from datetime import datetime
from typing import Any, Dict, Optional

logger = logging.getLogger("ups.service")


def save_ups_telemetry(
    conn,
    device_id: int,
    telemetry: Dict[str, Any],
    current_state: str,
    group_alert_sent: bool,
    outage_since: Optional[datetime],
    now: datetime,
) -> None:
    """Actualiza la tabla ups_devices e inserta un registro en ups_telemetry_histories."""
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE ups_devices
            SET input_voltage = %s,
                input_fault_voltage = %s,
                output_voltage = %s,
                load_percent = %s,
                frequency = %s,
                battery_voltage = %s,
                battery_percent = %s,
                temperature_c = %s,
                is_online = 1,
                is_on_battery = %s,
                is_battery_low = %s,
                is_bypass = %s,
                is_ups_failed = %s,
                beeper_on = %s,
                last_alert_state = %s,
                group_alert_sent = %s,
                outage_since = %s,
                last_seen_at = %s,
                updated_at = NOW()
            WHERE id = %s
            """,
            (
                telemetry["input_voltage"],
                telemetry["input_fault_voltage"],
                telemetry["output_voltage"],
                telemetry["load_percent"],
                telemetry["frequency"],
                telemetry["battery_voltage"],
                telemetry["battery_percent"],
                telemetry["temperature_c"],
                1 if telemetry["is_on_battery"] else 0,
                1 if telemetry["is_battery_low"] else 0,
                1 if telemetry["is_bypass"] else 0,
                1 if telemetry["is_ups_failed"] else 0,
                1 if telemetry["beeper_on"] else 0,
                current_state,
                1 if group_alert_sent else 0,
                outage_since,
                now,
                device_id,
            ),
        )

        cur.execute(
            """
            INSERT INTO ups_telemetry_histories
            (ups_device_id, input_voltage, output_voltage, load_percent,
             battery_percent, battery_voltage, temperature_c,
             is_on_battery, is_battery_low, is_bypass, recorded_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """,
            (
                device_id,
                telemetry["input_voltage"],
                telemetry["output_voltage"],
                telemetry["load_percent"],
                telemetry["battery_percent"],
                telemetry["battery_voltage"],
                telemetry["temperature_c"],
                1 if telemetry["is_on_battery"] else 0,
                1 if telemetry["is_battery_low"] else 0,
                1 if telemetry["is_bypass"] else 0,
                now,
            ),
        )

        # Purgar histórico antiguo para evitar degradación de rendimiento
        cur.execute("DELETE FROM ups_telemetry_histories WHERE recorded_at < NOW() - INTERVAL 30 DAY")
    conn.commit()


def set_outage_flag(conn, active: bool) -> None:
    """Actualiza banderín de corte de energía en MariaDB bot_settings (SSOT)."""
    val = "1" if active else "0"
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO bot_settings (setting_key, setting_value, updated_at)
                VALUES ('outage_alert_active', %s, NOW())
                ON DUPLICATE KEY UPDATE setting_value = %s, updated_at = NOW()
                """,
                (val, val),
            )
        conn.commit()
    except Exception as e:
        logger.warning(f"Error actualizando outage_alert_active en bot_settings: {e}")
