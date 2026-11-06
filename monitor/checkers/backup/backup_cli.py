"""
CLI y punto de entrada para el gestor de respaldos de configuraciones.
"""
import argparse
import json
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.checkers.backup.backup_manager import ConfigBackupManager


def main():
    """Punto de entrada principal para ejecución por línea de comandos."""
    parser = argparse.ArgumentParser(description="Gestor de Respaldos de Configuraciones de Red")
    parser.add_argument("--target-net", type=int, help="Ejecuta respaldo para un dispositivo de red por ID")
    parser.add_argument("--target-snmp", type=int, help="Ejecuta respaldo para un dispositivo SNMP por ID")
    parser.add_argument("--backup-all", action="store_true", help="Ejecuta respaldo de todos los equipos con credenciales SSH")
    parser.add_argument("--seed", action="store_true", help="Obsoleto bajo Regla #6 (Cero Datos Simulados)")
    parser.add_argument("--list", action="store_true", help="Lista los respaldos más recientes")
    parser.add_argument("--diff", type=int, help="Muestra el diff unificado para una configuración específica por ID")
    parser.add_argument("--stats", action="store_true", help="Muestra estadísticas de respaldos")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON")

    args = parser.parse_args()
    mgr = ConfigBackupManager()

    if args.target_net:
        res = mgr.backup_network_device_by_id(args.target_net, captured_by="manual")
        if args.json:
            print(json.dumps(res, default=str))
        else:
            status_ico = "✅" if res.get("action") in ("initial_baseline", "no_change", "modified", "reverted") else "❌"
            print(f"{status_ico} Respaldo de Dispositivo #{args.target_net}: {res.get('message') or res.get('error') or res.get('action')}")
        sys.exit(0 if res.get("action") != "failed" else 1)

    if args.target_snmp:
        res = mgr.backup_snmp_device_by_id(args.target_snmp, captured_by="manual")
        if args.json:
            print(json.dumps(res, default=str))
        else:
            status_ico = "✅" if res.get("action") in ("initial_baseline", "no_change", "modified", "reverted") else "❌"
            print(f"{status_ico} Respaldo de Dispositivo SNMP #{args.target_snmp}: {res.get('message') or res.get('error') or res.get('action')}")
        sys.exit(0 if res.get("action") != "failed" else 1)

    if args.backup_all:
        results = mgr.backup_all_configured_devices(captured_by="cron")
        if args.json:
            print(json.dumps(results, default=str))
        else:
            print(f"✅ Ciclo de respaldo finalizado: {len(results)} equipos procesados.")
        sys.exit(0)

    if args.seed:
        print("⚠️ Modo simulación erradicado en cumplimiento estricto de la Regla de Oro #6 (Cero Datos Ficticios).")
        sys.exit(0)

    if args.diff:
        diff_info = mgr.get_diff_for_configuration(args.diff)
        if not diff_info:
            print(f"❌ No se encontró diff para la configuración #{args.diff}")
            sys.exit(1)
        if args.json:
            print(json.dumps(diff_info, default=str))
        else:
            print(f"📋 DIFF PARA {diff_info.get('device_name')} (#{args.diff}):")
            print(f"   Tipo: {diff_info.get('change_type')} | +{diff_info.get('lines_added')}/-{diff_info.get('lines_removed')}")
            print("=" * 60)
            print(diff_info.get("diff_unified") or "Sin cambios registrados con la versión previa.")
        sys.exit(0)

    if args.stats:
        stats = mgr.get_backups_summary()
        if args.json:
            print(json.dumps(stats, default=str))
        else:
            print("📊 ESTADÍSTICAS DE RESPALDOS DE CONFIGURACIÓN:")
            print(f"• Equipos respaldados: {stats['total_devices']}")
            print(f"• Total respaldos: {stats['total_backups']}")
            print(f"• Respaldos hoy: {stats['backups_today']}")
            print(f"• Modificaciones detectadas: {stats['total_changes']}")
            print(f"• Espacio total: {stats['total_size_formatted']}")
        sys.exit(0)

    backups = mgr.list_latest_backups()
    if args.json:
        print(json.dumps(backups, default=str))
    else:
        print(f"💾 ÚLTIMOS RESPALDOS REGISTRADOS ({len(backups)}):")
        print("-" * 75)
        for b in backups[:15]:
            ch_str = f" [{b.get('change_type', '').upper()}]" if b.get('change_type') else ""
            print(f"#{b['id']:<4} {b.get('device_name', 'N/A')[:25]:<26} {b.get('device_ip', ''):<15} {b.get('config_size_bytes', 0)}B {ch_str} {b.get('captured_at')}")


if __name__ == "__main__":
    main()
