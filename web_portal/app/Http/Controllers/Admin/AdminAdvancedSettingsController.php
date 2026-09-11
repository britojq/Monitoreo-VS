<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\MonitoringSnapshot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAdvancedSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $stats = [
            'users_count' => User::count(),
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

        $cronConfig = (new AdminCronController())->getCronConfig();
        $latestSnapshot = MonitoringSnapshot::latest()->first();

        return view('admin.settings.advanced', [
            'stats' => $stats,
            'cronConfig' => $cronConfig,
            'latestSnapshot' => $latestSnapshot,
        ]);
    }
}
