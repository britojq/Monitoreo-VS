<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

readonly class ApproveInventoryAssetAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    public function execute(int $id, Request $request): InventoryAsset
    {
        $asset = $this->repository->findById($id);
        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        $newStatus = $request->input('target_status', 'in_stock');
        if (!in_array($newStatus, ['in_stock', 'assigned', 'maintenance'], true)) {
            $newStatus = 'in_stock';
        }

        $asset->update([
            'status' => $newStatus,
            'asset_tag' => $request->input('asset_tag', $asset->asset_tag),
            'monitored_site_id' => $request->input('monitored_site_id', $asset->monitored_site_id),
            'location_detail' => $request->input('location_detail', $asset->location_detail),
            'custodian_name' => $request->input('custodian_name', $asset->custodian_name),
            'custodian_id_number' => $request->input('custodian_id_number', $asset->custodian_id_number),
            'custodian_department' => $request->input('custodian_department', $asset->custodian_department),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'approved',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->name,
            'entity_label' => $asset->asset_tag,
            'description' => "Equipo descubierto por GLPI '{$asset->name}' (#{$asset->id}) aprobado e incorporado con estado: {$newStatus}",
            'ip_address' => $request->ip(),
        ]);

        return $asset;
    }
}
