# monitor/alerts/cli_formatter.py
import argparse
import asyncio
from datetime import datetime
from typing import Any, Dict, List

def format_table(rows: List[Dict[str, Any]]) -> str:
    """Genera tabla ANSI formateada para consola."""
    if not rows:
        return "No hay alertas activas en este momento."

    headers = ["ID", "Severidad", "Estado", "Entidad", "Condición", "Nivel", "Disparada"]
    col_w = [6, 12, 14, 25, 20, 7, 19]

    sep = "+" + "+".join("-" * (w + 2) for w in col_w) + "+"
    hdr = "| " + " | ".join(h.ljust(col_w[i]) for i, h in enumerate(headers)) + " |"

    lines = [sep, hdr, sep]
    for r in rows:
        fired_str = r["fired_at"].strftime("%Y-%m-%d %H:%M") if isinstance(r["fired_at"], datetime) else str(r["fired_at"] or "")
        vals = [
            str(r["id"]),
            r["severity"].upper(),
            r["status"].upper(),
            (r["entity_name"] or "")[:25],
            (r["condition_type"] or "")[:20],
            str(r["current_escalation_level"]),
            fired_str
        ]
        line = "| " + " | ".join(vals[i].ljust(col_w[i]) for i in range(len(headers))) + " |"
        lines.append(line)
    lines.append(sep)
    return "\n".join(lines)

async def main_cli(engine_instance: Any = None) -> None:
    """CLI runner para el motor de alertas y gestión de consola."""
    from monitor.alerts.alert_engine_service import AlertEngine
    engine = engine_instance or AlertEngine()

    parser = argparse.ArgumentParser(description="Motor de Alertas y Correlación")
    parser.add_argument("--evaluate", action="store_true", help="Ejecutar ciclo de evaluación de reglas")
    parser.add_argument("--escalate", action="store_true", help="Procesar cola de escalación y avisos")
    parser.add_argument("--list", action="store_true", help="Listar alertas activas")
    parser.add_argument("--ack", type=int, help="Reconocer alerta por ID")
    parser.add_argument("--resolve", type=int, help="Resolver manualmente alerta por ID")
    parser.add_argument("--silence", type=int, help="Silenciar alerta por ID")
    parser.add_argument("--minutes", type=int, default=60, help="Minutos para silenciar (default: 60)")
    parser.add_argument("--notes", type=str, default="Acción ejecutada vía CLI", help="Notas de auditoría")
    args = parser.parse_args()

    if args.evaluate:
        engine.evaluate_all_rules()

    if args.escalate:
        await engine.process_escalations()

    if args.ack:
        ok = engine.acknowledge_alert(args.ack, user_id=1, notes=args.notes)
        print(f"[{'OK' if ok else 'ERROR'}] Alerta #{args.ack} {'reconocida' if ok else 'no encontrada o ya procesada'}.")

    if args.resolve:
        ok = engine.resolve_alert(args.resolve, notes=args.notes)
        print(f"[{'OK' if ok else 'ERROR'}] Alerta #{args.resolve} {'resuelta' if ok else 'no encontrada o ya cerrada'}.")

    if args.silence:
        ok = engine.silence_alert(args.silence, minutes=args.minutes, user_id=1)
        print(f"[{'OK' if ok else 'ERROR'}] Alerta #{args.silence} silenciada por {args.minutes} min.")

    if args.list or (not args.evaluate and not args.escalate and not args.ack and not args.resolve and not args.silence):
        engine._ensure_db()
        with engine.db.cursor() as cur:
            cur.execute(
                """
                SELECT id, severity, status, entity_name, condition_type, current_escalation_level, fired_at
                FROM alerts
                WHERE status IN ('firing', 'acknowledged', 'suppressed')
                ORDER BY FIELD(severity, 'emergency', 'critical', 'warning', 'info'), fired_at DESC
                """
            )
            rows = cur.fetchall()
            print("\n🚨 ALERTAS ACTIVAS DEL SISTEMA:")
            print(format_table(rows))
            print(f"Total activas: {len(rows)}\n")
