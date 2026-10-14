<?php

namespace App\Services;

use App\Models\InventoryAsset;
use App\Models\DiscoveredDevice;
use App\Models\SnmpDevice;
use App\Models\NetRadarHost;
use App\Models\WolDevice;
use App\Models\MonitoredNetworkDevice;

class EntityResolverService
{
    /**
     * Resuelve un activo del inventario ITAM a partir de su dirección IP o dirección MAC.
     */
    public function resolve(?string $ip, ?string $mac = null): ?InventoryAsset
    {
        if (empty($ip) && empty($mac)) {
            return null;
        }

        $cleanIp = !empty($ip) ? trim($ip) : null;
        $cleanMac = (!empty($mac) && !str_starts_with($mac, 'IP:')) ? strtoupper(trim($mac)) : null;

        $query = InventoryAsset::query();

        if ($cleanIp && $cleanMac) {
            $query->where(function ($q) use ($cleanIp, $cleanMac) {
                $q->where('ip_address', $cleanIp)
                  ->orWhere('mac_address', $cleanMac);
            });
        } elseif ($cleanIp) {
            $query->where('ip_address', $cleanIp);
        } elseif ($cleanMac) {
            $query->where('mac_address', $cleanMac);
        }

        return $query->with(['site', 'currentUser'])->first();
    }

    /**
     * Obtiene el perfil integrado y la telemetría cruzada de un activo.
     */
    public function getUnifiedProfile(InventoryAsset $asset): array
    {
        $ip = $asset->ip_address;
        $mac = $asset->mac_address;

        // 1. Radar / Descubrimiento
        $discovered = DiscoveredDevice::where(function ($q) use ($asset, $ip, $mac) {
            $q->where('linked_network_device_id', $asset->id);
            if (!empty($ip)) {
                $q->orWhere('ip_address', $ip);
            }
            if (!empty($mac) && !str_starts_with($mac, 'IP:')) {
                $q->orWhere('mac_address', $mac);
            }
        })->first();

        // 2. Telemetría SNMP
        $snmp = SnmpDevice::where('network_device_id', $asset->id)
            ->orWhere(function ($q) use ($ip) {
                if (!empty($ip)) {
                    $q->where('ip_address', $ip);
                }
            })
            ->withCount('interfaces')
            ->first();

        // 3. Flujo NetRadar
        $netRadar = null;
        if (!empty($ip)) {
            $netRadar = NetRadarHost::where('ip', $ip)->first();
        }

        // 4. Wake-on-LAN
        $wol = null;
        if (!empty($mac) && !str_starts_with($mac, 'IP:')) {
            $wol = WolDevice::where('mac_address', $mac)->first();
        }

        // 5. Estado NOC Ping
        $noc = MonitoredNetworkDevice::find($asset->id);

        return [
            'asset' => $asset,
            'display_identity' => $asset->display_identity,
            'technical_subtitle' => $asset->technical_subtitle,
            'discovered' => $discovered,
            'snmp' => $snmp,
            'net_radar' => $netRadar,
            'wol' => $wol,
            'noc' => $noc,
        ];
    }
}
