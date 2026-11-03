# monitor/gitops/dtos/gitops_dtos.py
from dataclasses import dataclass, field
from typing import Dict, List, Optional, Any

@dataclass(frozen=True)
class UpdateCheckResultDto:
    """Resultado inmutable de la comprobación de actualizaciones remotas."""
    success: bool
    is_locked: bool
    error: str = ""
    has_update: bool = False
    local_hash: str = "N/A"
    remote_hash: str = "N/A"
    current_commit: str = ""
    commits: List[str] = field(default_factory=list)
    files: List[str] = field(default_factory=list)
    has_migrations: bool = False
    route_label: str = "Desconocida"
    message: str = ""

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "success": self.success,
            "is_locked": self.is_locked,
            "error": self.error,
            "has_update": self.has_update,
            "local_hash": self.local_hash,
            "remote_hash": self.remote_hash,
            "current_commit": self.current_commit,
            "commits": self.commits,
            "files": self.files,
            "has_migrations": self.has_migrations,
            "route_label": self.route_label,
            "message": self.message,
        }

@dataclass(frozen=True)
class DeploymentStatusDto:
    """Estado técnico inmutable del pipeline de despliegue y respaldos."""
    commit_hash: str
    commit_msg: str
    commit_date: str
    route_label: str
    is_net_ok: bool
    is_locked: bool
    lock_reason: str
    backups: List[Dict[str, Any]] = field(default_factory=list)

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "commit_hash": self.commit_hash,
            "commit_msg": self.commit_msg,
            "commit_date": self.commit_date,
            "route_label": self.route_label,
            "is_net_ok": self.is_net_ok,
            "is_locked": self.is_locked,
            "lock_reason": self.lock_reason,
            "backups": self.backups,
        }

@dataclass(frozen=True)
class SmokeTestResultDto:
    """Resultado inmutable de las pruebas post-despliegue."""
    passed: bool
    message: str

@dataclass(frozen=True)
class DeploymentManifestDto:
    """Manifiesto inmutable de un despliegue registrado en auditoría."""
    timestamp: str
    prev_commit: str
    new_hash: str
    status: str
    files_changed: int
    duration_seconds: float
    census_before: Dict[str, int] = field(default_factory=dict)
    census_after: Dict[str, int] = field(default_factory=dict)
