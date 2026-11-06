<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Contracts\AssetTimelineInterface;
use App\Models\GlpiInventoryLog;
use App\Models\InventoryAsset;

readonly class GetInventoryShowViewModelAction
{
    public function execute(int $id): array
    {
        $asset = InventoryAsset::with([
            'monitoredSite',
            'components',
            'networkInterfaces',
            'componentHistories' => function ($q) {
                $q->orderBy('recorded_at', 'desc')->limit(30);
            },
            'assignments' => function ($q) {
                $q->orderBy('assigned_at', 'desc');
            },
            'maintenanceTickets' => function ($q) {
                $q->orderBy('id', 'desc');
            }
        ])->findOrFail($id);

        $cpus = $asset->components->where('type', 'cpu');
        $rams = $asset->components->where('type', 'ram');
        $disks = $asset->components->where('type', 'disk');
        $gpus = $asset->components->where('type', 'gpu');
        $otherComponents = $asset->components->whereNotIn('type', ['cpu', 'ram', 'disk', 'gpu']);

        $extraData = [
            'anydesk_id' => null,
            'screen_info' => null,
            'uptime' => null,
            'video_gpu' => null,
            'usb_devices' => [],
            'software_count' => 0,
        ];

        $latestLog = GlpiInventoryLog::where(function ($q) use ($asset) {
            if ($asset->serial_number) {
                $q->where('serial_number', $asset->serial_number);
            }
            if ($asset->name) {
                $q->orWhere('device_name', $asset->name);
            }
            if ($asset->mac_address) {
                $q->orWhere('mac_address', $asset->mac_address);
            }
        })->latest('id')->first();

        if ($latestLog && !empty($latestLog->raw_payload)) {
            $parsed = @json_decode($latestLog->raw_payload, true);
            $content = $parsed['content'] ?? $parsed ?? [];

            $extraData['anydesk_id'] = $content['remote_mgmt']['anydesk_id'] 
                ?? $content['remote_mgmt']['anydesk'] 
                ?? null;

            if (empty($extraData['anydesk_id']) && isset($content['remote_mgmt']) && is_array($content['remote_mgmt'])) {
                foreach ($content['remote_mgmt'] as $rm) {
                    if (is_array($rm) && isset($rm['id'])) {
                        $extraData['anydesk_id'] = $rm['id'];
                        break;
                    }
                }
            }

            if (!empty($content['videos']) && is_array($content['videos'])) {
                $v = $content['videos'][0] ?? [];
                $extraData['video_gpu'] = ($v['name'] ?? '') . ($v['resolution'] ? " ({$v['resolution']})" : "");
            }

            if (!empty($content['usbdevices']) && is_array($content['usbdevices'])) {
                $extraData['usb_devices'] = array_slice($content['usbdevices'], 0, 8);
            }

            if (!empty($content['softwares']) && is_array($content['softwares'])) {
                $extraData['software_count'] = count($content['softwares']);
            }
        }

        $interfaces = $asset->networkInterfaces;
        $timeline = app(AssetTimelineInterface::class)->getAssetTimeline($asset);

        return compact(
            'asset',
            'cpus',
            'rams',
            'disks',
            'gpus',
            'interfaces',
            'otherComponents',
            'extraData',
            'timeline'
        );
    }
}
