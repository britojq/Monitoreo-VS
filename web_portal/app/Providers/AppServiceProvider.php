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
        $this->app->singleton(\App\Contracts\NotifierInterface::class, \App\Services\Notification\TelegramTransport::class);
        $this->app->singleton(\App\Contracts\CommandRunnerInterface::class, \App\Services\System\SystemCommandRunner::class);
        $this->app->singleton(\App\Contracts\HardwareInspectorInterface::class, \App\Services\Hardware\LinuxHardwareInspector::class);
        $this->app->singleton(\App\Contracts\ClusterVerifierInterface::class, \App\Services\Cluster\ClusterVerifierService::class);
        $this->app->singleton(\App\Contracts\ClusterAuditLoggerInterface::class, \App\Services\Cluster\ClusterAuditLoggerService::class);
        $this->app->singleton(\App\Contracts\HardwareDriftDetectorInterface::class, \App\Services\Glpi\HardwareDriftDetectorService::class);
        $this->app->singleton(\App\Contracts\GlpiAgentTriggerInterface::class, \App\Services\Glpi\GlpiAgentTriggerService::class);
        $this->app->singleton(\App\Contracts\IpamGridInterface::class, \App\Services\Ipam\IpamGridService::class);
        $this->app->singleton(\App\Contracts\QrCodeGeneratorInterface::class, \App\Services\Qr\QrCodeGeneratorService::class);
        $this->app->singleton(\App\Contracts\ActaVerificationInterface::class, \App\Services\Inventory\ActaVerificationService::class);
        $this->app->singleton(\App\Contracts\NetworkTopologyInterface::class, \App\Services\Topology\NetworkTopologyService::class);
        $this->app->singleton(\App\Contracts\AssetTimelineInterface::class, \App\Services\Inventory\AssetTimelineService::class);
        $this->app->singleton(\App\Contracts\ExecutiveReportInterface::class, \App\Services\Reports\ExecutiveReportService::class);

        // Repositorios de Dominio (Principios SOLID)
        $this->app->bind(\App\Contracts\Repositories\InventoryAssetRepositoryInterface::class, \App\Repositories\Eloquent\EloquentInventoryAssetRepository::class);
        $this->app->bind(\App\Contracts\Repositories\NetworkDeviceRepositoryInterface::class, \App\Repositories\Eloquent\EloquentNetworkDeviceRepository::class);
        $this->app->bind(\App\Contracts\Repositories\SiteRepositoryInterface::class, \App\Repositories\Eloquent\EloquentSiteRepository::class);
        $this->app->bind(\App\Contracts\Repositories\ServiceRepositoryInterface::class, \App\Repositories\Eloquent\EloquentServiceRepository::class);
        $this->app->bind(\App\Contracts\Repositories\AlertRepositoryInterface::class, \App\Repositories\Eloquent\EloquentAlertRepository::class);
        $this->app->bind(\App\Contracts\Repositories\ProxyRepositoryInterface::class, \App\Repositories\Eloquent\EloquentProxyRepository::class);
        $this->app->bind(\App\Contracts\Repositories\UserRepositoryInterface::class, \App\Repositories\Eloquent\EloquentUserRepository::class);
        $this->app->bind(\App\Contracts\Repositories\MaintenanceTicketRepositoryInterface::class, \App\Repositories\Eloquent\EloquentMaintenanceTicketRepository::class);
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

            $aiService = new \App\Services\AiConfigService();
            $view->with('aiConfig', $aiService->getConfig());
            $view->with('isAiEnabled', $aiService->isEnabled());
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\HousekeepingTelemetryCommand::class,
                \App\Console\Commands\SyncInventoryFromExcelCommand::class,
            ]);
        }
    }
}
