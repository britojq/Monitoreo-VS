# monitor/alerts/services/alert_correlator.py
import logging
from typing import Any, Optional, Tuple

logger = logging.getLogger("alert.engine.correlator")

class AlertCorrelator:
    """Servicio desacoplado de correlación topológica jerárquica padre-hijo."""

    @staticmethod
    def apply_correlation(
        db: Any,
        child_type: str,
        child_id: int,
        severity: str,
    ) -> Tuple[bool, Optional[int], Optional[int], str]:
        """
        Aplica reglas de correlación topológica.
        Retorna: (is_suppressed, correlation_group_id, parent_alert_id, final_severity)
        """
        with db.cursor() as cur:
            sql = """
                SELECT g.id as group_id, g.parent_entity_type, g.parent_entity_id, g.suppression_strategy
                FROM alert_correlation_members m
                JOIN alert_correlation_groups g ON m.correlation_group_id = g.id
                WHERE m.child_entity_type = %s AND m.child_entity_id = %s AND g.is_active = 1
                LIMIT 1
            """
            cur.execute(sql, (child_type, child_id))
            group = cur.fetchone()

            if not group:
                return False, None, None, severity

            group_id = group["group_id"]
            p_type = group["parent_entity_type"]
            p_id = group["parent_entity_id"]
            strategy = group["suppression_strategy"]

            cur.execute(
                """
                SELECT id, severity FROM alerts 
                WHERE entity_type = %s AND entity_id = %s AND status = 'firing'
                ORDER BY id DESC LIMIT 1
                """,
                (p_type, p_id)
            )
            parent_alert = cur.fetchone()

            if not parent_alert:
                return False, group_id, None, severity

            parent_alert_id = parent_alert["id"]

            if strategy in ("suppress_if_parent_down", "suppress_all"):
                logger.info(
                    f"🔗 Correlación aplicada: Suprimiendo alerta de hijo ({child_type} #{child_id}) "
                    f"porque el padre ({p_type} #{p_id}) tiene alerta activa #{parent_alert_id}."
                )
                return True, group_id, parent_alert_id, severity

            elif strategy == "reduce_severity":
                severity_map = {
                    "emergency": "critical",
                    "critical": "warning",
                    "warning": "info",
                    "info": "info"
                }
                new_sev = severity_map.get(severity, "info")
                logger.info(
                    f"🔗 Correlación aplicada: Reduciendo severidad de {severity} a {new_sev} para "
                    f"hijo ({child_type} #{child_id}) por alerta activa en padre #{parent_alert_id}."
                )
                return False, group_id, parent_alert_id, new_sev

        return False, None, None, severity
