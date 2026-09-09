#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ GUARDIÁN TÉRMICO Y DE PROTECCIÓN FÍSICA DE CPU: thermal_guard.py (@IA_ValleSeco_bot)
# Monitoreo continuo de temperatura, parada de emergencia de IA y alertas forenses
# Ubicación: /scripts/telegram-admin-bot/monitor/thermal_guard.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import argparse
import html
import json
import logging
import os
import platform
import signal
import subprocess
import sys
import time
import urllib.parse
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import httpx

# Constantes de Entorno y Rutas
BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_PATH = BASE_DIR / "config" / "config.json"
BOT_CONF_PATH = BASE_DIR / "config" / "bot.conf"
AUDIT_DIR = BASE_DIR / "audit"
LOGS_DIR = BASE_DIR / "logs"

# ID Inmutable del Administrador (Regla de Oro #1: Jamás despachar a grupos)
IMMUTABLE_OWNER_ID = 38914901

# Umbrales Térmicos (°C) y Tiempos de Seguridad
DEFAULT_TEMP_WARNING = 68.0       # Nivel advertencia en logs
DEFAULT_TEMP_CRITICAL = 75.0      # Parada de emergencia inmediata del servicio de IA
DEFAULT_TEMP_RECOVERY = 55.0      # Temperatura de enfriamiento requerida para reanudar
DEFAULT_COOLDOWN_SECONDS = 180    # 3 minutos continuos bajo TEMP_RECOVERY
CHECK_INTERVAL_SECONDS = 5        # Intervalo de muestreo continuo en segundos

# Nombre de la unidad del servicio a controlar en systemd
AI_SERVICE_UNIT = "ollama.service"

# Configuración de Logging
try:
    LOGS_DIR.mkdir(parents=True, exist_ok=True)
    AUDIT_DIR.mkdir(parents=True, exist_ok=True)
except Exception:
    pass

log_handlers: List[logging.Handler] = [logging.StreamHandler(sys.stdout)]
try:
    f_h = logging.FileHandler(LOGS_DIR / "thermal_guard.log", encoding="utf-8")
    log_handlers.append(f_h)
    # Asegurar permisos de lectura/escritura para usuarios no root
    try:
        os.chmod(LOGS_DIR / "thermal_guard.log", 0o666)
    except Exception:
        pass
except Exception:
    pass

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    handlers=log_handlers
)
logger = logging.getLogger("thermal_guard")


def get_local_ip_addresses() -> List[str]:
    """Obtiene las direcciones IP locales no loopback del host."""
    ips = []
    try:
        res = subprocess.run(["hostname", "-I"], capture_output=True, text=True, timeout=2.0)
        for ip in res.stdout.strip().split():
            if ip and not ip.startswith("127.") and ":" not in ip:
                ips.append(ip)
    except Exception:
        pass
    return ips or ["127.0.0.1"]


def get_core_bot_token() -> str:
    """Extrae el bot_token configurado desde config.json."""
    if CONFIG_PATH.exists():
        try:
            data = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
            tok = data.get("bot_token", "").strip()
            if tok and ":" in tok and len(tok) >= 30:
                return tok
        except Exception:
            pass
    return ""


def get_proxies_list() -> List[Optional[str]]:
    """Construye la lista de proxies corporativos con fallback a conexión directa."""
    proxies: List[Optional[str]] = [None]
    if CONFIG_PATH.exists():
        try:
            data = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
            for p in data.get("proxies", []):
                p_url = p.get("url") if isinstance(p, dict) else p
                if p_url and p_url not in proxies:
                    proxies.append(p_url)
        except Exception:
            pass

    if BOT_CONF_PATH.exists():
        try:
            b_lines = BOT_CONF_PATH.read_text(encoding="utf-8").splitlines()
            b_data = {}
            for l in b_lines:
                l = l.strip()
                if l and not l.startswith("#") and "=" in l:
                    k, v = l.split("=", 1)
                    b_data[k.strip()] = v.strip().strip("'\"")
            for letter in ("A", "B", "C", "D"):
                ip = b_data.get(f"IPADDRPORTPROXY{letter}")
                auth = b_data.get(f"USERPASSWDPROXY{letter}")
                if ip:
                    if auth and ":" in auth:
                        u, pwd = auth.split(":", 1)
                        p_url = f"http://{urllib.parse.quote(u)}:{urllib.parse.quote(pwd)}@{ip}"
                    else:
                        p_url = f"http://{ip}"
                    if p_url not in proxies:
                        proxies.append(p_url)
        except Exception:
            pass
    return proxies


def dispatch_telegram_alert(text: str, alert_type: str = "thermal") -> bool:
    """
    Despacha una alerta directa y exclusiva al Owner mediante el bot principal.
    Cumple estrictamente la Regla de Oro #1 (chat_id: 38914901, jamás a grupos).
    """
    token = get_core_bot_token()
    if not token:
        logger.error("No se pudo obtener el bot_token para despachar la alerta de temperatura.")
        return False

    url = f"https://api.telegram.org/bot{token}/sendMessage"
    payload = {
        "chat_id": IMMUTABLE_OWNER_ID,
        "text": text,
        "parse_mode": "HTML"
    }

    proxies = get_proxies_list()
    for proxy in proxies:
        try:
            with httpx.Client(proxy=proxy, timeout=8.0) as client:
                resp = client.post(url, json=payload)
                if resp.status_code == 200 and resp.json().get("ok"):
                    logger.info(f"Alerta de temperatura ({alert_type}) entregada exitosamente al Owner.")
                    return True
        except Exception as e:
            logger.debug(f"Fallo enviando alerta vía proxy [{proxy}]: {e}")
            continue

    logger.error(f"Fallo el despacho de alerta de temperatura ({alert_type}) por todas las rutas.")
    return False


def get_cpu_temperatures() -> Dict[str, Any]:
    """
    Lee las temperaturas físicas del procesador directamente desde sysfs (/sys/class/hwmon).
    Altamente eficiente: no crea subprocesos ni añade carga a la CPU.
    """
    package_temp: Optional[float] = None
    core_temps: Dict[str, float] = {}
    other_temps: Dict[str, float] = {}
    found_coretemp = False

    # 1. Explorar /sys/class/hwmon
    hwmon_base = Path("/sys/class/hwmon")
    if hwmon_base.exists():
        try:
            for hdir in sorted(hwmon_base.iterdir()):
                if not hdir.is_dir():
                    continue
                name_file = hdir / "name"
                drv_name = name_file.read_text(encoding="utf-8").strip() if name_file.exists() else ""

                if drv_name in ("coretemp", "k10temp"):
                    found_coretemp = True
                    # Leer sensores específicos de CPU
                    for temp_file in sorted(hdir.glob("temp*_input")):
                        label_file = temp_file.parent / f"{temp_file.name[:-6]}_label"
                        label = label_file.read_text(encoding="utf-8").strip() if label_file.exists() else temp_file.name
                        try:
                            milli_c = int(temp_file.read_text(encoding="utf-8").strip())
                            deg_c = round(milli_c / 1000.0, 1)
                            if "Package" in label or "Tdie" in label or "Tctl" in label:
                                package_temp = deg_c
                            elif "Core" in label:
                                core_temps[label] = deg_c
                            else:
                                other_temps[label] = deg_c
                        except Exception:
                            continue
                elif drv_name in ("acpitz", "cpu_thermal") and not found_coretemp:
                    for temp_file in sorted(hdir.glob("temp*_input")):
                        try:
                            milli_c = int(temp_file.read_text(encoding="utf-8").strip())
                            deg_c = round(milli_c / 1000.0, 1)
                            other_temps[f"{drv_name}_{temp_file.stem}"] = deg_c
                        except Exception:
                            continue
        except Exception as e:
            logger.warning(f"Error explorando /sys/class/hwmon: {e}")

    # 2. Fallback a /sys/class/thermal
    if not package_temp and not core_temps and not other_temps:
        thermal_base = Path("/sys/class/thermal")
        if thermal_base.exists():
            try:
                for zdir in sorted(thermal_base.glob("thermal_zone*")):
                    temp_f = zdir / "temp"
                    type_f = zdir / "type"
                    z_type = type_f.read_text(encoding="utf-8").strip() if type_f.exists() else zdir.name
                    if temp_f.exists():
                        try:
                            milli_c = int(temp_f.read_text(encoding="utf-8").strip())
                            deg_c = round(milli_c / 1000.0, 1)
                            other_temps[z_type] = deg_c
                        except Exception:
                            continue
            except Exception as e:
                logger.warning(f"Error en fallback /sys/class/thermal: {e}")

    # 3. Fallback adicional con comando sensors -j si sysfs no arrojó nada
    if not package_temp and not core_temps and not other_temps:
        try:
            res = subprocess.run(["sensors", "-j"], capture_output=True, text=True, timeout=2.0)
            if res.returncode == 0 and res.stdout:
                data = json.loads(res.stdout)
                for chip, sensors in data.items():
                    if "coretemp" in chip.lower() or "k10temp" in chip.lower():
                        for s_name, s_vals in sensors.items():
                            if isinstance(s_vals, dict):
                                for k, val in s_vals.items():
                                    if "input" in k and isinstance(val, (int, float)):
                                        val_f = round(float(val), 1)
                                        if "package" in s_name.lower():
                                            package_temp = val_f
                                        elif "core" in s_name.lower():
                                            core_temps[s_name] = val_f
        except Exception:
            pass

    # Consolidar temperatura máxima representativa
    all_readings = []
    if package_temp is not None:
        all_readings.append(package_temp)
    all_readings.extend(core_temps.values())
    all_readings.extend(other_temps.values())

    max_temp = max(all_readings) if all_readings else 0.0
    avg_temp = round(sum(all_readings) / len(all_readings), 1) if all_readings else 0.0

    if max_temp >= DEFAULT_TEMP_CRITICAL:
        level = "CRITICAL"
        badge = "🔴"
    elif max_temp >= DEFAULT_TEMP_WARNING:
        level = "WARNING"
        badge = "🟡"
    else:
        level = "NORMAL"
        badge = "🟢"

    return {
        "package_temp": package_temp if package_temp is not None else max_temp,
        "core_temps": core_temps,
        "other_temps": other_temps,
        "max_temp": max_temp,
        "avg_temp": avg_temp,
        "level": level,
        "badge": badge
    }


def is_ai_service_active() -> bool:
    """Verifica si el servicio del motor local de IA se encuentra actualmente activo en systemd."""
    try:
        res = subprocess.run(
            ["systemctl", "is-active", AI_SERVICE_UNIT],
            capture_output=True,
            text=True,
            timeout=3.0
        )
        return res.stdout.strip() == "active"
    except Exception:
        return False


def stop_ai_service() -> bool:
    """Detiene inmediatamente el servicio del motor local de IA para salvaguardar el procesador."""
    try:
        cmd = ["sudo", "systemctl", "stop", AI_SERVICE_UNIT] if os.geteuid() != 0 else ["systemctl", "stop", AI_SERVICE_UNIT]
        logger.critical(f"🛑 [CONTROL DE IA] Ejecutando: {' '.join(cmd)}")
        res = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=10.0
        )
        return res.returncode == 0
    except Exception as e:
        logger.error(f"Error deteniendo {AI_SERVICE_UNIT}: {e}")
        return False


def start_ai_service() -> bool:
    """Reanuda el servicio del motor local de IA una vez que la CPU ha alcanzado temperatura segura."""
    try:
        cmd = ["sudo", "systemctl", "start", AI_SERVICE_UNIT] if os.geteuid() != 0 else ["systemctl", "start", AI_SERVICE_UNIT]
        logger.info(f"🟢 [CONTROL DE IA] Ejecutando: {' '.join(cmd)}")
        res = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=10.0
        )
        return res.returncode == 0
    except Exception as e:
        logger.error(f"Error iniciando {AI_SERVICE_UNIT}: {e}")
        return False


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
                logger.error(f"Excepción en bucle de supervisión térmica: {e}", exc_info=True)

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

        # -------------------------------------------------------------
        # CASO 1: Estado de Emergencia Activo (Esperando enfriamiento)
        # -------------------------------------------------------------
        if self.in_emergency_state:
            if max_t < self.temp_recovery:
                if self.cooldown_started_at is None:
                    self.cooldown_started_at = now
                    logger.info(
                        f"❄️ CPU por debajo de {self.temp_recovery}°C ({max_t}°C). "
                        f"Iniciando cuenta regresiva de enfriamiento ({self.cooldown_seconds}s)..."
                    )
                else:
                    elapsed = now - self.cooldown_started_at
                    if elapsed >= self.cooldown_seconds:
                        # Recuperación completada exitosamente
                        self._handle_recovery(status)
            else:
                # La temperatura volvió a subir por encima de TEMP_RECOVERY; reiniciar contador
                if self.cooldown_started_at is not None:
                    logger.warning(
                        f"⚠️ La temperatura subió a {max_t}°C antes de completar enfriamiento seguro. Reiniciando temporizador."
                    )
                    self.cooldown_started_at = None
            return

        # -------------------------------------------------------------
        # CASO 2: Temperatura Normal
        # -------------------------------------------------------------
        if max_t < self.temp_warning:
            return

        # -------------------------------------------------------------
        # CASO 3: Nivel Advertencia (68°C - 74.9°C)
        # -------------------------------------------------------------
        if self.temp_warning <= max_t < self.temp_critical:
            if now - self.last_warning_log_at > 60:
                logger.warning(
                    f"🟡 Temperatura elevada en CPU: {max_t}°C (Package: {status['package_temp']}°C). Vigilando..."
                )
                self.last_warning_log_at = now
            return

        # -------------------------------------------------------------
        # CASO 4: NIVEL CRÍTICO (>= 75.0°C) -> DISPARO DE EMERGENCIA
        # -------------------------------------------------------------
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

        # 1. Detener el servicio del motor local de IA
        stop_success = stop_ai_service()

        # 2. Registrar en archivo de auditoría forense
        audit_file = AUDIT_DIR / "thermal_emergency.log"
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        hostname = platform.node()
        ips_str = ", ".join(get_local_ip_addresses())
        cores_str = ", ".join([f"{k}: {v}°C" for k, v in status["core_temps"].items()])

        log_entry = (
            f"[{now_str}] 🚨 PARADA DE EMERGENCIA TÉRMICA | Host: {hostname} ({ips_str}) | "
            f"Temp Max: {status['max_temp']}°C (Umbral: {self.temp_critical}°C) | "
            f"Package: {status['package_temp']}°C | Cores: [{cores_str}] | "
            f"Servicio Detenido: {stop_success}\n"
        )
        try:
            with open(audit_file, "a", encoding="utf-8") as f:
                f.write(log_entry)
        except Exception as e:
            logger.error(f"Error escribiendo en auditoría térmica: {e}")

        # 3. Construir mensaje de alerta privada para Telegram (Reglas de Oro #1 y #2)
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
            "El <b>Servicio local de IA</b> ha sido <b>DETENIDO INMEDIATAMENTE</b> para eliminar al 100% la carga de cómputo y permitir la disipación térmica.\n\n"
            "🔄 <b>Protocolo de Recuperación:</b>\n"
            f"El guardián mantendrá el monitoreo continuo y reactivará el servicio automáticamente cuando la temperatura descienda por debajo de los <b>{self.temp_recovery:.1f}°C</b> de forma sostenida durante 3 minutos."
        )

        dispatch_telegram_alert(alert_msg, alert_type="thermal_critical")

    def _handle_recovery(self, status: Dict[str, Any]) -> None:
        """Restaura el servicio de IA y envía reporte de normalización."""
        logger.info(
            f"🟢 Enfriamiento sostenido verificado ({status['max_temp']}°C < {self.temp_recovery}°C). "
            "Restableciendo servicio de IA..."
        )

        # 1. Iniciar el servicio del motor local de IA
        start_success = start_ai_service()

        # 2. Registrar en auditoría
        audit_file = AUDIT_DIR / "thermal_emergency.log"
        now_str = time.strftime("%Y-%m-%d %H:%M:%S")
        hostname = platform.node()
        ips_str = ", ".join(get_local_ip_addresses())

        log_entry = (
            f"[{now_str}] 🟢 RESTABLECIMIENTO TÉRMICO | Host: {hostname} ({ips_str}) | "
            f"Temp Actual: {status['max_temp']}°C | Servicio Reactivado: {start_success}\n"
        )
        try:
            with open(audit_file, "a", encoding="utf-8") as f:
                f.write(log_entry)
        except Exception:
            pass

        # 3. Notificación a Telegram
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

        # 4. Restablecer banderas
        self.in_emergency_state = False
        self.cooldown_started_at = None
        self.emergency_triggered_at = 0.0


def print_cli_status() -> None:
    """Imprime el diagnóstico térmico en consola en formato legible y elegante."""
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
    print(f"   - Recuperación (Cooldown):   < {DEFAULT_TEMP_RECOVERY}°C (sostenido 3 min)")
    print("==============================================================")


def test_telegram_alert() -> None:
    """Envía una alerta de prueba al Administrador para verificar la conexión."""
    hostname = platform.node()
    ips_str = ", ".join(get_local_ip_addresses())
    status = get_cpu_temperatures()
    now_str = time.strftime("%Y-%m-%d %H:%M:%S")

    msg = (
        "🧪 <b>[PRUEBA DE GUARDIÁN TÉRMICO: TELEGRAM OK]</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "Esta es una prueba de verificación del sistema de alertas térmicas.\n\n"
        f"🖥️ <b>Servidor:</b> <code>{html.escape(hostname)}</code>\n"
        f"🌐 <b>Dirección IP:</b> <code>{ips_str}</code>\n"
        f"🌡️ <b>Temperatura Actual:</b> <code>{status['max_temp']:.1f}°C</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
        "✅ <i>El canal privado de notificaciones de emergencia térmica opera correctamente.</i>"
    )
    print(f"Enviando alerta de prueba al Owner ({IMMUTABLE_OWNER_ID})...")
    ok = dispatch_telegram_alert(msg, alert_type="test")
    if ok:
        print("✅ Alerta de prueba enviada exitosamente por Telegram.")
    else:
        print("❌ Error enviando alerta de prueba. Revise bot_token o conectividad.")


def main():
    parser = argparse.ArgumentParser(description="Guardián Térmico y de Protección Física de CPU")
    parser.add_argument("--status", action="store_true", help="Muestra el estado térmico actual y sale")
    parser.add_argument("--test-alert", action="store_true", help="Envía una alerta de prueba a Telegram al Owner")
    parser.add_argument("--daemon", action="store_true", help="Ejecuta en segundo plano como daemon continuo")
    parser.add_argument("--critical", type=float, default=DEFAULT_TEMP_CRITICAL, help="Umbral crítico (°C)")
    parser.add_argument("--warning", type=float, default=DEFAULT_TEMP_WARNING, help="Umbral de advertencia (°C)")
    parser.add_argument("--recovery", type=float, default=DEFAULT_TEMP_RECOVERY, help="Umbral de recuperación (°C)")

    args = parser.parse_args()

    if args.status:
        print_cli_status()
        return

    if args.test_alert:
        test_telegram_alert()
        return

    guardian = ThermalGuardian(
        temp_critical=args.critical,
        temp_warning=args.warning,
        temp_recovery=args.recovery
    )
    guardian.run_daemon_loop()


if __name__ == "__main__":
    main()
