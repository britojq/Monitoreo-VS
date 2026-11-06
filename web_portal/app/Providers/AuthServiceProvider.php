<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Alert;
use App\Models\AlertRule;
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
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * El mapa de políticas para la aplicación (OWASP A01:2021).
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Alert::class => AlertPolicy::class,
        AlertRule::class => AlertPolicy::class,
        UpsDevice::class => UpsPolicy::class,
        SslCertificate::class => SslPolicy::class,
        DiscoveredDevice::class => DiscoveryPolicy::class,
        User::class => UserPolicy::class,
        MonitoredSite::class => SitePolicy::class,
        MonitoredNetworkDevice::class => NetworkDevicePolicy::class,
        MonitoredService::class => ServicePolicy::class,
        BotMessageTemplate::class => BotTemplatePolicy::class,
        InventoryAsset::class => InventoryAssetPolicy::class,
        SnmpDevice::class => SnmpDevicePolicy::class,
    ];

    /**
     * Registra servicios de autenticación y autorización.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Acceso global para Administradores (SuperAdmin)
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isAdmin()) {
                return true;
            }

            return null;
        });

        // Gate formal de visualización del dashboard (OWASP A01:2021)
        Gate::define('view-dashboard', function (User $user): bool {
            return $user->isAdmin() || $user->hasPermission('infra.view');
        });

        // Gates para módulos y acciones desacopladas
        Gate::define('alert.manage', [AlertPolicy::class, 'manageRules']);
        Gate::define('alert.resolve', [AlertPolicy::class, 'resolve']);
        Gate::define('alert.ack', [AlertPolicy::class, 'acknowledge']);
        Gate::define('ups.view', [UpsPolicy::class, 'view']);
        Gate::define('ups.manage', [UpsPolicy::class, 'manage']);
        Gate::define('netradar.view', [NetRadarPolicy::class, 'view']);
        Gate::define('netradar.scan', [NetRadarPolicy::class, 'scan']);
        Gate::define('netradar.export', [NetRadarPolicy::class, 'export']);
        Gate::define('ssl.view', [SslPolicy::class, 'view']);
        Gate::define('ssl.manage', [SslPolicy::class, 'manage']);
        Gate::define('ssl.recheck', [SslPolicy::class, 'recheck']);
        Gate::define('discovery.view', [DiscoveryPolicy::class, 'view']);
        Gate::define('discovery.scan', [DiscoveryPolicy::class, 'scan']);
        Gate::define('discovery.authorize', [DiscoveryPolicy::class, 'authorize']);
        Gate::define('advanced.view', [AdminAdvancedSettingsPolicy::class, 'view']);
        Gate::define('advanced.manage', [AdminAdvancedSettingsPolicy::class, 'manage']);
        Gate::define('snmp.poll', [SnmpDevicePolicy::class, 'poll']);
    }
}
