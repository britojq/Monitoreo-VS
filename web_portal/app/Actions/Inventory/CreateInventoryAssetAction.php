<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\Repositories\InventoryAssetRepositoryInterface;
use App\DTOs\InventoryAssetData;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredNetworkDevice;
use Illuminate\Support\Facades\Auth;

readonly class CreateInventoryAssetAction
{
    public function __construct(
        private InventoryAssetRepositoryInterface $repository
    ) {}

    public function execute(InventoryAssetData $dto, array $extraInput = []): InventoryAsset
    {
        $asset = $this->repository->create($dto);

        // Si es PC e indicó incluir periféricos de puesto de trabajo (o se proveyeron datos de periféricos)
        $hasPeripherals = !empty($extraInput['include_peripherals'])
            || ($extraInput['scope_option'] ?? '') === 'pc_bundle'
            || !empty($extraInput['monitor_brand'])
            || !empty($extraInput['monitor_serial'])
            || !empty($extraInput['keyboard_brand'])
            || !empty($extraInput['mouse_brand'])
            || !empty($extraInput['surge_protector_brand']);

        if ($hasPeripherals) {
            $this->createPeripheralsBundle($asset, $extraInput);
        }

        // Si es un dispositivo de red y se proveyó IP, registrar/vincular en monitoreo de red
        if (in_array($asset->type, ['router', 'switch', 'server', 'printer', 'network'], true) && !empty($asset->ip_address)) {
            $siteId = $asset->monitored_site_id ?: 1;
            $nextDevNum = (MonitoredNetworkDevice::where('monitored_site_id', $siteId)->max('device_number') ?? 0) + 1;
            $netDev = MonitoredNetworkDevice::firstOrCreate(
                ['ip' => $asset->ip_address],
                [
                    'monitored_site_id' => $siteId,
                    'device_number' => $nextDevNum,
                    'name' => $asset->name,
                    'mac' => $asset->mac_address,
                    'model' => $asset->model,
                    'serial' => $asset->serial_number ?: null,
                    'ports' => $extraInput['ports'] ?? null,
                    'access_type' => $extraInput['access_type'] ?? 'SIN SOPORTE',
                    'access_port' => $extraInput['access_port'] ?? null,
                    'notes' => $asset->notes ?: 'Creado desde Inventario ITAM',
                    'is_active' => true,
                ]
            );
            $asset->update(['monitored_network_device_id' => $netDev->id]);
        }

        // Auditoría
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'created',
            'module' => 'inventory',
            'auditable_type' => InventoryAsset::class,
            'auditable_id' => $asset->id,
            'entity_name' => $asset->name,
            'entity_label' => $asset->asset_tag,
            'description' => "Activo ITAM creado manualmente: {$asset->name} (Tag: {$asset->asset_tag})",
            'ip_address' => request()->ip(),
        ]);

        return $asset;
    }

    private function createPeripheralsBundle(InventoryAsset $asset, array $extraInput): void
    {
        // 1. Monitor
        if (!empty($extraInput['monitor_brand']) || !empty($extraInput['monitor_model']) || !empty($extraInput['monitor_serial'])) {
            InventoryPeripheral::create([
                'asset_tag' => 'MON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'type' => 'monitor',
                'brand' => $extraInput['monitor_brand'] ?? 'Genérico',
                'model' => $extraInput['monitor_model'] ?? 'Monitor Estándar',
                'serial_number' => $extraInput['monitor_serial'] ?? null,
                'status' => 'assigned',
                'current_asset_id' => $asset->id,
                'monitored_site_id' => $asset->monitored_site_id,
            ]);
        }

        // 2. Teclado
        if (!empty($extraInput['keyboard_brand']) || !empty($extraInput['keyboard_serial'])) {
            InventoryPeripheral::create([
                'asset_tag' => 'KBD-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'type' => 'keyboard',
                'brand' => $extraInput['keyboard_brand'] ?? 'Genérico',
                'model' => 'Teclado Estándar USB',
                'serial_number' => $extraInput['keyboard_serial'] ?? null,
                'status' => 'assigned',
                'current_asset_id' => $asset->id,
                'monitored_site_id' => $asset->monitored_site_id,
            ]);
        }

        // 3. Mouse
        if (!empty($extraInput['mouse_brand']) || !empty($extraInput['mouse_serial'])) {
            InventoryPeripheral::create([
                'asset_tag' => 'MSE-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'type' => 'mouse',
                'brand' => $extraInput['mouse_brand'] ?? 'Genérico',
                'model' => 'Mouse Óptico USB',
                'serial_number' => $extraInput['mouse_serial'] ?? null,
                'status' => 'assigned',
                'current_asset_id' => $asset->id,
                'monitored_site_id' => $asset->monitored_site_id,
            ]);
        }

        // 4. Protector de Voltaje
        if (!empty($extraInput['surge_protector_brand']) || !empty($extraInput['surge_protector_serial'])) {
            InventoryPeripheral::create([
                'asset_tag' => 'PWR-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'type' => 'surge_protector',
                'brand' => $extraInput['surge_protector_brand'] ?? 'Genérico',
                'model' => 'Protector de Voltaje / Regulador',
                'serial_number' => $extraInput['surge_protector_serial'] ?? null,
                'status' => 'assigned',
                'current_asset_id' => $asset->id,
                'monitored_site_id' => $asset->monitored_site_id,
            ]);
        }
    }
}
