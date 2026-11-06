# monitor/sync/dtos/cluster_sync_dtos.py
from dataclasses import dataclass
from typing import Dict, Any, Optional

@dataclass(frozen=True)
class ClusterTelemetryResponseDto:
    """Payload inmutable retornado por el API del clúster Master."""
    success: bool
    snapshot: Dict[str, Any]
    config: Dict[str, Any]
    alerts: list
    ups_telemetry_histories: list
    ups_power_events: list
    inventory_components_histories: list
    entities: Dict[str, Any]
    net_radar_hosts: list
    net_radar_events: list
    net_radar_snapshots: list
    snmp_metrics_history: list
    snmp_interface_metrics: list
    snmp_metric_hourly_rollups: list
    snmp_interface_hourly_rollups: list
    snmp_traps_received: list
    syslog_events: list
    netflow_top_talkers: list
    network_topology_links: list
    predictive_anomalies: list
    ssl_certificate_history: list
