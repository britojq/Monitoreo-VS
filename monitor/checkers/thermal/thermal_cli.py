"""
CLI y punto de entrada para Thermal Guard.
"""
import argparse
import json
import sys

from monitor.checkers.thermal.constants import (
    DEFAULT_COOLDOWN_SECONDS,
    DEFAULT_TEMP_CRITICAL,
    DEFAULT_TEMP_RECOVERY,
    DEFAULT_TEMP_WARNING,
)
from monitor.checkers.thermal.sensor_reader import get_cpu_temperatures
from monitor.checkers.thermal.thermal_guardian import (
    ThermalGuardian,
    print_cli_status,
    test_telegram_alert,
)


def main():
    parser = argparse.ArgumentParser(description="Guardián Térmico y Protección Física de CPU (@IA_ValleSeco_bot)")
    parser.add_argument("--status", action="store_true", help="Muestra el diagnóstico térmico instantáneo y sale")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON para integraciones")
    parser.add_argument("--test-alert", action="store_true", help="Envía una alerta de prueba a Telegram")
    parser.add_argument("--daemon", action="store_true", help="Ejecuta el bucle de supervisión continua (default)")
    parser.add_argument("--temp-critical", type=float, default=DEFAULT_TEMP_CRITICAL, help="Umbral crítico de parada (°C)")
    parser.add_argument("--temp-warning", type=float, default=DEFAULT_TEMP_WARNING, help="Umbral de advertencia (°C)")
    parser.add_argument("--temp-recovery", type=float, default=DEFAULT_TEMP_RECOVERY, help="Umbral de reanudación (°C)")
    parser.add_argument("--cooldown", type=int, default=DEFAULT_COOLDOWN_SECONDS, help="Segundos sostenidos para reanudar")

    args = parser.parse_args()

    if args.json:
        data = get_cpu_temperatures()
        print(json.dumps(data, indent=2))
        sys.exit(0)

    if args.status:
        print_cli_status()
        sys.exit(0)

    if args.test_alert:
        ok = test_telegram_alert()
        print("✅ Alerta de prueba enviada exitosamente." if ok else "❌ Falló el envío de la alerta de prueba.")
        sys.exit(0 if ok else 1)

    guardian = ThermalGuardian(
        temp_critical=args.temp_critical,
        temp_warning=args.temp_warning,
        temp_recovery=args.temp_recovery,
        cooldown_seconds=args.cooldown
    )
    guardian.run_daemon_loop()


if __name__ == "__main__":
    main()
