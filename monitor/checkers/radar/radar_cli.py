"""
CLI y punto de entrada para NET Radar.
"""
import argparse
import asyncio
import sys

from monitor.checkers.radar.markdown_reporter import generate_markdown_report_from_db
from monitor.checkers.radar.radar_orchestrator import main_daemon, run_radar_cycle


def main():
    """Punto de entrada principal para ejecución por línea de comandos."""
    parser = argparse.ArgumentParser(description="Motor NET Radar de Tráfico y Actualizaciones")
    parser.add_argument("--once", action="store_true", help="Ejecuta un único ciclo de escaneo y sale")
    parser.add_argument("--daemon", action="store_true", help="Ejecuta el ciclo de monitoreo continuo")
    parser.add_argument("--report-md", action="store_true", help="Genera reporte en Markdown desde la base de datos")
    parser.add_argument("--cycle", type=int, default=15, help="Duración del ciclo de captura en segundos (default 15)")
    args = parser.parse_args()

    if args.report_md:
        summary, path, _ = generate_markdown_report_from_db()
        print(f"Reporte generado en: {path}")
        print(summary)
        sys.exit(0)

    if args.daemon:
        asyncio.run(main_daemon())
    else:
        asyncio.run(run_radar_cycle(cycle_duration=args.cycle))


if __name__ == "__main__":
    main()
