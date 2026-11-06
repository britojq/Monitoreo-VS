# monitor/alerts/evaluators/discovered_device_rule_evaluator.py
from typing import Any, Callable, Dict, Optional, Set, Tuple

class DiscoveredDeviceRuleEvaluator:
    """Evaluador polimórfico para reglas de detección de dispositivos nuevos o desaparecidos."""

    @staticmethod
    def evaluate(
        cur: Any,
        rule: Dict[str, Any],
        c_type: str,
        threshold: Optional[float],
        comparison: Optional[str],
        severity: str,
        firing_keys: Set[Tuple[str, int, str]],
        trigger_callback: Callable[..., Optional[int]],
    ) -> None:
        """Evalúa la métrica recibida contra las reglas y dispara la alerta correspondiente.

        Args:
            cur: Parámetro operativo de entrada.
            rule: Parámetro operativo de entrada.
            c_type: Parámetro operativo de entrada.
            threshold: Parámetro operativo de entrada.
            comparison: Parámetro operativo de entrada.
            severity: Parámetro operativo de entrada.
            firing_keys: Parámetro operativo de entrada.
            trigger_callback: Parámetro operativo de entrada.
        """
        if c_type == "new_device":
            cur.execute(
                """
                SELECT id, ip_address, mac_address, hostname, vendor
                FROM discovered_devices
                WHERE classification_status = 'rogue'
                  AND first_seen >= NOW() - INTERVAL 1 HOUR
                """
            )
            rogues = cur.fetchall()
            for r in rogues:
                rid = r["id"]
                rname = f"{r['ip_address']} ({r['mac_address'] or 'Sin MAC'})"
                firing_keys.add(("discovered_device", rid, c_type))
                trigger_callback(
                    rule, "discovered_device", rid, rname, c_type, severity,
                    message=f"Dispositivo no autorizado detectado en red: {rname} ({r.get('vendor') or 'Genérico'})."
                )

        elif c_type == "device_disappeared":
            cur.execute(
                """
                SELECT id, ip_address, mac_address, hostname, vendor, last_seen
                FROM discovered_devices
                WHERE is_authorized = 1
                  AND last_seen < NOW() - INTERVAL 1 HOUR
                """
            )
            disappeared = cur.fetchall()
            for d in disappeared:
                did = d["id"]
                dname = f"{d['ip_address']} ({d['mac_address'] or 'Sin MAC'})"
                firing_keys.add(("discovered_device", did, c_type))
                trigger_callback(
                    rule, "discovered_device", did, dname, c_type, severity,
                    message=f"Dispositivo autorizado desaparecido de la red por más de 1 hora: {dname}."
                )
