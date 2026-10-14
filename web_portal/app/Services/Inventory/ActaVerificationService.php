<?php

namespace App\Services\Inventory;

use App\Contracts\ActaVerificationInterface;
use App\Models\InventoryAssignment;

/**
 * Servicio para la firma criptográfica y verificación forense de Actas de TI.
 * Cumple con principios SOLID y garantiza integridad documental con MariaDB como SSOT.
 */
class ActaVerificationService implements ActaVerificationInterface
{
    /**
     * Genera la firma criptográfica (HMAC-SHA256) de integridad del acta.
     */
    public function generateSignature(InventoryAssignment $assignment): string
    {
        $payload = implode('|', [
            $assignment->id,
            $assignment->assignment_code,
            $assignment->inventory_asset_id,
            $assignment->custodian_id_number ?? '',
            $assignment->movement_type,
            $assignment->assigned_at ? $assignment->assigned_at->format('Y-m-d') : '',
        ]);

        $key = config('app.key') ?: 'valle_seco_acta_secure_token_key_2026';
        return hash_hmac('sha256', $payload, $key);
    }

    /**
     * Obtiene la URL completa del endpoint de verificación pública corporativa.
     */
    public function getVerificationUrl(InventoryAssignment $assignment): string
    {
        $sigShort = substr($this->generateSignature($assignment), 0, 16);
        return route('public.acta.verify', [
            'code' => $assignment->assignment_code,
            'sig' => $sigShort,
        ]);
    }

    /**
     * Valida si una firma dada coincide con la integridad de los datos del acta en MariaDB.
     */
    public function verifySignature(InventoryAssignment $assignment, string $signature): bool
    {
        $expectedSignature = $this->generateSignature($assignment);
        $expectedShort = substr($expectedSignature, 0, 16);

        return hash_equals($expectedSignature, $signature) || hash_equals($expectedShort, $signature);
    }

    /**
     * Recupera el censo detallado de la asignación para la vista pública de validación.
     */
    public function getPublicVerificationData(string $code): ?array
    {
        $assignment = InventoryAssignment::with(['asset.components', 'site', 'deliveredByUser'])
            ->where('assignment_code', trim($code))
            ->first();

        if (!$assignment) {
            return null;
        }

        $fullSignature = $this->generateSignature($assignment);
        $asset = $assignment->asset;
        $specs = $assignment->hardware_specs ?? [];

        // Obtención de periféricos asociados al activo
        $peripherals = $asset ? $asset->peripherals()->where('status', 'in_use')->get() : collect();

        $cpu = $specs['processor'] ?? ($asset ? $asset->components->where('type', 'cpu')->first()?->name : 'No especificado');
        $ram = $specs['ram'] ?? ($asset ? $asset->components->where('type', 'ram')->pluck('capacity')->implode(' + ') : 'No especificado');
        $disk = $specs['disk'] ?? ($asset ? $asset->components->where('type', 'disk')->pluck('capacity')->implode(' + ') : 'No especificado');

        return [
            'assignment' => $assignment,
            'code' => $assignment->assignment_code,
            'control_number' => $assignment->control_number,
            'movement_type' => $assignment->movement_type,
            'movement_label' => $assignment->movement_type === 'assignment' ? 'Asignación de Equipo' : 'Pase de Movilización / Devolución',
            'status' => $assignment->status,
            'is_active' => $assignment->status === 'active',
            'has_physical_scan' => !empty($assignment->document_pdf_path),
            'issued_at' => $assignment->assigned_at ? $assignment->assigned_at->format('d/m/Y H:i') : now()->format('d/m/Y'),
            'site_name' => $assignment->site?->name ?? 'Sede Central Valle Seco',
            'destination_site_name' => $assignment->destinationSite?->name ?? null,
            // Terminología oficial obligatoria (Regla de Oro #9: Cero "custodio")
            'responsible_name' => $assignment->custodian_name,
            'responsible_id_number' => $assignment->custodian_id_number,
            'responsible_department' => $assignment->custodian_department,
            'delivered_by' => $assignment->deliveredByUser?->name ?? 'Unidad de Informática',
            'asset' => $asset ? [
                'id' => $asset->id,
                'name' => $asset->name, // Nombre Etiqueta (SSOT Regla #7)
                'asset_tag' => $asset->asset_tag,
                'serial_number' => $asset->serial_number,
                'brand' => $asset->brand,
                'model' => $asset->model,
                'type' => $asset->type,
                'ip_address' => $asset->ip_address,
                'status' => $asset->status,
            ] : null,
            'specs' => [
                'cpu' => $cpu,
                'ram' => $ram,
                'disk' => $disk,
            ],
            'peripherals' => $peripherals->map(fn($p) => [
                'type' => $p->type,
                'brand' => $p->brand,
                'model' => $p->model,
                'serial' => $p->serial_number,
            ])->toArray(),
            'signature' => $fullSignature,
            'signature_short' => substr($fullSignature, 0, 16),
            'verified_at' => now()->format('d/m/Y H:i:s'),
        ];
    }
}
