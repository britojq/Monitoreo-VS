<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\DiscoveredDevice;
use App\Models\SslCertificate;
use App\Models\UpsDevice;
use App\Policies\AlertPolicy;
use App\Policies\DiscoveryPolicy;
use App\Policies\NetRadarPolicy;
use App\Policies\SslPolicy;
use App\Policies\UpsPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * El mapa de políticas para la aplicación.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Alert::class => AlertPolicy::class,
        AlertRule::class => AlertPolicy::class,
        UpsDevice::class => UpsPolicy::class,
        SslCertificate::class => SslPolicy::class,
        DiscoveredDevice::class => DiscoveryPolicy::class,
    ];

    /**
     * Registra servicios de autenticación y autorización.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Gates para acciones y módulos desacoplados
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
    }
}
