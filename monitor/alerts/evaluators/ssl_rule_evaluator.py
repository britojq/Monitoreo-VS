# monitor/alerts/evaluators/ssl_rule_evaluator.py
from datetime import datetime
from typing import Any, Callable, Dict, Optional, Set, Tuple

class SslRuleEvaluator:
    """Evaluador polimórfico para reglas de caducidad y certificados SSL/TLS."""

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
            SELECT id, domain, days_remaining, valid_to, is_active
            FROM ssl_certificates
            WHERE is_active = 1
            """
        )
        certs = cur.fetchall()

        now = datetime.now()
        for c in certs:
            cid = c["id"]
            domain = c["domain"]
            days = c["days_remaining"]
            valid_to = c["valid_to"]

            if c_type == "cert_expired":
                if (valid_to and valid_to < now) or (days is not None and days <= 0):
                    firing_keys.add(("ssl_certificate", cid, c_type))
                    trigger_callback(
                        rule, "ssl_certificate", cid, domain, c_type, severity,
                        value_at_trigger=float(days or 0),
                        message=f"El certificado SSL para {domain} ha EXPIRADO."
                    )

            elif c_type == "cert_expiring" and threshold is not None:
                if days is not None and 0 < days <= threshold:
                    firing_keys.add(("ssl_certificate", cid, c_type))
                    trigger_callback(
                        rule, "ssl_certificate", cid, domain, c_type, severity,
                        value_at_trigger=float(days),
                        threshold_value=threshold,
                        message=f"El certificado SSL para {domain} vence en {days} días (umbral: {int(threshold)}d)."
                    )
