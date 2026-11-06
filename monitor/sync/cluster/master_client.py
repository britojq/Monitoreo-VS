# monitor/sync/cluster/master_client.py
import hashlib
import hmac
import logging
import time
from typing import Any, Dict, Optional, Tuple
import httpx

logger = logging.getLogger("monitor.sync.cluster")

class MasterClusterClient:
    """Cliente HTTP desacoplado para telemetría y sincronización desde el nodo Master."""

    def __init__(self, master_url: str = "http://192.168.1.250", cluster_token: str = ""):
        """Inicializa la instancia con sus dependencias y configuración operativa.

        Args:
            master_url: Parámetro operativo de entrada.
            cluster_token: Parámetro operativo de entrada.
        """
        self.master_url = master_url.rstrip("/")
        self.cluster_token = cluster_token

    @classmethod
    def from_config(cls) -> "MasterClusterClient":
        """Instancia el cliente leyendo la configuración desde MariaDB SSOT."""
        master_url = "http://192.168.1.250"
        cluster_token = ""
        try:
            from bot_core.services.config_manager import load_config
            cfg = load_config()
            master_url = cfg.get("master_api_url", cfg.get("cluster_master_api_url", "http://192.168.1.250"))
            cluster_token = cfg.get("cluster_token", "")
        except Exception:
            pass
        return cls(master_url=master_url, cluster_token=cluster_token)

    async def fetch_telemetry(self, timeout: float = 10.0) -> Tuple[bool, Optional[Dict[str, Any]]]:
        """Obtiene el snapshot y catálogo del nodo Master vía /api/cluster/telemetry."""
        timestamp = str(int(time.time()))
        signature = hmac.new(
            self.cluster_token.encode("utf-8"),
            f"{timestamp}:".encode("utf-8"),
            hashlib.sha256
        ).hexdigest()

        headers = {
            "X-Cluster-Token": self.cluster_token,
            "X-Cluster-Timestamp": timestamp,
            "X-Cluster-Signature": signature,
            "Accept": "application/json",
            "Accept-Encoding": "gzip",
            "User-Agent": "ATIT-ValleSeco-ClusterSync/1.0",
        }

        try:
            async with httpx.AsyncClient(timeout=timeout, verify=False) as client:
                resp = await client.get(f"{self.master_url}/api/cluster/telemetry", headers=headers)
                if resp.status_code != 200:
                    logger.warning(f"Fallo al sincronizar con Master ({self.master_url}): HTTP {resp.status_code}")
                    return False, None

                data = resp.json()
                if not data.get("success") or not data.get("snapshot"):
                    logger.warning(f"Respuesta inválida del Master ({self.master_url})")
                    return False, None

                return True, data
        except Exception as e:
            logger.warning(f"Error de conexión con Master ({self.master_url}): {e}")
            return False, None
