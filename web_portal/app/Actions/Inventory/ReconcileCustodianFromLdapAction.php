<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class ReconcileCustodianFromLdapAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    /**
     * Concilia atómicamente la identidad del responsable de un equipo con datos oficiales de LDAP,
     * preservando intacto el archivo digitalizado existente (document_pdf_path).
     *
     * @param int $assetId
     * @param array{name: string, id_number?: string|null, department?: string|null, role?: string|null, phone?: string|null, username?: string|null} $ldapData
     * @param int|null $userId
     * @return array{asset: InventoryAsset, assignment: InventoryAssignment|null, old_name: string|null, new_name: string}
     */
    public function execute(int $assetId, array $ldapData, ?int $userId = null): array
    {
        $asset = $this->repository->findById($assetId);
        if (!$asset) {
            throw ValidationException::withMessages([
                'asset_id' => 'El activo especificado no existe en el censo maestro de MariaDB.',
            ]);
        }

        $newName = trim($ldapData['name']);
        if (empty($newName)) {
            throw ValidationException::withMessages([
                'custodian_name' => 'El nombre oficial del responsable es obligatorio.',
            ]);
        }

        $idNumber = !empty($ldapData['id_number']) ? trim((string)$ldapData['id_number']) : null;
        $department = !empty($ldapData['department']) ? trim((string)$ldapData['department']) : null;
        $role = !empty($ldapData['role']) ? trim((string)$ldapData['role']) : 'Personal Corporativo';
        $phone = !empty($ldapData['phone']) ? trim((string)$ldapData['phone']) : null;

        return DB::transaction(function () use ($asset, $newName, $idNumber, $department, $role, $phone, $userId, $ldapData) {
            $oldName = $asset->custodian_name;
            $oldDept = $asset->custodian_department;
            $oldId = $asset->custodian_id_number;

            // 1. Actualizar el activo en inventory_assets
            $asset->update([
                'custodian_name' => $newName,
                'custodian_id_number' => $idNumber ?: $asset->custodian_id_number,
                'custodian_department' => $department ?: $asset->custodian_department,
                'status' => 'assigned',
                'updated_at' => now(),
            ]);

            // 2. Localizar acta activa o pase vigente para conciliar datos manteniendo el PDF
            $activeAssignment = InventoryAssignment::where('inventory_asset_id', $asset->id)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if ($activeAssignment) {
                // Conservar de forma sagrada el PDF escaneado preexistente
                $activeAssignment->update([
                    'custodian_name' => $newName,
                    'custodian_id_number' => $idNumber ?: $activeAssignment->custodian_id_number,
                    'custodian_department' => $department ?: $activeAssignment->custodian_department,
                    'custodian_role' => $role ?: $activeAssignment->custodian_role,
                    'phone_extension' => $phone ?: $activeAssignment->phone_extension,
                    'updated_at' => now(),
                ]);
            } else {
                // Crear acta base si el equipo no tenía acta previa
                $year = date('Y');
                $code = sprintf("UINF-4.05-%s-%04d", $year, InventoryAssignment::whereYear('created_at', $year)->count() + 1);

                $activeAssignment = InventoryAssignment::create([
                    'assignment_code' => $code,
                    'inventory_asset_id' => $asset->id,
                    'user_id' => $userId ?: (Auth::id() ?: 1),
                    'custodian_name' => $newName,
                    'custodian_id_number' => $idNumber,
                    'custodian_department' => $department,
                    'custodian_role' => $role,
                    'phone_extension' => $phone,
                    'monitored_site_id' => $asset->monitored_site_id,
                    'movement_type' => 'assignment',
                    'provenance' => 'stock',
                    'hardware_specs' => [
                        'device_name' => $asset->name,
                        'ip_address' => $asset->ip_address,
                        'model' => $asset->model,
                        'serial' => $asset->serial_number,
                    ],
                    'assigned_at' => now(),
                    'document_pdf_path' => null,
                    'status' => 'active',
                    'delivered_by_user_id' => $userId ?: (Auth::id() ?: 1),
                    'observations' => 'Asignación conciliada directamente desde Directorio Activo (LDAP).',
                ]);
            }

            // 3. Registrar auditoría forense inmutable
            AuditLog::create([
                'user_id' => $userId ?: Auth::id(),
                'user_name' => Auth::user()?->name ?? 'Administrador',
                'event' => 'reconcile_custodian_ldap',
                'module' => 'inventory',
                'auditable_type' => InventoryAsset::class,
                'auditable_id' => $asset->id,
                'entity_name' => $asset->name,
                'entity_label' => $asset->display_identity ?: $asset->name,
                'description' => sprintf(
                    "Conciliación de responsable en activo #%d (%s): '%s' -> '%s' (Cédula: %s, Dpto: %s) vía LDAP.",
                    $asset->id,
                    $asset->display_identity ?: $asset->name,
                    $oldName ?: 'Sin asignar',
                    $newName,
                    $idNumber ?: 'N/A',
                    $department ?: 'N/A'
                ),
                'old_values' => json_encode(['custodian_name' => $oldName, 'custodian_id_number' => $oldId, 'custodian_department' => $oldDept]),
                'new_values' => json_encode(['custodian_name' => $newName, 'custodian_id_number' => $idNumber, 'custodian_department' => $department]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return [
                'asset' => $asset->fresh(),
                'assignment' => $activeAssignment->fresh(),
                'old_name' => $oldName,
                'new_name' => $newName,
            ];
        });
    }
}
