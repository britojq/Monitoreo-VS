<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

readonly class DisassociatePeripheralAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    public function execute(int $id, int $peripheralId, Request $request): void
    {
        $asset = $this->repository->findById($id);
        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        $peripheral = InventoryPeripheral::where('current_asset_id', $asset->id)->findOrFail($peripheralId);

        $oldData = [
            'brand' => $peripheral->brand,
            'model' => $peripheral->model,
            'serial' => $peripheral->serial_number,
        ];

        $peripheral->update([
            'current_asset_id' => null,
            'status' => 'in_stock',
        ]);

        InventoryComponentHistory::create([
            'inventory_asset_id' => $asset->id,
            'action' => 'removed',
            'component_type' => $peripheral->type,
            'old_value' => $oldData,
            'new_value' => null,
            'reason' => $request->input('reason', 'Periférico desvinculado / reintegrado al stock'),
            'registered_by_user_id' => Auth::id(),
            'recorded_at' => now(),
        ]);
    }
}
