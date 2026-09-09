<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        view()->composer('*', function ($view) {
            $clusterService = new \App\Services\ClusterConfigService();
            $view->with('clusterConfig', $clusterService->getConfig());
            $view->with('isClusterSlave', $clusterService->isSlave());
            $view->with('isClusterMaster', $clusterService->isMaster());

            $ldapService = new \App\Services\LdapAuthService();
            $view->with('ldapConfig', $ldapService->getConfig());
            $view->with('isLdapEnabled', $ldapService->isEnabled());
        });
    }
}
