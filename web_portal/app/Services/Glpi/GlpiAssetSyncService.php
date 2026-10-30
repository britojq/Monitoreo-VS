<?php

namespace App\Services\Glpi;

use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Services\TelegramNotificationService;

/**
 * Servicio de Sincronización y Persistencia de Activos ITAM.
 * Responsabilidad Única (S): Cotejo contra MariaDB como fuente centralizada, protección del Nombre Etiqueta y vinculación con red.
 */
class GlpiAssetSyncService
{
    protected GlpiHardwareDiffEngine $diffEngine;
    protected ?TelegramNotificationService $notifier;

    public function __construct(
        ?GlpiHardwareDiffEngine $diffEngine = null,
        ?TelegramNotificationService $notifier = null
    ) {
        $this->diffEngine = $diffEngine ?? new GlpiHardwareDiffEngine(new GlpiHardwareNormalizer());
        $this->notifier = $notifier ?? (function_exists('app') && app()->has(TelegramNotificationService::class) ? app(TelegramNotificationService::class) : null);
    }

    /**
     * Sincroniza o crea el activo de inventario, audita componentes y notifica eventos.
     */
    public function sync(array $norm): array
    {
        $asset = null;

        // Prioridad 1: Coincidencia por Serial Number
        if (!empty($norm['serial_number'])) {
            $asset = InventoryAsset::where('serial_number', $norm['serial_number'])->first();
        }

        // Prioridad 2: Coincidencia por MAC Address
        if (!$asset && !empty($norm['mac_address'])) {
            $asset = InventoryAsset::where('mac_address', $norm['mac_address'])->first();
        }

        // Prioridad 3: Coincidencia por Dirección IP
        if (!$asset && !empty($norm['ip_address']) && $norm['ip_address'] !== '127.0.0.1') {
            $asset = InventoryAsset::where('ip_address', $norm['ip_address'])->first();
        }

        // Prioridad 4: Coincidencia por Nombre
        if (!$asset && !empty($norm['name'])) {
            $asset = InventoryAsset::where('name', $norm['name'])->first();
        }

        $osLower = strtolower($norm['operating_system'] ?? '');
        $inferredAccessType = null;
        $inferredAccessPort = null;
        $inferredSshPort = null;
        $inferredVncPort = null;
        $inferredWebPort = null;
        if (($norm['type'] ?? null) === 'printer') {
            $inferredAccessType = 'WEB';
            $inferredAccessPort = 80;
            $inferredWebPort = 80;
        } elseif (str_contains($osLower, 'linux') || str_contains($osLower, 'ubuntu') || str_contains($osLower, 'debian') || str_contains($osLower, 'centos') || str_contains($osLower, 'redhat')) {
            $inferredAccessType = 'SSH+VNC';
            $inferredAccessPort = 22;
            $inferredSshPort = 22;
            $inferredVncPort = 5900;
        } elseif (str_contains($osLower, 'windows')) {
            $inferredAccessType = 'VNC';
            $inferredAccessPort = 5900;
            $inferredVncPort = 5900;
        }

        $isNew = false;
        if (!$asset) {
            $isNew = true;
            $assetData = [
                'name' => $norm['name'],
                'asset_tag' => $norm['asset_tag'] ?? null,
                'location_detail' => $norm['location_detail'] ?? null,
                'serial_number' => $norm['serial_number'] ?? null,
                'mac_address' => $norm['mac_address'] ?? null,
                'ip_address' => $norm['ip_address'] ?? null,
                'brand' => $norm['brand'] ?? null,
                'model' => $norm['model'] ?? null,
                'type' => $norm['type'] ?? 'desktop',
                'status' => 'unauthorized', // Queda como descubierto para aprobación
                'operating_system' => $norm['operating_system'] ?? null,
                'os_kernel' => $norm['os_kernel'] ?? null,
                'last_glpi_sync_at' => now(),
            ];
            if ($inferredAccessType) {
                $assetData['access_type'] = $inferredAccessType;
                $assetData['access_port'] = $inferredAccessPort;
                $assetData['ssh_port'] = $inferredSshPort;
                $assetData['vnc_port'] = $inferredVncPort;
                $assetData['web_port'] = $inferredWebPort;
            }
            $asset = InventoryAsset::create($assetData);

            // Notificar nuevo equipo descubierto al Owner
            if (config('inventory.notify_telegram', true) && $this->notifier) {
                $this->notifier->notifyGlpiNewAsset($asset);
            }
        } else {
            $updates = [
                'last_glpi_sync_at' => now(),
            ];

            if ($inferredAccessType && (empty($asset->access_type) || $asset->access_type === 'SIN SOPORTE')) {
                $updates['access_type'] = $inferredAccessType;
                $updates['access_port'] = $inferredAccessPort;
                $updates['ssh_port'] = $inferredSshPort;
                $updates['vnc_port'] = $inferredVncPort;
                $updates['web_port'] = $inferredWebPort;
            }

            if (!empty($norm['asset_tag']) && empty($asset->asset_tag)) {
                $updates['asset_tag'] = $norm['asset_tag'];
            }
            if (!empty($norm['location_detail']) && empty($asset->location_detail)) {
                $updates['location_detail'] = $norm['location_detail'];
            }

            if (!empty($norm['ip_address']) && $norm['ip_address'] !== '127.0.0.1') {
                $updates['ip_address'] = $norm['ip_address'];
            }
            if (!empty($norm['mac_address'])) {
                $updates['mac_address'] = $norm['mac_address'];
            }
            if (!empty($norm['brand']) && (empty($asset->brand) || $asset->brand === 'Marca Genérica' || str_contains($asset->brand, 'VMware') || str_contains($asset->brand, 'Linux'))) {
                $updates['brand'] = $norm['brand'];
            }
            if (!empty($norm['model']) && (empty($asset->model) || str_contains(strtolower($asset->model), 'virtual') || str_contains(strtolower($asset->model), 'generico') || str_contains(strtolower($asset->model), 'sin modelo'))) {
                $updates['model'] = $norm['model'];
            }
            if (!empty($norm['serial_number']) && (empty($asset->serial_number) || str_starts_with($asset->serial_number, 'SN-NET-') || str_starts_with($asset->serial_number, 'SN-GEN-'))) {
                $updates['serial_number'] = $norm['serial_number'];
            }
            if (!empty($norm['has_real_os'])) {
                $updates['operating_system'] = $norm['operating_system'];
            }
            if (!empty($norm['os_kernel'])) {
                $updates['os_kernel'] = $norm['os_kernel'];
            }

            $asset->update($updates);
        }

        // Auto-vincular con dispositivo de monitoreo si coincide por IP o MAC (Regla de Oro #8)
        $netDev = $asset->monitored_network_device_id ? MonitoredNetworkDevice::find($asset->monitored_network_device_id) : null;
        if (!$netDev) {
            if (!empty($norm['ip_address']) && $norm['ip_address'] !== '127.0.0.1') {
                $netDev = MonitoredNetworkDevice::where('ip', $norm['ip_address'])->first();
            }
            if (!$netDev && !empty($norm['mac_address'])) {
                $netDev = MonitoredNetworkDevice::where('mac', $norm['mac_address'])->first();
            }
        }

        if ($netDev) {
            $netUpdates = [];
            if ($inferredAccessType && (empty($netDev->access_type) || $netDev->access_type === 'SIN SOPORTE')) {
                $netUpdates['access_type'] = $inferredAccessType;
                $netUpdates['access_port'] = $inferredAccessPort;
                $netUpdates['ssh_port'] = $inferredSshPort;
                $netUpdates['vnc_port'] = $inferredVncPort;
            }
            if (!empty($norm['brand']) && (empty($netDev->vendor_data) || str_contains($netDev->vendor_data, 'VMware') || str_contains($netDev->vendor_data, 'Linux'))) {
                $netUpdates['vendor_data'] = $norm['brand'];
            }
            if (!empty($norm['model']) && (empty($netDev->model) || str_contains(strtolower($netDev->model), 'virtual') || str_contains(strtolower($netDev->model), 'generico'))) {
                $netUpdates['model'] = $norm['model'];
            }
            if (!empty($norm['serial_number']) && (empty($netDev->serial) || str_starts_with($netDev->serial, 'SN-NET-'))) {
                $netUpdates['serial'] = $norm['serial_number'];
            }
            if (!empty($norm['mac_address']) && empty($netDev->mac)) {
                $netUpdates['mac'] = $norm['mac_address'];
            }
            if (!empty($netUpdates)) {
                $netDev->update($netUpdates);
            }
            if (!$asset->monitored_network_device_id) {
                $asset->update([
                    'monitored_network_device_id' => $netDev->id,
                    'monitored_site_id' => $asset->monitored_site_id ?: $netDev->monitored_site_id,
                ]);
            }
        }

        // REGLA DE ORO #7 (errores-cometidos.md):
        // NOMBRE ETIQUETA es la identidad operativa visible (ej. JEFE DE OFICINA, TAQUILLA 01).
        // NUNCA debe ser sobrescrito por el hostname técnico reportado por GLPI.
        if (!empty($norm['name'])) {
            $hostname = trim($norm['name']);
            $notes = $asset->notes ?? '';
            if (!str_contains($notes, "Hostname NetBIOS: {$hostname}") && !str_contains($notes, "Hostname: {$hostname}")) {
                $asset->update(['notes' => trim($notes . " | Hostname NetBIOS: {$hostname}")]);
            }
            
            // Solo si el activo fue recién descubierto y tiene un nombre temporal autogenerado tipo GLPI-DISCOVERED-
            if (str_starts_with($asset->name, 'GLPI-DISCOVERED-')) {
                $asset->update(['name' => $hostname]);
            }
        }

        // Procesar componentes y auditar cambios
        $changes = $this->diffEngine->diffAndSyncComponents($asset, $norm, $isNew);

        // Procesar interfaces de red normalizadas (Multi-NIC)
        $this->diffEngine->syncNetworkInterfaces($asset, $norm, $isNew);

        // Procesar periféricos detectados (monitores, impresoras USB)
        $this->diffEngine->syncPeripherals($asset, $norm, $isNew);

        // Si es un equipo existente y tuvo cambios de hardware, notificar de inmediato
        if (!$isNew && !empty($changes) && config('inventory.notify_telegram', true) && $this->notifier) {
            $this->notifier->notifyGlpiHardwareChanged($asset, $changes);
        }

        return [
            'action' => $isNew ? 'created' : 'updated',
            'asset' => $asset,
            'changes' => $changes,
        ];
    }
}
