"""
# ==============================================================================
# 🚀 ALERTAS DE ARRANQUE Y PARADA DEL SISTEMA: boot_alert.py (@IA_ValleSeco_bot)
# Notificación inmediata al Owner / Grupo corporativo en arranque o apagado
# Ubicación: /opt/sonar/monitor/boot_alert.py
# ==============================================================================
"""

import asyncio
from datetime import datetime
import html
import logging
import os
from pathlib import Path
import signal
import socket
import subprocess
import sys
from typing import Any, Dict, List, Optional

from monitor.alerts.boot import (
    get_bot_setting,
    get_local_ips,
    load_config,
    send_shutdown_alert_fast,
    send_telegram_alert,
    set_bot_setting,
    wait_for_network_and_telegram,
)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)
logger = logging.getLogger("monitor.boot_alert")

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

__all__ = [
    "get_bot_setting",
    "set_bot_setting",
    "load_config",
    "get_local_ips",
    "send_telegram_alert",
    "wait_for_network_and_telegram",
    "send_shutdown_alert_fast",
    "handle_start",
    "handle_stop",
    "handle_background_start",
    "main",
]


async def handle_start() -> int:
    """Maneja la notificación de arranque del sistema."""
    config = load_config()
    token = config.get("bot_token")
    owner_id = config.get("owner_id")
    proxies = config.get("proxies", [])

    if not token or not owner_id:
        logger.error("Configuración incompleta (bot_token u owner_id faltante).")
        return 0

    # Esperar conectividad activa hasta 60 segundos
    connected = await wait_for_network_and_telegram(token, proxies, max_wait_seconds=60)

    hostname = socket.gethostname()
    now_str = datetime.now().strftime("%d/%m/%Y %H:%M:%S")
    local_ips = get_local_ips()

    # Evaluar banderines de apagado limpio y corte eléctrico en MariaDB bot_settings (SSOT)
    clean_val = get_bot_setting("system_clean_shutdown", default="0")
    is_clean = (clean_val == "1")

    outage_val = get_bot_setting("outage_alert_active", default="0")
    had_group_outage = (outage_val not in ("0", "", "false", "False", None))

    if is_clean:
        estado_titulo = "🟢 <b>Reinicio Limpio o Apagado Controlado</b>"
        detalle_texto = "El equipo se apagó o reinició de manera controlada por el sistema."
        set_bot_setting("system_clean_shutdown", "0")
    else:
        estado_titulo = "🚨 <b>Falla Eléctrica / Apagado Forzado Inesperado</b>"
        detalle_texto = "Se detectó un corte de energía eléctrica, desconexión o apagado abrupto (no se registró una parada limpia previa)."

    mensaje = (
        "🚀 <b>Servidor Iniciado</b>\n\n"
        f"🖥️ <b>Host:</b> <code>{html.escape(hostname)}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
        f"🌐 <b>IPs Locales:</b> <code>{html.escape(local_ips)}</code>\n"
        f"🏷️ <b>Estado:</b> {estado_titulo}\n"
        f"📋 <b>Detalle:</b> {detalle_texto}\n\n"
        "<i>Sistema operando en Debian GNU/Linux • MONITOREO-DEV</i>"
    )

    # Exclusivo para el Owner (Arranque de infraestructura)
    recipients = [owner_id]
    if connected:
        await send_telegram_alert(token, recipients, mensaje, proxies)

        # Si el servidor encendió tras falla eléctrica y se había alertado al grupo (>45s en batería)
        if not is_clean and had_group_outage:
            node_role = str(config.get("node_role", "master")).lower()
            is_non_prod = (
                node_role in ("slave", "dev")
                or os.environ.get("TESTING") == "1"
                or os.environ.get("APP_ENV") == "local"
            )
            groups = config.get("allowed_group_ids", [])
            group_targets = [owner_id]
            if groups and not is_non_prod:
                group_targets.append(str(groups[0]))

            msg_recovery_group = (
                "✅ <b>RECUPERACIÓN DEL SERVICIO ELÉCTRICO</b>\n"
                "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                "📍 <b>Ubicación:</b> <code>Sede Valle Seco (Rack Principal)</code>\n"
                "🖥️ <b>Servidor de Monitoreo:</b> <code>EN LINEA</code> ✅\n"
                f"🕒 <b>Hora de Encendido Servidor:</b> <code>{now_str}</code>\n"
                "⚡ <b>Condición:</b> Servicio eléctrico restablecido al 100% .\n"
                "🔄 <i>El servidor de monitoreo ha iniciado tras la falla eléctrica reportada.</i>\n\n"
                "⏳ <i>Se ejecutara una verificación automática de servicios corporativos en 2 minutos (esperando estabilización de red)...</i>"
            )

            logger.info("📤 Despachando notificación de recuperación plena al grupo corporativo...")
            await send_telegram_alert(token, group_targets, msg_recovery_group, proxies)

            # Esperar estabilización física de Pfsense, switches y enlaces WAN (120 segundos)
            is_testing = os.environ.get("TESTING") == "1"
            stabilization_delay = 0 if is_testing else int(os.environ.get("POST_BOOT_STABILIZATION_SECONDS", "120"))
            if stabilization_delay > 0:
                logger.info(f"⏳ Esperando {stabilization_delay}s para estabilización de red post-recuperación...")
                await asyncio.sleep(stabilization_delay)

            # Ejecutar chequeo concurrente de servicios corporativos y enviar reporte técnico limpio
            try:
                logger.info("🔍 Ejecutando chequeo automático de servicios corporativos post-recuperación...")
                from monitor.monitor_engine import run_services_check

                _, vars_s, _, _ = await run_services_check(debug_mode=False)
                corp_services = (vars_s.get("SERVICIOS_CORPORATIVOS") or "").strip()
                reg_services = (vars_s.get("SERVICIOS_REGIONALES") or "").strip()
                check_time_str = datetime.now().strftime("%d/%m/%Y %H:%M:%S")

                body_parts = [
                    "📋 <b>ESTADO DE SERVICIOS TRAS RESTABLECIMIENTO ELÉCTRICO</b>",
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                    f"🕒 <b>Verificación Ejecutada:</b> <code>{check_time_str}</code>",
                ]
                if corp_services:
                    body_parts.extend([
                        "\n<b>Servicios Corporativos:</b>",
                        corp_services,
                    ])
                if reg_services:
                    body_parts.extend([
                        "\n<b>Servicios Regionales (Carabobo):</b>",
                        reg_services,
                    ])
                body_parts.extend([
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                    "🛡️ <i>Chequeo automático completado tras estabilización de red.</i>",
                ])

                msg_status = "\n".join(body_parts)
                await send_telegram_alert(token, group_targets, msg_status, proxies)
            except Exception as e_srv:
                logger.error(f"Aviso ejecutando chequeo de servicios en arranque: {e_srv}")

            # Limpiar banderín de corte en MariaDB bot_settings y actualizar base de datos
            try:
                set_bot_setting("outage_alert_active", "0")
                from monitor.monitor_db import get_db_connection
                conn = get_db_connection()
                with conn.cursor() as cur:
                    cur.execute(
                        """
                        UPDATE ups_power_events
                        SET ended_at = NOW(),
                            status = 'resolved',
                            description = 'Servicio eléctrico y servidor central restablecidos tras apagón',
                            updated_at = NOW()
                        WHERE status = 'ongoing'
                        """
                    )
                    cur.execute(
                        """
                        UPDATE ups_devices
                        SET last_alert_state = 'NORMAL',
                            group_alert_sent = 0,
                            is_on_battery = 0,
                            outage_since = NULL,
                            updated_at = NOW()
                        """
                    )
                conn.commit()
                conn.close()
            except Exception as e_db:
                logger.warning(f"Aviso cerrando evento de corte en BD post-inicio: {e_db}")

    else:
        logger.warning("Red no disponible aún al inicio tras 60s. Delegando notificación a proceso en segundo plano...")
        if os.environ.get("TESTING") == "1" or os.environ.get("PYTEST_CURRENT_TEST"):
            logger.info("Modo testing activo: Omitiendo lanzamiento de subproceso diferido.")
            return 0
        try:
            cmd = [
                sys.executable,
                str(Path(__file__).resolve()),
                "bg-start",
                mensaje
            ]
            subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
        except Exception as e:
            logger.error(f"Error lanzando envío diferido en segundo plano: {e}")

    # Retornar SIEMPRE 0 para que systemd marque el servicio como active (exited) de forma garantizada
    return 0


async def handle_stop() -> int:
    """Maneja la notificación de parada / apagado limpio del sistema."""
    # 1. Registrar banderín de apagado limpio en MariaDB bot_settings (SSOT)
    if set_bot_setting("system_clean_shutdown", "1"):
        logger.info("Banderín system_clean_shutdown = '1' persistido en MariaDB exitosamente.")
    else:
        logger.error("No se pudo persistir banderín system_clean_shutdown en MariaDB.")

    # 2. Cargar config y enviar alerta ultra-rápida
    config = load_config()
    token = config.get("bot_token")
    owner_id = config.get("owner_id")
    proxies = config.get("proxies", [])

    if token and owner_id:
        hostname = socket.gethostname()
        now_str = datetime.now().strftime("%d/%m/%Y %H:%M:%S")
        mensaje = (
            "🛑 <b>Alerta: Servidor Apagándose / Reiniciando</b>\n\n"
            f"🖥️ <b>Host:</b> <code>{html.escape(hostname)}</code>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
            f"📋 <b>Motivo:</b> Parada limpia / reinicio ordenado por el sistema.\n\n"
            "<i>El sistema está cerrando servicios antes de desconectar la red.</i>"
        )
        try:
            await send_shutdown_alert_fast(token, owner_id, mensaje, proxies)
        except Exception as e:
            logger.warning(f"No se pudo enviar notificación de stop: {e}")

    return 0


async def handle_background_start(mensaje: str) -> int:
    """Reintenta enviar la alerta de arranque en segundo plano sin demorar systemd."""
    config = load_config()
    token = config.get("bot_token")
    owner_id = config.get("owner_id")
    proxies = config.get("proxies", [])

    if not token or not owner_id:
        return 0

    # Reintentar hasta 10 minutos (600 segundos) en segundo plano
    connected = await wait_for_network_and_telegram(token, proxies, max_wait_seconds=600)
    if connected:
        await send_telegram_alert(token, [owner_id], mensaje, proxies)
        logger.info("Alerta de arranque diferida entregada con éxito en segundo plano.")
    else:
        logger.error("No se pudo entregar la alerta de arranque tras 10 minutos de espera en segundo plano.")
    return 0


def main():
    def _graceful_exit(signum, frame):
        logger.info(f"Señal {signum} recibida en boot_alert. Terminando limpiamente...")
        sys.exit(0)

    try:
        signal.signal(signal.SIGTERM, _graceful_exit)
        signal.signal(signal.SIGINT, _graceful_exit)
    except Exception:
        pass

    action = sys.argv[1].lower() if len(sys.argv) > 1 else "start"
    if action == "stop":
        sys.exit(asyncio.run(handle_stop()))
    elif action in ("start", "test"):
        sys.exit(asyncio.run(handle_start()))
    elif action == "bg-start":
        msg = sys.argv[2] if len(sys.argv) > 2 else ""
        if msg:
            sys.exit(asyncio.run(handle_background_start(msg)))
        sys.exit(0)
    else:
        print(f"Uso: {sys.argv[0]} [start|stop|test]")
        sys.exit(1)


if __name__ == "__main__":
    main()
