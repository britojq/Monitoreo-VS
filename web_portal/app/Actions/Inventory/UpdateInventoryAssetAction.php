<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\DTOs\InventoryAssetData;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryPeripheral;
use Illuminate\Support\Facades\Auth;

readonly class UpdateInventoryAssetAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    public function execute(int $id, InventoryAssetData $dto, array $extraInput = []): InventoryAsset
    {
        $asset = $this->repository->findById($id);
        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        $oldValues = $asset->only(['name', 'asset_tag', 'status', 'custodian_name', 'ip_address', 'monitored_site_id']);

        $updated = $this->repository->update($id, $dto);

        // Limpieza de credenciales si se solicita explícitamente
        if (!empty($extraInput['clear_ssh_credentials'])) {
            $updated->update([
                'ssh_username' => null,
                'ssh_password_encrypted' => null,
                'ssh_enable_secret_encrypted' => null,
            ]);
        }
        if (!empty($extraInput['clear_vnc_credentials'])) {
            $updated->update([
                'vnc_password_encrypted' => null,
            ]);
        }

        // Gestión de acción de asignación
        if (($extraInput['assignment_action'] ?? null) === 'release') {
            $updated->update([
                'status' => 'in_stock',
                'custodian_name' => null,
                'custodian_id_number' => null,
                'custodian_department' => null,
                'current_user_id' => null,
            ]);
        }

        // Actualizar periféricos si se enviaron
        $this->syncPeripherals($updated, $extraInput);

        // Auditoría
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'updated',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $updated->id,
            'entity_name' => $updated->name,
            'entity_label' => $updated->asset_tag,
            'old_values' => $oldValues,
            'new_values' => $updated->only(['name', 'asset_tag', 'status', 'custodian_name', 'ip_address', 'monitored_site_id']),
            'description' => "Activo ITAM actualizado: {$updated->name} (Tag: {$updated->asset_tag})",
            'ip_address' => request()->ip(),
        ]);

        return $updated;
    }

    private function syncPeripherals(InventoryAsset $asset, array $extraInput): void
    {
        $periphMap = [
            'monitor' => [
                'brand' => $extraInput['monitor_brand'] ?? null,
                'model' => $extraInput['monitor_model'] ?? null,
                'serial' => $extraInput['monitor_serial'] ?? null,
            ],
            'keyboard' => [
                'brand' => $extraInput['keyboard_brand'] ?? null,
                'model' => 'Teclado USB',
                'serial' => $extraInput['keyboard_serial'] ?? null,
            ],
            'mouse' => [
                'brand' => $extraInput['mouse_brand'] ?? null,
                'model' => 'Mouse USB',
                'serial' => $extraInput['mouse_serial'] ?? null,
            ],
            'surge_protector' => [
                'brand' => $extraInput['surge_protector_brand'] ?? null,
                'model' => 'Protector Eléctrico',
                'serial' => $extraInput['surge_protector_serial'] ?? null,
            ],
        ];

        foreach ($periphMap as $type => $data) {
            if (!empty($data['brand']) || !empty($data['serial'])) {
                $existing = $asset->peripherals()->where('type', $type)->first();
                if ($existing) {
                    $existing->update([
                        'brand' => $data['brand'] ?: $existing->brand,
                        'model' => $data['model'] ?: $existing->model,
                        'serial_number' => $data['serial'] ?: $existing->serial_number,
                    ]);
                } else {
                    InventoryPeripheral::create([
                        'asset_tag' => strtoupper(substr($type, 0, 3)) . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                        'type' => $type,
                        'brand' => $data['brand'] ?: 'Genérico',
                        'model' => $data['model'] ?: 'Estándar',
                        'serial_number' => $data['serial'] ?? null,
                        'status' => 'assigned',
                        'current_asset_id' => $asset->id,
                        'monitored_site_id' => $asset->monitored_site_id,
                    ]);
                }
            }
        }
    }
}
