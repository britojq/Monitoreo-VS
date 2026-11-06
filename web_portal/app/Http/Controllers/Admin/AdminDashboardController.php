<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\CronConfigServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\User;
use App\Services\MonitoringDataService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Inyección formal de dependencias cumpliendo con el Principio de Inversión de Dependencias (SOLID DIP).
     */
    public function __construct(
        private readonly MonitoringDataService $monitoringService,
        private readonly CronConfigServiceInterface $cronConfigService,
    ) {}

    public function index(Request $request): View
    {
        // Autorización formal mediante Gate (OWASP A01:2021)
        $this->authorize('view-dashboard');

        $user = $request->user();
        $isAdmin = $user !== null && $user->isAdmin();

        $stats = [
            'services_count' => MonitoredService::count(),
            'services_active' => MonitoredService::where('is_active', true)->count(),
            'sites_count' => MonitoredSite::count(),
            'sites_active' => MonitoredSite::where('is_active', true)->count(),
            'devices_count' => MonitoredSiteDevice::count(),
            'network_devices_count' => MonitoredNetworkDevice::count(),
            'network_devices_active' => MonitoredNetworkDevice::where('is_active', true)->count(),
            'proxies_count' => MonitoredProxy::count(),
            'proxies_active' => MonitoredProxy::where('is_active', true)->count(),
        ];

        if ($isAdmin) {
            $stats['users_count'] = User::count();
        }

        // Obtención de configuración a través del servicio inyectado (cero operador new)
        $cronConfig = $isAdmin ? $this->cronConfigService->getCronConfig() : null;
        $monitoringData = $this->monitoringService->getMonitoringBoardData();

        $viewData = array_merge($monitoringData, [
            'stats' => $stats,
            'cronConfig' => $cronConfig,
        ]);

        return view('admin.dashboard', $viewData);
    }
}
