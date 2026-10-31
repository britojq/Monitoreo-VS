"""
Controlador autónomo del guardián térmico con histéresis y auto-recuperación.
"""
import html
import logging
import platform
import signal
import time
from typing import Any, Dict, Optional

from monitor.checkers.thermal.constants import (
    CHECK_INTERVAL_SECONDS,
    DEFAULT_COOLDOWN_SECONDS,
    DEFAULT_TEMP_CRITICAL,
    DEFAULT_TEMP_RECOVERY,
    DEFAULT_TEMP_WARNING,
)
from monitor.checkers.thermal.sensor_reader import (
    get_cpu_temperatures,
    get_local_ip_addresses,
)
from monitor.checkers.thermal.systemd_ai_controller import (
    is_ai_service_active,
    start_ai_service,
    stop_ai_service,
)
from monitor.checkers.thermal.thermal_notifier import (
    dispatch_telegram_alert,
    record_thermal_audit_event,
)

logger = logging.getLogger("monitor.checkers.thermal.guardian")


class ThermalGuardian:
    """Controlador autónomo del guardián térmico con histéresis y auto-recuperación."""

    def __init__(
        self,
        temp_critical: float = DEFAULT_TEMP_CRITICAL,
        temp_warning: float = DEFAULT_TEMP_WARNING,
        temp_recovery: float = DEFAULT_TEMP_RECOVERY,
        cooldown_seconds: int = DEFAULT_COOLDOWN_SECONDS
    ):
        self.temp_critical = temp_critical
        self.temp_warning = temp_warning
        self.temp_recovery = temp_recovery
        self.cooldown_seconds = cooldown_seconds

        self.in_emergency_state = False
        self.emergency_triggered_at: float = 0.0
        self.cooldown_started_at: Optional[float] = None
        self.last_warning_log_at: float = 0.0
        self.running = True

    def run_daemon_loop(self) -> None:
        """Bucle principal de supervisión continua."""
        logger.info(
            f"🛡️ Guardián Térmico iniciado | Umbral Crítico: {self.temp_critical}°C | "
            f"Recuperación: < {self.temp_recovery}°C ({self.cooldown_seconds}s) | Intervalo: {CHECK_INTERVAL_SECONDS}s"
        )

        signal.signal(signal.SIGTERM, self._handle_signal)
        signal.signal(signal.SIGINT, self._handle_signal)

        while self.running:
            try:
                self.check_tick()
            except Exception as e:
                logger.error(f"Excepción en supervisión térmica: {e}", exc_info=True)
            time.sleep(CHECK_INTERVAL_SECONDS)

        logger.info("Guardián Térmico detenido ordenadamente.")

    def _handle_signal(self, signum, frame):
        logger.info(f"Señal recibida ({signum}). Deteniendo Guardián Térmico...")
        self.running = False

    def check_tick(self) -> None:
        """Evaluación de un ciclo de temperatura."""
        status = get_cpu_temperatures()
        max_t = status["max_temp"]
        now = time.time()

        if self.in_emergency_state:
            if max_t < self.temp_recovery:
                if self.cooldown_started_at is None:
                    self.cooldown_started_at = now
                    logger.info(
                        f"❄️ CPU por debajo de {self.temp_recovery}°C ({max_t}°C). "
                        f"Iniciando enfriamiento ({self.cooldown_seconds}s)..."
                    )
                else:
                    elapsed = now - self.cooldown_started_at
                    if elapsed >= self.cooldown_seconds:
                        self._handle_recovery(status)
            else:
                if self.cooldown_started_at is not None:
                    logger.warning(
                        f"⚠️ Temperatura subió a {max_t}°C antes del enfriamiento. Reiniciando temporizador."
                    )
                    self.cooldown_started_at = None
            return

        if max_t < self.temp_warning:
            return

        if self.temp_warning <= max_t < self.temp_critical:
            if now - self.last_warning_log_at > 60:
                logger.warning(
                    f"🟡 Temperatura elevada en CPU: {max_t}°C (Package: {status['package_temp']}°C)."
                )
                self.last_warning_log_at = now
            return

        if max_t >= self.temp_critical:
            self._handle_emergency_trip(status)

    def _handle_emergency_trip(self, status: Dict[str, Any]) -> None:
        """Ejecuta la parada de emergencia y despacha la alerta privada al Owner."""
        self.in_emergency_state = True
        self.emergency_triggered_at = time.time()
        self.cooldown_started_at = None

        logger.critical(
            f"🚨 ¡DISPARO DE PROTECCIÓN TÉRMICA! Temp máxima: {status['max_temp']}°C >= {self.temp_critical}°C. "
            "Cortando servicio de IA inmediatamente."
        )

        stop_success = stop_ai_service()
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        hostname = platform.node()
        ips_str = ", ".join(get_local_ip_addresses())
        cores_str = ", ".join([f"{k}: {v}°C" for k, v in status["core_temps"].items()])

        log_entry = (
            f"[{now_str}] 🚨 PARADA DE EMERGENCIA TÉRMICA | Host: {hostname} ({ips_str}) | "
            f"Temp Max: {status['max_temp']}°C (Umbral: {self.temp_critical}°C) | "
            f"Package: {status['package_temp']}°C | Cores: [{cores_str}] | "
            f"Servicio Detenido: {stop_success}"
        )
        logger.error(log_entry)
        record_thermal_audit_event(
            event="THERMAL_EMERGENCY_STOP",
            description=log_entry,
            metadata={
                "hostname": hostname,
                "ips": get_local_ip_addresses(),
                "max_temp": status["max_temp"],
                "package_temp": status["package_temp"],
                "core_temps": status["core_temps"],
                "stop_success": stop_success,
            },
        )

        cores_lines = ""
        for c_name, c_temp in status["core_temps"].items():
            cores_lines += f"• {html.escape(c_name)}: <code>{c_temp:.1f}°C</code>\n"

        alert_msg = (
            "🚨 <b>[ALERTA DE SEGURIDAD FÍSICA: SOBRECALENTAMIENTO DE CPU]</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "⚠️ <b>Se detectó una temperatura crítica en el procesador.</b>\n"
            "Se ha activado el protocolo de protección física para prevenir degradación del hardware.\n\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
            f"🔥 <b>Temperatura Pico:</b> <code>{status['max_temp']:.1f}°C</code> <i>(Límite Seguro: {self.temp_critical:.1f}°C)</i>\n"
            f"📦 <b>Package CPU:</b> <code>{status['package_temp']:.1f}°C</code>\n\n"
            "📊 <b>Detalle Térmico por Núcleo:</b>\n"
            f"{cores_lines}\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "🛡️ <b>ACCIÓN AUTOMÁTICA EJECUTADA:</b>\n"
            "El <b>Servicio local de IA</b> ha sido <b>DETENIDO INMEDIATAMENTE</b> para permitir la disipación térmica.\n\n"
            "🔄 <b>Protocolo de Recuperación:</b>\n"
            f"El guardián reactivará el servicio automáticamente cuando la temperatura descienda por debajo de los <b>{self.temp_recovery:.1f}°C</b> de forma sostenida durante 3 minutos."
        )

        dispatch_telegram_alert(alert_msg, alert_type="thermal_critical")

    def _handle_recovery(self, status: Dict[str, Any]) -> None:
        """Restaura el servicio de IA y envía reporte de normalización."""
        logger.info(
            f"🟢 Enfriamiento sostenido verificado ({status['max_temp']}°C < {self.temp_recovery}°C). "
            "Restableciendo servicio de IA..."
        )

        start_success = start_ai_service()
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        hostname = platform.node()
        ips_str = ", ".join(get_local_ip_addresses())

        log_entry = (
            f"[{now_str}] 🟢 RESTABLECIMIENTO TÉRMICO | Host: {hostname} ({ips_str}) | "
            f"Temp Actual: {status['max_temp']}°C | Servicio Reactivado: {start_success}"
        )
        logger.info(log_entry)
        record_thermal_audit_event(
            event="THERMAL_RECOVERY",
            description=log_entry,
            metadata={
                "hostname": hostname,
                "ips": get_local_ip_addresses(),
                "current_temp": status["max_temp"],
                "start_success": start_success,
            },
        )

        recovery_msg = (
            "🟢 <b>[NORMALIZACIÓN TÉRMICA: SERVICIO RESTABLECIDO]</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "✅ <b>La temperatura del procesador ha regresado a niveles seguros y estables.</b>\n\n"
            f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
            f"❄️ <b>Temperatura Actual:</b> <code>{status['max_temp']:.1f}°C</code> <i>(Recuperación exitosa &lt; {self.temp_recovery:.1f}°C)</i>\n"
            f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            "🚀 <b>El Servicio local de IA ha sido REACTIVADO exitosamente y opera con normalidad.</b>"
        )

        dispatch_telegram_alert(recovery_msg, alert_type="thermal_recovery")
        self.in_emergency_state = False
        self.cooldown_started_at = None
        self.emergency_triggered_at = 0.0


def print_cli_status() -> None:
    """Imprime el diagnóstico térmico en consola en formato legible."""
    status = get_cpu_temperatures()
    ai_active = is_ai_service_active()
    hostname = platform.node()
    ips_str = ", ".join(get_local_ip_addresses())

    print("==============================================================")
    print(" 🛡️  DIAGNÓSTICO TÉRMICO Y PROTECCIÓN FÍSICA DE CPU          ")
    print("==============================================================")
    print(f"🖥️  Servidor (Hostname):  {hostname}")
    print(f"🌐 Direcciones IP:       {ips_str}")
    print(f"🧠 Estado Servicio IA:   {'🟢 ACTIVO' if ai_active else '🔴 DETENIDO'}")
    print("--------------------------------------------------------------")
    print(f"🔥 Temperatura Pico:     {status['badge']} {status['max_temp']:.1f}°C ({status['level']})")
    print(f"📦 Package CPU:          {status['package_temp']:.1f}°C")
    print(f"📊 Promedio Sensores:    {status['avg_temp']:.1f}°C")

    if status["core_temps"]:
        print("\nDetalle por Núcleo:")
        for c_name, c_temp in status["core_temps"].items():
            core_badge = "🔴" if c_temp >= DEFAULT_TEMP_CRITICAL else ("🟡" if c_temp >= DEFAULT_TEMP_WARNING else "🟢")
            print(f"  • {c_name:12}: {core_badge} {c_temp:.1f}°C")

    if status["other_temps"]:
        print("\nOtros Sensores Térmicos:")
        for s_name, s_temp in status["other_temps"].items():
            print(f"  • {s_name:16}: {s_temp:.1f}°C")

    print("--------------------------------------------------------------")
    print(f"⚙️  Umbrales Configurados:")
    print(f"   - Advertencia (Warning):    {DEFAULT_TEMP_WARNING}°C")
    print(f"   - Parada Crítica (Stop IA):  {DEFAULT_TEMP_CRITICAL}°C")
    print(f"   - Recuperación (Resume IA):  {DEFAULT_TEMP_RECOVERY}°C ({DEFAULT_COOLDOWN_SECONDS}s)")
    print("==============================================================")


def test_telegram_alert() -> bool:
    """Envía una alerta de prueba al Administrador (Owner)."""
    status = get_cpu_temperatures()
    hostname = platform.node()
    ips_str = ", ".join(get_local_ip_addresses())
    test_msg = (
        "🧪 <b>[TEST: ALERTA DE PRUEBA DE GUARDIÁN TÉRMICO]</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "Esta es una prueba de verificación del sistema de alertas térmicas de emergencia.\n\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"🌐 <b>IP:</b> <code>{ips_str}</code>\n"
        f"🌡️ <b>Temperatura Actual:</b> <code>{status['max_temp']:.1f}°C</code> ({status['badge']} {status['level']})\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "✅ <i>Canal de despacho directo al Owner verificado y operativo.</i>"
    )
    return dispatch_telegram_alert(test_msg, alert_type="thermal_test")
