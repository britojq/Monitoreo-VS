<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeripheralSynchronizerService
{
    public function sync(Request $request, InventoryAsset $asset): void
    {
        $groups = [
            [
                'type' => 'monitor',
                'brand' => trim((string)$request->input('monitor_brand', '')),
                'model' => trim((string)$request->input('monitor_model', '')),
                'serial' => trim((string)$request->input('monitor_serial', '')),
                'remove' => $request->boolean('remove_monitor'),
            ],
            [
                'type' => 'keyboard',
                'brand' => trim((string)$request->input('keyboard_brand', '')),
                'model' => trim((string)$request->input('keyboard_model', '')),
                'serial' => trim((string)$request->input('keyboard_serial', '')),
                'remove' => $request->boolean('remove_keyboard'),
            ],
            [
                'type' => 'mouse',
                'brand' => trim((string)$request->input('mouse_brand', '')),
                'model' => trim((string)$request->input('mouse_model', '')),
                'serial' => trim((string)$request->input('mouse_serial', '')),
                'remove' => $request->boolean('remove_mouse'),
            ],
            [
                'type' => in_array($request->input('protector_type'), ['ups', 'surge_protector'], true) ? $request->input('protector_type') : 'surge_protector',
                'match_types' => ['surge_protector', 'ups'],
                'brand' => trim((string)$request->input('protector_brand', '')),
                'model' => trim((string)$request->input('protector_model', '')),
                'serial' => trim((string)$request->input('protector_serial', '')),
                'remove' => $request->boolean('remove_protector'),
            ],
        ];

        foreach ($groups as $g) {
            $type = $g['type'];
            $matchTypes = $g['match_types'] ?? [$type];
            $brand = $g['brand'];
            $model = $g['model'];
            $serial = $g['serial'];
            $remove = $g['remove'];

            $existing = InventoryPeripheral::where('current_asset_id', $asset->id)
                ->whereIn('type', $matchTypes)
                ->first();

            $hasData = ($brand !== '' || $model !== '' || $serial !== '');

            if ($remove || (!$hasData && $existing)) {
                if ($existing) {
                    $oldData = [
                        'brand' => $existing->brand,
                        'model' => $existing->model,
                        'serial' => $existing->serial_number,
                    ];
                    $existing->update([
                        'current_asset_id' => null,
                        'status' => 'in_stock',
                    ]);
                    InventoryComponentHistory::create([
                        'inventory_asset_id' => $asset->id,
                        'action' => 'removed',
                        'component_type' => $existing->type,
                        'old_value' => $oldData,
                        'new_value' => null,
                        'reason' => 'Periférico desvinculado desde puesto',
                        'registered_by_user_id' => Auth::id(),
                        'recorded_at' => now(),
                    ]);
                }
            } elseif ($hasData) {
                $newData = [
                    'brand' => $brand ?: 'Genérico',
                    'model' => $model ?: ($type === 'monitor' ? 'Monitor Estándar' : ($type === 'keyboard' ? 'Teclado USB' : ($type === 'mouse' ? 'Mouse USB' : 'Protector Eléctrico'))),
                    'serial_number' => $serial ?: null,
                    'status' => 'assigned',
                    'current_asset_id' => $asset->id,
                    'monitored_site_id' => $asset->monitored_site_id,
                ];

                if ($existing) {
                    $existing->update($newData);
                } else {
                    $newData['asset_tag'] = strtoupper(substr($type, 0, 3)) . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                    $newData['type'] = $type;
                    InventoryPeripheral::create($newData);
                }
            }
        }

        // Periféricos adicionales dinámicos (impresoras, escáneres, etc.)
        $extraList = $request->input('extra_peripherals', []);
        if (is_array($extraList)) {
            foreach ($extraList as $extra) {
                if (!is_array($extra)) {
                    continue;
                }
                $extraId = !empty($extra['id']) ? (int)$extra['id'] : null;
                $extraType = !empty($extra['type']) ? trim((string)$extra['type']) : 'other_peripheral';
                $extraBrand = trim((string)($extra['brand'] ?? ''));
                $extraModel = trim((string)($extra['model'] ?? ''));
                $extraSerial = trim((string)($extra['serial'] ?? ''));
                $extraRemove = !empty($extra['remove']);

                if ($extraId) {
                    $existingExtra = InventoryPeripheral::where('id', $extraId)
                        ->where('current_asset_id', $asset->id)
                        ->first();
                    if ($existingExtra) {
                        if ($extraRemove) {
                            $oldData = [
                                'brand' => $existingExtra->brand,
                                'model' => $existingExtra->model,
                                'serial' => $existingExtra->serial_number,
                            ];
                            $existingExtra->update([
                                'current_asset_id' => null,
                                'status' => 'in_stock',
                            ]);
                            InventoryComponentHistory::create([
                                'inventory_asset_id' => $asset->id,
                                'action' => 'removed',
                                'component_type' => $existingExtra->type,
                                'old_value' => $oldData,
                                'new_value' => null,
                                'reason' => 'Periférico adicional desvinculado desde puesto',
                                'registered_by_user_id' => Auth::id(),
                                'recorded_at' => now(),
                            ]);
                        } else {
                            $hasData = ($extraBrand !== '' || $extraModel !== '' || $extraSerial !== '');
                            if ($hasData) {
                                $newData = [
                                    'brand' => $extraBrand ?: 'Genérico',
                                    'model' => $extraModel ?: 'Periférico',
                                    'serial_number' => $extraSerial ?: null,
                                ];
                                $existingExtra->update($newData);
                            }
                        }
                    }
                } else {
                    $hasData = ($extraBrand !== '' || $extraModel !== '' || $extraSerial !== '');
                    if ($hasData && !$extraRemove) {
                        $prefix = strtoupper(substr($extraType, 0, 3));
                        if (!in_array($prefix, ['PRN', 'SCN', 'HDS', 'OTH'], true)) {
                            $prefix = 'PER';
                        }
                        InventoryPeripheral::create([
                            'asset_tag' => $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                            'type' => $extraType,
                            'brand' => $extraBrand ?: 'Genérico',
                            'model' => $extraModel ?: 'Periférico',
                            'serial_number' => $extraSerial ?: null,
                            'status' => 'assigned',
                            'current_asset_id' => $asset->id,
                            'monitored_site_id' => $asset->monitored_site_id,
                        ]);
                    }
                }
            }
        }
    }
}
