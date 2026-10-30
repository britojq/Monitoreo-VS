<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\AlertRepositoryInterface;
use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Contracts\Repositories\NetworkDeviceRepositoryInterface;
use App\Contracts\Repositories\ServiceRepositoryInterface;
use App\Contracts\Repositories\SiteRepositoryInterface;
use App\Repositories\Eloquent\EloquentAlertRepository;
use App\Repositories\Eloquent\EloquentInventoryAssetRepository;
use App\Repositories\Eloquent\EloquentNetworkDeviceRepository;
use App\Repositories\Eloquent\EloquentServiceRepository;
use App\Repositories\Eloquent\EloquentSiteRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        InventoryAssetRepositoryInterface::class => EloquentInventoryAssetRepository::class,
        NetworkDeviceRepositoryInterface::class => EloquentNetworkDeviceRepository::class,
        SiteRepositoryInterface::class => EloquentSiteRepository::class,
        ServiceRepositoryInterface::class => EloquentServiceRepository::class,
        AlertRepositoryInterface::class => EloquentAlertRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
