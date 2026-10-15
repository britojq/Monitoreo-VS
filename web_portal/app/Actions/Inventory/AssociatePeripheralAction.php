<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

readonly class AssociatePeripheralAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    public function execute(int $id, Request $request): void
    {
        $asset = $this->repository->findById($id);
        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        $validated = $request->validate([
            'type' => 'required|in:monitor,keyboard,mouse,surge_protector,ups,printer,scanner,other',
            'brand' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:96',
            'serial_number' => 'nullable|string|max:128',
            'status' => 'required|in:assigned,in_stock,in_maintenance,damaged',
            'notes' => 'nullable|string',
            'reason' => 'nullable|string|max:191',
        ]);

        $existing = InventoryPeripheral::where('current_asset_id', $asset->id)
            ->where('type', $validated['type'])
            ->first();

        $action = $existing ? 'replaced' : 'added';
        $oldData = $existing ? [
            'brand' => $existing->brand,
            'model' => $existing->model,
            'serial' => $existing->serial_number,
        ] : null;

        $newData = [
            'brand' => $validated['brand'] ?: 'Genérico',
            'model' => $validated['model'] ?: 'Estándar',
            'serial' => $validated['serial_number'],
        ];

        if ($existing) {
            $existing->update([
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'serial_number' => $validated['serial_number'],
                'status' => $validated['status'],
                'monitored_site_id' => $asset->monitored_site_id,
                'notes' => $validated['notes'] ?? $existing->notes,
            ]);
        } else {
            $prefix = match($validated['type']) {
                'monitor' => 'MON',
                'keyboard' => 'KBD',
                'mouse' => 'MSE',
                'surge_protector', 'ups' => 'PWR',
                default => 'PER',
            };
            InventoryPeripheral::create([
                'asset_tag' => $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'type' => $validated['type'],
                'brand' => $validated['brand'] ?: 'Genérico',
                'model' => $validated['model'] ?: 'Estándar',
                'serial_number' => $validated['serial_number'],
                'status' => $validated['status'],
                'monitored_site_id' => $asset->monitored_site_id,
                'current_asset_id' => $asset->id,
                'notes' => $validated['notes'],
            ]);
        }

        InventoryComponentHistory::create([
            'inventory_asset_id' => $asset->id,
            'action' => $action,
            'component_type' => $validated['type'],
            'old_value' => $oldData,
            'new_value' => $newData,
            'reason' => $validated['reason'] ?: 'Asignación / reemplazo de periférico desde catálogo ITAM',
            'registered_by_user_id' => Auth::id(),
            'recorded_at' => now(),
        ]);
    }
}
