<?php

declare(strict_types=1);

namespace App\Actions\Discovery;

use App\Models\DiscoveredDevice;
use App\Models\DiscoveryScan;
use App\Models\DiscoverySubnet;
use App\Models\MonitoredSite;
use App\Models\SnmpActivationLog;
use App\Models\SnmpDevice;
use App\Models\SnmpInterface;
use App\Models\SnmpOid;
use Illuminate\Http\Request;

class FetchDiscoveredDevicesAction
{
    public function execute(Request $request): array
    {
        $activeTab = $request->query('tab', 'radar');
        $statusFilter = $request->query('status');
        $siteFilter = $request->query('site_id');
        $typeFilter = $request->query('device_type');
        $search = $request->query('search');

        $radarPendingDevices = DiscoveredDevice::where('classification_status', 'pendiente')->count();
        $snmpTotalDevices = SnmpDevice::count();
        $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();

        if ($activeTab === 'snmp') {
            $query = SnmpDevice::with(['site', 'networkDevice', 'interfaces'])
                ->orderByDesc('is_active')
                ->orderBy('name');

            if (!empty($statusFilter)) {
                $query->where('last_poll_status', $statusFilter);
            }
            if (!empty($typeFilter)) {
                $query->where('device_type', $typeFilter);
            }
            if (!empty($siteFilter)) {
                $query->where('site_id', $siteFilter);
            }
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhere('vendor', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('sys_name', 'like', "%{$search}%");
                });
            }

            return [
                'activeTab' => $activeTab,
                'devices' => $query->paginate(15)->withQueryString(),
                'sites' => $sites,
                'recentLogs' => SnmpActivationLog::latest('executed_at')->take(5)->get(),
                'snmpTotalDevices' => $snmpTotalDevices,
                'onlineDevices' => SnmpDevice::where('last_poll_status', 'success')->count(),
                'failingDevices' => SnmpDevice::where('consecutive_failures', '>', 0)->count(),
                'totalInterfaces' => SnmpInterface::count(),
                'monitoredInterfaces' => SnmpInterface::where('is_monitored', true)->count(),
                'oidsCount' => SnmpOid::where('is_active', true)->count(),
                'radarPendingDevices' => $radarPendingDevices,
                'statusFilter' => $statusFilter,
                'typeFilter' => $typeFilter,
                'siteFilter' => $siteFilter,
                'search' => $search,
            ];
        }

        $query = DiscoveredDevice::with(['site', 'classifiedBy', 'linkedNetworkDevice', 'inventoryAsset'])
            ->orderByDesc('last_seen');

        if (!empty($statusFilter)) {
            $query->where('classification_status', $statusFilter);
        }
        if (!empty($siteFilter)) {
            $query->where('site_id', $siteFilter);
        }
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                  ->orWhere('mac_address', 'like', "%{$search}%")
                  ->orWhere('hostname', 'like', "%{$search}%")
                  ->orWhere('vendor', 'like', "%{$search}%")
                  ->orWhereHas('inventoryAsset', function ($aq) use ($search) {
                      $aq->where('name', 'like', "%{$search}%")
                         ->orWhere('brand', 'like', "%{$search}%")
                         ->orWhere('model', 'like', "%{$search}%");
                  });
            });
        }

        return [
            'activeTab' => $activeTab,
            'devices' => $query->paginate(15)->withQueryString(),
            'sites' => $sites,
            'subnets' => DiscoverySubnet::with('site')->orderBy('subnet')->get(),
            'recentScans' => DiscoveryScan::recent(5)->get(),
            'totalDevices' => DiscoveredDevice::count(),
            'pendingDevices' => $radarPendingDevices,
            'authorizedDevices' => DiscoveredDevice::where('is_authorized', true)->count(),
            'rogueDevices' => DiscoveredDevice::where('classification_status', 'rogue')->count(),
            'radarPendingDevices' => $radarPendingDevices,
            'snmpTotalDevices' => $snmpTotalDevices,
            'statusFilter' => $statusFilter,
            'siteFilter' => $siteFilter,
            'search' => $search,
        ];
    }

    public function getHistory(int $id): array
    {
        $device = DiscoveredDevice::findOrFail($id);
        $history = $device->history()->orderByDesc('occurred_at')->limit(30)->get()->map(fn($h) => [
            'id' => $h->id,
            'event_type' => $h->event_type,
            'event_type_label' => $h->event_type_label,
            'previous_value' => $h->previous_value,
            'new_value' => $h->new_value,
            'occurred_at' => $h->occurred_at ? $h->occurred_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : '-',
            'occurred_at_human' => $h->occurred_at ? $h->occurred_at->diffForHumans() : '-',
        ]);

        return [
            'success' => true,
            'device' => [
                'id' => $device->id,
                'ip' => $device->ip_address,
                'mac' => $device->mac_address,
                'vendor' => $device->vendor,
                'hostname' => $device->hostname,
            ],
            'history' => $history,
        ];
    }
}
