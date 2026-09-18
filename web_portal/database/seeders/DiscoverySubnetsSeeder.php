<?php

namespace Database\Seeders;

use App\Models\DiscoverySubnet;
use App\Models\MonitoredSite;
use Illuminate\Database\Seeder;

class DiscoverySubnetsSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener IDs de sedes conocidas si existen
        $valleSecoSite = MonitoredSite::where('name', 'like', '%VALLE SECO%')->first();
        $moronSite = MonitoredSite::where('name', 'like', '%MORON%')->first();
        $caraboboSite = MonitoredSite::where('name', 'like', '%CARABOBO%')->orWhere('name', 'like', '%CONSOLIDADO%')->first();
        $pasMarianoSite = MonitoredSite::where('name', 'like', '%MARI%')->first();

        $subnets = [
            [
                'subnet' => '10.20.23.0/24',
                'site_id' => $valleSecoSite?->id,
                'scan_method' => 'arp_sweep',
                'scan_interval_minutes' => 15,
                'scan_window_start' => null,
                'scan_window_end' => null,
                'rate_limit_pps' => 150,
                'is_active' => true,
            ],
            [
                'subnet' => '10.20.0.0/24',
                'site_id' => $caraboboSite?->id,
                'scan_method' => 'arp_sweep',
                'scan_interval_minutes' => 30,
                'scan_window_start' => null,
                'scan_window_end' => null,
                'rate_limit_pps' => 100,
                'is_active' => true,
            ],
            [
                'subnet' => '10.100.94.0/24',
                'site_id' => $moronSite?->id,
                'scan_method' => 'arp_sweep',
                'scan_interval_minutes' => 30,
                'scan_window_start' => null,
                'scan_window_end' => null,
                'rate_limit_pps' => 100,
                'is_active' => true,
            ],
            [
                'subnet' => '10.20.106.0/24',
                'site_id' => $pasMarianoSite?->id,
                'scan_method' => 'arp_sweep',
                'scan_interval_minutes' => 60,
                'scan_window_start' => null,
                'scan_window_end' => null,
                'rate_limit_pps' => 80,
                'is_active' => true,
            ],
        ];

        foreach ($subnets as $s) {
            DiscoverySubnet::updateOrCreate(
                ['subnet' => $s['subnet']],
                $s
            );
        }
    }
}
