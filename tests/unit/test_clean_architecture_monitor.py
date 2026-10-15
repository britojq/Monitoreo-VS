"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS: ARQUITECTURA LIMPIA Y SOLID EN PYTHON
# test_clean_architecture_monitor.py
# Valida entidades inmutables, contratos, servicios de dominio y casos de uso.
# ==============================================================================
"""

import pytest
from unittest.mock import AsyncMock, MagicMock

from monitor.domain.entities import (
    SiteEntity,
    EquipmentEntity,
    ServiceEntity,
    NetworkDeviceEntity,
    AlertEntity,
    UpsStatusEntity,
)
from monitor.domain.exceptions import (
    DomainException,
    EntityNotFoundException,
    DatabaseConnectionException,
)
from monitor.domain.services import (
    format_duration,
    evaluate_state_transition,
    build_service_alert_message,
    build_device_alert_message,
    resolve_safe_target,
    calculate_battery_percentage,
    is_spurious_transient,
)
from monitor.application.dtos import (
    CheckResultDTO,
    SiteCheckResultDTO,
    ServiceCheckResultDTO,
    SnapshotDTO,
    AlertNotificationDTO,
)
from monitor.domain.protocols import (
    SiteRepositoryProtocol,
    ServiceRepositoryProtocol,
    SnapshotRepositoryProtocol,
    TelegramNotifierProtocol,
)
from monitor.application.use_cases import (
    CheckAllSitesUseCase,
    CheckAllServicesUseCase,
    SyncTelemetrySnapshotUseCase,
    ProcessUpsTelemetryUseCase,
)
from monitor.service_provider import ServiceProvider


class TestCleanArchitectureDomainEntities:
    """Verifica que las entidades de dominio sean inmutables y cumplan con los contratos."""

    def test_site_entity_immutability(self):
        eq = EquipmentEntity(
            id=1,
            name="Router",
            ip_host="10.20.23.1",
            site_letter="A",
            num=1,
        )
        site = SiteEntity(
            id=10,
            name="CIAU Principal",
            letter="A",
            ip_host="10.20.23.1",
            equipment=(eq,),
        )
        assert site.display_name == "[A] CIAU Principal"
        assert eq.identifier == "A1"

        # Verificar que es inmutable (@dataclass(frozen=True))
        with pytest.raises(Exception):
            site.name = "Otro Nombre"  # type: ignore

    def test_network_device_entity_identity_rule_7(self):
        """Verifica el cumplimiento de la Regla de Oro #7 (Nombre Etiqueta vs Hostname)."""
        dev = NetworkDeviceEntity(
            id=5,
            label_name="JEFE DE OFICINA",  # Nombre Etiqueta (SSOT)
            ip_address="10.20.23.100",
            hostname="CENCARCOM012010",    # Nombre de Equipo (Secundario)
        )
        assert dev.display_identity == "JEFE DE OFICINA"
        assert dev.hostname == "CENCARCOM012010"

    def test_alert_entity_security_check(self):
        """Verifica la detección de alertas de seguridad."""
        alert_sec = AlertEntity(
            id=1,
            event_type="LOGIN",
            severity="WARNING",
            title="Acceso Detectado",
            message="Login desde 10.20.23.50",
            target_identifier="admin",
        )
        assert alert_sec.is_security_event is True

        alert_svc = AlertEntity(
            id=2,
            event_type="SERVICE_DOWN",
            severity="CRITICAL",
            title="Caída de Servicio",
            message="DNS no responde",
            target_identifier="DNS",
        )
        assert alert_svc.is_security_event is False


class TestCleanArchitectureDomainServices:
    """Verifica la pureza matemática y lógica de los servicios de dominio."""

    def test_ups_battery_calculation(self):
        assert calculate_battery_percentage(2.25) == 100
        assert calculate_battery_percentage(1.75) == 0
        assert calculate_battery_percentage(2.00) == 50

    def test_format_duration(self):
        assert format_duration(30) == "30 seg"
        assert format_duration(125) == "2 min 5 seg"
        assert format_duration(3665) == "1 h 1 min"

    def test_spurious_transient_threshold(self):
        assert is_spurious_transient(5.0, min_duration=8.0) is True
        assert is_spurious_transient(12.0, min_duration=8.0) is False

    def test_state_transitions(self):
        # Inicialización
        notify, is_rec = evaluate_state_transition(True, None)
        assert notify is False

        # Caída (UP -> DOWN)
        notify, is_rec = evaluate_state_transition(False, True)
        assert notify is True
        assert is_rec is False

        # Recuperación (DOWN -> UP)
        notify, is_rec = evaluate_state_transition(True, False)
        assert notify is True
        assert is_rec is True

        # Sin cambio
        notify, is_rec = evaluate_state_transition(True, True)
        assert notify is False

    def test_safe_target_golden_rule_1(self):
        """Regla de Oro #1: En modo no productivo o esclavo, jamás despachar a grupo."""
        target = resolve_safe_target(
            target_type="group",
            node_role="slave",
            owner_id="38914901",
            group_id="-1001383163558",
            is_non_prod=True,
        )
        assert target == "38914901"

        target_prod = resolve_safe_target(
            target_type="group",
            node_role="master",
            owner_id="38914901",
            group_id="-1001383163558",
            is_non_prod=False,
        )
        assert target_prod == "-1001383163558"


class TestCleanArchitectureNotificationDTO:
    """Verifica la seguridad del DTO de notificación."""

    def test_security_alert_blocks_negative_group_id(self):
        dto_group_security = AlertNotificationDTO(
            recipient="-1001383163558",
            message="Alerta de Intrusión PAM",
            is_security_alert=True,
        )
        assert dto_group_security.is_safe_for_groups() is False

        dto_owner_security = AlertNotificationDTO(
            recipient="38914901",
            message="Alerta de Intrusión PAM",
            is_security_alert=True,
        )
        assert dto_owner_security.is_safe_for_groups() is True


class TestCleanArchitectureUseCases:
    """Verifica la orquestación de casos de uso con inversión de dependencias."""

    @pytest.mark.anyio
    async def test_check_all_sites_use_case(self):
        mock_repo = MagicMock(spec=SiteRepositoryProtocol)
        mock_repo.get_all_active.return_value = [
            SiteEntity(
                id=1,
                name="Sede A",
                letter="A",
                ip_host="127.0.0.1",
                equipment=(
                    EquipmentEntity(id=1, name="Sw1", ip_host="127.0.0.1", site_letter="A", num=1),
                ),
            )
        ]

        use_case = CheckAllSitesUseCase(site_repo=mock_repo)
        results, logs, elapsed = await use_case.execute()

        assert len(results) == 1
        assert results[0].site_letter == "A"
        assert len(results[0].equipment_results) == 1
        assert elapsed >= 0.0

    @pytest.mark.anyio
    async def test_process_ups_telemetry_use_case_suppresses_transient(self):
        mock_notifier = MagicMock(spec=TelegramNotifierProtocol)
        mock_notifier.send_message = AsyncMock(return_value=True)

        use_case = ProcessUpsTelemetryUseCase(notifier=mock_notifier)
        ups = UpsStatusEntity(
            status="OB",
            input_voltage=0.0,
            output_voltage=120.0,
            battery_percentage=95.0,
            load_percentage=20.0,
            is_on_battery=True,
        )

        # Micro-fluctuación de 4 segundos (<8s) debe ser suprimida
        sent = await use_case.notify_outage("38914901", ups, duration_sec=4.0)
        assert sent is False
        mock_notifier.send_message.assert_not_called()

        # Corte prolongado de 15 segundos debe despacharse
        sent = await use_case.notify_outage("38914901", ups, duration_sec=15.0)
        assert sent is True
        mock_notifier.send_message.assert_called_once()

    def test_sync_snapshot_use_case(self):
        mock_snapshot_repo = MagicMock(spec=SnapshotRepositoryProtocol)
        mock_snapshot_repo.save_snapshot.return_value = 42

        use_case = SyncTelemetrySnapshotUseCase(snapshot_repo=mock_snapshot_repo)
        snap_id = use_case.execute(
            total_sites=10,
            up_sites=10,
            down_sites=0,
            total_services=5,
            up_services=5,
            down_services=0,
            total_devices=20,
            up_devices=20,
            down_devices=0,
            payload_json={"status": "all_ok"},
        )
        assert snap_id == 42
        mock_snapshot_repo.save_snapshot.assert_called_once()

    def test_container_dependency_wiring(self):
        app_services = ServiceProvider()
        assert app_services.site_repository is not None
        assert app_services.service_repository is not None
        assert app_services.network_device_repository is not None
        assert app_services.snapshot_repository is not None
        assert app_services.telegram_notifier is not None
        assert app_services.get_check_all_sites_use_case() is not None

    def test_functional_dependency_factories(self):
        from monitor.dependencies import (
            get_site_repository,
            get_service_repository,
            get_network_device_repository,
            get_snapshot_repository,
            get_telemetry_repository,
            get_telegram_notifier,
            get_screenshot_generator,
            get_check_all_sites_use_case,
            get_check_all_services_use_case,
            get_sync_telemetry_snapshot_use_case,
            get_process_ups_telemetry_use_case,
        )
        assert get_site_repository() is not None
        assert get_service_repository() is not None
        assert get_network_device_repository() is not None
        assert get_snapshot_repository() is not None
        assert get_telemetry_repository() is not None
        assert get_telegram_notifier() is not None
        assert get_screenshot_generator() is not None
        assert get_check_all_sites_use_case() is not None
        assert get_check_all_services_use_case() is not None
        assert get_sync_telemetry_snapshot_use_case() is not None
        assert get_process_ups_telemetry_use_case() is not None
