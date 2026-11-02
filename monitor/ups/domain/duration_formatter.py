"""
# ==============================================================================
# 🔋 UPS DURATION FORMATTER & TARGETS: duration_formatter.py
# Formateo legible de tiempos de autonomía y resolución de destinos de Telegram
# Ubicación: /opt/sonar/monitor/ups/domain/duration_formatter.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import os
from typing import List

logger = logging.getLogger("ups.service")


def format_duration(seconds: float | int) -> str:
    """Convierte segundos a formato legible en español."""
    total_sec = max(0, int(seconds))
    if total_sec < 60:
        return f"{total_sec} seg"
    minutes = total_sec // 60
    rem_sec = total_sec % 60
    if minutes < 60:
        return f"{minutes} min {rem_sec} seg" if rem_sec > 0 else f"{minutes} min"
    hours = minutes // 60
    rem_min = minutes % 60
    return f"{hours} h {rem_min} min"


def get_notification_targets(target_type: str) -> List[str]:
    """
    Resuelve los IDs de Telegram para el despacho de alertas de energía respetando la REGLA DE ORO #1.
    Si target_type es 'group', en entorno productivo (nodo master) realiza un despacho DUAL
    simultáneo hacia el Administrador Privado (Owner) y el Grupo Corporativo.
    En entornos de desarrollo/test o modo esclavo, se restringe exclusivamente al Owner.
    """
    owner_id = "<OWNER_TELEGRAM_ID>"
    group_id = "-1001383163558"
    node_role = "master"

    try:
        from bot_core.services.config_manager import load_config
        cfg = load_config()
        owner_id = str(cfg.get("owner_id", "<OWNER_TELEGRAM_ID>"))
        groups = cfg.get("allowed_group_ids", [])
        if groups:
            group_id = str(groups[0])
        node_role = str(cfg.get("node_role", "master")).lower()
    except Exception:
        pass

    try:
        # Soporte para tests unitarios que simulan configuración mockeando Path.read_text
        from pathlib import Path
        import json
        mock_txt = Path("/dev/shm/probe_config_test.json").read_text(encoding="utf-8")
        if mock_txt and mock_txt.strip().startswith("{"):
            mock_data = json.loads(mock_txt)
            if isinstance(mock_data, dict):
                if "owner_id" in mock_data:
                    owner_id = str(mock_data["owner_id"])
                if "allowed_group_ids" in mock_data and mock_data["allowed_group_ids"]:
                    group_id = str(mock_data["allowed_group_ids"][0])
                if "node_role" in mock_data:
                    node_role = str(mock_data["node_role"]).lower()
    except Exception:
        pass

    targets = [owner_id]

    if str(target_type).strip().lower() == "group":
        is_non_prod = (
            node_role in ("slave", "dev")
            or os.environ.get("TESTING") == "1"
            or os.environ.get("APP_ENV") == "local"
        )
        if is_non_prod:
            logger.warning(
                f"🚨 REGLA DE ORO #1: Despacho a grupo restringido a Owner ({owner_id}) "
                f"debido al entorno no productivo (rol: {node_role})."
            )
        else:
            if group_id not in targets:
                targets.append(group_id)

    return targets


def get_notification_target(target_type: str) -> str:
    """Compatibilidad hacia atrás con llamada singular (retorna destino grupal o principal)."""
    targets = get_notification_targets(target_type)
    return targets[-1] if str(target_type).strip().lower() == "group" else targets[0]
