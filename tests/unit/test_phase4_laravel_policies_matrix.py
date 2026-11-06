"""
# ==============================================================================
# 🧪 SUITE UNITARIA FASE 4: test_phase4_laravel_policies_matrix.py
# Validación rigurosa de SEC-003 (Auditoría v5.51.70).
# - Existencia y estricto tipado de la matriz de Políticas de Autorización Laravel.
# - Registro en AuthServiceProvider con Gate::before para administradores.
# - Inyección de $this->authorize() en controladores de la superficie administrativa.
# ==============================================================================
"""

from pathlib import Path
import pytest

WEB_PORTAL_DIR = Path(__file__).resolve().parent.parent.parent / "web_portal"
POLICIES_DIR = WEB_PORTAL_DIR / "app" / "Policies"
CONTROLLERS_DIR = WEB_PORTAL_DIR / "app" / "Http" / "Controllers" / "Admin"
AUTH_PROVIDER = WEB_PORTAL_DIR / "app" / "Providers" / "AuthServiceProvider.php"


class TestPhase4LaravelPoliciesMatrix:
    """Verifica el cumplimiento de SEC-003: Matriz de Políticas y Autorización Web."""

    def test_all_required_policies_exist_with_strict_types(self):
        """Certifica que cada recurso administrativo cuente con su Policy dedicada."""
        expected_policies = [
            "AlertPolicy.php",
            "DiscoveryPolicy.php",
            "NetRadarPolicy.php",
            "SslPolicy.php",
            "UpsPolicy.php",
            "UserPolicy.php",
            "SitePolicy.php",
            "NetworkDevicePolicy.php",
            "ServicePolicy.php",
            "BotTemplatePolicy.php",
            "InventoryAssetPolicy.php",
            "SnmpDevicePolicy.php",
            "AdminAdvancedSettingsPolicy.php",
        ]

        for policy_name in expected_policies:
            policy_path = POLICIES_DIR / policy_name
            assert policy_path.exists(), f"Falta política requerida: {policy_name}"

            content = policy_path.read_text(encoding="utf-8")
            assert "declare(strict_types=1);" in content, f"Falta strict_types en {policy_name}"
            assert "class " in content, f"Definición de clase ausente en {policy_name}"
            assert "before(User $user, string $ability)" in content, f"Método before() ausente en {policy_name}"

    def test_auth_service_provider_registers_policies_and_gate_before(self):
        """Certifica que AuthServiceProvider registre todas las políticas y el Gate::before."""
        assert AUTH_PROVIDER.exists(), "AuthServiceProvider no existe"
        content = AUTH_PROVIDER.read_text(encoding="utf-8")

        # Registro de modelos en $policies
        assert "User::class => UserPolicy::class" in content
        assert "MonitoredSite::class => SitePolicy::class" in content
        assert "MonitoredNetworkDevice::class => NetworkDevicePolicy::class" in content
        assert "MonitoredService::class => ServicePolicy::class" in content
        assert "BotMessageTemplate::class => BotTemplatePolicy::class" in content
        assert "InventoryAsset::class => InventoryAssetPolicy::class" in content
        assert "SnmpDevice::class => SnmpDevicePolicy::class" in content

        # Gate::before global para admins
        assert "Gate::before" in content
        assert "$user->isAdmin()" in content

        # Gates complementarios
        assert "Gate::define('advanced.view'" in content
        assert "Gate::define('advanced.manage'" in content
        assert "Gate::define('snmp.poll'" in content

    def test_controllers_invoke_authorization_checks(self):
        """Certifica que los controladores de la superficie administrativa usen $this->authorize()."""
        controllers_to_check = {
            "AdminUserController.php": ["authorize('viewAny', User::class)", "authorize('create', User::class)"],
            "AdminSiteController.php": ["authorize('viewAny', MonitoredSite::class)", "authorize('create', MonitoredSite::class)"],
            "AdminNetworkDeviceController.php": ["authorize('viewAny', MonitoredNetworkDevice::class)", "authorize('create', MonitoredNetworkDevice::class)"],
            "AdminServiceController.php": ["authorize('viewAny', MonitoredService::class)", "authorize('create', MonitoredService::class)"],
            "AdminBotTemplateController.php": ["authorize('viewAny', BotMessageTemplate::class)", "authorize('update', $template)"],
            "AdminSnmpController.php": ["authorize('view', SnmpDevice::class)", "authorize('create', SnmpDevice::class)"],
            "InventoryAssetController.php": ["authorize('viewAny', InventoryAsset::class)", "authorize('create', InventoryAsset::class)"],
            "AdminAdvancedSettingsController.php": ["authorize('advanced.view')", "authorize('advanced.manage')"],
        }

        for controller_file, required_snippets in controllers_to_check.items():
            ctrl_path = CONTROLLERS_DIR / controller_file
            assert ctrl_path.exists(), f"Controlador no encontrado: {controller_file}"
            content = ctrl_path.read_text(encoding="utf-8")
            for snippet in required_snippets:
                assert snippet in content, f"Snippet '{snippet}' no encontrado en {controller_file}"
