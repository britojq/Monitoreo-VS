"""
Motor de cálculo de diferencias unificadas (diff estilo Git) para configuraciones.
"""
import difflib
from typing import Any, Dict, Optional, Tuple

from monitor.infrastructure.db_context import db_cursor


def generate_unified_diff(
    old_text: str,
    new_text: str,
    label_old: str = "Versión Previa",
    label_new: str = "Versión Actual"
) -> Tuple[str, int, int]:
    """Genera un diff unificado estilo git y computa líneas añadidas y removidas."""
    old_lines = old_text.splitlines(keepends=True)
    new_lines = new_text.splitlines(keepends=True)

    diff = list(difflib.unified_diff(
        old_lines,
        new_lines,
        fromfile=label_old,
        tofile=label_new,
        lineterm=""
    ))

    diff_str = "\n".join(diff)
    lines_added = sum(1 for line in diff if line.startswith("+") and not line.startswith("+++"))
    lines_removed = sum(1 for line in diff if line.startswith("-") and not line.startswith("---"))
    return diff_str, lines_added, lines_removed


def get_diff_for_configuration(config_id: int) -> Optional[Dict[str, Any]]:
    """Obtiene el diff unificado asociado a una configuración específica desde MariaDB."""
    with db_cursor() as cur:
        cur.execute(
            """
            SELECT l.*, c.device_name, c.device_ip, c.captured_at,
                   p.captured_at as previous_captured_at
            FROM config_change_logs l
            JOIN device_configurations c ON c.id = l.device_configuration_id
            LEFT JOIN device_configurations p ON p.id = l.previous_config_id
            WHERE l.device_configuration_id = %s
            LIMIT 1
            """,
            (config_id,)
        )
        return cur.fetchone()
