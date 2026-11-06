"""Fachada ultradelgada de delegación para replicación de tablas clúster."""
from typing import Any, Dict
from monitor.sync.cluster.replicators import (
    CoreCatalogReplicator, HistoryReplicator, ItamReplicator,
    SecurityAlertsReplicator, SnmpDiscoveryReplicator,
    TelemetryTopologyReplicator, UpsReplicator,
)

_CORE = CoreCatalogReplicator()
_HIST = HistoryReplicator()
_SNMP = SnmpDiscoveryReplicator()
_SEC = SecurityAlertsReplicator()
_TOPO = TelemetryTopologyReplicator()
_UPS = UpsReplicator()
_ITAM = ItamReplicator()


class TableReplicationService:
    """Fachada delegadora de compatibilidad para sincronización de tablas de clúster."""
    replicate_core_catalog = staticmethod(lambda c, d: _CORE.replicate(c, d))
    replicate_histories = staticmethod(lambda c, d: _HIST.replicate(c, d))
    replicate_snmp_discovery = staticmethod(lambda c, d: _SNMP.replicate(c, d))
    replicate_security_and_alerts = staticmethod(lambda c, d: _SEC.replicate(c, d))
    replicate_netradar_and_topology = staticmethod(lambda c, d: _TOPO.replicate(c, d))
    replicate_ups = staticmethod(lambda c, d: _UPS.replicate(c, d))
    replicate_itam = staticmethod(lambda c, d: _ITAM.replicate(c, d))
