# monitor/alerts/evaluators/snmp_device_rule_evaluator.py
from typing import Any, Callable, Dict, Optional, Set, Tuple

class SnmpDeviceRuleEvaluator:
    """Evaluador polimórfico para reglas de equipos de red y telemetría SNMP."""

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
        cur.execute(
            """
            SELECT id, name, ip_address, last_poll_status, consecutive_failures
            FROM snmp_devices
            WHERE is_active = 1
            """
        )
        devices = cur.fetchall()

        for d in devices:
            did = d["id"]
            dname = d["name"] or d["ip_address"]
            poll_status = d["last_poll_status"] or "unknown"

            if c_type == "snmp_unreachable":
                if poll_status in ("timeout", "auth_error", "error") or (d.get("consecutive_failures") or 0) >= 3:
                    firing_keys.add(("snmp_device", did, c_type))
                    trigger_callback(
                        rule, "snmp_device", did, dname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"Dispositivo SNMP {dname} inalcanzable (estado: {poll_status})."
                    )

            elif c_type == "cpu_high" and threshold is not None:
                cur.execute(
                    """
                    SELECT m.metric_value
                    FROM snmp_metrics_history m
                    JOIN snmp_oids o ON m.snmp_oid_id = o.id
                    WHERE m.snmp_device_id = %s AND (o.name LIKE '%%cpu%%' OR o.name LIKE '%%CPU%%')
                    ORDER BY m.id DESC LIMIT 1
                    """,
                    (did,)
                )
                cpu_row = cur.fetchone()
                if cpu_row and cpu_row["metric_value"] is not None:
                    cpu_val = float(cpu_row["metric_value"])
                    if cpu_val > threshold:
                        firing_keys.add(("snmp_device", did, c_type))
                        trigger_callback(
                            rule, "snmp_device", did, dname, c_type, severity,
                            value_at_trigger=cpu_val,
                            threshold_value=threshold,
                            message=f"Uso de CPU elevado en {dname}: {cpu_val:.1f}% (> {threshold}%)."
                        )

            elif c_type == "temperature_high" and threshold is not None:
                cur.execute(
                    """
                    SELECT m.metric_value
                    FROM snmp_metrics_history m
                    JOIN snmp_oids o ON m.snmp_oid_id = o.id
                    WHERE m.snmp_device_id = %s AND (o.name LIKE '%%temp%%' OR o.name LIKE '%%Temp%%')
                    ORDER BY m.id DESC LIMIT 1
                    """,
                    (did,)
                )
                temp_row = cur.fetchone()
                if temp_row and temp_row["metric_value"] is not None:
                    temp_val = float(temp_row["metric_value"])
                    if temp_val > threshold:
                        firing_keys.add(("snmp_device", did, c_type))
                        trigger_callback(
                            rule, "snmp_device", did, dname, c_type, severity,
                            value_at_trigger=temp_val,
                            threshold_value=threshold,
                            message=f"Temperatura de chasis elevada en {dname}: {temp_val:.1f}°C (> {threshold}°C)."
                        )
