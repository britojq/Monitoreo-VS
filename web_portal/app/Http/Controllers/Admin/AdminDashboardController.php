<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use App\Models\MonitoringSnapshot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = $request->user() && $request->user()->isAdmin();

        $stats = [
            'services_count' => MonitoredService::count(),
            'services_active' => MonitoredService::where('is_active', true)->count(),
            'sites_count' => MonitoredSite::count(),
            'sites_active' => MonitoredSite::where('is_active', true)->count(),
            'devices_count' => MonitoredSiteDevice::count(),
            'proxies_count' => MonitoredProxy::count(),
            'proxies_active' => MonitoredProxy::where('is_active', true)->count(),
        ];

        if ($isAdmin) {
            $stats['users_count'] = User::count();
        }

        $latestSnapshot = MonitoringSnapshot::latest()->first();

        return view('admin.dashboard', compact('stats', 'latestSnapshot'));
    }
}
