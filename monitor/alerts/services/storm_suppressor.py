# monitor/alerts/services/storm_suppressor.py
import hashlib
import logging
from datetime import datetime, timedelta
from typing import Any

logger = logging.getLogger("alert.engine.storm")

def compute_fingerprint(entity_type: str, entity_id: int | str, condition_type: str) -> str:
    """Genera hash SHA-256 único para deduplicación y control de tormentas."""
    raw = f"{entity_type}:{entity_id}:{condition_type}".encode("utf-8")
    return hashlib.sha256(raw).hexdigest()

class StormSuppressor:
    """Servicio desacoplado para detectar flapping y suprimir tormentas de alertas."""

    @staticmethod
    def check_storm_suppression(
        db: Any,
        fingerprint: str,
        max_per_hour: int = 5,
        cooldown_minutes: int = 30,
    ) -> bool:
        """
        Evalúa si la alerta debe ser suprimida por tormenta (flapping).
        Retorna True si debe suprimirse, False si puede procesarse.
        """
        now = datetime.now()
        now_str = now.strftime("%Y-%m-%d %H:%M:%S")

        with db.cursor() as cur:
            cur.execute("SELECT * FROM alert_storm_suppression WHERE fingerprint = %s", (fingerprint,))
            record = cur.fetchone()

            if not record:
                cur.execute(
                    """
                    INSERT INTO alert_storm_suppression 
                    (fingerprint, alert_count, first_alert_at, last_alert_at, suppressed_count, next_allowed_at)
                    VALUES (%s, 1, %s, %s, 0, NULL)
                    """,
                    (fingerprint, now_str, now_str)
                )
                return False

            next_allowed = record.get("next_allowed_at")
            if next_allowed and next_allowed > now:
                cur.execute(
                    """
                    UPDATE alert_storm_suppression 
                    SET suppressed_count = suppressed_count + 1, last_alert_at = %s
                    WHERE fingerprint = %s
                    """,
                    (now_str, fingerprint)
                )
                return True

            first_alert = record.get("first_alert_at") or now
            if (now - first_alert).total_seconds() > 3600:
                cur.execute(
                    """
                    UPDATE alert_storm_suppression
                    SET alert_count = 1, first_alert_at = %s, last_alert_at = %s, next_allowed_at = NULL
                    WHERE fingerprint = %s
                    """,
                    (now_str, now_str, fingerprint)
                )
                return False

            new_count = (record.get("alert_count") or 0) + 1
            if new_count > max_per_hour:
                cooldown_until = now + timedelta(minutes=cooldown_minutes)
                cooldown_str = cooldown_until.strftime("%Y-%m-%d %H:%M:%S")
                cur.execute(
                    """
                    UPDATE alert_storm_suppression
                    SET alert_count = %s, last_alert_at = %s, suppressed_count = suppressed_count + 1, next_allowed_at = %s
                    WHERE fingerprint = %s
                    """,
                    (new_count, now_str, cooldown_str, fingerprint)
                )
                logger.warning(
                    f"⛈️ Tormenta detectada en fingerprint {fingerprint[:8]}! Suprimiendo por {cooldown_minutes}m hasta {cooldown_str}."
                )
                return True

            cur.execute(
                """
                UPDATE alert_storm_suppression
                SET alert_count = %s, last_alert_at = %s
                WHERE fingerprint = %s
                """,
                (new_count, now_str, fingerprint)
            )
            return False
