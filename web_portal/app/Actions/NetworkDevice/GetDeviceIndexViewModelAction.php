<?php

declare(strict_types=1);

namespace App\Actions\NetworkDevice;

use App\Models\InventoryAsset;
use App\Models\MonitoredSite;
use App\Models\MonitoringSnapshot;
use App\Models\User;
use Illuminate\Http\Request;

readonly class GetDeviceIndexViewModelAction
{
    public function execute(Request $request): array
    {
        $siteFilter = $request->query('site_id');
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $query = InventoryAsset::with([
            'site',
            'components',
            'networkInterfaces',
            'peripherals',
            'assignments' => fn($q) => $q->orderBy('assigned_at', 'desc'),
            'maintenanceTickets' => fn($q) => $q->orderBy('created_at', 'desc'),
            'componentHistories' => fn($q) => $q->orderBy('recorded_at', 'desc'),
            'discoveredDevice',
            'snmpDevice.interfaces',
            'netRadarHost',
            'wolDevice',
        ]);

        if (!empty($siteFilter)) {
            $query->where('monitored_site_id', $siteFilter);
        }

        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        // Base query para métricas superiores
        $baseQuery = clone $query;
        if (!empty($search)) {
            $baseQuery->search($search);
        }

        $totalCount = (clone $baseQuery)->count();
        $activeCount = (clone $baseQuery)->where('is_active', true)->count();
        $inactiveCount = (clone $baseQuery)->where('is_active', false)->count();
        $withAssignmentCount = (clone $baseQuery)->where(function ($q) {
            $q->whereHas('assignments', fn($qa) => $qa->where('status', 'active'))
              ->orWhere('status', 'assigned');
        })->count();
        $withoutAssignmentCount = (clone $baseQuery)->whereDoesntHave('assignments', fn($qa) => $qa->where('status', 'active'))
            ->where('status', '!=', 'assigned')
            ->count();
        $maintenanceCount = (clone $baseQuery)->where('status', 'maintenance')->count();

        // Filtro de estado
        if (!empty($statusFilter)) {
            if ($statusFilter === 'active') {
                $query->where('is_active', true);
            } elseif ($statusFilter === 'inactive') {
                $query->where('is_active', false);
            } elseif ($statusFilter === 'assigned' || $statusFilter === 'with_assignment') {
                $query->where(function ($q) {
                    $q->whereHas('assignments', fn($qa) => $qa->where('status', 'active'))
                      ->orWhere('status', 'assigned');
                });
            } elseif ($statusFilter === 'unassigned' || $statusFilter === 'without_assignment') {
                $query->whereDoesntHave('assignments', fn($qa) => $qa->where('status', 'active'))
                      ->where('status', '!=', 'assigned');
            } else {
                $query->where('status', $statusFilter);
            }
        }

        if (!empty($search)) {
            $query->search($search);
        }

        $assignedCount = $withAssignmentCount;
        $unassignedCount = $withoutAssignmentCount;

        $devices = $query->orderBy('monitored_site_id')->orderBy('device_number')->paginate(15)->withQueryString();
        $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();
        $technicians = User::where('is_active', true)->orderBy('name')->get();

        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotDevices = ($latestSnapshot && isset($latestSnapshot->payload_json['network_devices']))
            ? collect($latestSnapshot->payload_json['network_devices'])->keyBy('id')
            : collect();

        return compact(
            'devices',
            'sites',
            'siteFilter',
            'typeFilter',
            'statusFilter',
            'search',
            'snapshotDevices',
            'totalCount',
            'activeCount',
            'inactiveCount',
            'assignedCount',
            'withAssignmentCount',
            'withoutAssignmentCount',
            'unassignedCount',
            'maintenanceCount',
            'technicians'
        );
    }
}
