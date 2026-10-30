# monitor/sync/cluster/datetime_sanitizer.py
from typing import Any, Optional

def clean_mysql_dt(val: Any, date_only: bool = False) -> Optional[str]:
    """Convierte timestamps ISO 8601 (ej: '2026-09-22T18:29:57.000000Z') a formato DATETIME o DATE compatible con MariaDB/MySQL."""
    if not val:
        return None
    s = str(val).strip()
    if not s or s.lower() in ("null", "none"):
        return None
    if "T" in s:
        s = s.replace("T", " ").split(".")[0].rstrip("Z")
    if date_only:
        return s[:10]
    return s[:19] if len(s) >= 19 else s
