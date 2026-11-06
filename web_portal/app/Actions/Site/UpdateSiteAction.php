<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Http\Controllers\Admin\SyncController;
use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;

class UpdateSiteAction
{
    /**
     * Actualiza la sede y sincroniza sus dispositivos en cascada con MonitoredNetworkDevice e ITAM.
     *
     * @param MonitoredSite $site
     * @param array $inputData
     * @param bool $isActive
     * @return void
     */
    public function execute(MonitoredSite $site, array $inputData, bool $isActive = true): void
    {
        $letter = !empty($inputData['letter'])
            ? strtoupper(trim((string)$inputData['letter']))
            : "ST{$site->id}";

        $site->update([
            'letter' => $letter,
            'name' => (string)($inputData['name'] ?? $site->name),
            'ip' => $inputData['ip'] ?? $site->ip,
            'phone_1' => $inputData['phone_1'] ?? $site->phone_1,
            'phone_2' => $inputData['phone_2'] ?? $site->phone_2,
            'phone_3' => $inputData['phone_3'] ?? $site->phone_3,
            'phone_4' => $inputData['phone_4'] ?? $site->phone_4,
            'phone_5' => $inputData['phone_5'] ?? $site->phone_5,
            'phone_6' => $inputData['phone_6'] ?? $site->phone_6,
            'phone_7' => $inputData['phone_7'] ?? $site->phone_7,
            'phone_8' => $inputData['phone_8'] ?? $site->phone_8,
            'address' => $inputData['address'] ?? $site->address,
            'normal_state_msg' => $inputData['normal_state_msg'] ?? $site->normal_state_msg,
            'error_state_msg' => $inputData['error_state_msg'] ?? $site->error_state_msg,
            'is_active' => $isActive,
            'sort_order' => isset($inputData['sort_order']) ? (int)$inputData['sort_order'] : $site->sort_order,
        ]);

        if (!empty($inputData['devices']) && is_array($inputData['devices'])) {
            foreach ($inputData['devices'] as $devId => $devData) {
                if (!empty($devData['_delete'])) {
                    if (is_numeric($devId)) {
                        $device = MonitoredSiteDevice::where('monitored_site_id', $site->id)->find($devId);
                        if ($device) {
                            $delIp = $device->ip;
                            $device->delete();
                            MonitoredNetworkDevice::where('ip', $delIp)->delete();
                        }
                    }
                    continue;
                }

                if (!empty($devData['name']) && !empty($devData['ip'])) {
                    $accType = strtoupper(trim((string)($devData['access_type'] ?? 'SIN SOPORTE')));
                    $accPort = !empty($devData['access_port'])
                        ? (int)$devData['access_port']
                        : match ($accType) {
                            'SSH' => 22,
                            'TELNET' => 23,
                            'WEB' => 80,
                            'VNC' => 5900,
                            default => null,
                        };
                    $devActive = isset($devData['is_active']) && ($devData['is_active'] == '1' || $devData['is_active'] === true);

                    if (is_numeric($devId)) {
                        $device = MonitoredSiteDevice::where('monitored_site_id', $site->id)->find($devId);
                        if ($device) {
                            $oldIp = $device->ip;
                            $device->update([
                                'name' => $devData['name'],
                                'ip' => $devData['ip'],
                                'mac' => $devData['mac'] ?? null,
                                'vendor_data' => $devData['vendor_data'] ?? null,
                                'model' => $devData['model'] ?? null,
                                'serial' => $devData['serial'] ?? null,
                                'ports' => $devData['ports'] ?? null,
                                'access_type' => $accType,
                                'access_port' => $accPort,
                                'notes' => $devData['notes'] ?? null,
                                'is_active' => $devActive,
                            ]);

                            MonitoredNetworkDevice::updateOrCreate(
                                ['ip' => $oldIp],
                                [
                                    'monitored_site_id' => $site->id,
                                    'name' => $device->name,
                                    'ip' => $device->ip,
                                    'mac' => $device->mac,
                                    'vendor_data' => $device->vendor_data,
                                    'model' => $device->model,
                                    'serial' => $device->serial,
                                    'ports' => $device->ports,
                                    'access_type' => $device->access_type,
                                    'access_port' => $device->access_port,
                                    'notes' => $device->notes,
                                    'is_active' => $device->is_active,
                                ]
                            );
                        }
                    } else {
                        $maxNum = $site->devices()->max('device_number') ?? 0;
                        $newDev = $site->devices()->create([
                            'device_number' => $maxNum + 1,
                            'name' => $devData['name'],
                            'ip' => $devData['ip'],
                            'mac' => $devData['mac'] ?? null,
                            'vendor_data' => $devData['vendor_data'] ?? null,
                            'model' => $devData['model'] ?? null,
                            'serial' => $devData['serial'] ?? null,
                            'ports' => $devData['ports'] ?? null,
                            'access_type' => $accType,
                            'access_port' => $accPort,
                            'notes' => $devData['notes'] ?? null,
                            'is_active' => $devActive,
                        ]);

                        $netDev = MonitoredNetworkDevice::updateOrCreate(
                            ['ip' => $newDev->ip],
                            [
                                'monitored_site_id' => $site->id,
                                'device_number' => $newDev->device_number,
                                'name' => $newDev->name,
                                'ip' => $newDev->ip,
                                'mac' => $newDev->mac,
                                'vendor_data' => $newDev->vendor_data,
                                'model' => $newDev->model,
                                'serial' => $newDev->serial,
                                'ports' => $newDev->ports,
                                'access_type' => $newDev->access_type,
                                'access_port' => $newDev->access_port,
                                'notes' => $newDev->notes,
                                'is_active' => $newDev->is_active,
                            ]
                        );

                        $existingAsset = InventoryAsset::where('ip_address', $newDev->ip)
                            ->orWhere('monitored_network_device_id', $netDev->id)
                            ->first();

                        if ($existingAsset) {
                            $existingAsset->update([
                                'monitored_network_device_id' => $netDev->id,
                                'monitored_site_id' => $site->id,
                                'name' => $newDev->name,
                                'model' => $newDev->model ?: $existingAsset->model,
                                'brand' => $newDev->vendor_data ?: $existingAsset->brand,
                                'mac_address' => $newDev->mac ?: $existingAsset->mac_address,
                            ]);
                        } else {
                            $type = 'desktop';
                            $upper = strtoupper($newDev->name . ' ' . ($newDev->model ?? ''));
                            if (preg_match('/\bROUTER\b/i', $upper)) {
                                $type = 'router';
                            } elseif (preg_match('/\b(SWITCH|CONMUTADOR)\b/i', $upper) || preg_match('/\bSW\d+\b/i', $upper)) {
                                $type = 'switch';
                            } elseif (preg_match('/\b(IMPRESORA|PRINTER)\b/i', $upper)) {
                                $type = 'printer';
                            } elseif (preg_match('/\b(SERVIDOR|SERVER)\b/i', $upper)) {
                                $type = 'server';
                            }

                            InventoryAsset::create([
                                'asset_tag' => 'NET-' . str_pad((string)$netDev->id, 5, '0', STR_PAD_LEFT),
                                'name' => $newDev->name,
                                'type' => $type,
                                'status' => 'assigned',
                                'brand' => $newDev->vendor_data,
                                'model' => $newDev->model,
                                'serial_number' => ($newDev->serial && $newDev->serial !== '00000000' && !str_starts_with($newDev->serial, 'SN-NET-')) ? $newDev->serial : null,
                                'mac_address' => $newDev->mac,
                                'ip_address' => $newDev->ip,
                                'monitored_site_id' => $site->id,
                                'monitored_network_device_id' => $netDev->id,
                                'location_detail' => $site->name,
                                'notes' => $newDev->notes ?: "Dispositivo registrado en monitoreo ({$site->name})",
                            ]);
                        }
                    }
                }
            }
        }

        app(SyncController::class)->exportToConfigFiles();
    }
}
