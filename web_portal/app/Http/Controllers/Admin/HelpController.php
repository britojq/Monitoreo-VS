<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    /**
     * Muestra el Manual Interactivo de Usuario de la plataforma.
     */
    public function index(Request $request): View
    {
        $clusterRole = config('monitoring.cluster.role', 'slave');
        $systemVersion = 'v3.0.0';
        $masterUrl = config('monitoring.cluster.master_url', 'http://10.20.23.252');

        $stats = [
            'services' => MonitoredService::count(),
            'sites' => MonitoredSite::count(),
            'devices' => MonitoredNetworkDevice::count(),
            'proxies' => MonitoredProxy::count(),
            'alerts' => Alert::where('status', 'firing')->count(),
            'users' => User::count(),
        ];

        return view('admin.help.index', compact(
            'clusterRole',
            'systemVersion',
            'masterUrl',
            'stats'
        ));
    }
}
