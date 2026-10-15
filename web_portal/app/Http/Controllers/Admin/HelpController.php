<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\SnmpDevice;
use App\Models\UpsDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    /**
     * Muestra el Manual Interactivo de Usuario y Guía Operativa de la plataforma.
     */
    public function index(Request $request): View
    {
        $clusterRole = (string) config('monitoring.cluster.role', 'slave');
        $systemVersion = 'v5.9.90';

        $stats = [
            'services' => MonitoredService::count(),
            'sites' => MonitoredSite::count(),
            'devices' => MonitoredNetworkDevice::count(),
            'proxies' => MonitoredProxy::count(),
            'alerts' => Alert::where('status', 'firing')->count(),
            'users' => User::count(),
            'assets' => InventoryAsset::count(),
            'assignments' => InventoryAssignment::where('status', 'active')->count(),
            'peripherals' => InventoryPeripheral::count(),
            'ups' => UpsDevice::count(),
            'snmp_devices' => SnmpDevice::count(),
        ];

        return view('admin.help.index', compact(
            'clusterRole',
            'systemVersion',
            'stats'
        ));
    }
}
