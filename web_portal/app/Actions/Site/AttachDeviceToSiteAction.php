<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Http\Controllers\Admin\SyncController;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;

class AttachDeviceToSiteAction
{
    /**
     * Agrega un nuevo dispositivo a una sede y lo vincula con MonitoredNetworkDevice e ITAM.
     *
     * @param MonitoredSite $site
     * @param array $inputData
     * @return MonitoredSiteDevice
     */
    public function execute(MonitoredSite $site, array $inputData): MonitoredSiteDevice
    {
        $accessType = strtoupper(trim((string)($inputData['access_type'] ?? 'SIN SOPORTE')));
        $accessPort = !empty($inputData['access_port'])
            ? (int)$inputData['access_port']
            : match ($accessType) {
                'SSH' => 22,
                'TELNET' => 23,
                'WEB' => 80,
                'VNC' => 5900,
                default => null,
            };

        $maxNum = $site->devices()->max('device_number') ?? 0;
        $deviceNumber = $maxNum + 1;
        $isActive = isset($inputData['is_active']) && ($inputData['is_active'] == '1' || $inputData['is_active'] === true);
        $telegramAlertEnabled = !empty($inputData['telegram_alert_enabled']);
        $telegramAlertTarget = in_array($inputData['telegram_alert_target'] ?? '', ['owner', 'group'], true)
            ? $inputData['telegram_alert_target']
            : 'owner';

        $siteDevice = $site->devices()->create([
            'name' => (string)$inputData['name'],
            'ip' => (string)$inputData['ip'],
            'mac' => $inputData['mac'] ?? null,
            'vendor_data' => $inputData['vendor_data'] ?? null,
            'access_type' => $accessType,
            'access_port' => $accessPort,
            'model' => $inputData['model'] ?? null,
            'serial' => $inputData['serial'] ?? null,
            'ports' => $inputData['ports'] ?? null,
            'notes' => $inputData['notes'] ?? null,
            'device_number' => $deviceNumber,
            'is_active' => $isActive,
            'telegram_alert_enabled' => $telegramAlertEnabled,
            'telegram_alert_target' => $telegramAlertTarget,
        ]);

        $netDev = MonitoredNetworkDevice::updateOrCreate(
            ['ip' => $inputData['ip']],
            [
                'monitored_site_id' => $site->id,
                'device_number' => $deviceNumber,
                'name' => (string)$inputData['name'],
                'mac' => $inputData['mac'] ?? null,
                'vendor_data' => $inputData['vendor_data'] ?? null,
                'access_type' => $accessType,
                'access_port' => $accessPort,
                'model' => $inputData['model'] ?? null,
                'serial' => $inputData['serial'] ?? null,
                'ports' => $inputData['ports'] ?? null,
                'notes' => $inputData['notes'] ?? null,
                'is_active' => $isActive,
                'telegram_alert_enabled' => $telegramAlertEnabled,
                'telegram_alert_target' => $telegramAlertTarget,
            ]
        );

        $syncInventory = !isset($inputData['sync_inventory']) || (bool)$inputData['sync_inventory'];
        $inventoryAction = (string)($inputData['inventory_action'] ?? 'create');
        $assetType = (string)($inputData['asset_type'] ?? 'desktop');
        $assetStatus = (string)($inputData['asset_status'] ?? 'assigned');
        $linkAssetId = !empty($inputData['existing_asset_id']) ? (int)$inputData['existing_asset_id'] : null;

        if ($syncInventory && $inventoryAction !== 'none') {
            $valMac = $inputData['mac'] ?? null;
            $valModel = $inputData['model'] ?? null;
            $valVendor = $inputData['vendor_data'] ?? null;
            $valSerial = $inputData['serial'] ?? null;
            $valNotes = $inputData['notes'] ?? null;

            if ($inventoryAction === 'link' && $linkAssetId) {
                $existingAsset = InventoryAsset::find($linkAssetId);
                if ($existingAsset) {
                    $existingAsset->update([
                        'monitored_network_device_id' => $netDev->id,
                        'monitored_site_id' => $site->id,
                        'ip_address' => $inputData['ip'],
                        'mac_address' => $valMac ?: $existingAsset->mac_address,
                        'model' => $valModel ?: $existingAsset->model,
                        'brand' => $valVendor ?: $existingAsset->brand,
                        'serial_number' => ($valSerial && $valSerial !== '00000000') ? $valSerial : $existingAsset->serial_number,
                    ]);
                }
            } else {
                $existingByIp = InventoryAsset::where('ip_address', $inputData['ip'])->first();
                if ($existingByIp) {
                    $existingByIp->update([
                        'name' => (string)$inputData['name'],
                        'type' => $assetType,
                        'brand' => $valVendor ?: $existingByIp->brand,
                        'model' => $valModel ?: $existingByIp->model,
                        'serial_number' => ($valSerial && $valSerial !== '00000000') ? $valSerial : $existingByIp->serial_number,
                        'mac_address' => $valMac ?: $existingByIp->mac_address,
                        'monitored_site_id' => $site->id,
                        'monitored_network_device_id' => $netDev->id,
                        'location_detail' => $site->name,
                        'notes' => $valNotes ?: $existingByIp->notes,
                    ]);
                } else {
                    $serial = ($valSerial && $valSerial !== '00000000')
                        ? $valSerial
                        : ('SN-NET-' . str_pad((string)$netDev->id, 5, '0', STR_PAD_LEFT));

                    InventoryAsset::create([
                        'asset_tag' => 'NET-' . str_pad((string)$netDev->id, 5, '0', STR_PAD_LEFT),
                        'name' => (string)$inputData['name'],
                        'type' => $assetType,
                        'status' => $assetStatus,
                        'brand' => $valVendor ?: null,
                        'model' => $valModel ?: null,
                        'serial_number' => $serial,
                        'mac_address' => $valMac ?: null,
                        'ip_address' => (string)$inputData['ip'],
                        'monitored_site_id' => $site->id,
                        'monitored_network_device_id' => $netDev->id,
                        'location_detail' => $site->name,
                        'notes' => $valNotes ?: "Dispositivo registrado desde Monitoreo ({$site->name})",
                    ]);
                }
            }
        }

        app(SyncController::class)->exportToConfigFiles();

        return $siteDevice;
    }
}
