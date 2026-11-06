<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Http\Request;

readonly class GetInventoryIndexViewModelAction
{
    public function execute(Request $request): array
    {
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type');
        $siteFilter = $request->query('site_id');
        $search = trim((string)$request->query('search', ''));

        $query = InventoryAsset::with([
            'monitoredSite',
            'networkDevice',
            'components',
            'networkInterfaces',
            'peripherals',
            'componentHistories' => function ($q) {
                $q->with('registeredByUser')->orderBy('recorded_at', 'desc');
            },
            'assignments' => function ($q) {
                $q->orderBy('id', 'desc');
            },
            'maintenanceTickets' => function ($q) {
                $q->with('technician')->orderBy('id', 'desc');
            },
        ])->orderBy('id', 'desc');

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        if (!empty($siteFilter)) {
            $query->where('monitored_site_id', $siteFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('mac_address', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('operating_system', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('custodian_name', 'like', "%{$search}%")
                  ->orWhereHas('networkDevice', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('ip', 'like', "%{$search}%")
                         ->orWhere('model', 'like', "%{$search}%");
                  })
                  ->orWhereHas('monitoredSite', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $assets = $query->paginate(15)->withQueryString();

        $totalAssets = InventoryAsset::count();
        $assignedAssets = InventoryAsset::where('status', 'assigned')->count();
        $inStockAssets = InventoryAsset::where('status', 'in_stock')->count();
        $maintenanceAssets = InventoryAsset::where('status', 'maintenance')->count();
        $unauthorizedAssets = InventoryAsset::where('status', 'unauthorized')->count();

        $sites = MonitoredSite::orderBy('name')->get();
        $technicians = User::where('is_active', true)->orderBy('name')->get();

        return compact(
            'assets',
            'totalAssets',
            'assignedAssets',
            'inStockAssets',
            'maintenanceAssets',
            'unauthorizedAssets',
            'sites',
            'technicians',
            'statusFilter',
            'typeFilter',
            'siteFilter',
            'search'
        );
    }
}
