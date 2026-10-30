# monitor/alerts/evaluators/site_rule_evaluator.py
from typing import Any, Callable, Dict, Optional, Set, Tuple

class SiteRuleEvaluator:
    """Evaluador polimórfico para reglas de sedes remotas y enlaces WAN."""

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
            SELECT s.id, s.name, s.ip, s.is_active,
                   h.is_up, h.latency_ms, h.status_message, h.devices_online, h.devices_total
            FROM monitored_sites s
            LEFT JOIN (
                SELECT monitored_site_id, is_up, latency_ms, status_message, devices_online, devices_total
                FROM site_check_histories
                WHERE id IN (SELECT MAX(id) FROM site_check_histories GROUP BY monitored_site_id)
            ) h ON s.id = h.monitored_site_id
            WHERE s.is_active = 1
            """
        )
        sites = cur.fetchall()

        for st in sites:
            sid = st["id"]
            sname = st["name"]
            is_up = st.get("is_up")
            lat = float(st.get("latency_ms") or 0.0)

            if c_type == "is_down":
                if is_up is not None and is_up == 0:
                    firing_keys.add(("site", sid, c_type))
                    trigger_callback(
                        rule, "site", sid, sname, c_type, severity,
                        value_at_trigger=0.0,
                        message=f"La sede WAN {sname} ({st['ip'] or 'N/A'}) no responde o enlace caído."
                    )

            elif c_type == "latency_high" and threshold is not None:
                if is_up == 1 and lat > threshold:
                    firing_keys.add(("site", sid, c_type))
                    trigger_callback(
                        rule, "site", sid, sname, c_type, severity,
                        value_at_trigger=lat,
                        threshold_value=threshold,
                        message=f"Latencia WAN elevada hacia {sname} ({lat:.1f}ms > {threshold}ms)."
                    )
