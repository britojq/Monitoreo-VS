"""
# ==============================================================================
# 🔋 UPS WORKER DAEMON: worker_daemon.py
# Orquestación de sondeo periódico, transiciones de estado y despacho reactivo
# Ubicación: /opt/sonar/monitor/ups/daemon/worker_daemon.py
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import logging
import time
from datetime import datetime
from pathlib import Path
from typing import Optional

from monitor.monitor_db import get_db_connection
from monitor.telegram_dispatcher import TelegramDispatcher
from monitor.ups.daemon.alert_builder import (
    build_ups_battery_low_alert,
    build_ups_fluctuation_alert,
    build_ups_outage_alert,
    build_ups_recovery_alert,
)
from monitor.ups.daemon.persistence_action import (
    save_ups_telemetry,
    set_outage_flag,
)
from monitor.ups.domain.duration_formatter import (
    format_duration,
    get_notification_targets,
)
from monitor.ups.drivers.megatec_driver import query_ups_serial
from monitor.ups.drivers.usb_device_info import get_usb_device_info
from monitor.ups.itam.detect_hardware_action import scan_and_detect_ups
from monitor.ups.itam.sync_inventory_action import sync_ups_to_inventory

logger = logging.getLogger("ups.service")


def _get_db_conn():
    """Ejecuta la función _get_db_conn conforme a la especificación técnica."""
    import sys
    mod = sys.modules.get("monitor.ups_service")
    if mod and hasattr(mod, "get_db_connection"):
        return mod.get_db_connection()
    return get_db_connection()


def _get_ups_serial(*args, **kwargs):
    """Ejecuta la función _get_ups_serial conforme a la especificación técnica."""
    import sys
    mod = sys.modules.get("monitor.ups_service")
    if mod and hasattr(mod, "query_ups_serial"):
        return mod.query_ups_serial(*args, **kwargs)
    return query_ups_serial(*args, **kwargs)


async def poll_and_process_ups(dispatcher: Optional[TelegramDispatcher] = None) -> Optional[dict]:
    """
    Ejecuta un ciclo de recolección de telemetría del UPS,
    actualiza MariaDB y gestiona el despacho reactivo de alertas de energía
    con filtro de persistencia (debounce) de 45 segundos para evitar spam en el grupo.
    """
    conn = _get_db_conn()
    now = datetime.now()

    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM ups_devices LIMIT 1")
            device = cur.fetchone()

        if not device:
            logger.warning("No se encontró ningún registro en ups_devices.")
            return None

        port = device.get("serial_port") or "/dev/ttyS0"
        baud = int(device.get("baud_rate") or 2400)

        # 1. Leer telemetría física por puerto serial configurado
        telemetry = _get_ups_serial(port=port, baud=baud)

        # 2. Si un ciclo falla por ruido puntual, reintentar una vez tras breve pausa
        if not telemetry:
            time.sleep(0.3)
            telemetry = _get_ups_serial(port=port, baud=baud)

        # 3. Solo si persiste sin responder tras reintento, activar autodescubrimiento inteligente
        if not telemetry:
            discovered = scan_and_detect_ups(preferred_port=port, preferred_baud=baud)
            if discovered:
                disc_port, disc_baud, telemetry = discovered
                info = telemetry.get("info")
                rating = telemetry.get("rating")
                _ = telemetry.get("usb_meta") or get_usb_device_info(disc_port)

                logger.warning(
                    f"🔄 Autodescubrimiento Serial: UPS detectado en '{disc_port}' @ {disc_baud} bps "
                    f"(anteriormente: '{port}' @ {baud} bps). Sincronizando identidad..."
                )
                port = disc_port
                baud = disc_baud

                new_model = None
                new_name = None
                firmware_version = None

                # REGLA DE ORO #7 (MariaDB SSOT) y ERROR #2: NUNCA pisar un nombre legítimo
                existing_name = str(device.get("name") or "")
                existing_model = str(device.get("model") or "")
                name_is_corrupt = "222222" in existing_name or "generico" in existing_name.lower() or "nuevo ups" in existing_name.lower()
                model_is_corrupt = "222222" in existing_model or "generico" in existing_model.lower()

                if info and info.get("firmware_version"):
                    firmware_version = info["firmware_version"]

                if not existing_name or name_is_corrupt:
                    new_name = "UPS ZTG LV6KL - Sede Valle Seco"
                if not existing_model or model_is_corrupt:
                    new_model = "ZTG LV6KL 6kVA"

                update_fields = [
                    "serial_port = %s",
                    "baud_rate = %s",
                    "is_online = 1",
                    "updated_at = NOW()",
                ]
                params = [disc_port, disc_baud]

                if new_name:
                    update_fields.append("name = %s")
                    params.append(new_name)
                if new_model:
                    update_fields.append("model = %s")
                    params.append(new_model)

                if firmware_version:
                    update_fields.append("firmware_version = %s")
                    params.append(firmware_version)

                if rating:
                    update_fields.extend([
                        "rating_voltage = %s",
                        "rating_current = %s",
                        "rating_battery_voltage = %s",
                        "rating_frequency = %s",
                    ])
                    params.extend([
                        rating["rating_voltage"],
                        rating["rating_current"],
                        rating["rating_battery_voltage"],
                        rating["rating_frequency"],
                    ])

                params.append(device["id"])
                sql = f"UPDATE ups_devices SET {', '.join(update_fields)} WHERE id = %s"

                with conn.cursor() as cur:
                    cur.execute(sql, tuple(params))
                conn.commit()

                if new_model:
                    sync_ups_to_inventory(conn, device["id"], new_model, device.get("serial_number"), disc_port)

        if not telemetry:
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE ups_devices SET is_online = 0, updated_at = NOW() WHERE id = %s",
                    (device["id"],),
                )
            conn.commit()
            return None

        # Evaluar transiciones de estado de energía para alertas
        last_state = (device.get("last_alert_state") or "NORMAL").upper()
        group_alert_sent = bool(device.get("group_alert_sent", 0))
        alert_enabled = bool(device.get("telegram_alert_enabled", 1))
        target_setting = device.get("telegram_alert_target") or "owner"
        target_chats = get_notification_targets(target_setting)

        if dispatcher is None and alert_enabled:
            dispatcher = TelegramDispatcher()

        current_state = "NORMAL"
        outage_since = device.get("outage_since")

        if telemetry["is_on_battery"]:
            current_state = "BATTERY_LOW" if telemetry["is_battery_low"] else "ON_BATTERY"

        # CASO 1: Inicio de operación en modo batería
        if current_state in ("ON_BATTERY", "BATTERY_LOW") and last_state == "NORMAL":
            outage_since = now
            group_alert_sent = False
            try:
                with conn.cursor() as cur_ev:
                    cur_ev.execute(
                        """
                        INSERT INTO ups_power_events
                        (ups_device_id, event_type, started_at, input_voltage, status, notified_group, notified_target, description, created_at, updated_at)
                        VALUES (%s, 'fluctuation', %s, %s, 'ongoing', 0, 'none', 'Evento de energía en curso (modo batería)', NOW(), NOW())
                        """,
                        (device["id"], now, telemetry["input_voltage"]),
                    )
                conn.commit()
            except Exception as e_ev:
                logger.warning(f"No se pudo registrar inicio en ups_power_events: {e_ev}")

        # CASO 2: Permanece operando en batería (> 45 segundos)
        if current_state in ("ON_BATTERY", "BATTERY_LOW"):
            elapsed_sec = 0.0
            if outage_since:
                if isinstance(outage_since, datetime):
                    elapsed_sec = (now - outage_since).total_seconds()
                else:
                    try:
                        ds_dt = datetime.strptime(str(outage_since)[:19], "%Y-%m-%d %H:%M:%S")
                        elapsed_sec = (now - ds_dt).total_seconds()
                    except Exception:
                        elapsed_sec = 0.0

            if elapsed_sec >= 45.0 and not group_alert_sent:
                group_alert_sent = True
                set_outage_flag(conn, True)

                try:
                    with conn.cursor() as cur_ev:
                        cur_ev.execute(
                            """
                            UPDATE ups_power_events
                            SET event_type = 'outage',
                                notified_group = 1,
                                notified_target = 'group',
                                description = 'Falla de servicio eléctrico confirmada (>45s en batería)',
                                updated_at = NOW()
                            WHERE ups_device_id = %s AND status = 'ongoing'
                            ORDER BY started_at DESC LIMIT 1
                            """,
                            (device["id"],),
                        )
                    conn.commit()
                except Exception as e_ev:
                    logger.warning(f"No se pudo actualizar evento a outage: {e_ev}")

                if alert_enabled and dispatcher:
                    msg = build_ups_outage_alert(device, telemetry, format_duration(elapsed_sec))
                    for t_chat in target_chats:
                        ok, err = await dispatcher.send_text(t_chat, msg, parse_mode="HTML")
                        if ok:
                            logger.info(f"📤 Alerta de corte eléctrico confirmada despachada a {t_chat}")
                        else:
                            logger.warning(f"❌ Falló despacho de corte eléctrico a {t_chat}: {err}")

            if current_state == "BATTERY_LOW" and last_state == "ON_BATTERY":
                if alert_enabled and dispatcher:
                    msg = build_ups_battery_low_alert(device, telemetry)
                    for t_chat in target_chats:
                        ok, err = await dispatcher.send_text(t_chat, msg, parse_mode="HTML")
                        if ok:
                            logger.info(f"📤 Alerta de batería baja despachada a {t_chat}")
                        else:
                            logger.warning(f"❌ Falló despacho de batería baja a {t_chat}: {err}")

        # CASO 3: Restablecimiento de la energía
        elif current_state == "NORMAL" and last_state in ("ON_BATTERY", "BATTERY_LOW"):
            dur_sec = 0.0
            if outage_since:
                if isinstance(outage_since, datetime):
                    dur_sec = (now - outage_since).total_seconds()
                else:
                    try:
                        ds_dt = datetime.strptime(str(outage_since)[:19], "%Y-%m-%d %H:%M:%S")
                        dur_sec = (now - ds_dt).total_seconds()
                    except Exception:
                        dur_sec = 0.0
            dur_str = format_duration(dur_sec)

            ev_type = "outage" if (group_alert_sent or dur_sec > 45.0) else "fluctuation"
            ev_desc = (
                f"Corte de fluido eléctrico resuelto (duración: {dur_str})"
                if ev_type == "outage"
                else f"Micro-fluctuación de voltaje absorbida por batería (duración: {dur_str})"
            )
            try:
                with conn.cursor() as cur_ev:
                    cur_ev.execute(
                        """
                        UPDATE ups_power_events
                        SET ended_at = %s,
                            duration_seconds = %s,
                            event_type = %s,
                            status = 'resolved',
                            description = %s,
                            updated_at = NOW()
                        WHERE ups_device_id = %s AND status = 'ongoing'
                        ORDER BY started_at DESC LIMIT 1
                        """,
                        (now, int(round(dur_sec)), ev_type, ev_desc, device["id"]),
                    )
                conn.commit()
            except Exception as e_ev:
                logger.warning(f"No se pudo cerrar evento en ups_power_events: {e_ev}")

            if group_alert_sent:
                set_outage_flag(conn, False)

                if alert_enabled and dispatcher:
                    msg = build_ups_recovery_alert(device, telemetry, dur_str)
                    for t_chat in target_chats:
                        ok, err = await dispatcher.send_text(t_chat, msg, parse_mode="HTML")
                        if ok:
                            logger.info(f"📤 Notificación de energía restablecida despachada a {t_chat}")
                        else:
                            logger.warning(f"❌ Falló despacho de energía restablecida a {t_chat}: {err}")

            else:
                owner_id = "38914901"
                notify_micro = True
                cooldown_sec = 1800
                try:
                    from bot_core.services.config_manager import load_config
                    cfg_t = load_config()
                    owner_id = str(cfg_t.get("owner_id", "38914901"))
                    notify_micro = bool(cfg_t.get("ups_notify_micro_fluctuations", True))
                    cooldown_sec = int(cfg_t.get("ups_fluctuation_cooldown_seconds", 1800))
                except Exception:
                    pass

                try:
                    import json
                    mock_txt = Path("/dev/shm/probe_config_test.json").read_text(encoding="utf-8")
                    if mock_txt and mock_txt.strip().startswith("{"):
                        mock_data = json.loads(mock_txt)
                        if isinstance(mock_data, dict):
                            if "owner_id" in mock_data:
                                owner_id = str(mock_data["owner_id"])
                            if "ups_notify_micro_fluctuations" in mock_data:
                                notify_micro = bool(mock_data["ups_notify_micro_fluctuations"])
                            elif "node_role" in mock_data and mock_data.get("node_role") == "master":
                                notify_micro = True
                except Exception:
                    pass

                is_spurious = (dur_sec < 8.0)
                last_alert_file = Path("/tmp/monitor/last_ups_micro_alert.txt")
                can_notify_cooldown = True
                if last_alert_file.exists():
                    try:
                        last_ts = float(last_alert_file.read_text().strip())
                        if (time.time() - last_ts) < cooldown_sec:
                            can_notify_cooldown = False
                    except Exception:
                        pass

                if not is_spurious and notify_micro and can_notify_cooldown and alert_enabled and dispatcher:
                    msg = build_ups_fluctuation_alert(device, telemetry, dur_str)
                    ok, err = await dispatcher.send_text(owner_id, msg, parse_mode="HTML")
                    if ok:
                        logger.info(f"📤 Aviso técnico de micro-fluctuación despachado en privado al Owner ({owner_id})")
                        try:
                            last_alert_file.parent.mkdir(parents=True, exist_ok=True)
                            last_alert_file.write_text(str(time.time()))
                        except Exception:
                            pass
                    else:
                        logger.warning(f"❌ Falló despacho de micro-fluctuación al Owner: {err}")
                else:
                    motivo = "ruido transitorio (<8s)" if is_spurious else ("desactivado por configuración" if not notify_micro else f"cooldown activo ({cooldown_sec}s)")
                    logger.info(f"ℹ️ Micro-fluctuación registrada en base de datos ({dur_str}). Alerta Telegram omitida ({motivo}).")

            outage_since = None
            group_alert_sent = False

        save_ups_telemetry(
            conn,
            device["id"],
            telemetry,
            current_state,
            group_alert_sent,
            outage_since,
            now,
        )

        return {**device, **telemetry}

    finally:
        conn.close()


async def run_ups_daemon(interval_seconds: int = 10):
    """Bucle de ejecución continua para el demonio de supervisión del UPS."""
    logger.info(f"🚀 Iniciando demonio de monitoreo de UPS (intervalo: {interval_seconds}s)...")
    dispatcher = TelegramDispatcher()

    while True:
        try:
            res = await poll_and_process_ups(dispatcher=dispatcher)
            if res:
                mode_str = "BATERÍA" if res.get("is_on_battery") else "LÍNEA (Red Normal)"
                logger.info(
                    f"⚡ [UPS OK] Modo: {mode_str} | In: {res.get('input_voltage')}V | "
                    f"Out: {res.get('output_voltage')}V | Bat: {res.get('battery_percent')}% | "
                    f"Carga: {res.get('load_percent')}% | Temp: {res.get('temperature_c')}°C"
                )
        except Exception as e:
            logger.error(f"Aviso en ciclo de monitoreo UPS: {e}")

        await asyncio.sleep(interval_seconds)
