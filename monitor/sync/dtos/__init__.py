# monitor/sync/dtos/__init__.py
from monitor.sync.dtos.scan_dtos import (
    WanQualityResult,
    ServiceEvaluationResult,
    SiteDeviceEvaluationResult,
    SiteEvaluationResult,
    ProxyEvaluationResult,
    NetworkDeviceEvaluationResult,
)
from monitor.sync.dtos.cluster_sync_dtos import ClusterTelemetryResponseDto

__all__ = [
    "WanQualityResult",
    "ServiceEvaluationResult",
    "SiteDeviceEvaluationResult",
    "SiteEvaluationResult",
    "ProxyEvaluationResult",
    "NetworkDeviceEvaluationResult",
    "ClusterTelemetryResponseDto",
]
