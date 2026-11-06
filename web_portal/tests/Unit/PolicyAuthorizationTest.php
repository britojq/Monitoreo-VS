<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\BotMessageTemplate;
use App\Models\DiscoveredDevice;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\SnmpDevice;
use App\Models\SslCertificate;
use App\Models\UpsDevice;
use App\Models\User;
use App\Policies\AdminAdvancedSettingsPolicy;
use App\Policies\AlertPolicy;
use App\Policies\BotTemplatePolicy;
use App\Policies\DiscoveryPolicy;
use App\Policies\InventoryAssetPolicy;
use App\Policies\NetRadarPolicy;
use App\Policies\NetworkDevicePolicy;
use App\Policies\ServicePolicy;
use App\Policies\SitePolicy;
use App\Policies\SnmpDevicePolicy;
use App\Policies\SslPolicy;
use App\Policies\UpsPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    private function createAdminUser(): User
    {
        $user = new User();
        $user->id = 1;
        $user->name = 'Admin Test';
        $user->email = 'admin@example.com';
        $user->role = 'admin';
        return $user;
    }

    private function createOperatorUser(array $permissions = []): User
    {
        $user = new User();
        $user->id = 2;
        $user->name = 'Operator Test';
        $user->email = 'operator@example.com';
        $user->role = 'operator';
        $user->permissions = $permissions;
        return $user;
    }

    public function test_alert_policy_allows_admin_all_abilities(): void
    {
        $admin = $this->createAdminUser();
        $policy = new AlertPolicy();

        $this->assertTrue($policy->view($admin));
        $this->assertTrue($policy->acknowledge($admin));
        $this->assertTrue($policy->silence($admin));
        $this->assertTrue($policy->resolve($admin));
        $this->assertTrue($policy->evaluate($admin));
        $this->assertTrue($policy->manageRules($admin));
        $this->assertTrue($policy->manageMaintenance($admin));
    }

    public function test_alert_policy_blocks_operator_from_resolving(): void
    {
        $operator = $this->createOperatorUser(['alerts.view', 'alerts.ack']);
        $policy = new AlertPolicy();

        $this->assertTrue($policy->view($operator));
        $this->assertTrue($policy->acknowledge($operator));
        $this->assertFalse($policy->resolve($operator));
        $this->assertFalse($policy->manageRules($operator));
    }

    public function test_ups_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['ups.view']);
        $policy = new UpsPolicy();

        $this->assertTrue($policy->view($admin));
        $this->assertTrue($policy->manage($admin));

        $this->assertTrue($policy->view($operator));
        $this->assertFalse($policy->manage($operator));
    }

    public function test_netradar_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['netradar.view', 'netradar.export']);
        $policy = new NetRadarPolicy();

        $this->assertTrue($policy->view($admin));
        $this->assertTrue($policy->scan($admin));
        $this->assertTrue($policy->export($admin));

        $this->assertTrue($policy->view($operator));
        $this->assertTrue($policy->export($operator));
        $this->assertFalse($policy->scan($operator));
    }

    public function test_ssl_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['ssl.view']);
        $policy = new SslPolicy();

        $this->assertTrue($policy->view($admin));
        $this->assertTrue($policy->manage($admin));
        $this->assertTrue($policy->recheck($admin));

        $this->assertTrue($policy->view($operator));
        $this->assertFalse($policy->manage($operator));
        $this->assertFalse($policy->recheck($operator));
    }

    public function test_discovery_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['discovery.view']);
        $policy = new DiscoveryPolicy();

        $this->assertTrue($policy->view($admin));
        $this->assertTrue($policy->scan($admin));
        $this->assertTrue($policy->authorize($admin));

        $this->assertTrue($policy->view($operator));
        $this->assertFalse($policy->scan($operator));
        $this->assertFalse($policy->authorize($operator));
    }

    public function test_user_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['security.users']);
        $policy = new UserPolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->managePermissions($admin));

        $this->assertTrue($policy->viewAny($operator));
        $this->assertTrue($policy->create($operator));
        $this->assertFalse($policy->managePermissions($operator));
    }

    public function test_site_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['infra.view']);
        $policy = new SitePolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));

        $this->assertTrue($policy->viewAny($operator));
        $this->assertFalse($policy->create($operator));
    }

    public function test_network_device_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['infra.view']);
        $policy = new NetworkDevicePolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));

        $this->assertTrue($policy->viewAny($operator));
        $this->assertFalse($policy->create($operator));
    }

    public function test_service_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['infra.view']);
        $policy = new ServicePolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));

        $this->assertTrue($policy->viewAny($operator));
        $this->assertFalse($policy->create($operator));
    }

    public function test_bot_template_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['telegram.dispatch']);
        $policy = new BotTemplatePolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->update($admin));

        $this->assertTrue($policy->viewAny($operator));
        $this->assertFalse($policy->update($operator));
    }

    public function test_inventory_asset_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['inventory.view']);
        $policy = new InventoryAssetPolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));

        $this->assertTrue($policy->viewAny($operator));
        $this->assertFalse($policy->create($operator));
    }

    public function test_snmp_device_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser(['snmp.view']);
        $policy = new SnmpDevicePolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->poll($admin));

        $this->assertTrue($policy->viewAny($operator));
        $this->assertFalse($policy->create($operator));
        $this->assertFalse($policy->poll($operator));
    }

    public function test_admin_advanced_settings_policy_authorization(): void
    {
        $admin = $this->createAdminUser();
        $operator = $this->createOperatorUser();
        $policy = new AdminAdvancedSettingsPolicy();

        $this->assertTrue($policy->view($admin));
        $this->assertTrue($policy->manage($admin));

        $this->assertFalse($policy->view($operator));
        $this->assertFalse($policy->manage($operator));
    }

    public function test_gate_definitions(): void
    {
        $admin = $this->createAdminUser();

        $this->assertTrue(Gate::forUser($admin)->allows('alert.resolve'));
        $this->assertTrue(Gate::forUser($admin)->allows('netradar.scan'));
        $this->assertTrue(Gate::forUser($admin)->allows('ups.manage'));
        $this->assertTrue(Gate::forUser($admin)->allows('ssl.manage'));
        $this->assertTrue(Gate::forUser($admin)->allows('discovery.authorize'));
        $this->assertTrue(Gate::forUser($admin)->allows('advanced.view'));
        $this->assertTrue(Gate::forUser($admin)->allows('advanced.manage'));
        $this->assertTrue(Gate::forUser($admin)->allows('snmp.poll'));
    }
}
