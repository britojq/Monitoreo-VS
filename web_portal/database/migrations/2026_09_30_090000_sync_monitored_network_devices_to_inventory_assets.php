<?php

use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Sincronizar y vincular equipos existentes por IP
        $devices = MonitoredNetworkDevice::with('site')->get();

        foreach ($devices as $dev) {
            $existingAsset = InventoryAsset::where('ip_address', $dev->ip)
                ->orWhere('monitored_network_device_id', $dev->id)
                ->first();

            if ($existingAsset) {
                $existingAsset->update([
                    'monitored_network_device_id' => $dev->id,
                    'monitored_site_id' => $dev->monitored_site_id,
                    'mac_address' => $existingAsset->mac_address ?: $dev->mac,
                    'model' => $existingAsset->model ?: $dev->model,
                ]);
                continue;
            }

            // Deducción inteligente de tipo y marca
            $upper = strtoupper($dev->name . ' ' . ($dev->model ?? ''));
            $type = 'network';
            if (str_contains($upper, 'ROUTER')) {
                $type = 'router';
            } elseif (str_contains($upper, 'SW') || str_contains($upper, 'SWITCH')) {
                $type = 'switch';
            } elseif (str_contains($upper, 'IMPRESORA') || str_contains($upper, 'PRINTER')) {
                $type = 'printer';
            } elseif (str_contains($upper, 'SERVIDOR') || str_contains($upper, 'SERVER') || str_contains($upper, 'MONITOREO')) {
                $type = 'server';
            } elseif (str_contains($upper, 'TAQUILLA') || str_contains($upper, 'JEFE') || str_contains($upper, 'ATU') || str_contains($upper, 'PC') || str_contains($upper, 'ESTACIÓN')) {
                $type = 'desktop';
            }

            $brand = null;
            if (str_contains($upper, 'CISCO')) {
                $brand = 'Cisco Systems';
            } elseif (str_contains($upper, 'HON HAI')) {
                $brand = 'Hon Hai Precision';
            } elseif (str_contains($upper, 'CANON')) {
                $brand = 'Canon';
            } elseif (str_contains($upper, 'SIGECOM')) {
                $brand = 'Sigecom';
            }

            $serial = ($dev->serial && $dev->serial !== '00000000') 
                ? $dev->serial 
                : ('SN-NET-' . str_pad($dev->id, 5, '0', STR_PAD_LEFT));

            InventoryAsset::create([
                'asset_tag' => 'NET-' . str_pad($dev->id, 5, '0', STR_PAD_LEFT),
                'name' => $dev->name,
                'type' => $type,
                'status' => 'assigned',
                'brand' => $brand,
                'model' => $dev->model,
                'serial_number' => $serial,
                'mac_address' => $dev->mac,
                'ip_address' => $dev->ip,
                'monitored_site_id' => $dev->monitored_site_id,
                'monitored_network_device_id' => $dev->id,
                'location_detail' => $dev->site?->name ?? 'Sede Regional',
                'notes' => $dev->notes ?: "Dispositivo sincronizado desde Monitoreo de Red ({$dev->ports})",
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Desvincular equipos de monitoreo
        InventoryAsset::whereNotNull('monitored_network_device_id')->update([
            'monitored_network_device_id' => null,
        ]);
    }
};
