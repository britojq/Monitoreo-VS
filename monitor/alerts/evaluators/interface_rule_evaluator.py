# monitor/alerts/evaluators/interface_rule_evaluator.py
from typing import Any, Callable, Dict, Optional, Set, Tuple

class InterfaceRuleEvaluator:
    """Evaluador polimórfico para reglas de interfaces de red y puertos físicos."""

    @staticmethod
    def evaluate(
        cur: Any,
        rule: Dict[str, Any],
        c_type: str,
        severity: str,
        firing_keys: Set[Tuple[str, int, str]],
        trigger_callback: Callable[..., Optional[int]],
    ) -> None:
        """Evalúa la métrica recibida contra las reglas y dispara la alerta correspondiente.

        Args:
            cur: Parámetro operativo de entrada.
            rule: Parámetro operativo de entrada.
            c_type: Parámetro operativo de entrada.
            severity: Parámetro operativo de entrada.
            firing_keys: Parámetro operativo de entrada.
            trigger_callback: Parámetro operativo de entrada.
        """
        cur.execute(
            """
            SELECT i.id, i.if_name, i.if_oper_status, d.id as device_id, d.name as device_name
            FROM snmp_interfaces i
            JOIN snmp_devices d ON i.snmp_device_id = d.id
            WHERE i.is_monitored = 1 AND d.is_active = 1
            """
        )
        interfaces = cur.fetchall()

        for iface in interfaces:
            iid = iface["id"]
            iname = f"{iface['device_name']} - {iface['if_name'] or 'if#'+str(iid)}"
            oper = str(iface["if_oper_status"] or "").lower()

            if c_type == "interface_down":
                if oper in ("down", "2", "lowerlayerdown"):
                    firing_keys.add(("interface", iid, c_type))
                    trigger_callback(
                        rule, "interface", iid, iname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"La interfaz de red {iname} se encuentra CAÍDA (operState: down)."
                    )
