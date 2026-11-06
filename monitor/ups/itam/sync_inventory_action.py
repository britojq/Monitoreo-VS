"""
# ==============================================================================
# 🔋 UPS SYNC INVENTORY ACTION: sync_inventory_action.py
# Propagación en cascada de identidad física de UPS hacia ITAM en MariaDB
# Ubicación: /opt/sonar/monitor/ups/itam/sync_inventory_action.py
# ==============================================================================
"""

from __future__ import annotations

import logging
import re
from typing import Optional

logger = logging.getLogger("ups.service")


def sync_ups_to_inventory(conn, device_id: int, model: str, serial_number: Optional[str], serial_port: str) -> None:
    """Propaga en cascada la identidad del UPS hacia inventory_peripherals (Regla de Oro #8)."""
    try:
        with conn.cursor() as cur:
            asset_id = None
            try:
                cur.execute("SELECT inventory_asset_id FROM ups_devices WHERE id = %s", (device_id,))
                row = cur.fetchone()
                asset_id = row.get("inventory_asset_id") if row else None
            except Exception:
                asset_id = None

            if not asset_id:
                try:
                    cur.execute("SELECT id FROM inventory_assets WHERE ip_address = '192.168.1.250' OR name LIKE '%MONITOREO%' LIMIT 1")
                    asset_row = cur.fetchone()
                    if asset_row:
                        asset_id = asset_row["id"]
                        try:
                            cur.execute("UPDATE ups_devices SET inventory_asset_id = %s WHERE id = %s", (asset_id, device_id))
                        except Exception:
                            pass
                except Exception:
                    pass

            if not asset_id:
                return

            clean_model = model or "ZTG LV6KL 6kVA"
            if "222222" in clean_model or re.search(r"(.)\1{4,}", clean_model):
                clean_model = "ZTG LV6KL 6kVA"

            parts = clean_model.split(maxsplit=1)
            brand = "ZTG"
            if len(parts) == 2:
                brand = parts[0]
                clean_model = parts[1]
            elif clean_model.startswith("ZTG"):
                brand = "ZTG"
                clean_model = clean_model.replace("ZTG", "").strip() or "LV6KL 6kVA"

            cur.execute("SELECT id FROM inventory_peripherals WHERE current_asset_id = %s AND type = 'ups' LIMIT 1", (asset_id,))
            periph = cur.fetchone()
            if periph:
                cur.execute(
                    """
                    UPDATE inventory_peripherals
                    SET brand = %s, model = %s, serial_number = COALESCE(%s, serial_number),
                        notes = %s, updated_at = NOW()
                    WHERE id = %s
                    """,
                    (brand, clean_model, serial_number, f"UPS de respaldo eléctrico de rack/servidor conectado por puerto serial {serial_port}", periph["id"])
                )
            else:
                cur.execute("SELECT COUNT(*) as c FROM inventory_peripherals WHERE type = 'ups'")
                cnt = (cur.fetchone() or {}).get("c", 0) + 1
                tag = f"UPS-{cnt:05d}"
                cur.execute(
                    """
                    INSERT INTO inventory_peripherals
                    (asset_tag, type, brand, model, serial_number, status, monitored_site_id, current_asset_id, notes, created_at, updated_at)
                    VALUES (%s, 'ups', %s, %s, %s, 'assigned', 1, %s, %s, NOW(), NOW())
                    """,
                    (tag, brand, clean_model, serial_number, asset_id, f"UPS de respaldo eléctrico de rack/servidor conectado por puerto serial {serial_port}")
                )
            conn.commit()
    except Exception as e:
        logger.warning(f"No se pudo sincronizar en cascada hacia inventory_peripherals: {e}")
