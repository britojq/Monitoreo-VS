# monitor/sync/scheduler.py
import os
import time
from datetime import datetime
from pathlib import Path
from typing import Tuple

BASE_DIR = Path(__file__).resolve().parent.parent.parent
APP_DIR = BASE_DIR / "web_portal"
SNAPSHOT_FILE = APP_DIR / "storage" / "app" / "public" / "monitoring_snapshot.json"
LAST_SCAN_TIMESTAMP_FILE = Path("/tmp/last_web_sync_timestamp.txt")

def get_last_scan_time() -> float:
    """Obtiene la marca temporal de la última sincronización web ejecutada."""
    if LAST_SCAN_TIMESTAMP_FILE.exists():
        try:
            val = float(LAST_SCAN_TIMESTAMP_FILE.read_text(encoding="utf-8").strip())
            if val > 0:
                return val
        except Exception:
            pass
    if SNAPSHOT_FILE.exists():
        try:
            return SNAPSHOT_FILE.stat().st_mtime
        except Exception:
            pass
    return 0.0

def update_last_scan_time() -> None:
    """Actualiza la marca temporal de la última sincronización web."""
    try:
        LAST_SCAN_TIMESTAMP_FILE.write_text(str(time.time()), encoding="utf-8")
        try:
            os.chmod(LAST_SCAN_TIMESTAMP_FILE, 0o666)
        except Exception:
            pass
    except Exception:
        pass

def should_run_web_scan(force: bool = False) -> Tuple[bool, int, float]:
    """
    Determina si debe ejecutarse el escaneo web respetando el intervalo dinámico configurado.
    Retorna: (debe_ejecutar, intervalo_minutos, tiempo_restante_segundos)
    """
    if force:
        return True, 0, 0.0

    interval_minutes = 10
    try:
        from bot_core.services.config_manager import load_config
        cfg = load_config()
        role = str(cfg.get("node_role", "master")).lower()
        if role in ("slave", "dev"):
            interval_minutes = int(cfg.get("slave_sync_interval_minutes", 2))
        else:
            interval_minutes = int(cfg.get("web_check_interval_minutes", 10))
        if interval_minutes < 1:
            interval_minutes = 1
    except Exception:
        pass

    interval_seconds = interval_minutes * 60
    last_time = get_last_scan_time()
    if last_time <= 0:
        return True, interval_minutes, 0.0

    elapsed = time.time() - last_time
    # Margen de tolerancia de 5 segundos para sincronizar con cron de 1 minuto
    if elapsed >= (interval_seconds - 5):
        return True, interval_minutes, 0.0

    remaining = interval_seconds - elapsed
    return False, interval_minutes, max(0.0, remaining)

def update_cluster_status(status: str) -> None:
    """Actualiza el estado de sincronización del clúster en bot_settings."""
    try:
        from bot_core.services.config_manager import save_config
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        save_config({
            "cluster_last_sync_status": status,
            "cluster_last_sync_at": now_str,
        })
    except Exception:
        pass
