"""
# ==============================================================================
# 🔔 NOTIFICADOR SELECTIVO DE SERVICIOS Y DISPOSITIVOS (@IA_ValleSeco_bot)
# Despacho reactivo de caídas y recuperaciones por Telegram (Owner / Grupo)
# Ubicación: /scripts/telegram-admin-bot/monitor/device_service_notifier.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import os
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.monitor_db import get_db_connection
from monitor.telegram_dispatcher import TelegramDispatcher

logger = logging.getLogger("device.service.notifier")


def get_notification_target(target_type: str) -> str:
    """
    Resuelve el ID de Telegram de destino respetando la REGLA DE ORO #1.
    Si target_type es 'group', en modo esclavo / desarrollo se redirige al Owner.
    """
    config_file = BASE_DIR / "config" / "config.json"
    owner_id = "38914901"
    group_id = "-1001383163558"
    node_role = "master"

    if config_file.exists():
        try:
            cfg = json.loads(config_file.read_text(encoding="utf-8"))
            owner_id = str(cfg.get("owner_id", "38914901"))
            groups = cfg.get("allowed_group_ids", [])
            if groups:
                group_id = str(groups[0])
            node_role = str(cfg.get("node_role", "master")).lower()
        except Exception:
            pass

    if target_type == "group":
        # REGLA DE ORO #1: En modo esclavo/desarrollo o testing, JAMÁS despachar al grupo corporativo
        if node_role == "slave" or os.environ.get("TESTING") == "1" or os.environ.get("APP_ENV") == "local":
            logger.warning(
                f"🚨 REGLA DE ORO #1: Despacho a grupo redirigido a Owner ({owner_id}) "
                f"debido al entorno no productivo (rol: {node_role})."
            )
            return owner_id
        return group_id

    return owner_id


def format_duration(seconds: float | int) -> str:
    """Convierte segundos a formato legible en español."""
    total_sec = max(0, int(seconds))
    if total_sec < 60:
        return f"{total_sec} seg"
    minutes = total_sec // 60
    rem_sec = total_sec % 60
    if minutes < 60:
        return f"{minutes} min {rem_sec} seg" if rem_sec > 0 else f"{minutes} min"
    hours = minutes // 60
    rem_min = minutes % 60
    return f"{hours} h {rem_min} min"


def build_service_alert_message(s: dict, is_down: bool, duration_str: Optional[str] = None) -> str:
    """Construye el mensaje corporativo para eventos de servicios web/red."""
    name = html.escape(str(s.get("name") or "Servicio No Identificado"))
    target = html.escape(str(s.get("web_url") or s.get("host_ip") or "N/A"))
    stype = html.escape(str(s.get("type") or "WEB"))
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    if is_down:
        http_code = s.get("http_code") or s.get("status") or "Timeout"
        return (
            "🔴 <b>ALERTA: SERVICIO CAÍDO / SIN RESPUESTA</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Servicio:</b> <code>{name}</code>\n"
            f"🌐 <b>Destino / URL:</b> <code>{target}</code>\n"
            f"⚙️ <b>Protocolo:</b> {stype}\n"
            f"⚠️ <b>Diagnóstico:</b> {html.escape(str(http_code))}\n"
            f"🕒 <b>Detectado:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
    else:
        dur = duration_str or "Desconocido"
        latency = s.get("latency_ms", 0.0)
        lat_txt = f"{float(latency):.1f} ms" if latency else "< 10 ms"
        return (
            "✅ <b>RECUPERACIÓN: SERVICIO OPERATIVO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Servicio:</b> <code>{name}</code>\n"
            f"🌐 <b>Destino / URL:</b> <code>{target}</code>\n"
            f"⚙️ <b>Protocolo:</b> {stype}\n"
            f"📊 <b>Respuesta:</b> Operacional (Latencia: {lat_txt})\n"
            f"⏱️ <b>Tiempo fuera:</b> {dur}\n"
            f"🕒 <b>Restablecido:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )


def build_device_alert_message(d: dict, is_down: bool, duration_str: Optional[str] = None) -> str:
    """Construye el mensaje corporativo para eventos de dispositivos de red."""
    name = html.escape(str(d.get("name") or "Dispositivo"))
    ip = html.escape(str(d.get("ip") or "0.0.0.0"))
    acc_type = html.escape(str(d.get("access_type") or "ICMP"))
    acc_port = d.get("access_port") or ""
    acc_txt = f"{acc_type} (Puerto {acc_port})" if acc_port else acc_type
    site = html.escape(str(d.get("site_name") or "Infraestructura de Red"))
    now_str = datetime.now().strftime("%d/%m/%Y %I:%M:%S %p")

    if is_down:
        return (
            "🔴 <b>ALERTA: EQUIPO DESCONECTADO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Dispositivo:</b> <code>{name}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ip}</code>\n"
            f"🏢 <b>Sede / Ubicación:</b> {site}\n"
            f"⚙️ <b>Acceso:</b> {acc_txt}\n"
            "⚠️ <b>Estado:</b> Sin respuesta ICMP / Inalcanzable\n"
            f"🕒 <b>Detectado:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
    else:
        dur = duration_str or "Desconocido"
        latency = d.get("latency_ms", 0.0)
        lat_txt = f"{float(latency):.1f} ms" if latency else "< 1 ms"
        return (
            "✅ <b>RECUPERACIÓN: EQUIPO RESTABLECIDO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Dispositivo:</b> <code>{name}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ip}</code>\n"
            f"🏢 <b>Sede / Ubicación:</b> {site}\n"
            f"📊 <b>Respuesta:</b> Conexión Restablecida (Latencia: {lat_txt})\n"
            f"⏱️ <b>Tiempo fuera de línea:</b> {dur}\n"
            f"🕒 <b>Restablecido:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )


async def process_service_notifications(
    services_results: List[dict],
    dispatcher: Optional[TelegramDispatcher] = None
) -> int:
    """
    Evalúa transiciones UP/DOWN en servicios con notificaciones activas.
    Retorna el número de notificaciones despachadas.
    """
    if not services_results:
        return 0

    conn = get_db_connection()
    dispatched_count = 0
    now = datetime.now()

    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT id, name, type, web_url, host_ip,
                       telegram_alert_enabled, telegram_alert_target,
                       last_alert_state, down_since
                FROM monitored_services
                WHERE is_active = 1 AND telegram_alert_enabled = 1
                """
            )
            tracked_services = {row["id"]: row for row in cur.fetchall()}

        if not tracked_services:
            return 0

        if dispatcher is None:
            dispatcher = TelegramDispatcher()

        updates = []
        for s_res in services_results:
            sid = s_res.get("id")
            if not sid or sid not in tracked_services:
                continue

            rec = tracked_services[sid]
            is_up = bool(s_res.get("is_up"))
            last_state = (rec.get("last_alert_state") or "").upper()
            target_chat = get_notification_target(rec.get("telegram_alert_target") or "owner")

            # Caso 1: Caída de servicio (UP o inicial -> DOWN)
            if not is_up and last_state != "DOWN":
                msg = build_service_alert_message({**rec, **s_res}, is_down=True)
                ok, err = await dispatcher.send_text(target_chat, msg, parse_mode="HTML")
                if ok:
                    dispatched_count += 1
                    logger.info(f"📤 Alerta de servicio caído [{rec['name']}] enviada a {target_chat}")
                else:
                    logger.warning(f"❌ Falló envío de alerta de servicio caído [{rec['name']}]: {err}")
                updates.append((sid, "DOWN", now))

            # Caso 2: Recuperación de servicio (DOWN -> UP)
            elif is_up and last_state == "DOWN":
                down_since = rec.get("down_since")
                dur_str = "N/A"
                if down_since:
                    if isinstance(down_since, datetime):
                        dur_sec = (now - down_since).total_seconds()
                    else:
                        try:
                            ds_dt = datetime.strptime(str(down_since)[:19], "%Y-%m-%d %H:%M:%S")
                            dur_sec = (now - ds_dt).total_seconds()
                        except Exception:
                            dur_sec = 0
                    dur_str = format_duration(dur_sec)

                msg = build_service_alert_message({**rec, **s_res}, is_down=False, duration_str=dur_str)
                ok, err = await dispatcher.send_text(target_chat, msg, parse_mode="HTML")
                if ok:
                    dispatched_count += 1
                    logger.info(f"📤 Notificación de recuperación de servicio [{rec['name']}] enviada a {target_chat}")
                else:
                    logger.warning(f"❌ Falló envío de recuperación de servicio [{rec['name']}]: {err}")
                updates.append((sid, "UP", None))

            # Caso 3: Inicialización de estado cuando estaba NULL o vacío
            elif is_up and not last_state:
                updates.append((sid, "UP", None))

        # Actualizar base de datos con los nuevos estados
        if updates:
            with conn.cursor() as cur:
                for sid, state, d_since in updates:
                    cur.execute(
                        """
                        UPDATE monitored_services
                        SET last_alert_state = %s, down_since = %s
                        WHERE id = %s
                        """,
                        (state, d_since, sid)
                    )
            conn.commit()

    finally:
        conn.close()

    return dispatched_count


async def process_device_notifications(
    devices_results: List[dict],
    dispatcher: Optional[TelegramDispatcher] = None
) -> int:
    """
    Evalúa transiciones UP/DOWN en dispositivos de red con notificaciones activas.
    Retorna el número de notificaciones despachadas.
    """
    if not devices_results:
        return 0

    conn = get_db_connection()
    dispatched_count = 0
    now = datetime.now()

    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT d.id, d.name, d.ip, d.access_type, d.access_port,
                       d.telegram_alert_enabled, d.telegram_alert_target,
                       d.last_alert_state, d.down_since, s.name as site_name
                FROM monitored_network_devices d
                LEFT JOIN monitored_sites s ON d.monitored_site_id = s.id
                WHERE d.is_active = 1 AND d.telegram_alert_enabled = 1
                """
            )
            tracked_devices = {row["id"]: row for row in cur.fetchall()}

        if not tracked_devices:
            return 0

        if dispatcher is None:
            dispatcher = TelegramDispatcher()

        updates = []
        for d_res in devices_results:
            did = d_res.get("id")
            if not did or did not in tracked_devices:
                continue

            rec = tracked_devices[did]
            is_up = bool(d_res.get("is_up"))
            last_state = (rec.get("last_alert_state") or "").upper()
            target_chat = get_notification_target(rec.get("telegram_alert_target") or "owner")

            # Caso 1: Caída de dispositivo (UP o inicial -> DOWN)
            if not is_up and last_state != "DOWN":
                msg = build_device_alert_message({**rec, **d_res}, is_down=True)
                ok, err = await dispatcher.send_text(target_chat, msg, parse_mode="HTML")
                if ok:
                    dispatched_count += 1
                    logger.info(f"📤 Alerta de equipo caído [{rec['name']}] enviada a {target_chat}")
                else:
                    logger.warning(f"❌ Falló envío de alerta de equipo caído [{rec['name']}]: {err}")
                updates.append((did, "DOWN", now))

            # Caso 2: Recuperación de dispositivo (DOWN -> UP)
            elif is_up and last_state == "DOWN":
                down_since = rec.get("down_since")
                dur_str = "N/A"
                if down_since:
                    if isinstance(down_since, datetime):
                        dur_sec = (now - down_since).total_seconds()
                    else:
                        try:
                            ds_dt = datetime.strptime(str(down_since)[:19], "%Y-%m-%d %H:%M:%S")
                            dur_sec = (now - ds_dt).total_seconds()
                        except Exception:
                            dur_sec = 0
                    dur_str = format_duration(dur_sec)

                msg = build_device_alert_message({**rec, **d_res}, is_down=False, duration_str=dur_str)
                ok, err = await dispatcher.send_text(target_chat, msg, parse_mode="HTML")
                if ok:
                    dispatched_count += 1
                    logger.info(f"📤 Notificación de recuperación de equipo [{rec['name']}] enviada a {target_chat}")
                else:
                    logger.warning(f"❌ Falló envío de recuperación de equipo [{rec['name']}]: {err}")
                updates.append((did, "UP", None))

            # Caso 3: Inicialización de estado cuando estaba NULL o vacío
            elif is_up and not last_state:
                updates.append((did, "UP", None))

        # Actualizar base de datos con los nuevos estados
        if updates:
            with conn.cursor() as cur:
                for did, state, d_since in updates:
                    cur.execute(
                        """
                        UPDATE monitored_network_devices
                        SET last_alert_state = %s, down_since = %s
                        WHERE id = %s
                        """,
                        (state, d_since, did)
                    )
                    # Sincronizar en monitored_site_devices si existe
                    cur.execute(
                        """
                        UPDATE monitored_site_devices
                        SET last_alert_state = %s, down_since = %s
                        WHERE ip = (SELECT ip FROM monitored_network_devices WHERE id = %s)
                        """,
                        (state, d_since, did)
                    )
            conn.commit()

    finally:
        conn.close()

    return dispatched_count


async def evaluate_and_notify_all(
    services_results: List[dict],
    devices_results: List[dict],
    dispatcher: Optional[TelegramDispatcher] = None
) -> Tuple[int, int]:
    """Evalúa concurrentemente servicios y dispositivos y despacha alertas correspondientes."""
    s_count, d_count = await asyncio.gather(
        process_service_notifications(services_results, dispatcher=dispatcher),
        process_device_notifications(devices_results, dispatcher=dispatcher)
    )
    return s_count, d_count
