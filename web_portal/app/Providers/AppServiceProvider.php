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

        view()->composer('layouts.admin', function ($view) {
            try {
                $ups = \App\Models\UpsDevice::first();
                $upsHistories = collect();
                if ($ups) {
                    $upsHistories = \App\Models\UpsTelemetryHistory::where('ups_device_id', $ups->id)
                        ->select('input_voltage', 'output_voltage', 'load_percent', 'battery_percent', 'recorded_at')
                        ->orderBy('recorded_at', 'desc')
                        ->take(20)
                        ->get()
                        ->reverse()
                        ->values();
                }
                $view->with('headerUpsDevice', $ups);
                $view->with('headerUpsHistories', $upsHistories);
            } catch (\Throwable $e) {
                $view->with('headerUpsDevice', null);
                $view->with('headerUpsHistories', collect());
            }
        });
    }
}
