<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\MonitoredSite;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

readonly class BulkMobilizeAction
{
    public function execute(Request $request): InventoryAssignment
    {
        $validated = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'exists:inventory_assets,id',
            'monitored_site_id' => 'required|exists:monitored_sites,id',
            'custodian_name' => 'required|string|max:128',
            'custodian_id_number' => 'nullable|string|max:32',
            'custodian_department' => 'nullable|string|max:128',
            'checkpoint_name' => 'nullable|string|max:128',
            'gate_operator_name' => 'nullable|string|max:128',
            'departure_time' => 'nullable|string|max:32',
            'entry_time' => 'nullable|string|max:32',
            'assigned_at' => 'nullable|date',
            'observations' => 'nullable|string',
        ], [
            'asset_ids.required' => 'Debe seleccionar al menos un equipo para movilizar.',
            'monitored_site_id.required' => 'La sede de destino es obligatoria.',
            'custodian_name.required' => 'El nombre del funcionario responsable del traslado es obligatorio.',
        ]);

        $assignedAt = !empty($validated['assigned_at']) ? Carbon::parse($validated['assigned_at']) : Carbon::now();
        $targetSite = MonitoredSite::findOrFail($validated['monitored_site_id']);
        $year = $assignedAt->format('Y');

        $prefix = "PASE-MOV-{$year}";
        $countThisYear = InventoryAssignment::where('assignment_code', 'like', "{$prefix}-%")->count() + 1;
        $code = sprintf("%s-%04d", $prefix, $countThisYear);

        return DB::transaction(function () use ($validated, $assignedAt, $targetSite, $code, $request) {
            $assets = InventoryAsset::with(['components', 'peripherals'])->whereIn('id', $validated['asset_ids'])->get();
            if ($assets->isEmpty()) {
                throw new \RuntimeException('No se encontraron los activos seleccionados.');
            }

            $primaryAsset = $assets->first();
            $peripheralsList = [];
            $includeStandardKit = $request->has('include_standard_kit')
                ? $request->boolean('include_standard_kit')
                : true;

            $collectPeripheralsForAsset = function ($asset) use (&$peripheralsList, $includeStandardKit) {
                $hasMonitor = false;
                $hasKeyboard = false;
                $hasMouse = false;
                $hasUps = false;

                // 1. Anexar periféricos hijos reales
                foreach ($asset->peripherals as $peripheral) {
                    $pType = strtoupper($peripheral->type ?? 'PERIFÉRICO');
                    if (str_contains($pType, 'MONITOR')) $hasMonitor = true;
                    if (str_contains($pType, 'TECLADO') || str_contains($pType, 'KEYBOARD')) $hasKeyboard = true;
                    if (str_contains($pType, 'MOUSE') || str_contains($pType, 'RATON')) $hasMouse = true;
                    if (str_contains($pType, 'PROTECTOR') || str_contains($pType, 'UPS') || str_contains($pType, 'REGULADOR')) $hasUps = true;

                    $serial = trim($peripheral->serial_number ?? '');
                    if (str_starts_with($serial, '[') || in_array(strtoupper($serial), ['S/N', 'N/A', 'SIN SERIAL', 'NO REPORTADO', 'SIN SERIAL REGISTRADO'], true)) {
                        $serial = '';
                    }

                    $typeMap = [
                        'PRINTER' => 'IMPRESORA',
                        'DESKTOP' => 'COMPUTADORA',
                        'LAPTOP' => 'PORTÁTIL',
                        'MONITOR' => 'MONITOR',
                        'KEYBOARD' => 'TECLADO',
                        'MOUSE' => 'MOUSE',
                        'SCANNER' => 'ESCÁNER',
                    ];
                    $pTypeClean = $typeMap[$pType] ?? $pType;

                    $peripheralsList[] = [
                        'type' => $pTypeClean,
                        'brand' => $peripheral->brand ?: '',
                        'model' => $peripheral->model ?: '',
                        'serial_number' => $serial,
                        'observations' => '',
                    ];
                }

                // 2. Anexar kit operativo estándar
                if ($includeStandardKit && in_array($asset->type, ['desktop', 'laptop', 'server', 'workstation'], true)) {
                    if (!$hasMonitor && $asset->type !== 'laptop') {
                        $peripheralsList[] = [
                            'type' => 'MONITOR',
                            'brand' => 'AOC / HP',
                            'model' => 'ESTÁNDAR',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                    if (!$hasKeyboard && $asset->type !== 'laptop') {
                        $peripheralsList[] = [
                            'type' => 'TECLADO',
                            'brand' => 'GENIUS / HP',
                            'model' => 'USB',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                    if (!$hasMouse && $asset->type !== 'laptop') {
                        $peripheralsList[] = [
                            'type' => 'MOUSE',
                            'brand' => 'GENIUS / HP',
                            'model' => 'ÓPTICO USB',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                    if (!$hasUps) {
                        $peripheralsList[] = [
                            'type' => 'PROTECTOR DE VOLTAJE',
                            'brand' => 'MARSRIVA / CDP',
                            'model' => 'AVR600',
                            'serial_number' => '',
                            'observations' => '',
                        ];
                    }
                }
            };

            // Recolectar periféricos del activo primario
            $collectPeripheralsForAsset($primaryAsset);

            // Para los activos secundarios: listar CPU y recolectar periféricos
            foreach ($assets->slice(1) as $extraAsset) {
                $extraSerial = trim($extraAsset->serial_number ?? '');
                if (str_starts_with($extraSerial, '[') || in_array(strtoupper($extraSerial), ['S/N', 'N/A', 'SIN SERIAL', 'NO REPORTADO'], true)) {
                    $extraSerial = '';
                }

                $peripheralsList[] = [
                    'type' => strtoupper($extraAsset->type_label ?? $extraAsset->type ?? 'EQUIPO'),
                    'brand' => $extraAsset->brand ?? '',
                    'model' => $extraAsset->model ?? '',
                    'serial_number' => $extraSerial,
                    'observations' => '',
                ];
                $collectPeripheralsForAsset($extraAsset);
            }

            $cpuComponent = $primaryAsset->components->where('type', 'cpu')->first();
            $ramComponents = $primaryAsset->components->where('type', 'ram');
            $diskComponents = $primaryAsset->components->where('type', 'disk');

            $hardwareSpecs = [
                'processor' => $cpuComponent?->name ?? 'N/A',
                'speed' => 'N/A',
                'ram' => $ramComponents->isNotEmpty() ? $ramComponents->pluck('capacity')->implode(' + ') : 'N/A',
                'disk' => $diskComponents->isNotEmpty() ? $diskComponents->pluck('capacity')->implode(' + ') : 'N/A',
                'os' => $primaryAsset->operating_system ?? 'N/A',
                'device_name' => $primaryAsset->device_name ?? $primaryAsset->name,
                'ip_address' => $primaryAsset->ip_address ?? 'N/A',
            ];

            $checkpointName = ($validated['checkpoint_name'] ?? null) ?: ($targetSite->name ?? 'Punto de Control Valle Seco');
            $departureTime = ($validated['departure_time'] ?? null) ?: Carbon::now()->format('H:i');

            $assignment = InventoryAssignment::create([
                'assignment_code' => $code,
                'inventory_asset_id' => $primaryAsset->id,
                'user_id' => Auth::id(),
                'custodian_name' => $validated['custodian_name'],
                'custodian_id_number' => $validated['custodian_id_number'] ?? null,
                'custodian_department' => $validated['custodian_department'] ?? null,
                'custodian_role' => null,
                'monitored_site_id' => $targetSite->id,
                'movement_type' => 'transfer',
                'provenance' => 'stock',
                'provenance_other' => null,
                'checkpoint_name' => $checkpointName,
                'gate_operator_name' => $validated['gate_operator_name'] ?? null,
                'departure_time' => $departureTime,
                'entry_time' => $validated['entry_time'] ?? null,
                'control_number' => $code,
                'peripherals_json' => $peripheralsList,
                'hardware_specs' => $hardwareSpecs,
                'assigned_at' => $assignedAt,
                'returned_at' => null,
                'status' => 'active',
                'delivered_by_user_id' => Auth::id(),
                'observations' => ($validated['observations'] ?? null) ?: "Movilización masiva de {$assets->count()} equipo(s) con destino a {$targetSite->name}.",
            ]);

            foreach ($assets as $asset) {
                $asset->update([
                    'monitored_site_id' => $targetSite->id,
                    'location_detail' => $checkpointName,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()->name ?? 'Sistema',
                    'user_email' => Auth::user()->email ?? 'system@local',
                    'user_role' => Auth::user()->role ?? 'admin',
                    'event' => 'updated',
                    'module' => 'inventory',
                    'auditable_type' => InventoryAsset::class,
                    'auditable_id' => $asset->id,
                    'entity_name' => $asset->asset_tag ?? $asset->name,
                    'entity_label' => $asset->display_identity,
                    'description' => "Equipo reubicado a la sede [{$targetSite->name}] mediante Pase de Movilización {$code}. Responsable: {$validated['custodian_name']}",
                    'new_values' => ['monitored_site_id' => $targetSite->id, 'location_detail' => $checkpointName],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Sistema',
                'user_email' => Auth::user()->email ?? 'system@local',
                'user_role' => Auth::user()->role ?? 'admin',
                'event' => 'created',
                'module' => 'inventory',
                'auditable_type' => InventoryAssignment::class,
                'auditable_id' => $assignment->id,
                'entity_name' => $assignment->assignment_code,
                'entity_label' => $assignment->custodian_name,
                'description' => "Pase de Movilización Masiva {$code} emitido para {$assets->count()} equipo(s) hacia {$targetSite->name} - Responsable: {$validated['custodian_name']}",
                'new_values' => $assignment->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if (class_exists(TelegramNotificationService::class)) {
                app(TelegramNotificationService::class)->notifyAssetAssignment($assignment);
            }

            return $assignment;
        });
    }
}
