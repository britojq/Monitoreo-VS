# monitor/gitops/services/update_lock_service.py
import logging
from datetime import datetime
from pathlib import Path
from typing import Tuple

logger = logging.getLogger("monitor.gitops.lock")

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent
LOCK_FILE = BASE_DIR / ".update_lock"

class UpdateLockService:
    """Gestiona el Circuit Breaker para evitar despliegues en cascada ante errores."""

    @staticmethod
    def is_locked() -> Tuple[bool, str]:
        """Comprueba si el pipeline de despliegue se encuentra bloqueado por un error previo."""
        if LOCK_FILE.exists():
            try:
                content = LOCK_FILE.read_text(encoding="utf-8").strip()
                return True, content
            except Exception:
                return True, "Bloqueo activo por fallo previo."
        return False, ""

    @staticmethod
    def set_lock(reason: str, error_details: str = "") -> None:
        """Activa el bloqueo de seguridad para impedir futuros despliegues hasta resolución."""
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        content = f"Fecha: {now_str}\nMotivo: {reason}\n"
        if error_details:
            content += f"\nDetalle:\n{error_details}\n"
        try:
            LOCK_FILE.write_text(content, encoding="utf-8")
            logger.error(f"🔒 Circuit Breaker activado: {reason}")
        except Exception as e:
            logger.error(f"Error escribiendo archivo de bloqueo: {e}")

    @staticmethod
    def clear_lock() -> bool:
        """Retira el bloqueo de seguridad del pipeline."""
        if LOCK_FILE.exists():
            try:
                LOCK_FILE.unlink()
                logger.info("🔓 Circuit Breaker liberado. Pipeline de despliegue habilitado.")
                return True
            except Exception as e:
                logger.error(f"Error al eliminar lockfile: {e}")
                return False
        return True
