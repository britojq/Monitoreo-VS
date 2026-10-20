<?php

declare(strict_types=1);

namespace App\Actions\NetworkDevice;

use App\Contracts\Repositories\NetworkDeviceRepositoryInterface;
use App\DTOs\NetworkDeviceData;
use App\Http\Controllers\Admin\SyncController;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponentHistory;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredNetworkDevice;
use App\Services\Inventory\PeripheralSynchronizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

readonly class UpdateNetworkDeviceAction
{
    public function __construct(
        private NetworkDeviceRepositoryInterface $repository,
        private PeripheralSynchronizerService $peripheralSync,
    ) {}

    public function execute(MonitoredNetworkDevice $device, NetworkDeviceData $dto, Request $request): MonitoredNetworkDevice
    {
        $payload = $dto->toArray();

        // 1. Resolución de puertos según access_type
        $accessType = strtoupper(trim($dto->accessType));
        $accessPort = $dto->accessPort;

        if ($accessType === 'SSH+VNC') {
            $payload['ssh_port'] = $dto->sshPort ?? ($accessPort ?? 22);
            $payload['vnc_port'] = $dto->vncPort ?? 5900;
            $payload['access_port'] = $payload['ssh_port'];
        } elseif ($accessType === 'SSH') {
            $payload['ssh_port'] = $dto->sshPort ?? ($accessPort ?? 22);
            $payload['access_port'] = $payload['ssh_port'];
        } elseif ($accessType === 'VNC') {
            $payload['vnc_port'] = $dto->vncPort ?? ($accessPort ?? 5900);
            $payload['access_port'] = $payload['vnc_port'];
        } elseif ($accessType === 'TELNET') {
            $payload['telnet_port'] = $dto->telnetPort ?? ($accessPort ?? 23);
            $payload['access_port'] = $payload['telnet_port'];
        } elseif ($accessType === 'WEB') {
            $payload['web_port'] = $dto->webPort ?? ($accessPort ?? 80);
            $payload['access_port'] = $payload['web_port'];
        } else {
            $payload['access_port'] = null;
        }

        if ($request->filled('ssh_password')) {
            $payload['ssh_password_encrypted'] = $request->input('ssh_password');
        }
        if ($request->filled('ssh_enable_secret')) {
            $payload['ssh_enable_secret_encrypted'] = $request->input('ssh_enable_secret');
        }
        if ($request->boolean('clear_ssh_credentials')) {
            $payload['ssh_username'] = null;
            $payload['ssh_password_encrypted'] = null;
            $payload['ssh_enable_secret_encrypted'] = null;
        }
        if ($request->filled('vnc_password')) {
            $payload['vnc_password_encrypted'] = $request->input('vnc_password');
        }
        if ($request->boolean('clear_vnc_credentials')) {
            $payload['vnc_password_encrypted'] = null;
        }

        $oldSerial = trim((string)($device->serial ?? ''));
        $newSerial = trim((string)($payload['serial'] ?? ''));

        $cleanDto = NetworkDeviceData::fromArray($payload);
        $updatedDevice = $this->repository->update($device->id, $cleanDto);

        $asset = InventoryAsset::find($device->id);

        // 2. Determinar tipo canónico para censo de activos
        $type = $request->input('type') ?: ($asset?->type ?: null);
        if (empty($type)) {
            $upper = strtoupper($updatedDevice->name . ' ' . ($updatedDevice->model ?? ''));
            $type = 'desktop';
            if (preg_match('/\bROUTER\b/i', $upper)) {
                $type = 'router';
            } elseif (preg_match('/\b(SWITCH|CONMUTADOR)\b/i', $upper) || preg_match('/\bSW\d+\b/i', $upper)) {
                $type = 'switch';
            } elseif (preg_match('/\b(IMPRESORA|PRINTER)\b/i', $upper)) {
                $type = 'printer';
            } elseif (preg_match('/\b(SERVIDOR|SERVER)\b/i', $upper)) {
                $type = 'server';
            }
        }

        // 3. Auditoría inmutable de Serial
        if ($newSerial !== '' && $newSerial !== $oldSerial) {
            $hasGlpi = $asset && ($asset->last_glpi_sync_at !== null);
            InventoryComponentHistory::create([
                'inventory_asset_id' => $device->id,
                'action' => 'modified',
                'component_type' => 'serial',
                'old_value' => ['serial' => $oldSerial ?: 'Sin Serial'],
                'new_value' => ['serial' => $newSerial],
                'reason' => 'Modificación manual de número de serie por el administrador' . ($hasGlpi ? ' (⚠️ Advertencia: Dispositivo con agente GLPI activo)' : ''),
                'registered_by_user_id' => Auth::id(),
                'recorded_at' => now(),
            ]);
        }

        $assetUpdate = [
            'type' => $type,
            'monitored_site_id' => $updatedDevice->monitored_site_id,
            'name' => $updatedDevice->name,
            'ip_address' => $updatedDevice->ip,
            'mac_address' => $updatedDevice->mac,
            'brand' => $updatedDevice->vendor_data,
            'model' => $updatedDevice->model,
            'serial_number' => $updatedDevice->serial,
            'notes' => $updatedDevice->notes,
        ];

        $assignmentAction = $request->input('assignment_action', 'keep');
        $activeAssignment = $asset ? $asset->active_assignment : null;

        // Si se enviaron periféricos en el formulario, sincronizarlos primero para tener la foto actualizada
        if ($asset && ($request->has('has_peripherals_form') || $request->hasAny(['monitor_brand', 'keyboard_brand', 'mouse_brand', 'protector_brand', 'remove_monitor', 'remove_keyboard', 'remove_mouse', 'remove_protector', 'extra_peripherals']))) {
            $this->peripheralSync->sync($request, $asset);
            $asset->load('peripherals');
        }

        if ($assignmentAction === 'release') {
            $assetUpdate['status'] = 'in_stock';
            $assetUpdate['custodian_name'] = null;
            $assetUpdate['custodian_id_number'] = null;
            $assetUpdate['custodian_department'] = null;
            $assetUpdate['current_user_id'] = null;

            if ($activeAssignment) {
                $activeAssignment->update([
                    'status' => 'superseded',
                    'returned_at' => now(),
                ]);
            }

            try {
                $year = now()->format('Y');
                $count = InventoryAssignment::where('assignment_code', 'like', "ACTA-DEV-{$year}-%")->count() + 1;
                do {
                    $code = sprintf("ACTA-DEV-%s-%04d", $year, $count);
                    $count++;
                } while (InventoryAssignment::where('assignment_code', $code)->exists());

                InventoryAssignment::create([
                    'assignment_code' => $code,
                    'inventory_asset_id' => $device->id,
                    'movement_type' => 'return',
                    'custodian_name' => $asset?->custodian_name ?? 'Almacén / Stock',
                    'monitored_site_id' => $updatedDevice->monitored_site_id,
                    'provenance' => 'stock',
                    'assigned_at' => now(),
                    'returned_at' => now(),
                    'status' => 'active',
                    'delivered_by_user_id' => Auth::id(),
                    'observations' => 'Devolución a stock ejecutada desde edición de dispositivo.',
                ]);
            } catch (\Throwable $e) {
                Log::warning("Fallo al registrar devolución en InventoryAssignment para {$updatedDevice->name}: " . $e->getMessage());
            }
        } elseif ($assignmentAction === 'assign' && $request->filled('custodian_name')) {
            $custodianName = trim((string)$request->input('custodian_name'));
            $custodianIdNumber = trim((string)$request->input('custodian_id_number'));
            $custodianDept = trim((string)$request->input('custodian_department'));
            $custodianRole = trim((string)$request->input('custodian_role'));
            $userId = $request->input('user_id');

            $assetUpdate['status'] = 'assigned';
            $assetUpdate['custodian_name'] = $custodianName;
            $assetUpdate['custodian_id_number'] = $custodianIdNumber;
            $assetUpdate['custodian_department'] = $custodianDept;
            $assetUpdate['current_user_id'] = $userId;

            // Snapshot exhaustivo de periféricos actuales asociados
            $currentPeripherals = $asset ? $asset->peripherals()->where('status', 'assigned')->get() : collect();
            $peripheralsSnapshot = $currentPeripherals->map(fn($p) => [
                'type' => strtoupper($p->type),
                'brand' => $p->brand,
                'model' => $p->model,
                'serial_number' => $p->serial_number,
                'observations' => $p->notes ?: 'Periférico asignado al puesto',
            ])->values()->toArray();

            $cpuComponent = $asset?->components->where('type', 'cpu')->first();
            $ramComponents = $asset?->components->where('type', 'ram') ?? collect();
            $diskComponents = $asset?->components->where('type', 'disk') ?? collect();

            $hardwareSpecs = [
                'processor' => $cpuComponent?->name ?? 'Intel Core i5',
                'speed' => '3.4 GHz',
                'ram' => $ramComponents->isNotEmpty() ? $ramComponents->pluck('capacity')->implode(' + ') : '4 GB',
                'disk' => $diskComponents->isNotEmpty() ? $diskComponents->pluck('capacity')->implode(' + ') : '500 GB',
                'os' => $asset?->operating_system ?? ($updatedDevice->os ?? 'Windows 7'),
                'device_name' => $updatedDevice->name,
                'ip_address' => $updatedDevice->ip ?? 'N/A',
            ];

            // DISCRIMINACIÓN INTELIGENTE: ¿Corrección de erratas/datos del acta activa o reasignación a otro titular?
            $isSamePersonOrCorrection = false;
            if ($activeAssignment) {
                $cleanOldCed = preg_replace('/\D/', '', (string)($activeAssignment->custodian_id_number ?? ''));
                $cleanNewCed = preg_replace('/\D/', '', $custodianIdNumber);

                if (!empty($cleanOldCed) && !empty($cleanNewCed) && ($cleanOldCed === $cleanNewCed || str_contains($cleanNewCed, $cleanOldCed) || str_contains($cleanOldCed, $cleanNewCed))) {
                    $isSamePersonOrCorrection = true;
                } else {
                    similar_text(mb_strtoupper((string)($activeAssignment->custodian_name ?? '')), mb_strtoupper($custodianName), $nameSim);
                    if ($nameSim >= 60) {
                        $isSamePersonOrCorrection = true;
                    }
                }
            }

            if ($activeAssignment && $isSamePersonOrCorrection) {
                // MODO CORRECCIÓN: Actualizar metadatos del acta activa existente preservando correlativo y archivo PDF
                $updateData = [
                    'custodian_name' => $custodianName,
                    'custodian_id_number' => $custodianIdNumber,
                    'custodian_department' => $custodianDept ?: $activeAssignment->custodian_department,
                    'custodian_role' => $custodianRole ?: $activeAssignment->custodian_role,
                    'user_id' => $userId ?: $activeAssignment->user_id,
                    'monitored_site_id' => $updatedDevice->monitored_site_id,
                ];

                if (empty($activeAssignment->peripherals_json) && !empty($peripheralsSnapshot)) {
                    $updateData['peripherals_json'] = $peripheralsSnapshot;
                }
                if (empty($activeAssignment->hardware_specs) || ($activeAssignment->hardware_specs['processor'] ?? '') === 'N/A') {
                    $updateData['hardware_specs'] = $hardwareSpecs;
                }

                $activeAssignment->update($updateData);
            } else {
                // MODO REASIGNACIÓN FORMAL A NUEVA PERSONA:
                if ($activeAssignment) {
                    $activeAssignment->update(['status' => 'superseded', 'returned_at' => now()]);
                }

                try {
                    $year = now()->format('Y');
                    $count = InventoryAssignment::where('assignment_code', 'like', "UINF-4.05-{$year}-%")->count() + 1;
                    do {
                        $code = sprintf("UINF-4.05-%s-%04d", $year, $count);
                        $count++;
                    } while (InventoryAssignment::where('assignment_code', $code)->exists());

                    InventoryAssignment::create([
                        'assignment_code' => $code,
                        'inventory_asset_id' => $device->id,
                        'user_id' => $userId,
                        'custodian_name' => $custodianName,
                        'custodian_id_number' => $custodianIdNumber,
                        'custodian_department' => $custodianDept,
                        'custodian_role' => $custodianRole,
                        'monitored_site_id' => $updatedDevice->monitored_site_id,
                        'movement_type' => 'assignment',
                        'provenance' => 'stock',
                        'assigned_at' => now(),
                        'status' => 'active',
                        'delivered_by_user_id' => Auth::id(),
                        'peripherals_json' => $peripheralsSnapshot,
                        'hardware_specs' => $hardwareSpecs,
                        'observations' => 'Asignación registrada desde edición de dispositivo.',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Fallo al registrar InventoryAssignment para {$updatedDevice->name}: " . $e->getMessage());
                }
            }
        } elseif ($request->filled('status')) {
            $assetUpdate['status'] = $request->input('status');
        }

        if ($asset) {
            $asset->update($assetUpdate);
        }

        app(SyncController::class)->exportToConfigFiles();

        return $updatedDevice;
    }
}
