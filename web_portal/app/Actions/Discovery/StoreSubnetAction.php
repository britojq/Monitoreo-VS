<?php

declare(strict_types=1);

namespace App\Actions\Discovery;

use App\Models\DiscoverySubnet;

class StoreSubnetAction
{
    public function execute(array $validated): DiscoverySubnet
    {
        return DiscoverySubnet::create([
            'subnet' => $validated['subnet'],
            'site_id' => $validated['site_id'] ?? null,
            'scan_interval_minutes' => $validated['scan_interval_minutes'],
            'rate_limit_pps' => $validated['rate_limit_pps'],
            'is_active' => true,
        ]);
    }
}
