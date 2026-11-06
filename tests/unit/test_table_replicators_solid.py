"""
Pruebas Unitarias para la Suite de Replicadores de Clúster Desacoplados (SOLID Fase 4).
Verifica contratos (ISP/DIP), DTOs inmutables, registro polimórfico (OCP),
ejecución de los 7 replicadores y fachada TableReplicationService sin regresiones.
"""
from unittest.mock import MagicMock
import pytest

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.dtos import ReplicationBatchDto, ClusterSyncSummaryDto
from monitor.sync.cluster.replicator_registry import ReplicatorRegistry
from monitor.sync.cluster.replicators import (
    CoreCatalogReplicator,
    HistoryReplicator,
    ItamReplicator,
    SecurityAlertsReplicator,
    SnmpDiscoveryReplicator,
    TelemetryTopologyReplicator,
    UpsReplicator,
)
from monitor.sync.cluster.table_replicators import TableReplicationService


class TestTableReplicatorsSolidArchitecture:
    """Verifica que todos los replicadores cumplan los principios SOLID."""

    def test_replicators_implement_protocol(self):
        """Verifica que los 7 replicadores implementen formalmente TableReplicatorProtocol."""
        replicators = [
            CoreCatalogReplicator(),
            HistoryReplicator(),
            SnmpDiscoveryReplicator(),
            SecurityAlertsReplicator(),
            TelemetryTopologyReplicator(),
            UpsReplicator(),
            ItamReplicator(),
        ]
        for rep in replicators:
            assert isinstance(rep, TableReplicatorProtocol), f"{rep.__class__.__name__} no implementa TableReplicatorProtocol"

    def test_dtos_immutability(self):
        """Verifica que los DTOs sean inmutables (frozen=True)."""
        batch = ReplicationBatchDto(domain="sites", records_processed=5, success=True)
        assert batch.domain == "sites"
        assert batch.records_processed == 5
        assert batch.success is True

        with pytest.raises(Exception):
            batch.records_processed = 10  # type: ignore

        summary = ClusterSyncSummaryDto(total_domains=7, total_records=120, elapsed_seconds=0.45, status="ok")
        assert summary.total_domains == 7
        with pytest.raises(Exception):
            summary.status = "error"  # type: ignore

    def test_replicator_registry_flow(self):
        """Verifica el registro y despacho dinámico (OCP) en ReplicatorRegistry."""
        registry = ReplicatorRegistry()
        mock_rep = MagicMock(spec=TableReplicatorProtocol)
        mock_rep.replicate.return_value = 10

        registry.register("test_domain", mock_rep)
        assert registry.get("test_domain") is mock_rep

        mock_cursor = MagicMock()
        payload = {"data": [1, 2, 3]}
        res = registry.execute_domain("test_domain", mock_cursor, payload)
        assert res.success is True
        assert res.records_processed == 10
        mock_rep.replicate.assert_called_once_with(mock_cursor, payload)

        # Dominio inexistente
        res_none = registry.execute_domain("non_existent", mock_cursor, payload)
        assert res_none.success is False
        assert res_none.records_processed == 0

    def test_core_catalog_replicator_golden_rule_8_remap(self):
        """
        REGLA DE ORO #8: Router Principal ID 1 vs CENCARATIT ID 11519.
        Si el master envía el Router con ID 11519 y 192.168.1.1, debe ser remapeado al ID 1.
        """
        replicator = CoreCatalogReplicator()
        mock_cursor = MagicMock()
        cfg = {
            "sites": [{"id": 1, "name": "Valle Seco", "ip": "192.168.1.1", "is_active": True}],
            "network_devices": [
                {
                    "id": 11519,
                    "name": "ROUTER PRINCIPAL CISCO",
                    "ip": "192.168.1.1",
                    "monitored_site_id": 1,
                    "device_number": 1,
                    "is_active": True,
                }
            ],
            "services": [{"id": 1, "name": "OTRS", "type": "WEB", "is_active": True}],
            "proxies": [{"id": 1, "name": "Proxy 1", "ip_port": "192.168.1.220:8080", "is_active": True}],
        }
        total = replicator.replicate(mock_cursor, cfg)
        assert total == 4
        assert mock_cursor.executemany.call_count == 4

        # Verificar que el registro insertado para el router tenga dev_id == 1
        net_dev_call = mock_cursor.executemany.call_args_list[3]
        records = net_dev_call[0][1]
        assert len(records) == 1
        assert records[0][0] == 1  # ID remapeado a 1
        assert records[0][3] == "ROUTER PRINCIPAL CISCO"

    def test_history_replicator_execution(self):
        """Verifica HistoryReplicator con snapshot de servicios y sedes."""
        replicator = HistoryReplicator()
        mock_cursor = MagicMock()
        data = {
            "snapshot": {
                "services": [{"id": 1, "is_up": True, "latency_ms": 2.5, "status": "200 OK"}],
                "sites": [{"id": 1, "is_up": True, "latency_ms": 5.0, "devices": [{"is_up": True}]}],
                "proxies": [{"id": 1, "is_up": True, "latency_ms": 1.0}],
                "network_devices": [{"id": 1, "is_up": True, "latency_ms": 1.2}],
            },
            "audit_logs": [{"id": 100, "event": "LOGIN", "user_name": "Admin", "created_at": "2026-10-30 12:00:00"}],
        }
        total = replicator.replicate(mock_cursor, data)
        assert total == 5
        assert mock_cursor.executemany.call_count == 5

    def test_ups_replicator_execution(self):
        """Verifica UpsReplicator con dispositivos y telemetría."""
        replicator = UpsReplicator()
        mock_cursor = MagicMock()
        data = {
            "config": {
                "ups_devices": [{"id": 1, "name": "UPS ZTG LV6KL", "serial_number": "UPS-ZTG-6K"}],
            },
            "ups_telemetry_histories": [{"id": 1, "ups_device_id": 1, "input_voltage": 208.0}],
            "ups_power_events": [{"id": 1, "ups_device_id": 1, "event_type": "fluctuation", "started_at": "2026-10-30 12:00:00"}],
        }
        total = replicator.replicate(mock_cursor, data)
        assert total == 3
        assert mock_cursor.executemany.call_count == 3

    def test_snmp_discovery_replicator_execution(self):
        """Verifica SnmpDiscoveryReplicator con subredes y métricas."""
        replicator = SnmpDiscoveryReplicator()
        mock_cursor = MagicMock()
        data = {
            "config": {
                "discovery_subnets": [{"id": 1, "subnet": "192.168.1.0/24"}],
                "discovered_devices": [{"id": 1, "mac_address": "00:11:22:33:44:55", "ip_address": "192.168.1.10"}],
                "snmp_oids": [{"id": 1, "name": "sysUpTime", "oid": "1.3.6.1.2.1.1.3.0"}],
                "snmp_devices": [{"id": 1, "name": "Switch-Core", "ip_address": "192.168.1.2"}],
                "snmp_interfaces": [{"id": 1, "snmp_device_id": 1, "if_index": 1}],
            },
            "snmp_metrics_history": [{"id": 1, "snmp_device_id": 1, "snmp_oid_id": 1, "metric_value": 100}],
            "snmp_interface_metrics": [{"id": 1, "snmp_interface_id": 1, "in_octets": 1024}],
            "snmp_metric_hourly_rollups": [{"id": 1, "snmp_device_id": 1, "snmp_oid_id": 1, "hour_timestamp": "2026-10-30 12:00:00"}],
            "snmp_interface_hourly_rollups": [{"id": 1, "snmp_interface_id": 1, "hour_timestamp": "2026-10-30 12:00:00"}],
        }
        total = replicator.replicate(mock_cursor, data)
        assert total == 9
        assert mock_cursor.executemany.call_count == 9

    def test_security_alerts_replicator_execution(self):
        """Verifica SecurityAlertsReplicator con certificados SSL, alarmas y configuraciones."""
        replicator = SecurityAlertsReplicator()
        mock_cursor = MagicMock()
        mock_cursor.fetchall.return_value = []
        data = {
            "config": {
                "ssl_certificates": [{"id": 1, "domain": "sonar.valleseco.local"}],
                "alert_rules": [{"id": 1, "name": "Ping Down", "entity_type": "site", "condition_type": "packet_loss", "severity": "critical"}],
                "maintenance_windows": [{"id": 1, "title": "Ventana Mantenimiento", "starts_at": "2026-10-30 14:00:00"}],
            },
            "ssl_certificate_history": [{"id": 1, "ssl_certificate_id": 1, "event_type": "renewed"}],
            "alerts": [{"id": 1, "entity_type": "site", "entity_id": 1, "condition_type": "ping", "severity": "high"}],
            "device_configurations": [{"id": 1, "network_device_id": 1, "device_name": "RTR-CORE"}],
            "config_change_logs": [{"id": 1, "device_configuration_id": 1, "change_type": "modified"}],
        }
        total = replicator.replicate(mock_cursor, data)
        assert total == 7
        assert mock_cursor.executemany.call_count == 7

    def test_telemetry_topology_replicator_execution(self):
        """Verifica TelemetryTopologyReplicator con hosts de radar, eventos, syslog y topología."""
        replicator = TelemetryTopologyReplicator()
        mock_cursor = MagicMock()
        data = {
            "net_radar_hosts": [{"id": 1, "ip": "192.168.1.50"}],
            "net_radar_events": [{"id": 1, "host_ip": "192.168.1.50", "event_type": "dns_query"}],
            "net_radar_snapshots": [{"id": 1, "total_hosts": 10}],
            "snmp_traps_received": [{"id": 1, "source_ip": "192.168.1.1", "trap_oid": "1.3.6.1.4.1.9"}],
            "syslog_events": [{"id": 1, "source_ip": "192.168.1.1", "message": "LINK UP"}],
            "netflow_top_talkers": [{"id": 1, "rank_type": "src_ip", "rank_value": "192.168.1.50"}],
            "network_topology_links": [{"id": 1, "source_device_id": 1}],
            "predictive_anomalies": [{"id": 1, "entity_type": "site", "entity_id": 1}],
            "entities": {
                "wol_devices": [{"id": 1, "name": "PC-01", "mac_address": "00:11:22:33:44:55"}],
                "hardware_lifecycle": [{"id": 1, "entity_type": "server", "entity_id": 1}],
            },
        }
        total = replicator.replicate(mock_cursor, data)
        assert total == 10
        assert mock_cursor.executemany.call_count == 10

    def test_itam_replicator_execution(self):
        """Verifica ItamReplicator con activos, componentes, periféricos y asignaciones."""
        replicator = ItamReplicator()
        mock_cursor = MagicMock()
        data = {
            "config": {
                "inventory_assets": [{"id": 1, "name": "Workstation-01", "type": "desktop"}],
                "inventory_components": [{"id": 1, "inventory_asset_id": 1, "type": "ram", "name": "8GB DDR3"}],
                "inventory_peripherals": [{"id": 1, "type": "teclado"}],
                "inventory_assignments": [{"id": 1, "assignment_code": "ASG-001", "inventory_asset_id": 1, "custodian_name": "Funcionario A"}],
                "maintenance_tickets": [{"id": 1, "ticket_code": "TCK-001", "inventory_asset_id": 1, "title": "Limpieza"}],
            },
            "inventory_components_histories": [{"id": 1, "inventory_asset_id": 1, "action": "add", "component_type": "ram"}],
        }
        total = replicator.replicate(mock_cursor, data)
        assert total == 6
        assert mock_cursor.executemany.call_count == 6

    def test_table_replication_service_facade_delegates_correctly(self):
        """Verifica que la fachada TableReplicationService delegue a los replicadores correspondientes."""
        mock_cursor = MagicMock()
        # replicate_core_catalog
        TableReplicationService.replicate_core_catalog(mock_cursor, {"sites": [{"id": 1, "name": "Site A"}]})
        assert mock_cursor.executemany.called
        mock_cursor.reset_mock()

        # replicate_histories
        TableReplicationService.replicate_histories(mock_cursor, {"snapshot": {"services": [{"id": 1, "is_up": True}]}})
        assert mock_cursor.executemany.called
        mock_cursor.reset_mock()

        # replicate_ups
        TableReplicationService.replicate_ups(mock_cursor, {"config": {"ups_devices": [{"id": 1, "name": "UPS-1"}]}})
        assert mock_cursor.executemany.called
        mock_cursor.reset_mock()

        # replicate_snmp_discovery
        TableReplicationService.replicate_snmp_discovery(mock_cursor, {"config": {"snmp_oids": [{"id": 1, "name": "oid1", "oid": "1.2.3"}]}})
        assert mock_cursor.executemany.called
        mock_cursor.reset_mock()

        # replicate_security_and_alerts
        mock_cursor.fetchall.return_value = []
        TableReplicationService.replicate_security_and_alerts(mock_cursor, {"config": {"ssl_certificates": [{"id": 1, "domain": "a.b"}]}})
        assert mock_cursor.executemany.called
        mock_cursor.reset_mock()

        # replicate_netradar_and_topology
        TableReplicationService.replicate_netradar_and_topology(mock_cursor, {"net_radar_hosts": [{"id": 1, "ip": "1.2.3.4"}]})
        assert mock_cursor.executemany.called
        mock_cursor.reset_mock()

        # replicate_itam
        TableReplicationService.replicate_itam(mock_cursor, {"config": {"inventory_assets": [{"id": 1, "name": "PC-1"}]}})
        assert mock_cursor.executemany.called
