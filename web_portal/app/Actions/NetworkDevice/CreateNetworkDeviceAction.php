<?php

declare(strict_types=1);

namespace App\Actions\NetworkDevice;

use App\Contracts\Repositories\NetworkDeviceRepositoryInterface;
use App\DTOs\NetworkDeviceData;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredNetworkDevice;
use Illuminate\Http\Request;
use App\Services\Inventory\PeripheralSynchronizerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

readonly class CreateNetworkDeviceAction
{
    public function __construct(
        private NetworkDeviceRepositoryInterface $repository,
        private PeripheralSynchronizerService $peripheralSync,
    ) {}

    public function execute(NetworkDeviceData $dto, Request $request): MonitoredNetworkDevice
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

        $typeInput = $request->input('type');
        $isPassive = in_array($typeInput, ['monitor', 'keyboard', 'mouse', 'headset', 'scanner', 'surge_protector', 'other_peripheral'], true);

        if ($isPassive || empty($payload['ip'])) {
            $payload['is_active'] = false;
        }

        $nextNum = $this->repository->getNextDeviceNumber((int) $payload['monitored_site_id']);
        $payload['device_number'] = $nextNum;
        $payload['sort_order'] = MonitoredNetworkDevice::count() + 1;

        if ($request->filled('ssh_password')) {
            $payload['ssh_password_encrypted'] = $request->input('ssh_password');
        }
        if ($request->filled('ssh_enable_secret')) {
            $payload['ssh_enable_secret_encrypted'] = $request->input('ssh_enable_secret');
        }
        if ($request->filled('vnc_password')) {
            $payload['vnc_password_encrypted'] = $request->input('vnc_password');
        }

        $cleanDto = NetworkDeviceData::fromArray($payload);
        $device = $this->repository->create($cleanDto);

        // 2. Determinar tipo canónico para censo de activos
        $type = $typeInput;
        if (empty($type)) {
            $upper = strtoupper($device->name . ' ' . ($device->model ?? ''));
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

        $custodianName = $request->input('custodian_name');
        $custodianIdNumber = $request->input('custodian_id_number');
        $custodianDept = $request->input('custodian_department');
        $custodianRole = $request->input('custodian_role');
        $userId = $request->input('user_id');

        $initialStatus = $request->input('status') ?? ($isPassive ? 'in_stock' : (!empty($custodianName) ? 'assigned' : 'in_stock'));
        if (!empty($custodianName)) {
            $initialStatus = 'assigned';
        }

        InventoryAsset::where('id', $device->id)->update([
            'type' => $type,
            'status' => $initialStatus,
            'custodian_name' => !empty($custodianName) ? $custodianName : null,
            'custodian_id_number' => !empty($custodianIdNumber) ? $custodianIdNumber : null,
            'custodian_department' => !empty($custodianDept) ? $custodianDept : null,
            'current_user_id' => !empty($userId) ? $userId : null,
            'monitored_network_device_id' => $device->id,
            'location_detail' => $device->site?->name ?? 'Sede Regional',
        ]);

        // 3. Si tiene asignación inicial formal, registrar acta en InventoryAssignment
        if (!empty($custodianName)) {
            try {
                $year = now()->format('Y');
                $prefix = "UINF-4.05-{$year}";
                $countThisYear = InventoryAssignment::where('assignment_code', 'like', "{$prefix}-%")->count() + 1;
                do {
                    $code = sprintf("%s-%04d", $prefix, $countThisYear);
                    $countThisYear++;
                } while (InventoryAssignment::where('assignment_code', $code)->exists());

                InventoryAssignment::create([
                    'assignment_code' => $code,
                    'inventory_asset_id' => $device->id,
                    'user_id' => !empty($userId) ? $userId : null,
                    'custodian_name' => $custodianName,
                    'custodian_id_number' => $custodianIdNumber,
                    'custodian_department' => $custodianDept,
                    'custodian_role' => $custodianRole,
                    'monitored_site_id' => $device->monitored_site_id,
                    'movement_type' => 'assignment',
                    'provenance' => 'nuevo',
                    'checkpoint_name' => $device->site?->name ?? 'Valle Seco',
                    'control_number' => $code,
                    'assigned_at' => now(),
                    'status' => 'active',
                    'delivered_by_user_id' => Auth::id(),
                    'observations' => 'Asignación inicial registrada en la creación del dispositivo.',
                ]);
            } catch (\Throwable $e) {
                Log::warning("Fallo al registrar InventoryAssignment inicial para {$device->name}: " . $e->getMessage());
            }
        }

        // 4. Si es periférico de puesto, reflejarlo en inventory_peripherals
        if (in_array($type, ['monitor', 'keyboard', 'mouse', 'headset', 'scanner', 'surge_protector'], true)) {
            InventoryPeripheral::create([
                'asset_tag' => 'PER-' . str_pad((string) $device->id, 5, '0', STR_PAD_LEFT),
                'type' => $type,
                'brand' => $device->vendor_data,
                'model' => $device->model,
                'serial_number' => $device->serial,
                'status' => $initialStatus,
                'monitored_site_id' => $device->monitored_site_id,
                'notes' => $device->notes,
            ]);
        }

        // 5. Sincronizar periféricos asociados al puesto (Monitor, Teclado, Mouse, Protector/UPS + Extras)
        $asset = InventoryAsset::find($device->id);
        if ($asset && ($request->has('has_peripherals_form') || $request->hasAny(['monitor_brand', 'keyboard_brand', 'mouse_brand', 'protector_brand', 'extra_peripherals']))) {
            $this->peripheralSync->sync($request, $asset);
        }

        // 6. Auditoría
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'created',
            'module' => 'network_devices',
            'auditable_type' => MonitoredNetworkDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => $device->ip,
            'new_values' => $device->toArray(),
            'description' => "Dispositivo registrado en monitoreo de red y censo ITAM: {$device->name} ({$device->ip})",
            'ip_address' => $request->ip(),
        ]);

        return $device;
    }
}
