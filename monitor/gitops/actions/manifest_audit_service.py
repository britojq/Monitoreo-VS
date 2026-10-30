# monitor/gitops/actions/manifest_audit_service.py
import json
import logging
from pathlib import Path
from typing import Any, Dict, Optional

logger = logging.getLogger("monitor.gitops.audit")

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent
WEB_DIR = BASE_DIR / "web_portal"

class ManifestAuditService:
    """Persistencia de manifiestos y auditoría forense en MariaDB SSOT y disco."""

    @staticmethod
    def save_deployment_audit_manifest(manifest: Dict[str, Any], log_text: str) -> None:
        """Guarda el manifiesto en storage/app y registra el evento en MariaDB audit_logs (SSOT)."""
        try:
            storage_app = WEB_DIR / "storage" / "app"
            if storage_app.exists():
                (storage_app / "last_deployment.json").write_text(
                    json.dumps(manifest, indent=2, ensure_ascii=False),
                    encoding="utf-8"
                )
        except Exception as e:
            logger.warning(f"Error escribiendo last_deployment.json: {e}")

        try:
            from monitor.monitor_db import get_db_connection
            conn = get_db_connection()
            try:
                with conn.cursor() as cur:
                    audit_payload = {
                        "manifest": manifest,
                        "log_text": log_text,
                        "summary": f"Despliegue GitOps status={manifest.get('status', 'success')}"
                    }
                    cur.execute(
                        """
                        INSERT INTO audit_logs (user_name, event, module, description, new_values, ip_address, created_at)
                        VALUES (%s, %s, %s, %s, %s, %s, NOW())
                        """,
                        (
                            "GitOps Deployer",
                            "DEPLOYMENT",
                            "system_updater",
                            f"Despliegue GitOps - Commit {manifest.get('new_hash', manifest.get('prev_commit', 'N/A'))}",
                            json.dumps(audit_payload, ensure_ascii=False),
                            "127.0.0.1"
                        )
                    )
                    conn.commit()
            finally:
                conn.close()
        except Exception as e_db:
            logger.warning(f"Error registrando despliegue en MariaDB audit_logs: {e_db}")

    @staticmethod
    def set_pending_restart_notification(manifest: Dict[str, Any], chat_id: Optional[int] = None) -> None:
        """Guarda metadatos en MariaDB bot_settings para que el bot notifique al Owner tras arrancar con la nueva versión."""
        try:
            from monitor.boot_alert import set_bot_setting
            data = dict(manifest)
            if chat_id:
                data["chat_id"] = chat_id
            set_bot_setting("pending_restart_notification", json.dumps(data, ensure_ascii=False))
        except Exception as e:
            logger.warning(f"Error guardando pending_restart_notification en bot_settings: {e}")
