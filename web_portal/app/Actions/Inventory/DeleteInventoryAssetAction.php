<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

readonly class DeleteInventoryAssetAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    public function execute(int $id, Request $request): string
    {
        $asset = $this->repository->findById($id);
        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        $name = $asset->name;

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'deleted',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $id,
            'entity_name' => $name,
            'description' => "Activo ITAM eliminado/desincorporado: {$name} (#{$id})",
            'ip_address' => $request->ip(),
        ]);

        $asset->components()->delete();
        $asset->componentHistories()->delete();
        $this->repository->delete($id);

        return $name;
    }
}
