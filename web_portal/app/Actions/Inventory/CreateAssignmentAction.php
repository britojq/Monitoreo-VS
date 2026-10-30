<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAssignmentAction
{
    /**
     * Registra un acta de asignación, devolución, traslado o préstamo ITAM.
     *
     * @param array $validated
     * @param string|null $clientIp
     * @param string|null $userAgent
     * @return InventoryAssignment
     * @throws \Throwable
     */
    public function execute(array $validated, ?string $clientIp = null, ?string $userAgent = null): InventoryAssignment
    {
        $validated['provenance'] = $validated['provenance'] ?? 'stock';
        $assignedAt = !empty($validated['assigned_at']) ? Carbon::parse($validated['assigned_at']) : Carbon::now();
        $validated['assigned_at'] = $assignedAt;

        $asset = InventoryAsset::with(['components'])->findOrFail($validated['inventory_asset_id']);

        // Auto-resolver user_id si no vino en el formulario pero el usuario ya existe por Cédula o Nombre
        if ($validated['movement_type'] === 'assignment' && empty($validated['user_id'])) {
            $cedulaVal = trim((string)($validated['custodian_id_number'] ?? ''));
            $nameVal = trim((string)($validated['custodian_name'] ?? ''));
            $rawDigits = preg_replace('/\D/', '', $cedulaVal);

            $matchedUser = User::where(function ($q) use ($cedulaVal, $rawDigits, $nameVal) {
                $hasCond = false;
                if (!empty($cedulaVal)) {
                    $q->where('cedula', $cedulaVal);
                    if (!empty($rawDigits)) {
                        $q->orWhere('cedula', 'like', "%{$rawDigits}%");
                    }
                    $hasCond = true;
                }
                if (!empty($nameVal) && !in_array(strtolower($nameVal), ['usuario', 'operador', 'admin', 'user'], true)) {
                    if ($hasCond) {
                        $q->orWhere('name', $nameVal);
                    } else {
                        $q->where('name', $nameVal);
                    }
                }
            })->first();

            if ($matchedUser) {
                $validated['user_id'] = $matchedUser->id;
            }
        }

        // Generar correlativo oficial único
        $year = $assignedAt->format('Y');
        $prefix = ($validated['movement_type'] === 'return') ? "ACTA-DEV-{$year}" : "UINF-4.05-{$year}";
        $countThisYear = InventoryAssignment::where('assignment_code', 'like', "{$prefix}-%")->count() + 1;
        do {
            $code = sprintf("%s-%04d", $prefix, $countThisYear);
            $countThisYear++;
        } while (InventoryAssignment::where('assignment_code', $code)->exists());

        return DB::transaction(function () use ($validated, $asset, $code, $clientIp, $userAgent) {
            $cpuComponent = $asset->components->where('type', 'cpu')->first();
            $ramComponents = $asset->components->where('type', 'ram');
            $diskComponents = $asset->components->where('type', 'disk');

            $hardwareSpecs = [
                'processor' => $validated['specs']['processor'] ?? ($cpuComponent?->name ?? 'N/A'),
                'speed' => $validated['specs']['speed'] ?? 'N/A',
                'ram' => $validated['specs']['ram'] ?? ($ramComponents->isNotEmpty() ? $ramComponents->pluck('capacity')->implode(' + ') : 'N/A'),
                'disk' => $validated['specs']['disk'] ?? ($diskComponents->isNotEmpty() ? $diskComponents->pluck('capacity')->implode(' + ') : 'N/A'),
                'os' => $validated['specs']['os'] ?? ($asset->operating_system ?? 'N/A'),
                'device_name' => $validated['specs']['device_name'] ?? ($asset->device_name ?? $asset->name),
                'ip_address' => $validated['specs']['ip_address'] ?? ($asset->ip_address ?? 'N/A'),
            ];

            if ($validated['movement_type'] === 'assignment') {
                InventoryAssignment::where('inventory_asset_id', $asset->id)
                    ->where('status', 'active')
                    ->update(['status' => 'superseded']);
            } elseif ($validated['movement_type'] === 'return') {
                InventoryAssignment::where('inventory_asset_id', $asset->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'returned',
                        'returned_at' => Carbon::parse($validated['assigned_at']),
                    ]);
            }

            $assignment = InventoryAssignment::create([
                'assignment_code' => $code,
                'inventory_asset_id' => $asset->id,
                'user_id' => $validated['user_id'] ?? null,
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'] ?? null,
                'custodian_department' => $validated['custodian_department'] ?? null,
                'custodian_role' => $validated['custodian_role'] ?? null,
                'phone_extension' => $validated['phone_extension'] ?? null,
                'monitored_site_id' => $validated['monitored_site_id'] ?? $asset->monitored_site_id,
                'movement_type' => $validated['movement_type'],
                'provenance' => $validated['provenance'],
                'provenance_other' => $validated['provenance_other'] ?? null,
                'checkpoint_name' => $validated['checkpoint_name'] ?? ($asset->location ?? 'Centro de Servicio Valle Seco'),
                'gate_operator_name' => $validated['gate_operator_name'] ?? null,
                'departure_time' => $validated['departure_time'] ?? null,
                'entry_time' => $validated['entry_time'] ?? null,
                'control_number' => $validated['control_number'] ?? $code,
                'peripherals_json' => $validated['peripherals'] ?? [],
                'hardware_specs' => $hardwareSpecs,
                'assigned_at' => Carbon::parse($validated['assigned_at']),
                'returned_at' => ($validated['movement_type'] === 'return') ? Carbon::parse($validated['assigned_at']) : null,
                'status' => 'active',
                'delivered_by_user_id' => Auth::id(),
                'observations' => $validated['observations'] ?? null,
            ]);

            if ($validated['movement_type'] === 'assignment') {
                $asset->update([
                    'status' => 'assigned',
                    'current_user_id' => $validated['user_id'] ?? null,
                    'custodian_name' => $validated['custodian_name'],
                    'custodian_id_number' => $validated['custodian_id_number'] ?? null,
                    'custodian_department' => $validated['custodian_department'] ?? null,
                    'monitored_site_id' => $validated['monitored_site_id'] ?? $asset->monitored_site_id,
                ]);
            } elseif ($validated['movement_type'] === 'return') {
                $asset->update([
                    'status' => 'in_stock',
                    'current_user_id' => null,
                    'custodian_name' => null,
                    'custodian_id_number' => null,
                    'custodian_department' => null,
                ]);
            } elseif ($validated['movement_type'] === 'transfer') {
                $asset->update([
                    'monitored_site_id' => $validated['monitored_site_id'] ?? $asset->monitored_site_id,
                    'location' => $validated['checkpoint_name'] ?? $asset->location,
                ]);
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name ?? 'Sistema',
                'user_email' => Auth::user()?->email ?? 'system@local',
                'user_role' => Auth::user()?->role ?? 'admin',
                'event' => 'created',
                'module' => 'inventory',
                'auditable_type' => InventoryAssignment::class,
                'auditable_id' => $assignment->id,
                'entity_name' => $assignment->assignment_code,
                'entity_label' => $assignment->custodian_name,
                'description' => "Acta {$code} registrada para activo [{$asset->asset_tag}] ({$validated['movement_type']}) - Asignado a: {$validated['custodian_name']}",
                'new_values' => $assignment->toArray(),
                'ip_address' => $clientIp,
                'user_agent' => $userAgent,
            ]);

            if (class_exists(\App\Services\TelegramNotificationService::class)) {
                app(\App\Services\TelegramNotificationService::class)->notifyAssetAssignment($assignment);
            }

            return $assignment;
        });
    }
}
