# monitor/alerts/evaluators/service_rule_evaluator.py
from typing import Any, Callable, Dict, Optional, Set, Tuple

class ServiceRuleEvaluator:
    """Evaluador polimórfico para reglas de aplicativos y servicios web."""

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
        cur.execute(
            """
            SELECT s.id, s.name, s.web_url, s.is_active,
                   h.is_up, h.latency_ms, h.status_message
            FROM monitored_services s
            LEFT JOIN (
                SELECT monitored_service_id, is_up, latency_ms, status_message
                FROM service_check_histories
                WHERE id IN (SELECT MAX(id) FROM service_check_histories GROUP BY monitored_service_id)
            ) h ON s.id = h.monitored_service_id
            WHERE s.is_active = 1
            """
        )
        services = cur.fetchall()

        for s in services:
            sid = s["id"]
            sname = s["name"]
            is_up = s.get("is_up")
            lat = float(s.get("latency_ms") or 0.0)

            if c_type == "is_down":
                if is_up is not None and is_up == 0:
                    firing_keys.add(("service", sid, c_type))
                    trigger_callback(
                        rule, "service", sid, sname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"El servicio {sname} ({s['web_url'] or 'N/A'}) no responde o se encuentra en estado DOWN."
                    )

            elif c_type == "latency_high" and threshold is not None:
                if is_up == 1 and lat > threshold:
                    firing_keys.add(("service", sid, c_type))
                    trigger_callback(
                        rule, "service", sid, sname, c_type, severity,
                        value_at_trigger=lat,
                        threshold_value=threshold,
                        message=f"La latencia de {sname} es elevada ({lat:.1f}ms > {threshold}ms)."
                    )
